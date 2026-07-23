<?php
// app/Http/Controllers/ScannerController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Models\Bitacora;
use App\Models\Guardia;
use App\Models\Planta;
use App\Models\Sitec\Empleado;
use App\Models\EmpleadoExtSca;
use App\Models\Credencial;
use App\Models\TurnoGuardia;
use App\Models\RevisionHallazgo;
use App\Models\LogSistema;
use Illuminate\Support\Facades\Auth;

class ScannerController extends Controller
{
    public function kiosko(TurnoGuardia $turno)
    {
        if ($turno->fecha_hora_fin) {
            return redirect()->route('login')->withErrors('Este kiosko ha sido desactivado.');
        }
        return view('kiosko.index', compact('turno'));
    }

    public function activarKiosko(Request $request)
    {
        $request->validate([
            'pin' => 'required',
            'planta_id' => 'required|exists:plantas,id',
            'modo' => 'required|in:entrada,salida,mixto',
        ]);

        if ($request->pin !== config('sca_qr.pin_kiosko')) {
            return back()->with('kiosko_error', 'PIN incorrecto.')->withInput();
        }

        $guardiaKiosko = Guardia::firstOrCreate(
            ['nombre_completo' => 'Kiosko Sistema'],
            ['planta_id' => $request->planta_id, 'activo' => true]
        );

        $turno = TurnoGuardia::create([
            'guardia_id' => $guardiaKiosko->id,
            'planta_id' => $request->planta_id,
            'modo' => $request->modo,
            'fecha_hora_inicio' => now(),
        ]);

        LogSistema::registrar('INICIO_TURNO_GUARDIA', "Kiosko activado en planta {$request->planta_id} (Modo: {$request->modo})");

        return redirect()->route('kiosko.show', $turno->id);
    }

    public function salirKiosko(Request $request, TurnoGuardia $turno)
    {
        if ($request->pin !== config('sca_qr.pin_kiosko')) {
            return back()->withErrors('PIN incorrecto para salir.');
        }
        $turno->fecha_hora_fin = now();
        $turno->save();
        return redirect()->route('login');
    }

    public function procesarEscaneo(Request $request)
    {
        $codigo = trim($request->input('codigo'));
        $origen = $request->input('origen', 'kiosco');
        $turnoId = $request->input('turno_id');

        // NUEVO: Si el usuario está autenticado como guardia, usar su planta
        // (permite escanear/buscar desde su dashboard web, ej. desde su celular)
        if (Auth::check() && Auth::user()->isGuardia()) {
            $user = Auth::user();
            $plantaId = $user->guardia?->planta_id;

            if (!$plantaId) {
                return response()->json(['success' => false, 'message' => 'No tiene planta asignada.']);
            }

            // Buscar turno activo del guardia, o crear uno automático
            $turno = TurnoGuardia::where('guardia_id', $user->guardia_id)
                ->whereNull('fecha_hora_fin')
                ->latest('fecha_hora_inicio')
                ->first();

            if (!$turno) {
                $turno = TurnoGuardia::create([
                    'guardia_id' => $user->guardia_id,
                    'planta_id' => $plantaId,
                    'modo' => 'mixto',
                    'fecha_hora_inicio' => now(),
                ]);
                LogSistema::registrar('INICIO_TURNO_GUARDIA', "Turno automático para guardia {$user->username} (escaneo desde dashboard)");
            }

            $turnoId = $turno->id;
            $plantaId = $turno->planta_id;
            $modoKiosko = $turno->modo;
        } else {
            // Modo kiosko público: requiere turno_id válido
            $turno = TurnoGuardia::find($turnoId);
            if (!$turno || $turno->fecha_hora_fin) {
                return response()->json(['success' => false, 'message' => 'Kiosko desactivado.']);
            }
            $plantaId = $turno->planta_id;
            $modoKiosko = $turno->modo;
        }

        if (empty($codigo)) return response()->json(['success' => false, 'message' => 'Código vacío.'], 400);

        if (str_starts_with($codigo, config('sca_qr.prefijo_qr_externo'))) {
            return $this->procesarExterno($codigo, $origen, $turnoId, $plantaId);
        } else {
            return $this->procesarEmpleado($codigo, $origen, $turnoId, $plantaId, $modoKiosko);
        }
    }

