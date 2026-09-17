<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Externo;
use App\Models\Planta;
use App\Models\SolicitudExt;
use App\Models\SolicitudInt;
use App\Models\Sitec\Empleado;
use App\Services\BitacoraAccesoService;
use App\Services\BitacoraFiltro;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BitacoraController extends Controller
{
    public function index(Request $request, BitacoraAccesoService $bitacoraService)
    {
        $user = $request->user();
        $esSuperior = $user->isAdmin() || $user->isSuperadmin();

        $clasificacion = $this->normalizarClasificacion($request->input('clasificacion', 'todos'));
        $perPage = $this->normalizarPerPage($request->input('perPage', 25));
        $filtros = $this->normalizarFiltros($request);

        $query = $this->construirQuery($request, $esSuperior, $clasificacion, $filtros, $bitacoraService);

        // ────────────────────────────────────────────────────────
        // V10.3 · CAMBIO #28: los 5 COUNT del resumen se calculaban con
        // 5 queries casi idénticos (recorriendo la tabla filtrada 5
        // veces). Ahora es UNA sola consulta con SUM condicional —
        // mismo resultado, 1/5 del costo. La vista no cambia (mismas
        // llaves: total, entradas, salidas, denegados, pendientes).
        // ────────────────────────────────────────────────────────
        $stats = (clone $query)->selectRaw("
                COUNT(*) AS total,
                COALESCE(SUM(bitacora.tipo_evento = 'entrada'), 0) AS entradas,
                COALESCE(SUM(bitacora.tipo_evento = 'salida'), 0) AS salidas,
                COALESCE(SUM(bitacora.estado_evento = 'denegado'), 0) AS denegados,
                COALESCE(SUM(bitacora.estado_evento IN ('revision_horario', 'pendiente_revision')), 0) AS pendientes
            ")->first();

        $resumen = [
            'total'      => (int) $stats->total,
            'entradas'   => (int) $stats->entradas,
            'salidas'    => (int) $stats->salidas,
            'denegados'  => (int) $stats->denegados,
            'pendientes' => (int) $stats->pendientes,
        ];

        // ────────────────────────────────────────────────────────
        // V10.3.11 (P5): resumen de uso por persona cuando el filtro
        // activo es "Permiso de Entrada". Clona la MISMA consulta ya
        // filtrada y agrega un solo GROUP BY (barato en SQL). Solo se
        // calcula si el subtipo está activo para no pagar costos en
        // las demás vistas.
        // ────────────────────────────────────────────────────────
        $usoPermiso = null;
        if ($filtros->subtipo === 'permiso_entrada') {
            $agrupado = (clone $query)
                ->selectRaw("
                    bitacora.persona_id,
                    COUNT(*) AS eventos,
                    COALESCE(SUM(bitacora.tipo_evento = 'entrada'), 0) AS entradas,
                    COALESCE(SUM(bitacora.tipo_evento = 'salida'), 0) AS salidas,
                    MAX(bitacora.fecha_hora) AS ultimo
                ")
                ->groupBy('bitacora.persona_id')
                ->orderByDesc('eventos')
                ->limit(15)
                ->get();

            $nombres = Empleado::query()
                ->whereIn('id_empleado', $agrupado->pluck('persona_id')->map(fn($v) => (int) $v))
                ->pluck('nombre_completo', 'id_empleado');

            $usoPermiso = $agrupado->map(fn($r) => (object) [
                'persona'   => $nombres[(int) $r->persona_id] ?? ('ID ' . $r->persona_id),
                'eventos'   => (int) $r->eventos,
                'entradas'  => (int) $r->entradas,
                'salidas'   => (int) $r->salidas,
                'ultimo'    => $r->ultimo ? Carbon::parse($r->ultimo)->format('d/m/Y H:i') : '—',
            ]);
        }

        $bitacoras = $query
            ->with(['planta', 'guardia', 'justificacion', 'hallazgo'])
            ->orderByDesc('bitacora.fecha_hora')
            ->orderByDesc('bitacora.id_bitacora')
            ->paginate($perPage)
            ->appends($request->query());

        $this->cargarRelacionesMasivas($bitacoras->getCollection());
        $bitacoraService->clasificar($bitacoras->getCollection());

        $plantas = Planta::where('activa', true)->orderBy('nombre')->get();

        return view('bitacora.index', compact(
            'bitacoras',
            'plantas',
            'resumen',
            'esSuperior',
            'perPage',
            'clasificacion',
            'usoPermiso'
        ));
    }

    /**
     * Mantiene la URL/ruta existente para no romper enlaces guardados.
     * Aunque el método conserva el nombre exportarCsv por compatibilidad,
     * ahora genera un archivo Excel-compatible (.xls) con los filtros reales.
     */
    public function exportarCsv(Request $request, BitacoraAccesoService $bitacoraService): StreamedResponse
    {
        $user = $request->user();
        $esSuperior = $user->isAdmin() || $user->isSuperadmin();
        $clasificacion = $this->normalizarClasificacion($request->input('clasificacion', 'todos'));
        $filtros = $this->normalizarFiltros($request);
        $perPage = $this->normalizarPerPage($request->input('perPage', 25));
        $scope = in_array($request->input('export_scope', 'filtered'), ['filtered', 'page'], true)
            ? $request->input('export_scope', 'filtered')
            : 'filtered';

        $query = $this->construirQuery($request, $esSuperior, $clasificacion, $filtros, $bitacoraService)
            ->with(['planta', 'guardia', 'justificacion', 'hallazgo'])
            ->orderByDesc('bitacora.fecha_hora')
            ->orderByDesc('bitacora.id_bitacora');

        if ($scope === 'page') {
            $page = max(1, (int) $request->input('page', 1));
            $query->skip(($page - 1) * $perPage)->take($perPage);
        }

        $bitacoras = $query->get();
        $this->cargarRelacionesMasivas($bitacoras);
        $bitacoraService->clasificar($bitacoras);

        $rows = [];
        foreach ($bitacoras as $b) {
            $rows[] = [
                $b->fecha_hora?->format('d/m/Y H:i'),
                ucfirst($b->tipo_evento),
                $b->estado_evento === 'denegado' ? 'Denegado' : ($b->estado_evento === 'autorizado' ? 'Autorizado' : ucfirst(str_replace('_', ' ', $b->estado_evento))),
                $bitacoraService->etiquetaTipo($b->tipo_acceso ?? BitacoraAccesoService::ESTANDAR),
                $b->persona_obj?->nombre_completo ?? 'Desconocido',
                // V10.3.11 (P4): columnas nuevas del export — ID, guardia,
                // justificación y hallazgo F10 (auditoría completa sin abrir
                // detalle por detalle).
                (string) $b->persona_id,
                !str_starts_with((string) $b->persona_id, 'E-') ? 'Interno' : 'Externo',
                $b->planta?->nombre ?? 'N/A',
                $b->guardia?->nombre_completo ?? 'Sistema',
                $b->justificacion?->nombre ?? '',
                $b->hallazgo
                    ? ($b->hallazgo->resultado . ($b->hallazgo->descripcion_hallazgo ? ' — ' . $b->hallazgo->descripcion_hallazgo : ''))
                    : '',
                $this->textoIncidencia($b),
                $b->motivo_denegacion ?? '',
            ];
        }

        $filename = 'bitacora_accesos_' . now()->format('Ymd_His') . '.xls';

        $callback = function () use ($rows, $filename) {
            echo "\xEF\xBB\xBF";
            echo '<html><head><meta charset="UTF-8"></head><body><table border="1">';
            echo '<tr>';
            foreach (['Fecha/Hora', 'Movimiento', 'Estado', 'Tipo de acceso', 'Persona', 'ID Persona', 'Clasificación', 'Planta', 'Guardia', 'Justificación', 'Hallazgo F10', 'Incidencias', 'Motivo denegación'] as $head) {
                echo '<th>' . e($head) . '</th>';
            }
            echo '</tr>';
            foreach ($rows as $row) {
                echo '<tr>';
                foreach ($row as $cell) {
                    echo '<td>' . e((string) $cell) . '</td>';
                }
                echo '</tr>';
            }
            echo '</table></body></html>';
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    private function construirQuery(
        Request $request,
        bool $esSuperior,
        string $clasificacion,
        BitacoraFiltro $filtros,
        BitacoraAccesoService $bitacoraService
    ) {
        $query = Bitacora::query()->from('bitacora');

        if (!$esSuperior && $request->user()->isAprobador()) {
            // Seguridad: un Aprobador únicamente puede consultar eventos de su planta.
            $query->where('bitacora.id_planta', $request->user()->planta_id);
        }

        if ($filtros->fechaDesde) {
            $query->where('bitacora.fecha_hora', '>=', $filtros->fechaDesde);
        }
        if ($filtros->fechaHasta) {
            // Hasta es inclusivo por minuto; se normaliza a 59.999999 segundos.
            $query->where('bitacora.fecha_hora', '<=', $filtros->fechaHasta);
        }

        if ($esSuperior && $filtros->plantaEvento) {
            $query->where('bitacora.id_planta', $filtros->plantaEvento);
        }

        if ($filtros->movimiento) {
            $query->where('bitacora.tipo_evento', $filtros->movimiento);
        }
        if ($filtros->estadoEvento) {
            $query->where('bitacora.estado_evento', $filtros->estadoEvento);
        }
        if ($filtros->motivo) {
            $query->where('bitacora.motivo_denegacion', $filtros->motivo);
        }

        if ($filtros->incidencia === 'revision') {
            $query->where(function ($q) {
                $q->where('bitacora.revision_exhaustiva', true)
                    ->orWhereExists(function ($h) {
                        $h->selectRaw('1')
                            ->from('revision_hallazgos')
                            ->whereColumn('revision_hallazgos.id_bitacora', 'bitacora.id_bitacora');
                    });
            });
        } elseif ($filtros->incidencia === 'justificacion') {
            $query->where(function ($q) {
                $q->where('bitacora.estado_evento', 'revision_horario')
                    ->orWhereNotNull('bitacora.id_justificacion');
            });
        } elseif ($filtros->incidencia === 'hallazgos') {
            // V10.3.11 (P2): "Solo hallazgos F10" — eventos donde el guardia
            // registró un hallazgo en la revisión (distinto de la revisión
            // exhaustiva en sí, que ya la cubre la opción "Revisión").
            $query->whereExists(function ($h) {
                $h->selectRaw('1')
                    ->from('revision_hallazgos')
                    ->whereColumn('revision_hallazgos.id_bitacora', 'bitacora.id_bitacora');
            });
        }

        $this->aplicarBusquedaPersona($query, $filtros->buscar);

        $bitacoraService->aplicarFiltroTipo($query, $clasificacion, $filtros->subtipo, $filtros);

        return $query;
    }

    private function aplicarBusquedaPersona($query, ?string $buscar): void
    {
        if (!$buscar) {
            return;
        }

        $buscar = trim($buscar);
        if (strlen($buscar) < 3) {
            // Con menos de 3 caracteres no se aplica búsqueda por nombre.
            return;
        }

        if (ctype_digit($buscar)) {
            $query->where('bitacora.persona_id', $buscar);
            return;
        }

        if (preg_match('/^E-\d+$/i', $buscar)) {
            $query->where('bitacora.persona_id', strtoupper($buscar));
            return;
        }

        $terminos = preg_split('/\s+/', mb_strtolower($buscar, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY);

        $idsEmp = Empleado::query()
            ->where(function ($q) use ($terminos) {
                foreach ($terminos as $termino) {
                    $q->where(function ($name) use ($termino) {
                        $name->where('nombre', 'like', '%' . $termino . '%')
                            ->orWhere('apellido_paterno', 'like', '%' . $termino . '%')
                            ->orWhere('apellido_materno', 'like', '%' . $termino . '%')
                            ->orWhere('nombre_completo', 'like', '%' . $termino . '%');
                    });
                }
            })
            ->pluck('id_empleado')
            ->map(fn($id) => (string) $id);

        $idsExt = Externo::query()
            ->where(function ($q) use ($terminos) {
                foreach ($terminos as $termino) {
                    $q->where('nombre_completo', 'like', '%' . $termino . '%');
                }
            })
            ->pluck('id_externos')
            ->map(fn($id) => 'E-' . $id);

        $ids = $idsEmp->merge($idsExt)->unique()->values();

        $query->whereIn('bitacora.persona_id', $ids->isEmpty() ? ['__NO_RESULT__'] : $ids);
    }

    private function normalizarFiltros(Request $request): BitacoraFiltro
    {
        $hasta = $request->input('fecha_hasta');
        $fechaHasta = $hasta ? ($this->parseDateTimeInput($hasta, false) ?? now()->endOfMinute()) : now()->endOfMinute();

        $fechaDesde = $request->input('fecha_desde');
        $fechaDesdeParsed = $fechaDesde ? $this->parseDateTimeInput($fechaDesde, true) : null;

        return new BitacoraFiltro(
            // V10.3.6-fix: filtro "Estado permiso" eliminado (no está en
            // requisitos; ver marcador en BitacoraAccesoService).
            $request->filled('planta_origen') ? (int) $request->input('planta_origen') : null,
            $request->filled('planta_destino') ? (int) $request->input('planta_destino') : null,
            trim((string) $request->input('empresa')) ?: null,
            trim((string) $request->input('anfitrion')) ?: null,
            $request->input('subtipo') ?: null,
            $request->filled('planta') ? (int) $request->input('planta') : null,
            in_array($request->input('tipo'), ['entrada', 'salida'], true) ? $request->input('tipo') : null,
            // V10.3.11 (P6): el dropdown de Estado ahora también ofrece
            // los estados intermedios que la BD sí contiene.
            in_array($request->input('estado'), ['autorizado', 'denegado', 'pendiente_revision', 'revision_horario'], true) ? $request->input('estado') : null,
            trim((string) $request->input('motivo')) ?: null,
            // V10.3.11 (P2): nueva opción de incidencia "solo hallazgos".
            in_array($request->input('incidencia'), ['revision', 'justificacion', 'hallazgos'], true) ? $request->input('incidencia') : null,
            trim((string) $request->input('buscar')) ?: null,
            $fechaDesdeParsed,
            $fechaHasta
        );
    }

    /**
     * Parsea un datetime-local de forma defensiva. Si el navegador/envía un
     * valor inválido, se ignora en lugar de provocar un HTTP 500 al abrir la
     * Bitácora o aplicar filtros.
     */
    private function parseDateTimeInput(string $value, bool $start): ?Carbon
    {
        try {
            $date = Carbon::createFromFormat('Y-m-d\\TH:i', $value);
            return $start ? $date->startOfMinute() : $date->endOfMinute();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizarClasificacion(string $value): string
    {
        return in_array($value, ['todos', 'internos', 'externos'], true) ? $value : 'todos';
    }

    private function normalizarPerPage($value): int
    {
        $allowed = [15, 25, 50, 100, 500];
        $value = (int) $value;
        return in_array($value, $allowed, true) ? $value : 25;
    }

    private function cargarRelacionesMasivas($bitacoras): void
    {
        $idsEmp = [];
        $idsExt = [];
        foreach ($bitacoras as $b) {
            if (!str_starts_with((string) $b->persona_id, 'E-')) {
                $idsEmp[] = (int) $b->persona_id;
            } else {
                $idsExt[] = (int) str_replace('E-', '', $b->persona_id);
            }
        }

        $empleados = collect();
        if ($idsEmp) {
            $empleados = Empleado::with(['puesto', 'departamento', 'turno'])
                ->whereIn('id_empleado', array_values(array_unique($idsEmp)))
                ->get()->keyBy('id_empleado');
        }

        $externos = collect();
        if ($idsExt) {
            $externos = Externo::whereIn('id_externos', array_values(array_unique($idsExt)))
                ->get()->keyBy('id_externos');
        }

        foreach ($bitacoras as $b) {
            if (!str_starts_with((string) $b->persona_id, 'E-')) {
                $b->persona_obj = $empleados->get((int) $b->persona_id);
            } else {
                $b->persona_obj = $externos->get((int) str_replace('E-', '', $b->persona_id));
            }
        }
    }

    private function textoIncidencia($b): string
    {
        $items = [];
        if ($b->revision_exhaustiva || $b->hallazgo) {
            $items[] = 'Revisión';
        }
        if ($b->id_justificacion || $b->estado_evento === 'revision_horario') {
            $items[] = 'Justificación';
        }
        return $items ? implode(' / ', $items) : '—';
    }

    public function detalle($id, BitacoraAccesoService $bitacoraService)
    {
        $bitacora = Bitacora::with(['planta', 'guardia', 'justificacion', 'hallazgo'])->findOrFail($id);

        // ────────────────────────────────────────────────────────
        // V10.3 · CAMBIO #25 (hueco de seguridad detectado en la
        // auditoría): el index() ya limita al Aprobador a su planta,
        // pero detalle() NO aplicaba ese scope — un Aprobador podía
        // abrir el detalle de cualquier bitácora manipulando el id
        // en la URL/JS. Ahora: Aprobador (no Admin/Super) solo puede
        // ver detalles de SU planta (403 en caso contrario).
        // ────────────────────────────────────────────────────────
        $userDetalle = request()->user();
        if ($userDetalle && !$userDetalle->isAdmin() && !$userDetalle->isSuperadmin() && $userDetalle->isAprobador()) {
            if ((int) $bitacora->id_planta !== (int) $userDetalle->planta_id) {
                abort(403, 'No tiene permisos para consultar bitácoras de otra planta.');
            }
        }

        $bitacoraService->clasificar(collect([$bitacora]));

        $data = [
            'evento' => [
                'fecha_hora' => $bitacora->fecha_hora->format('d/m/Y H:i'),
                'planta' => $bitacora->planta?->nombre ?? 'N/A',
                'tipo' => $bitacora->tipo_evento,
                'estado' => $bitacora->estado_evento,
                'tipo_acceso' => $bitacoraService->etiquetaTipo($bitacora->tipo_acceso ?? BitacoraAccesoService::ESTANDAR),
                'guardia' => $bitacora->guardia?->nombre_completo ?? 'Sistema',
                'motivo_denegacion' => null,
                'justificacion' => null,
                'f10' => null,
                // V10.3 · CAMBIO #27: referencia directa a la solicitud que
                // autorizó el acceso (NULL = estándar/histórico).
                'id_solicitud' => $bitacora->id_solicitud,
                'solicitud_codigo' => !empty($bitacora->id_solicitud)
                    ? (str_starts_with((string) $bitacora->persona_id, 'E-') ? 'EXT-' : 'INT-') . $bitacora->id_solicitud
                    : null,
                'solicitud_inconsistente' => (bool) ($bitacora->solicitud_inconsistente ?? false),
                'detalle_inconsistencia' => null,
            ],
            'persona' => null,
            'contexto' => null,
        ];

        $motivos = [
            'sin_clasificar' => 'El empleado no tiene clasificación asignada en RR.HH.',
            'sin_planta' => 'El empleado no tiene una planta base configurada.',
            'planta_no_autorizada' => 'Intentó acceder a una planta distinta sin permiso de Interplantas.',
            'salida_temprana' => 'Intentó salir antes de que terminara su turno sin Pase de Salida.',
            'empleado_inactivo' => 'El empleado está marcado como inactivo (baja).',
            'manual_bloqueado' => 'Agotó sus 4 intentos de búsqueda manual. Requiere reposición.',
            'credencial_expirada' => 'El código QR de la credencial ha expirado.',
            'pendiente_sin_resolver' => 'Tenía un acceso pendiente anterior sin resolver.',
            'rechazado_guardia' => 'El guardia rechazó explícitamente el acceso.',
            'cerrado_sin_resolver' => 'El guardia cerró su turno sin resolver la pendiente.',
            'planta_inconsistente' => 'Su último registro fue en otra planta. Debe regularizar.',
            'sin_turno' => 'El empleado no tiene horario de turno asignado.',
            'dia_no_laboral' => 'Intentó acceder en un día no laboral.',
            'salida_antes_turno' => 'Intentó salir antes de que iniciara su turno.',
            'llegada_tardia' => 'Llegó fuera de su ventana de tolerancia.',
            'salida_tardia' => 'Salió más de 30 minutos después de que terminó su turno.',
            // V10.3 · CAMBIO #26: dos motivos nuevos del motor V10.3.
            'llegada_temprana' => 'Llegó más de 30 minutos antes de su turno (antes de la tolerancia).',
            'entrada_sin_permiso' => 'Intentó ENTRAR fuera de turno (día no laboral, turno vencido o sin turno) sin un Permiso de Entrada vigente.',
        ];

        if ($bitacora->estado_evento === 'denegado' && $bitacora->motivo_denegacion) {
            $data['evento']['motivo_denegacion'] = $motivos[$bitacora->motivo_denegacion] ?? $bitacora->motivo_denegacion;
        }
        if ($bitacora->justificacion) {
            $data['evento']['justificacion'] = $bitacora->justificacion->nombre ?? 'N/A';
        }
        if ($bitacora->hallazgo) {
            $data['evento']['f10'] = [
                'resultado' => $bitacora->hallazgo->resultado,
                'desc' => $bitacora->hallazgo->descripcion_hallazgo,
            ];
        }

        $personaData = null;
        if (!str_starts_with((string) $bitacora->persona_id, 'E-')) {
            $emp = Empleado::with(['puesto', 'departamento', 'turno'])->find((int) $bitacora->persona_id);
            if ($emp) {
                $personaData = [
                    'tipo' => 'Empleado',
                    'nombre' => $emp->nombre_completo,
                    'id' => $emp->id_empleado,
                    'foto' => route('foto.empleado', ['id' => $emp->id_empleado]),
                    'extras' => [
                        'Puesto' => $emp->puesto?->nombre ?? 'N/A',
                        'Departamento' => $emp->departamento?->nombre ?? 'N/A',
                        'Planta Base' => $emp->plantaNombre() ?? 'N/A',
                        'Clasificación' => ucfirst($emp->tipoEmpleado() ?? 'Sin clasificar'),
                        'Turno' => $emp->turno ? ($emp->turno->entrada . ' - ' . $emp->turno->salida) : 'Sin turno asignado',
                    ],
                ];
            }
        } else {
            $extId = (int) str_replace('E-', '', $bitacora->persona_id);
            $ext = Externo::find($extId);
            if ($ext) {
                $personaData = [
                    'tipo' => 'Externo',
                    'nombre' => $ext->nombre_completo,
                    'id' => $ext->id_externos,
                    'foto' => 'https://ui-avatars.com/api/?name=' . urlencode($ext->nombre_completo) . '&background=0d6efd&color=fff',
                    'extras' => [
                        'Identificación' => ucfirst($ext->tipo_identificacion) . ': ' . $ext->identificacion,
                        'Teléfono' => $ext->telefono ?? 'N/A',
                    ],
                ];
            }
        }
        $data['persona'] = $personaData;

        // ────────────────────────────────────────────────────────
        // V10.3 · CAMBIO #27: PRIORIDAD de la referencia ABSOLUTA.
        // Si la bitácora trae id_solicitud (columna nueva, Script 1),
        // se resuelve DIRECTAMENTE por id (sin reconstrucción por
        // reglas) — es el dato que el escáner grabó en el momento.
        // Si el id ya no existe (huérfano), se marca "inconsistente"
        // pero NUNCA se oculta (auditoría honesta).
        // Si NO hay referencia (registros históricos previos a V10.3),
        // se conserva la reconstrucción por reglas de V11.4
        // (permiso_contexto) como fallback.
        // ────────────────────────────────────────────────────────
        // V10.3-fix: la clasificación ya resolvió referencia directa,
        // membresía/ruteo e inconsistencia. El detalle consume ese resultado
        // y no vuelve a inferir ni a mostrar una solicitud incompatible.
        $permiso = $bitacora->permiso_contexto ?? null;

        if (!empty($bitacora->id_solicitud) && !$permiso) {
            $data['evento']['solicitud_inconsistente'] = true;
            $data['evento']['detalle_inconsistencia'] = 'El registro referencia la solicitud '
                . (str_starts_with((string) $bitacora->persona_id, 'E-') ? 'EXT-' : 'INT-')
                . $bitacora->id_solicitud
                . ', pero la referencia no existe o no corresponde con la persona del evento. El registro NO se oculta: se muestra como inconsistente.';
        }

        if ($permiso) {
            if ($permiso instanceof SolicitudInt) {
                $data['contexto'] = [
                    'tipo_permiso' => ucfirst(str_replace('_', ' ', $permiso->tipo)),
                    'motivo' => $permiso->motivo,
                    'vigencia' => $permiso->fecha_inicio->format('d/m/Y H:i') . ' - ' . $permiso->fecha_fin->format('d/m/Y H:i'),
                    'estado' => $permiso->estado,
                    'ruta' => $permiso->tipo === 'interplantas'
                        ? (($permiso->plantaOrigen?->nombre ?? 'N/A') . ' → ' . ($permiso->plantaDestino?->nombre ?? 'N/A'))
                        : null,
                    'creador' => $permiso->creador?->nombre_completo ?? 'N/A',
                    'url_solicitud' => route($permiso->tipo === 'interplantas' ? 'solicitudes.show.int' : 'solicitudes.show.int', $permiso->id_solicitud_int),
                ];
            } elseif ($permiso instanceof SolicitudExt) {
                $data['contexto'] = [
                    'tipo_permiso' => $permiso->tipo === 'contratista' ? 'Contratista' : 'Visita',
                    'motivo' => $permiso->motivo,
                    'vigencia' => $permiso->fecha_inicio->format('d/m/Y H:i') . ' - ' . $permiso->fecha_fin->format('d/m/Y H:i'),
                    'estado' => $permiso->estado,
                    'empresa' => $permiso->empresa,
                    'anfitrion' => $permiso->anfitrion?->nombre_completo ?? 'N/A',
                    'creador' => $permiso->creador?->nombre_completo ?? 'N/A',
                    'url_solicitud' => route('solicitudes.show.ext', $permiso->id_solicitud_ext),
                ];
            }
        }

        return response()->json($data);
    }

    public function buscarPersonaAjax(Request $request)
    {
        $q = trim($request->get('q', ''));
        if (mb_strlen($q, 'UTF-8') < 3) {
            return response()->json([]);
        }

        $terminos = preg_split('/\s+/', mb_strtolower($q, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY);

        $empleados = Empleado::query()
            ->where(function ($query) use ($terminos) {
                if (ctype_digit($q)) {
                    $query->where('id_empleado', $q);
                    return;
                }
                foreach ($terminos as $termino) {
                    $query->where(function ($name) use ($termino) {
                        $name->where('nombre_completo', 'like', '%' . $termino . '%')
                            ->orWhere('nombre', 'like', '%' . $termino . '%')
                            ->orWhere('apellido_paterno', 'like', '%' . $termino . '%')
                            ->orWhere('apellido_materno', 'like', '%' . $termino . '%');
                    });
                }
            })
            ->limit(10)
            ->get()
            ->map(fn($e) => [
                'id' => $e->id_empleado,
                'texto' => $e->nombre_completo . ' (Interno)',
                'nombre' => $e->nombre_completo,
                'tipo' => 'Interno',
            ]);

        $externos = Externo::query()
            ->where(function ($query) use ($terminos) {
                foreach ($terminos as $termino) {
                    $query->where('nombre_completo', 'like', '%' . $termino . '%');
                }
            })
            ->limit(10)
            ->get()
            ->map(fn($e) => [
                'id' => 'E-' . $e->id_externos,
                'texto' => $e->nombre_completo . ' (Externo)',
                'nombre' => $e->nombre_completo,
                'tipo' => 'Externo',
            ]);

        return response()->json($empleados->merge($externos)->take(15)->values());
    }
}