    /**
     * Lógica principal de Empleados.
     *
     * CAMBIOS:
     *   - F10 (revisión aleatoria) aplica a TODOS los orígenes (kiosco, cámara, manual)
     *   - Horario validado vía AccesoService::validarHorario()
     *   - Operativos que intentan salir antes de fin de turno → DENEGADO
     *   - Día no laboral → anomalía, justificar
     */
    
    /**
     * OPTIMIZADO: eager loading masivo al buscar empleado.
     */
    private function procesarEmpleado($codigo, $origen, $turnoId, $plantaId, $modoKiosko = 'mixto')
    {
        try {
        
            // QUITAMOS 'ext' del with() — la cargamos manualmente después
            // porque la relación cross-DB no funciona con eager loading
            if ($origen === 'manual') {
                $empleado = Empleado::with(['turno', 'clasificacion', 'puesto', 'departamento'])
                    ->where('id_empleado', $codigo)->first();
            } else {
                $empleado = Empleado::with(['turno', 'clasificacion', 'puesto', 'departamento'])
                    ->where('semilla', $codigo)->first();
            }

            if (!$empleado) {
                return response()->json(['success' => false, 'message' => 'QR o número de empleado no válido.']);
            }
            if (!$empleado->estaActivo()) {
                return response()->json(['success' => false, 'message' => 'Empleado no activo.']);
            }

            // Cargar ext manualmente (conexión correcta, sin eager loading cross-DB)
            $ext = EmpleadoExtSca::where('empleado_id', $empleado->id_empleado)->first()
            ?: EmpleadoExtSca::firstOrNew(['empleado_id' => $empleado->id_empleado]);
            if (!$ext->exists) {
                $ext->usos_manuales_restantes = 4;
                $ext->bloqueo_manual = false;
                $ext->save();
            }

            $usosRestantes = $ext->usos_manuales_restantes;

            // Lógica de búsqueda manual
            if ($origen === 'manual') {
                if ($ext->bloqueo_manual || $usosRestantes <= 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Búsqueda bloqueada. URGE REPOSICIÓN DE CREDENCIAL.'
                    ]);
                }

                $usoManualHoy = Bitacora::empleadoId($empleado->id_empleado)
                    ->where('origen_escaneo', 'manual')
                    ->hoy()
                    ->exists();

                if (!$usoManualHoy) {
                    $ext->usos_manuales_restantes--;
                    if ($ext->usos_manuales_restantes <= 0) {
                        $ext->bloqueo_manual = true;
                    }
                    $ext->save();
                    $usosRestantes = $ext->usos_manuales_restantes;
                }
            } elseif ($origen === 'kiosco' || $origen === 'guardia') {
                if ($ext->usos_manuales_restantes < 4 || $ext->bloqueo_manual) {
                    $ext->usos_manuales_restantes = 4;
                    $ext->bloqueo_manual = false;
                    $ext->save();
                    $usosRestantes = 4;
                }
            }

            // Guardia del turno
            $guardiaId = 1;
            if ($turnoId) {
                $turnoTmp = TurnoGuardia::find($turnoId);
                if ($turnoTmp) $guardiaId = $turnoTmp->guardia_id;
            }

            // Validación de planta
            $accesoService = app(\App\Services\AccesoService::class);
            $validacion = $accesoService->validarAccesoPlanta($empleado, $plantaId);

            if (!$validacion['permitido']) {
                Bitacora::create([
                    'persona_tipo' => 'empleado',
                    'persona_id' => (string) $empleado->id_empleado,
                    'guardia_id' => $guardiaId,
                    'turno_guardia_id' => $turnoId,
                    'tipo_evento' => $modoKiosko === 'salida' ? 'salida' : 'entrada',
                    'estado_evento' => 'denegado',
                    'origen_escaneo' => $origen,
                    'fecha_hora' => now(),
                    'planta_id' => $plantaId,
                    'motivo_denegacion' => $validacion['motivo_codigo'],
                ]);

                return response()->json(['success' => false, 'message' => $validacion['motivo']]);
            }

            // Determinar tipo de evento
            $ultimoEvento = Bitacora::empleadoId($empleado->id_empleado)
                ->hoy()
                ->noDenegados()
                ->latest('fecha_hora')
                ->first();

            $tipoEvento = null;

            if ($modoKiosko === 'entrada') {
                if ($ultimoEvento && $ultimoEvento->tipo_evento === 'entrada') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ya registró su entrada. Diríjase al escáner de SALIDA.'
                    ]);
                }
                $tipoEvento = 'entrada';
            } elseif ($modoKiosko === 'salida') {
                if (!$ultimoEvento || $ultimoEvento->tipo_evento === 'salida') {
                    return response()->json([
                        'success' => false,
                        'message' => 'No tiene entrada registrada. Diríjase al escáner de ENTRADA.'
                    ]);
                }
                $tipoEvento = 'salida';
            } else {
                $tipoEvento = (!$ultimoEvento || $ultimoEvento->tipo_evento === 'salida')
                    ? 'entrada' : 'salida';
            }

            // ¿Es primera entrada del día?
            $huboEntradaHoy = Bitacora::empleadoId($empleado->id_empleado)
                ->where('tipo_evento', 'entrada')
                ->noDenegados()
                ->hoy()
                ->exists();
            $esPrimeraEntrada = ($tipoEvento === 'entrada' && !$huboEntradaHoy);

            // Validación de horario
            $horario = $accesoService->validarHorario($empleado, $tipoEvento, $esPrimeraEntrada);

            if (!$horario['permitido']) {
                Bitacora::create([
                    'persona_tipo' => 'empleado',
                    'persona_id' => (string) $empleado->id_empleado,
                    'guardia_id' => $guardiaId,
                    'turno_guardia_id' => $turnoId,
                    'tipo_evento' => $tipoEvento,
                    'estado_evento' => 'denegado',
                    'origen_escaneo' => $origen,
                    'fecha_hora' => now(),
                    'planta_id' => $plantaId,
                    'motivo_denegacion' => $horario['motivo_codigo'],
                ]);

                return response()->json(['success' => false, 'message' => $horario['motivo']]);
            }

            $requiereJustificacion = $horario['requiere_justificacion'];

            // Revisión aleatoria F10
            $revisionRequerida = false;
            $tipoEmp = $empleado->tipoEmpleado();
            if ($tipoEmp && in_array($tipoEmp, ['operativo', 'administrativo'])) {
                if (!in_array($empleado->id_empleado, config('sca_qr.exentos_revision'))) {
                    if (rand(1, 100) <= config('sca_qr.probabilidad_revision')) {
                        $revisionRequerida = true;
                    }
                }
            }

            $estadoEvento = $revisionRequerida ? 'pendiente_revision' : 'autorizado';

            $observaciones = $validacion['es_interplantas']
                ? 'Acceso por interplantas aprobado.' : null;

            $bitacora = Bitacora::create([
                'persona_tipo' => 'empleado',
                'persona_id' => (string) $empleado->id_empleado,
                'guardia_id' => $guardiaId,
                'turno_guardia_id' => $turnoId,
                'tipo_evento' => $tipoEvento,
                'estado_evento' => $estadoEvento,
                'origen_escaneo' => $origen,
                'fecha_hora' => now(),
                'planta_id' => $plantaId,
                'anomalia_horario' => $requiereJustificacion,
                'estado_anomalia' => $requiereJustificacion ? 'pendiente' : null,
                'observaciones' => $observaciones,
            ]);

            $message = ucfirst($tipoEvento) . ' registrada correctamente.';
            if ($revisionRequerida) {
                $message = 'Atención: Espere al guardia para revisión.';
            }
            if ($requiereJustificacion) {
                $message = $horario['motivo'] ?? 'Acceso fuera de horario. Justifique su acceso.';
            }
            if ($validacion['es_interplantas']) {
                $message .= ' (Acceso interplantas autorizado.)';
            }

            if ($origen === 'manual' && $usosRestantes <= 1 && $usosRestantes > 0) {
                $message .= " ¡URGE REPOSICIÓN DE CREDENCIAL! Le queda {$usosRestantes} uso manual.";
            } elseif ($origen === 'manual') {
                $message .= " (Le quedan {$usosRestantes} usos manuales.)";
            }

            $plantaDisplay = $empleado->plantaNombre();
            if ($tipoEmp === 'corporativo' && !$empleado->plantaId()) {
                $plantaDisplay = 'Corporativo (multiplanta)';
            }

            return response()->json([
                'success' => true,
                'autorizado' => true,
                'revision_pendiente' => $revisionRequerida,
                'requiere_justificacion' => $requiereJustificacion,
                'bitacora_id' => $bitacora->id,
                'tipo_evento' => $tipoEvento,
                'usos_restantes' => $usosRestantes,
                'persona' => [
                    'nombre' => $empleado->nombre_completo,
                    'puesto' => $empleado->puesto?->nombre,
                    'tipo_empleado' => $tipoEmp ? ucfirst($tipoEmp) : 'No clasificado',
                    'planta' => $plantaDisplay,
                    'foto' => route('foto.empleado', ['id' => $empleado->id_empleado]),
                ],
                'message' => $message
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error interno: ' . $e->getMessage()
            ], 500);
        }
    }

    private function procesarExterno($codigo, $origen, $turnoId, $plantaId)
    {
        $credencial = Credencial::where('codigo_qr', $codigo)->first();
        if (!$credencial || !$credencial->activa) return response()->json(['success' => false, 'message' => 'QR no válido.']);
        if ($credencial->fecha_expiracion < now()) return response()->json(['success' => false, 'message' => 'Credencial expirada.']);

        $solicitud = $credencial->solicitud;
        if ($solicitud->planta_destino_id != $plantaId) return response()->json(['success' => false, 'message' => 'Planta no autorizada.']);

        $persona = $solicitud->persona;
        $ultimoEvento = Bitacora::where('persona_tipo', 'persona')
            ->where('persona_id', (string) $persona->id)
            ->whereDate('fecha_hora', today())
            ->latest('fecha_hora')->first();

        $tipoEvento = (!$ultimoEvento || $ultimoEvento->tipo_evento === 'salida') ? 'entrada' : 'salida';

        $bitacora = Bitacora::create([
            'persona_tipo' => 'persona',
            'persona_id' => (string) $persona->id,
            'guardia_id' => $turnoId ? TurnoGuardia::find($turnoId)->guardia_id : 1,
            'turno_guardia_id' => $turnoId,
            'tipo_evento' => $tipoEvento,
            'estado_evento' => 'autorizado',
            'origen_escaneo' => $origen,
            'fecha_hora' => now(),
            'planta_id' => $plantaId,
        ]);

        $fotoUrl = $persona->foto ? Storage::url($persona->foto) : 'https://ui-avatars.com/api/?name='.urlencode($persona->nombre_completo).'&background=0D8ABC&color=fff';

        return response()->json([
            'success' => true,
            'autorizado' => true,
            'revision_pendiente' => false,
            'bitacora_id' => $bitacora->id,
            'tipo_evento' => $tipoEvento,
            'persona' => [
                'nombre' => $persona->nombre_completo,
                'puesto' => $persona->empresa ?? 'Visitante',
                'tipo_empleado' => ucfirst($credencial->tipo),
                'foto' => $fotoUrl,
            ],
            'message' => ucfirst($tipoEvento) . ' registrada.'
        ]);
    }

    public function justificarEscaneo(Request $request)
    {
        $bitacora = Bitacora::find($request->bitacora_id);
        if ($bitacora) {
            $bitacora->observaciones = "Justificación usuario: " . $request->justificacion;
            $bitacora->save();
            return response()->json(['success' => true]);
        }
        return response()->json(['success' => false], 404);
    }

    public function estadoRevision($bitacora_id)
    {
        $bitacora = Bitacora::find($bitacora_id);
        if (!$bitacora) return response()->json(['estado' => 'invalido']);
        return response()->json([
            'estado' => $bitacora->estado_evento,
            'autorizado' => $bitacora->estado_evento === 'autorizado' || $bitacora->estado_evento === 'revision_completada',
        ]);
    }

    public function panelGuardia()
    {
        $user = Auth::user();
        if (!$user->isGuardia()) abort(403);

        $turnoActivo = TurnoGuardia::where('guardia_id', $user->guardia_id)
            ->whereNull('fecha_hora_fin')->first();

        return view('guardia.dashboard', compact('turnoActivo'));
    }

    /**
     * OPTIMIZADO: carga empleados en 1 sola query (evita N+1).
     */
    public function pendientesGuardia()
    {
        $user = Auth::user();
        $plantaId = $user->guardia?->planta_id;

        $pendientes = Bitacora::where(function($q) {
                $q->pendientesRevision()
                  ->orWhere('estado_anomalia', 'pendiente');
            })
            ->dePlanta($plantaId)
            ->hoy()
            ->get();

        if ($pendientes->isEmpty()) {
            return response()->json([]);
        }

        // Cargar todos los empleados en 1 sola query
        $idsEmpleados = $pendientes->pluck('persona_id')->unique()->toArray();
        $empleados = Empleado::whereIn('id_empleado', $idsEmpleados)
            ->pluck('nombre_completo', 'id_empleado');

        $resultado = $pendientes->map(function($b) use ($empleados) {
            $tipo = $b->estado_evento === 'pendiente_revision' ? 'revision' : 'anomalia';
            return [
                'tipo' => $tipo,
                'bitacora_id' => $b->id,
                'nombre' => $empleados[$b->persona_id] ?? 'Desconocido',
                'hora' => $b->fecha_hora->format('H:i'),
                'justificacion' => $b->observaciones,
            ];
        });

        return response()->json($resultado);
    }

    /**
     * NUEVO: Feed de actividad reciente (últimos 15 eventos de la planta hoy).
     * Incluye autorizados, denegados, revisiones, anomalías.
     */
    public function feedActividad()
    {
        $user = Auth::user();
        $plantaId = $user->guardia?->planta_id;

        $eventos = Bitacora::where('planta_id', $plantaId)
            ->whereDate('fecha_hora', today())
            ->orderBy('fecha_hora', 'desc')
            ->limit(15)
            ->get();

        if ($eventos->isEmpty()) {
            return response()->json([]);
        }

        // Cargar empleados en 1 sola query
        $idsEmpleados = $eventos->where('persona_tipo', 'empleado')
            ->pluck('persona_id')->unique()->toArray();
        $empleados = Empleado::whereIn('id_empleado', $idsEmpleados)
            ->pluck('nombre_completo', 'id_empleado');

        // Cargar personas (visitantes/contratistas) en 1 sola query
        $idsPersonas = $eventos->where('persona_tipo', 'persona')
            ->pluck('persona_id')->unique()->toArray();
        $personas = \App\Models\Persona::whereIn('id', $idsPersonas)
            ->pluck('nombre_completo', 'id');

        $resultado = $eventos->map(function ($b) use ($empleados, $personas) {
            $nombre = $b->persona_tipo === 'empleado'
                ? ($empleados[$b->persona_id] ?? 'Desconocido')
                : ($personas[$b->persona_id] ?? 'Visitante');

            // Icono y color según estado
            $icono = match($b->estado_evento) {
                'autorizado' => $b->tipo_evento === 'entrada' ? 'bi-box-arrow-in-right' : 'bi-box-arrow-right',
                'denegado' => 'bi-x-circle',
                'pendiente_revision' => 'bi-shield-exclamation',
                'revision_completada' => 'bi-check2-shield',
                default => 'bi-question-circle',
            };
            $color = match($b->estado_evento) {
                'autorizado' => 'success',
                'denegado' => 'danger',
                'pendiente_revision' => 'warning',
                'revision_completada' => 'info',
                default => 'secondary',
            };

            // Tipo de persona (para mostrar en feed)
            $tipoPersona = $b->persona_tipo === 'empleado' ? 'Emp' : 'Vis';

            return [
                'hora' => $b->fecha_hora->format('H:i'),
                'icono' => $icono,
                'color' => $color,
                'evento' => $b->tipo_evento,  // minúsculas: 'entrada' o 'salida'
                'estado' => $b->estado_evento,
                'nombre' => $nombre,
                'tipo_persona' => $tipoPersona,
                'origen' => $b->origen_escaneo,
                'motivo' => $b->motivo_denegacion,
            ];
        });

        return response()->json($resultado);
    }

    /**
     * Personas actualmente dentro de la planta.
     * Funciona para Guardia (su planta) y para Admin/Aprobador/Superadmin (planta seleccionada o propia).
     */
    public function personasDentro()
    {
        $user = Auth::user();
        
        // Si es guardia, usa su planta. Si es admin/aprobador, usa su planta_id.
        $plantaId = $user->guardia?->planta_id ?? $user->planta_id;

        if (!$plantaId) {
            return response()->json(['total' => 0, 'personas' => [], 'empleados' => 0, 'visitantes' => 0]);
        }

        $eventos = Bitacora::where('planta_id', $plantaId)
            ->whereDate('fecha_hora', today())
            ->whereNotIn('estado_evento', ['denegado'])
            ->orderBy('fecha_hora', 'desc')
            ->get()
            ->unique(fn($b) => $b->persona_tipo . '_' . $b->persona_id);

        $dentro = $eventos->filter(fn($b) => $b->tipo_evento === 'entrada')->values();

        if ($dentro->isEmpty()) {
            return response()->json(['total' => 0, 'personas' => [], 'empleados' => 0, 'visitantes' => 0]);
        }

        $idsEmpleados = $dentro->where('persona_tipo', 'empleado')->pluck('persona_id')->unique()->toArray();
        $empleados = Empleado::with('puesto')->whereIn('id_empleado', $idsEmpleados)->get()->keyBy('id_empleado');

        $idsPersonas = $dentro->where('persona_tipo', 'persona')->pluck('persona_id')->unique()->toArray();
        $personas = \App\Models\Persona::whereIn('id', $idsPersonas)->get()->keyBy('id');

        $lista = $dentro->map(function ($b) use ($empleados, $personas) {
            if ($b->persona_tipo === 'empleado') {
                $emp = $empleados->get($b->persona_id);
                return [
                    'tipo' => 'empleado',
                    'id' => $b->persona_id,
                    'nombre' => $emp?->nombre_completo ?? 'Desconocido',
                    'puesto' => $emp?->puesto?->nombre ?? '—',
                    'hora_entrada' => $b->fecha_hora->format('H:i'),
                ];
            } else {
                $per = $personas->get($b->persona_id);
                return [
                    'tipo' => 'visitante',
                    'id' => $b->persona_id,
                    'nombre' => $per?->nombre_completo ?? 'Visitante',
                    'puesto' => $per?->empresa ?? 'Visitante',
                    'hora_entrada' => $b->fecha_hora->format('H:i'),
                ];
            }
        });

        return response()->json([
            'total' => $lista->count(),
            'empleados' => $lista->where('tipo', 'empleado')->count(),
            'visitantes' => $lista->where('tipo', 'visitante')->count(),
            'personas' => $lista->sortBy('hora_entrada')->values(),
        ]);
    }

    /**
     * NUEVO: Marcar un denegado como "ya revisado" por el guardia.
     */
    public function marcarDenegadoVisto(Request $request)
    {
        $request->validate([
            'bitacora_id' => 'required|integer|exists:bitacora,id',
        ]);

        $bitacora = Bitacora::find($request->bitacora_id);
        if (!$bitacora) {
            return response()->json(['success' => false, 'message' => 'Registro no encontrado.'], 404);
        }

        // Solo se pueden marcar los denegados
        if ($bitacora->estado_evento !== 'denegado') {
            return response()->json(['success' => false, 'message' => 'Solo se pueden marcar los accesos denegados.'], 400);
        }

        // Verificar que sea de la planta del guardia
        $user = Auth::user();
        $plantaId = $user->guardia?->planta_id;
        if ($bitacora->planta_id != $plantaId) {
            return response()->json(['success' => false, 'message' => 'No tiene permisos sobre este registro.'], 403);
        }

        $bitacora->visto_guardia = true;
        $bitacora->observaciones = ($bitacora->observaciones ? $bitacora->observaciones . "\n" : "") 
            . "Revisado por guardia: " . $user->nombre_completo;
        $bitacora->save();

        return response()->json(['success' => true]);
    }

    /**
     * MODIFICADO: denegados NO vistos por el guardia.
     */
    public function denegadosGuardia()
    {
        $user = Auth::user();
        $plantaId = $user->guardia?->planta_id;

        $denegados = Bitacora::denegados()
            ->noVistosGuardia()  // NUEVO: solo los no vistos
            ->dePlanta($plantaId)
            ->hoy()
            ->orderBy('fecha_hora', 'desc')
            ->get();

        if ($denegados->isEmpty()) {
            return response()->json([]);
        }

        $idsEmpleados = $denegados->pluck('persona_id')->unique()->toArray();
        $empleados = Empleado::whereIn('id_empleado', $idsEmpleados)
            ->pluck('nombre_completo', 'id_empleado');

        $resultado = $denegados->map(function ($b) use ($empleados) {
            $motivoTexto = match($b->motivo_denegacion) {
                'sin_clasificar' => 'Empleado sin clasificar (acuda a RRHH)',
                'sin_planta' => 'Planta no configurada',
                'planta_no_autorizada' => 'Planta no autorizada',
                'salida_temprana' => 'Intentó salir antes de su hora',
                default => 'Motivo desconocido',
            };
            return [
                'bitacora_id' => $b->id,
                'nombre' => $empleados[$b->persona_id] ?? 'Desconocido',
                'hora' => $b->fecha_hora->format('H:i'),
                'motivo' => $motivoTexto,
            ];
        });

        return response()->json($resultado);
    }

    public function resolverPendiente(Request $request)
    {
        $bitacora = Bitacora::find($request->bitacora_id);
        if (!$bitacora) return response()->json(['success' => false], 404);

        if ($bitacora->estado_evento === 'pendiente_revision') {
            $bitacora->estado_evento = 'revision_completada';
            $bitacora->revision_exhaustiva = true;
        }

        if ($bitacora->estado_anomalia === 'pendiente') {
            $bitacora->estado_anomalia = 'revisada';
            $bitacora->observaciones = ($bitacora->observaciones ? $bitacora->observaciones . "\n" : "") . "Acción guardia: " . $request->descripcion;
        }

        $bitacora->save();

        if ($request->resultado === 'anomalia') {
            RevisionHallazgo::create([
                'bitacora_id' => $bitacora->id,
                'resultado' => 'anomalia',
                'descripcion_hallazgo' => $request->descripcion,
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function terminarTurno(Request $request)
    {
        $user = Auth::user();
        $turno = TurnoGuardia::where('guardia_id', $user->guardia_id)
            ->whereNull('fecha_hora_fin')->first();

        if ($turno) {
            $turno->fecha_hora_fin = now();
            $turno->save();
            LogSistema::registrar('FIN_TURNO_GUARDIA', "Turno finalizado");
        }

        // NUEVO: borrar el token de remember_me de la BD
        if ($user) {
            $user->setRememberToken(null);
            $user->save();
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function fotoEmpleado($id)
    {
        $empleado = Empleado::find($id);
        if (!$empleado || !$empleado->foto) {
            return response()->file(public_path('default-avatar.png'));
        }

        $url = config('sca_qr.url_fotos_sitec') . $empleado->foto;

        try {
            $imageContent = Http::get($url)->body();
            return response($imageContent, 200, ['Content-Type' => 'image/jpeg']);
        } catch (\Exception $e) {
            return response()->file(public_path('default-avatar.png'));
        }
    }
}