<?php
// app/Http/Controllers/SolicitudController.php
//
// CONTROLADOR DE SOLICITUDES (M4).
//
// CAMBIOS V4:
//   - Usa SolicitudInt (pase_salida, interplantas) y SolicitudExt (visita, contratista)
//     por separado, en lugar de un único modelo Solicitud.
//   - Usa Externo + SolicitudExternoDetalle para personas externas (no Persona + Credencial).
//   - Dedup de externos por CURP (o nombre+teléfono si no hay CURP).
//   - Checkbox "incluirme" para interplantas/pase_salida grupal.
//   - Generación de QR al aprobar solicitud ext (1 QR por externo).
//   - Auto-aprobación si el creador es Aprobador+ (marca ambos pasos).
//   - FKs renombradas: id_planta_origen, id_planta_destino, id_solicitud_int, etc.
//
// RESPONSABILIDADES:
//   - hub(): página principal según rol.
//   - create(): formulario de nueva solicitud.
//   - store(): crear solicitud (int o ext) con N+ personas.
//   - showInt()/showExt(): ver detalle.
//   - cancel(): cancelar pendiente.
//   - bandeja(): pendientes de aprobación (int + ext).
//   - historial(): todas las solicitudes resueltas (Admin/Super).
//   - misSolicitudes(): propias (int + ext).
//   - misAprobaciones(): que el usuario aprobó (int + ext).
//   - buscarEmpleado(): API para el formulario.
//   - buscarExternoPorCurp(): API para precargar externo por CURP.

namespace App\Http\Controllers;

use App\Http\Requests\StoreSolicitudRequest;
use App\Models\SolicitudInt;
use App\Models\SolicitudExt;
use App\Models\SolicitudExternoDetalle;
use App\Models\SolicitudEmpleado;
use App\Models\Externo;
use App\Models\Planta;
use App\Models\Sitec\Empleado;
use App\Models\LogSistema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SolicitudController extends Controller
{
    // ============================================================
    // HUB DE SOLICITUDES
    // ============================================================

    /**
     * Página principal: menú según rol.
     * Ruta: GET /solicitudes
     */
    public function hub()
    {
        $user = request()->user();

        // Contar pendientes de aprobación (para el badge de la bandeja)
        $pendientesAprobar = 0;
        if ($user->isAprobador() || $user->isAdmin() || $user->isSuperadmin()) {
            $plantaId = $user->planta_id;

            // Pendientes internos (origen o destino)
            $pendientesInt = SolicitudInt::where(function ($q) use ($plantaId) {
                $q->where('id_planta_origen', $plantaId)->where('estado', 'pendiente_origen')
                  ->orWhere('id_planta_destino', $plantaId)->where('estado', 'pendiente_destino');
            })->count();

            // Pendientes externos (destino)
            $pendientesExt = SolicitudExt::where('id_planta_destino', $plantaId)
                ->where('estado', 'pendiente')
                ->count();

            $pendientesAprobar = $pendientesInt + $pendientesExt;
        }

        return view('solicitudes.hub', compact('user', 'pendientesAprobar'));
    }

    // ============================================================
    // FORMULARIO DE NUEVA SOLICITUD
    // ============================================================

    public function create()
    {
        $user = request()->user();
        $tiposPermitidos = $this->tiposPermitidos($user);

        // FIX A-3: Si el usuario no tiene ningún tipo permitido (ej. guardia),
        // no debería ver el formulario. Redirigir al hub con mensaje.
        if (empty($tiposPermitidos)) {
            return redirect()->route('solicitudes.hub')
                ->with('error', 'Su rol no puede crear solicitudes.');
        }

        $plantas = Planta::where('activa', true)->orderBy('nombre')->get();

        $plantaOrigenId = $user->planta_id;
        $plantaOrigenNombre = $user->planta_nombre;

        // Admin y Superadmin pueden elegir planta destino para externos
        $puedeElegirDestinoExterno = $user->isAdmin() || $user->isSuperadmin();

        return view('solicitudes.create', compact(
            'plantas', 'plantaOrigenId', 'plantaOrigenNombre', 'tiposPermitidos', 'puedeElegirDestinoExterno'
        ));
    }

    // ============================================================
    // GUARDAR SOLICITUD
    // ============================================================

    /**
     * Crea una solicitud nueva (interna o externa).
     * Ruta: POST /solicitudes
     *
     * Lógica:
     *   1. Determinar si es interna (pase_salida, interplantas) o externa (visita, contratista).
     *   2. Calcular planta origen/destino según el tipo.
     *   3. Calcular estado inicial y auto-aprobación si el creador es Aprobador+.
     *   4. Crear la solicitud en la tabla correspondiente.
     *   5. Si es grupal: insertar empleados (con checkbox "incluirme").
     *   6. Si es externa: crear externos (dedup por CURP) + detalles con QR=NULL.
     *   7. Si fue auto-aprobada y es externa: generar QRs ahora.
     *   8. Disparar notificaciones.
     */
    public function store(StoreSolicitudRequest $request)
    {
        $user = $request->user();
        $tipo = $request->tipo;

        if (!in_array($tipo, $this->tiposPermitidos($user))) {
            return back()->withErrors(['tipo' => 'No tiene permisos para crear este tipo de solicitud.'])->withInput();
        }

        DB::beginTransaction();
        try {
            if (in_array($tipo, ['pase_salida', 'interplantas'])) {
                return $this->storeSolicitudInt($request, $user, $tipo);
            } else {
                return $this->storeSolicitudExt($request, $user, $tipo);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Error al crear la solicitud: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Crea una solicitud interna (pase_salida o interplantas).
     *
     * Lógica de planta destino (D1-bis):
     *   - pase_salida: destino = origen (el empleado se ausenta, no va a otra planta).
     *   - interplantas: destino != origen (validación obligatoria).
     *
     * Lógica de N+ empleados (G1):
     *   - El creador NO se incluye automáticamente.
     *   - Hay un checkbox "incluirme": si lo marca, se agrega a solicitud_empleados.
     *   - Si no hay empleados en la lista Y no marcó "incluirme" → es individual (para sí mismo).
     */
    private function storeSolicitudInt(StoreSolicitudRequest $request, $user, string $tipo)
    {
        $userId = (int) $user->username;
        $esAprobadorOSuperior = $user->isAprobador() || $user->isAdmin() || $user->isSuperadmin();
        $ahora = now();

        // V4: Admin/Superadmin eligen planta origen; Empleado/Aprobador usan su planta fija
        if ($user->isAdmin() || $user->isSuperadmin()) {
            $plantaOrigenId = $request->input('planta_origen_id');
            if (!$plantaOrigenId) {
                throw new \Exception('Debe seleccionar la planta origen.');
            }
        } else {
            $plantaOrigenId = $user->planta_id;
        }

        // Planta destino
        if ($tipo === 'pase_salida') {
            // D1-bis: destino = origen (no va a otra planta)
            $plantaDestinoId = $plantaOrigenId;
        } else {
            // interplantas: destino != origen
            $plantaDestinoId = $request->planta_destino_id;
            if ($plantaOrigenId === $plantaDestinoId) {
                throw new \Exception('Para interplantas, la planta destino debe ser distinta a su planta origen.');
            }
        }

        // Estado inicial y auto-aprobación
        $estado = 'pendiente_origen';
        $aprobadorOrigen = null;
        $fechaAprobOrigen = null;
        $aprobadorDestino = null;
        $fechaAprobDestino = null;

        if ($esAprobadorOSuperior) {
            // Auto-aprobación: marca origen (y destino si es interplantas)
            $estado = 'aprobado';
            $aprobadorOrigen = $userId;
            $fechaAprobOrigen = $ahora;
            if ($tipo === 'interplantas') {
                $aprobadorDestino = $userId;
                $fechaAprobDestino = $ahora;
            }
        }

        // FIX 3c: Cálculo automático de fechas para 'pase_salida'.
        // Para otros tipos (interplantas), se usan las fechas del form tal cual.
        $fechaInicio = $request->fecha_inicio;
        $fechaFin = $request->fecha_fin;

        if ($tipo === 'pase_salida') {
            // Recopilar TODOS los empleados involucrados (creador + agregados)
            $empleadosInput = $request->input('empleados', []);
            if ($request->boolean('incluirme') && !in_array($userId, $empleadosInput)) {
                $empleadosInput[] = $userId;
            }
            $empleadosIds = array_unique(array_merge([$userId], $empleadosInput));

            // Validar que todos tengan turno asignado
            $empleadoresConTurno = Empleado::with('turno')->whereIn('id_empleado', $empleadosIds)->get();
            $sinTurno = [];
            foreach ($empleadoresConTurno as $emp) {
                if (!$emp->turno || !$emp->turno->entrada || !$emp->turno->salida) {
                    $sinTurno[] = $emp->nombre_completo;
                }
            }

            if (!empty($sinTurno)) {
                // Caso 4: alguien sin turno → exigir fechas manuales
                if (empty($fechaInicio) || empty($fechaFin)) {
                    $nombres = implode(', ', $sinTurno);
                    throw new \Exception("Los siguientes empleados no tienen turno asignado en sitec: {$nombres}. Debe capturar la fecha y hora de inicio y fin manualmente.");
                }
                // Si enviaron fechas manuales, las usamos tal cual ($fechaInicio y $fechaFin ya están asignados)
            } else {
                // Todos tienen turno → cálculo automático
                // El creador es la referencia para el turno
                $empleadoCreador = $empleadoresConTurno->firstWhere('id_empleado', $userId);
                if (!$empleadoCreador) {
                    $empleadoCreador = $empleadoresConTurno->first();
                }

                $turno = $empleadoCreador->turno;
                $horaSalida = $turno->salida;   // "HH:MM:SS"
                $horaEntrada = $turno->entrada; // "HH:MM:SS"

                // Determinar la fecha base (el request trae fecha_inicio como date o datetime)
                $fechaBase = \Carbon\Carbon::parse($fechaInicio)->startOfDay();
                $hoy = now()->startOfDay();

                if ($fechaBase->isSameDay($hoy)) {
                    // Caso 1: HOY → inicio = ahora, fin = hoy + hora salida
                    $fechaInicio = now();
                    $fechaFin = $hoy->copy()->setTimeFromTimeString($horaSalida);
                } else {
                    // Caso 2 y 3: FUTURO
                    $horaManual = $request->input('hora_inicio_manual');
                    if (!empty($horaManual)) {
                        // Caso 3: hora de inicio modificada manualmente
                        $fechaInicio = $fechaBase->copy()->setTimeFromTimeString($horaManual);
                    } else {
                        // Caso 2: inicio = hora de entrada del turno
                        $fechaInicio = $fechaBase->copy()->setTimeFromTimeString($horaEntrada);
                    }
                    $fechaFin = $fechaBase->copy()->setTimeFromTimeString($horaSalida);
                }
            }
        }

        // ============================================================
        // FIX A-3: VALIDACIÓN DE DÍA LABORAL, INTERSECCIÓN Y "ESTÁ EN PLANTA"
        // ============================================================
        if (in_array($tipo, ['pase_salida', 'interplantas'])) {
            $empleadosIds = [$userId];
            $empleadosInput = $request->input('empleados', []);
            if ($request->boolean('incluirme') && !in_array($userId, $empleadosInput)) {
                $empleadosInput[] = $userId;
            }
            $empleadosIds = array_unique(array_merge($empleadosIds, $empleadosInput));

            $empleadosAValidar = Empleado::with(['turno', 'clasificacion'])->whereIn('id_empleado', $empleadosIds)->get();
            $fechaBase = \Carbon\Carbon::parse($fechaInicio)->startOfDay();
            $esHoy = $fechaBase->isSameDay(now()->startOfDay());
            $diaSemana = $fechaBase->dayOfWeekIso; // 1=Lun, 7=Dom

            foreach ($empleadosAValidar as $emp) {
                $tipoEmp = $emp->tipoEmpleado();
                
                // 1. Validar día laboral
                $esDiaLaboral = true;
                if ($tipoEmp === 'administrativo' && $diaSemana > 5) $esDiaLaboral = false;
                if ($tipoEmp === 'operativo' && $diaSemana > 6) $esDiaLaboral = false;

                if (!$esDiaLaboral) {
                    if ($tipo === 'pase_salida') {
                        // Excepción: HOY + está en planta
                        $ultimaBitacora = \App\Models\Bitacora::where('persona_id', (string) $emp->id_empleado)
                            ->whereDate('fecha_hora', today())
                            ->whereNotIn('estado_evento', ['denegado'])
                            ->latest('fecha_hora')->first();
                        $estaEnPlanta = $ultimaBitacora && $ultimaBitacora->tipo_evento === 'entrada';

                        if ($esHoy && $estaEnPlanta) {
                            // Permite crear, pero no hacemos nada (el guardia justificará al salir)
                        } else {
                            $nombresDias = [1=>'Lunes',2=>'Martes',3=>'Miércoles',4=>'Jueves',5=>'Viernes',6=>'Sábado',7=>'Domingo'];
                            throw new \Exception("No es posible crear el Pase de Salida para {$emp->nombre_completo} porque {$nombresDias[$diaSemana]} no es día laboral.");
                        }
                    } else {
                        // Interplantas: advertir pero permitir
                        LogSistema::registrar('WARN_INTERPLANTAS_DIA', "Solicitud INT para {$emp->nombre_completo} en día no laboral. Se permite con advertencia.");
                    }
                }
            }
        }

        // Crear la solicitud interna (con las fechas calculadas o manuales)
        $solicitud = SolicitudInt::create([
            'creador_empleado_id'             => $userId,
            'tipo'                            => $tipo,
            'id_planta_origen'                => $plantaOrigenId,
            'id_planta_destino'               => $plantaDestinoId,
            'fecha_inicio'                    => $fechaInicio,
            'fecha_fin'                       => $fechaFin,
            'motivo'                          => $request->motivo,
            'estado'                          => $estado,
            'aprobador_origen_empleado_id'    => $aprobadorOrigen,
            'fecha_aprobacion_origen'         => $fechaAprobOrigen,
            'aprobador_destino_empleado_id'   => $aprobadorDestino,
            'fecha_aprobacion_destino'        => $fechaAprobDestino,
        ]);

        // N+ empleados (interplantas o pase_salida grupal)
        $empleados = $request->input('empleados', []);

        // FIX A-6: El campo 'incluirme' ahora es controlado por el selector
        // "para_quien" en el form. Si es 'mi' o 'mi_y_otros', el JS envía
        // incluirme=1 automáticamente. Si es 'otros', envía 0.
        if ($request->boolean('incluirme')) {
            $empleados[] = $userId;
        }

        // Si es "solo para mí" (no hay empleados agregados ni incluirme),
        // aseguramos que el creador quede como único solicitante
        $paraQuien = $request->input('para_quien', 'mi');
        if ($paraQuien === 'mi' && empty($empleados)) {
            $empleados = [$userId];
        }

        // Validar que todos los empleados sean de la misma planta origen
        // FIX A-7: Castear $plantaOrigenId a int para que la comparación con
        // $emp->plantaId() (que retorna int) funcione. Antes usábamos !==
        // (estricto) y "3" !== 3 → true, así que rechazaba a todos.
        $plantaOrigenIdInt = (int) $plantaOrigenId;

        if (!empty($empleados)) {
            $empleados = array_unique($empleados);
            foreach ($empleados as $empId) {
                $emp = Empleado::find($empId);
                if (!$emp) {
                    throw new \Exception("Empleado ID {$empId} no encontrado.");
                }
                $empPlantaId = $emp->plantaId();
                // FIX A-7: comparar como enteros, no estricto string vs int
                if ($empPlantaId === null || $empPlantaId !== $plantaOrigenIdInt) {
                    throw new \Exception("El empleado {$emp->nombre_completo} no pertenece a su planta origen (su planta es {$empPlantaId}, la origen es {$plantaOrigenIdInt}). Todos los empleados deben ser de la misma planta.");
                }
            }

            // Insertar en solicitud_empleados
            foreach ($empleados as $empId) {
                SolicitudEmpleado::create([
                    'id_solicitud_int' => $solicitud->id_solicitud_int,
                    'empleado_id'      => $empId,
                    'estado'           => $esAprobadorOSuperior ? 'aprobado' : 'pendiente',
                ]);
            }
        }

        DB::commit();
        LogSistema::registrar('CREAR_SOLICITUD', "Solicitud INT #{$solicitud->id_solicitud_int} tipo={$tipo} estado={$estado}");

        // Notificaciones (defensivo: si fallan, no rompen la creación)
        try {
            if ($estado === 'aprobado' && $esAprobadorOSuperior) {
                \App\Services\NotificacionService::solicitudCreadaYAutoAprobada($solicitud, $user);
            } else {
                \App\Services\NotificacionService::nuevaSolicitudPendiente($solicitud);
            }
        } catch (\Exception $e) {
            LogSistema::registrar('ERROR_NOTIF', 'Error al notificar: ' . $e->getMessage());
        }

        return redirect()->route('solicitudes.show.int', $solicitud->id_solicitud_int)
            ->with('success', 'Solicitud #' . $solicitud->id_solicitud_int . ' creada correctamente.');
    }

        /**
     * Crea una solicitud externa (visita o contratista).
     *
     * CAMBIO V5:
     *   - La 'empresa' se guarda en la solicitud (no en cada externo).
     *   - Los externos solo guardan datos personales (curp, nombre, telefono).
     */
    private function storeSolicitudExt(StoreSolicitudRequest $request, $user, string $tipo)
    {
        $userId = (int) $user->username;
        $esAprobadorOSuperior = $user->isAprobador() || $user->isAdmin() || $user->isSuperadmin();
        $ahora = now();
        $tipoExterno = $tipo === 'visita' ? 'visitante' : 'contratista';

        // Anfitrión: si no se especifica o viene vacío, es el creador
        $anfitrionId = $request->filled('anfitrion_empleado_id')
            ? $request->input('anfitrion_empleado_id')
            : $userId;
        $anfitrion = Empleado::find($anfitrionId);
        if (!$anfitrion) {
            throw new \Exception("Anfitrión ID {$anfitrionId} no encontrado.");
        }

        // Planta destino
        if ($user->isAdmin() || $user->isSuperadmin()) {
            $plantaDestinoId = $request->planta_destino_id;
        } else {
            $plantaDestinoId = $user->planta_id;
        }

        // V5: empresa a nivel de solicitud (opcional para visita, obligatoria para contratista)
        $empresa = $request->filled('empresa') ? $request->input('empresa') : null;
        if ($tipo === 'contratista' && empty($empresa)) {
            throw new \Exception('La empresa es obligatoria para contratistas.');
        }

        // Estado inicial y auto-aprobación
        $estado = 'pendiente';
        $aprobadorDestino = null;
        $fechaAprobDestino = null;

        if ($esAprobadorOSuperior) {
            $estado = 'aprobado';
            $aprobadorDestino = $userId;
            $fechaAprobDestino = $ahora;
        }

        // ============================================================
        // FIX: Validar que los externos no tengan permisos vigentes solapados
        // (validación por planta destino)
        // ============================================================
        $nuevaFechaInicio = \Carbon\Carbon::parse($request->fecha_inicio);
        // Usar endOfDay() en fecha_fin por si la BD guarda solo DATE (sin hora)
        $nuevaFechaFin = \Carbon\Carbon::parse($request->fecha_fin)->endOfDay();
        $nuevaPlantaId = $plantaDestinoId; // La planta destino de la solicitud NUEVA
        
        $externosInput = $request->input('externos', []);
        foreach ($externosInput as $extData) {
            $externoExistente = null;
            
            // Buscar si el externo ya existe por CURP o Pasaporte
            if (!empty($extData['curp'])) {
                $externoExistente = Externo::where('curp', strtoupper(trim($extData['curp'])))->first();
            } elseif (!empty($extData['pasaporte'])) {
                $externoExistente = Externo::where('pasaporte', strtoupper(trim($extData['pasaporte'])))->first();
            }

            if ($externoExistente) {
                // Buscar solicitudes anteriores de este externo
                $detallesAnteriores = \App\Models\SolicitudExternoDetalle::where('id_externos', $externoExistente->id_externos)
                    ->with('solicitudExt')
                    ->get();

                foreach ($detallesAnteriores as $detalleAnt) {
                    $solAnt = $detalleAnt->solicitudExt;
                    if (!$solAnt) continue;

                    // Si la solicitud anterior está activa (no rechazada/cancelada)
                    if (in_array($solAnt->estado, ['pendiente', 'aprobado', 'aprobado_parcial'])) {
                        
                        // FIX: Solo bloquear si es para la MISMA planta destino
                        if ($solAnt->id_planta_destino == $nuevaPlantaId) {
                            $solAntInicio = \Carbon\Carbon::parse($solAnt->fecha_inicio);
                            $solAntFin = \Carbon\Carbon::parse($solAnt->fecha_fin)->endOfDay();

                            // Verificar solapamiento de fechas:
                            // Hay solapamiento si: inicio_nuevo < fin_anterior AND fin_nuevo > inicio_anterior
                            if ($nuevaFechaInicio < $solAntFin && $nuevaFechaFin > $solAntInicio) {
                                throw new \Exception("El externo {$externoExistente->nombre_completo} ya tiene un permiso vigente (Solicitud #{$solAnt->id_solicitud_ext}) para esta planta en las fechas seleccionadas. Debe cancelar el permiso anterior o esperar a que expire.");
                            }
                        }
                    }
                }
            }
        }
        // ============================================================

        // Crear la solicitud externa (con empresa)
        $solicitud = SolicitudExt::create([
            'creador_empleado_id'              => $userId,
            'tipo'                             => $tipoExterno,
            'anfitrion_empleado_id'            => $anfitrionId,
            'empresa'                          => $empresa, // V5: Se guarda aquí
            'id_planta_destino'                => $plantaDestinoId,
            'fecha_inicio'                     => $request->fecha_inicio,
            'fecha_fin'                        => $request->fecha_fin,
            'motivo'                           => $request->motivo,
            'estado'                           => $estado,
            'aprobador_destino_empleado_id'    => $aprobadorDestino,
            'fecha_aprobacion_destino'         => $fechaAprobDestino,
        ]);

        // N+ externos: crear/buscar cada uno (solo datos personales) y vincular a la solicitud
        $externos = $request->input('externos', []);
        foreach ($externos as $extData) {
            $externo = Externo::buscarOcrear([
                'curp'            => $extData['curp'] ?? null,
                'pasaporte'       => $extData['pasaporte'] ?? null,
                'nombre_completo' => $extData['nombre'],
                'telefono'        => $extData['telefono'] ?? null,
            ]);

            // Crear el detalle con QR=NULL y estado_qr='cancelado'
            SolicitudExternoDetalle::create([
                'id_solicitud_ext' => $solicitud->id_solicitud_ext,
                'id_externos'      => $externo->id_externos,
                'codigo_qr'        => null,
                'estado_qr'        => 'cancelado',
            ]);
        }

        // Si fue auto-aprobada, generar QRs para todos los externos
        if ($estado === 'aprobado') {
            $prefijoQr = config('sca_qr.prefijo_qr_externo');
            foreach ($solicitud->externos as $detalle) {
                $detalle->codigo_qr = $prefijoQr . Str::uuid()->toString();
                $detalle->estado_qr = 'autorizado';
                $detalle->save();
            }
        }

        DB::commit();
        LogSistema::registrar('CREAR_SOLICITUD', "Solicitud EXT #{$solicitud->id_solicitud_ext} tipo={$tipoExterno} estado={$estado}");

        // Notificaciones (defensivo)
        try {
            if ($estado === 'aprobado' && $esAprobadorOSuperior) {
                \App\Services\NotificacionService::solicitudCreadaYAutoAprobada($solicitud, $user);
            } else {
                \App\Services\NotificacionService::nuevaSolicitudPendiente($solicitud);
            }
        } catch (\Exception $e) {
            LogSistema::registrar('ERROR_NOTIF', 'Error al notificar: ' . $e->getMessage());
        }

        return redirect()->route('solicitudes.show.ext', $solicitud->id_solicitud_ext)
            ->with('success', 'Solicitud #' . $solicitud->id_solicitud_ext . ' creada correctamente.');
    }

    // ============================================================
    // VER DETALLE
    // ============================================================

    /**
     * Ver detalle de una solicitud interna.
     * Ruta: GET /solicitudes/int/{id}
     */
    public function showInt(SolicitudInt $solicitud)
    {
        $user = request()->user();
        $this->autorizarVistaInt($solicitud, $user);

        $solicitud->load(['plantaOrigen', 'plantaDestino', 'empleados']);

        // Cargar datos de empleados desde sitec (para mostrar nombres)
        $empleadosDetalle = collect();
        if ($solicitud->empleados->isNotEmpty()) {
            $ids = $solicitud->empleados->pluck('empleado_id')->toArray();
            $empleadosDetalle = Empleado::whereIn('id_empleado', $ids)
                ->with(['puesto', 'departamento'])
                ->get()
                ->keyBy('id_empleado');
        }

        $creador = Empleado::find($solicitud->creador_empleado_id);

        return view('solicitudes.show_int', compact('solicitud', 'empleadosDetalle', 'creador'));
    }

    /**
     * Ver detalle de una solicitud externa.
     * Ruta: GET /solicitudes/ext/{id}
     */
    public function showExt(SolicitudExt $solicitud)
    {
        $user = request()->user();
        $this->autorizarVistaExt($solicitud, $user);

        $solicitud->load(['plantaDestino', 'externos.externo']);

        $creador = Empleado::find($solicitud->creador_empleado_id);
        $anfitrion = Empleado::find($solicitud->anfitrion_empleado_id);

        return view('solicitudes.show_ext', compact('solicitud', 'creador', 'anfitrion'));
    }

    /**
     * Scoping de visibilidad para solicitudes internas.
     */
    private function autorizarVistaInt(SolicitudInt $solicitud, $user): void
    {
        // Empleado: solo las que él creó
        if ($user->isEmpleado() && $solicitud->creador_empleado_id !== (int) $user->username) {
            abort(403, 'No tiene permisos para ver esta solicitud.');
        }

        // Aprobador: solo las de su planta (origen o destino) o las que él creó
        if ($user->isAprobador()) {
            $plantaId = $user->planta_id;
            $esDeSuPlanta = ($solicitud->id_planta_origen === $plantaId || $solicitud->id_planta_destino === $plantaId);
            $esCreador = ($solicitud->creador_empleado_id === (int) $user->username);
            if (!$esDeSuPlanta && !$esCreador) {
                abort(403, 'No tiene permisos para ver solicitudes de otras plantas.');
            }
        }
        // Admin y Superadmin pasan directo
    }

    /**
     * Scoping de visibilidad para solicitudes externas.
     */
    private function autorizarVistaExt(SolicitudExt $solicitud, $user): void
    {
        // Empleado: solo las que él creó
        if ($user->isEmpleado() && $solicitud->creador_empleado_id !== (int) $user->username) {
            abort(403, 'No tiene permisos para ver esta solicitud.');
        }

        // Aprobador: solo las de su planta destino o las que él creó
        if ($user->isAprobador()) {
            $plantaId = $user->planta_id;
            $esDeSuPlanta = ($solicitud->id_planta_destino === $plantaId);
            $esCreador = ($solicitud->creador_empleado_id === (int) $user->username);
            if (!$esDeSuPlanta && !$esCreador) {
                abort(403, 'No tiene permisos para ver solicitudes de otras plantas.');
            }
        }
    }

    // ============================================================
    // CANCELAR SOLICITUD PENDIENTE
    // ============================================================

    /**
     * Cancela una solicitud interna (sin importar su estado actual).
     * Ruta: POST /solicitudes/int/{solicitud}/cancel
     *
     * V5: Se puede cancelar incluso si ya fue aprobada (el creador o un admin
     *     deciden anularla porque ya no se necesita).
     *     Solo el creador o Admin/Superadmin pueden cancelar.
     *     Marca estado = 'rechazado' con motivo "Cancelada por el creador".
     *     Si era grupal, marca todos los empleados como 'rechazado'.
     */
    public function cancelInt(SolicitudInt $solicitud, Request $request)
    {
        $user = $request->user();
        $esSuperior = $user->isAdmin() || $user->isSuperadmin();

        // Permiso: solo el creador o Admin/Superadmin
        if (!$esSuperior && $solicitud->creador_empleado_id !== (int) $user->username) {
            abort(403, 'No puede cancelar esta solicitud.');
        }

        // V5: permitir cancelar en cualquier estado (incluso aprobado)
        // Solo NO se puede cancelar si ya está rechazada/cancelada
        if ($solicitud->estado === 'rechazado') {
            return back()->withErrors(['error' => 'Esta solicitud ya fue cancelada/rechazada.']);
        }

        $solicitud->update([
            'estado'        => 'rechazado',
            'motivo_rechazo'=> 'Cancelada por ' . $user->nombre_completo,
        ]);

        // Si era grupal, marcar todos los empleados como rechazados
        if ($solicitud->empleados()->exists()) {
            \App\Models\SolicitudEmpleado::where('id_solicitud_int', $solicitud->id_solicitud_int)
                ->update(['estado' => 'rechazado']);
        }

        LogSistema::registrar('CANCELAR_SOLICITUD', "Solicitud INT #{$solicitud->id_solicitud_int} cancelada por {$user->username}");

        return redirect()->route('solicitudes.hub')
            ->with('success', "Solicitud #{$solicitud->id_solicitud_int} cancelada.");
    }

    /**
     * FIX A-8: Cancela un empleado específico de una solicitud grupal.
     * Ruta: POST /solicitudes/int/{solicitud}/cancel-empleado
     *
     * Lógica:
     *   - Solo el creador o Admin/Super pueden cancelar.
     *   - Marca el SolicitudEmpleado como 'rechazado'.
     *   - Si TODOS los empleados quedan rechazados, la solicitud completa
     *     pasa a 'rechazado' (cancelada).
     *   - No se puede cancelar al creador (él debe cancelar la solicitud completa).
     */
    public function cancelEmpleadoInt(SolicitudInt $solicitud, Request $request)
    {
        $request->validate([
            'empleado_id' => ['required', 'integer'],
        ]);

        $user = $request->user();
        $esSuperior = $user->isAdmin() || $user->isSuperadmin();
        $empleadoId = (int) $request->empleado_id;

        // Permiso: solo el creador o Admin/Superadmin
        if (!$esSuperior && $solicitud->creador_empleado_id !== (int) $user->username) {
            return back()->withErrors(['error' => 'No puede cancelar empleados de esta solicitud.']);
        }

        // No se puede cancelar al creador (él debe cancelar la solicitud completa)
        if ($solicitud->creador_empleado_id === $empleadoId) {
            return back()->withErrors(['error' => 'No puede cancelar al creador de la solicitud. Si desea cancelarla completamente, use el botón "Cancelar Solicitud".']);
        }

        // Verificar que el empleado esté en la solicitud
        $detalle = \App\Models\SolicitudEmpleado::where('id_solicitud_int', $solicitud->id_solicitud_int)
            ->where('empleado_id', $empleadoId)
            ->first();

        if (!$detalle) {
            return back()->withErrors(['error' => 'El empleado no está en esta solicitud.']);
        }

        // No se puede cancelar si ya está rechazado
        if ($detalle->estado === 'rechazado') {
            return back()->withErrors(['error' => 'Este empleado ya fue cancelado.']);
        }

        // Marcar el empleado como rechazado
        $empleado = Empleado::find($empleadoId);
        $detalle->update(['estado' => 'rechazado']);

        LogSistema::registrar(
            'CANCELAR_EMPLEADO_SOLICITUD',
            "Empleado {$empleadoId} ({$empleado?->nombre_completo}) cancelado de la solicitud INT #{$solicitud->id_solicitud_int} por {$user->username}"
        );

        // Verificar si TODOS los empleados (excluyendo al creador) están rechazados
        $empleadosActivos = \App\Models\SolicitudEmpleado::where('id_solicitud_int', $solicitud->id_solicitud_int)
            ->where('estado', '!=', 'rechazado')
            ->count();

        if ($empleadosActivos === 0) {
            // Todos cancelados → la solicitud completa se cancela
            $solicitud->update([
                'estado' => 'rechazado',
                'motivo_rechazo' => 'Todos los empleados fueron cancelados por ' . $user->nombre_completo,
            ]);
            return redirect()->route('solicitudes.show.int', $solicitud->id_solicitud_int)
                ->with('warning', "Empleado {$empleado?->nombre_completo} cancelado. Como no quedan más empleados, la solicitud completa fue cancelada.");
        }

        // FIX 2d: Si la solicitud estaba 'aprobado' y cancelamos a un empleado,
        // pasarla a 'aprobado_parcial' para que quede claro que ya no es completa.
        // Si estaba pendiente_origen/pendiente_destino, la dejamos igual (sigue pendiente).
        if ($solicitud->estado === 'aprobado') {
            $solicitud->update([
                'estado' => 'aprobado_parcial',
                'motivo_rechazo' => 'Un empleado fue cancelado por ' . $user->nombre_completo . '. Solicitud parcial.',
            ]);
        }

        return redirect()->route('solicitudes.show.int', $solicitud->id_solicitud_int)
            ->with('success', "Empleado {$empleado?->nombre_completo} cancelado de la solicitud.");
    }

    /**
     * Cancela una solicitud externa (sin importar su estado actual).
     * Ruta: POST /solicitudes/ext/{solicitud}/cancel
     *
     * V5: Se puede cancelar incluso si ya fue aprobada.
     *     Al cancelar una solicitud ext aprobada, se invalidan TODOS los QRs
     *     de los externos (estado_qr = 'cancelado') para que ya no puedan entrar.
     *     Solo el creador o Admin/Superadmin pueden cancelar.
     */
    public function cancelExt(SolicitudExt $solicitud, Request $request)
    {
        $user = $request->user();
        $esSuperior = $user->isAdmin() || $user->isSuperadmin();

        // Permiso: solo el creador o Admin/Superadmin
        if (!$esSuperior && $solicitud->creador_empleado_id !== (int) $user->username) {
            abort(403, 'No puede cancelar esta solicitud.');
        }

        // V5: permitir cancelar en cualquier estado (incluso aprobado)
        if ($solicitud->estado === 'rechazado') {
            return back()->withErrors(['error' => 'Esta solicitud ya fue cancelada/rechazada.']);
        }

        $solicitud->update([
            'estado'        => 'rechazado',
            'motivo_rechazo'=> 'Cancelada por ' . $user->nombre_completo,
        ]);

        // V5: Invalidar TODOS los QRs de los externos (ya no pueden entrar)
        foreach ($solicitud->externos as $detalle) {
            $detalle->estado_qr = 'cancelado';
            $detalle->save();
        }

        LogSistema::registrar('CANCELAR_SOLICITUD', "Solicitud EXT #{$solicitud->id_solicitud_ext} cancelada por {$user->username}. QRs invalidados.");

        return redirect()->route('solicitudes.hub')
            ->with('success', "Solicitud #{$solicitud->id_solicitud_ext} cancelada. Los QRs de acceso han sido invalidados.");
    }

        /**
     * FIX M-1: Cancela un externo específico de una solicitud grupal.
     * Ruta: POST /solicitudes/ext/{solicitud}/cancel-externo
     */
    public function cancelExternoIndividual(SolicitudExt $solicitud, Request $request)
    {
        $request->validate([
            'id_solicitud_externo_detalle' => ['required', 'integer'],
        ]);

        $user = $request->user();
        $esSuperior = $user->isAdmin() || $user->isSuperadmin();

        // Permiso: solo el creador o Admin/Superadmin
        if (!$esSuperior && $solicitud->creador_empleado_id !== (int) $user->username) {
            return back()->withErrors(['error' => 'No puede cancelar externos de esta solicitud.']);
        }

        $detalleId = (int) $request->id_solicitud_externo_detalle;

        $detalle = \App\Models\SolicitudExternoDetalle::where('id_solicitud_ext', $solicitud->id_solicitud_ext)
            ->where('id_solicitud_externo_detalle', $detalleId)
            ->first();

        if (!$detalle) {
            return back()->withErrors(['error' => 'El externo no está en esta solicitud.']);
        }

        if ($detalle->estado_qr === 'cancelado') {
            return back()->withErrors(['error' => 'Este externo ya fue cancelado.']);
        }

        // Marcar el externo como cancelado y anular su QR
        $detalle->estado_qr = 'cancelado';
        $detalle->codigo_qr = null;
        $detalle->save();

        $externo = $detalle->externo;
        LogSistema::registrar(
            'CANCELAR_EXTERNO_SOLICITUD',
            "Externo {$externo?->nombre_completo} cancelado de la solicitud EXT #{$solicitud->id_solicitud_ext} por {$user->username}"
        );

        // Verificar si TODOS los externos están cancelados
        $activos = \App\Models\SolicitudExternoDetalle::where('id_solicitud_ext', $solicitud->id_solicitud_ext)
            ->where('estado_qr', 'autorizado')
            ->count();

        if ($activos === 0) {
            // Todos cancelados → la solicitud completa se cancela
            $solicitud->update([
                'estado' => 'rechazado',
                'motivo_rechazo' => 'Todos los externos fueron cancelados por ' . $user->nombre_completo,
            ]);
            return redirect()->route('solicitudes.show.ext', $solicitud->id_solicitud_ext)
                ->with('warning', "Externo cancelado. Como no quedan más personas, la solicitud completa fue cancelada.");
        }

        // Si la solicitud estaba 'aprobado' y cancelamos a uno, pasarla a 'aprobado_parcial'
        if ($solicitud->estado === 'aprobado') {
            $solicitud->update([
                'estado' => 'aprobado_parcial',
                'motivo_rechazo' => 'Un externo fue cancelado por ' . $user->nombre_completo . '. Solicitud parcial.',
            ]);
        }

        return redirect()->route('solicitudes.show.ext', $solicitud->id_solicitud_ext)
            ->with('success', "Externo {$externo?->nombre_completo} cancelado de la solicitud.");
    }

    // ============================================================
    // BANDEJA DE APROBACIONES
    // ============================================================

    /**
     * Bandeja: muestra pendientes de aprobación para el usuario.
     * Ruta: GET /solicitudes/bandeja
     *
     * Muestra 3 listas:
     *   - Pendientes internos en origen (para aprobador de planta origen)
     *   - Pendientes internos en destino (para aprobador de planta destino)
     *   - Pendientes externos (para aprobador de planta destino)
     */
    public function bandeja(Request $request)
    {
        $user = request()->user();
        $plantaId = $user->planta_id;

        // Internos pendientes en origen
        $queryOrigen = SolicitudInt::with(['plantaOrigen', 'plantaDestino', 'creador'])
            ->where('estado', 'pendiente_origen');

        // Internos pendientes en destino
        $queryDestino = SolicitudInt::with(['plantaOrigen', 'plantaDestino', 'creador'])
            ->where('estado', 'pendiente_destino');

        // Externos pendientes
        $queryExt = SolicitudExt::with(['plantaDestino', 'creador', 'anfitrion', 'externos.externo'])
            ->where('estado', 'pendiente');

        // Scoping por rol
        if ($user->isAprobador()) {
            if (!$plantaId) {
                // Aprobador sin planta: no ve nada
                $plantas = Planta::where('activa', true)->orderBy('nombre')->get();
                return view('solicitudes.bandeja', [
                    'pendientesOrigen' => collect(),
                    'pendientesDestino' => collect(),
                    'pendientesExt'     => collect(),
                    'plantas'           => $plantas,
                ]);
            }
            $queryOrigen->where('id_planta_origen', $plantaId);
            $queryDestino->where('id_planta_destino', $plantaId);
            $queryExt->where('id_planta_destino', $plantaId);
        }

        // Filtros GET (tipo)
        if ($request->filled('tipo')) {
            $queryOrigen->where('tipo', $request->tipo);
            $queryDestino->where('tipo', $request->tipo);
            $tipoExt = $request->tipo === 'visita' ? 'visitante' : ($request->tipo === 'contratista' ? 'contratista' : null);
            if ($tipoExt) {
                $queryExt->where('tipo', $tipoExt);
            } else {
                $queryExt->whereRaw('1=0'); // no hay externos si filtra por tipo interno
            }
        }

        // Filtro por planta (solo Admin/Super)
        if ($request->filled('planta') && ($user->isAdmin() || $user->isSuperadmin())) {
            $plantaFiltro = $request->planta;
            $queryOrigen->where('id_planta_origen', $plantaFiltro);
            $queryDestino->where('id_planta_destino', $plantaFiltro);
            $queryExt->where('id_planta_destino', $plantaFiltro);
        }

        $pendientesOrigen = $queryOrigen->orderBy('created_at', 'desc')->get();
        $pendientesDestino = $queryDestino->orderBy('created_at', 'desc')->get();
        $pendientesExt = $queryExt->orderBy('created_at', 'desc')->get();
        $plantas = Planta::where('activa', true)->orderBy('nombre')->get();

        return view('solicitudes.bandeja', compact('pendientesOrigen', 'pendientesDestino', 'pendientesExt', 'plantas'));
    }

    // ============================================================
    // HISTORIAL GENERAL (Admin/Super)
    // ============================================================

    /**
     * Historial: todas las solicitudes resueltas (aprobadas o rechazadas).
     * Ruta: GET /solicitudes/historial
     *
     * Retorna 2 colecciones separadas (int y ext) para mostrar en la vista.
     */
    public function historial(Request $request)
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->isSuperadmin()) {
            abort(403, 'No tiene permisos para ver el Historial General.');
        }

        // Query para solicitudes internas resueltas
        $queryInt = SolicitudInt::with(['plantaOrigen', 'plantaDestino', 'creador', 'aprobadorOrigen', 'aprobadorDestino'])
            ->whereIn('estado', ['aprobado', 'rechazado']);

        // Query para solicitudes externas resueltas
        $queryExt = SolicitudExt::with(['plantaDestino', 'creador', 'anfitrion', 'aprobadorDestino', 'externos.externo'])
            ->whereIn('estado', ['aprobado', 'rechazado']);

        // Filtros
        if ($request->filled('tipo')) {
            if (in_array($request->tipo, ['pase_salida', 'interplantas'])) {
                $queryInt->where('tipo', $request->tipo);
                $queryExt->whereRaw('1=0');
            } else {
                $queryExt->where('tipo', $request->tipo === 'visita' ? 'visitante' : 'contratista');
                $queryInt->whereRaw('1=0');
            }
        }

        if ($request->filled('planta')) {
            $plantaId = $request->planta;
            $queryInt->where(function ($q) use ($plantaId) {
                $q->where('id_planta_origen', $plantaId)
                  ->orWhere('id_planta_destino', $plantaId);
            });
            $queryExt->where('id_planta_destino', $plantaId);
        }

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $queryInt->where(function ($q) use ($buscar) {
                $q->where('creador_empleado_id', $buscar)
                  ->orWhere('aprobador_origen_empleado_id', $buscar)
                  ->orWhere('aprobador_destino_empleado_id', $buscar);
            });
            $queryExt->where(function ($q) use ($buscar) {
                $q->where('creador_empleado_id', $buscar)
                  ->orWhere('anfitrion_empleado_id', $buscar)
                  ->orWhere('aprobador_destino_empleado_id', $buscar);
            });
        }

        if ($request->filled('fecha_desde')) {
            $queryInt->whereDate('created_at', '>=', $request->fecha_desde);
            $queryExt->whereDate('created_at', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $queryInt->whereDate('created_at', '<=', $request->fecha_hasta);
            $queryExt->whereDate('created_at', '<=', $request->fecha_hasta);
        }

        $solicitudesInt = $queryInt->orderBy('created_at', 'desc')->paginate(15, ['*'], 'int_page');
        $solicitudesExt = $queryExt->orderBy('created_at', 'desc')->paginate(15, ['*'], 'ext_page');
        $plantas = Planta::where('activa', true)->orderBy('nombre')->get();

        return view('solicitudes.index', compact('solicitudesInt', 'solicitudesExt', 'plantas'));
    }

    // ============================================================
    // MIS SOLICITUDES
    // ============================================================

    /**
     * Muestra las solicitudes creadas por el usuario (int + ext).
     * Ruta: GET /solicitudes/mis
     */
    public function misSolicitudes(Request $request)
    {
        $user = $request->user();
        $userId = (int) $user->username;

        $queryInt = SolicitudInt::with(['plantaOrigen', 'plantaDestino'])
            ->where('creador_empleado_id', $userId);

        $queryExt = SolicitudExt::with(['plantaDestino', 'externos.externo'])
            ->where('creador_empleado_id', $userId);

        // Filtros
        if ($request->filled('tipo')) {
            if (in_array($request->tipo, ['pase_salida', 'interplantas'])) {
                $queryInt->where('tipo', $request->tipo);
                $queryExt->whereRaw('1=0');
            } else {
                $queryExt->where('tipo', $request->tipo === 'visita' ? 'visitante' : 'contratista');
                $queryInt->whereRaw('1=0');
            }
        }

        if ($request->filled('estado')) {
            $queryInt->where('estado', $request->estado);
            $queryExt->where('estado', $request->estado);
        }

        $solicitudesInt = $queryInt->orderBy('created_at', 'desc')->paginate(15, ['*'], 'int_page');
        $solicitudesExt = $queryExt->orderBy('created_at', 'desc')->paginate(15, ['*'], 'ext_page');
        $plantas = Planta::where('activa', true)->orderBy('nombre')->get();

        return view('solicitudes.index', compact('solicitudesInt', 'solicitudesExt', 'plantas'));
    }

    // ============================================================
    // MIS APROBACIONES
    // ============================================================

    /**
     * Muestra las solicitudes que el usuario ha aprobado (int + ext).
     * Ruta: GET /solicitudes/mis-aprobaciones
     */
    public function misAprobaciones(Request $request)
    {
        $user = $request->user();
        $userId = (int) $user->username;

        $queryInt = SolicitudInt::with(['plantaOrigen', 'plantaDestino', 'creador'])
            ->where(function ($q) use ($userId) {
                $q->where('aprobador_origen_empleado_id', $userId)
                  ->orWhere('aprobador_destino_empleado_id', $userId);
            });

        $queryExt = SolicitudExt::with(['plantaDestino', 'creador', 'anfitrion'])
            ->where('aprobador_destino_empleado_id', $userId);

        if ($request->filled('tipo')) {
            if (in_array($request->tipo, ['pase_salida', 'interplantas'])) {
                $queryInt->where('tipo', $request->tipo);
                $queryExt->whereRaw('1=0');
            } else {
                $queryExt->where('tipo', $request->tipo === 'visita' ? 'visitante' : 'contratista');
                $queryInt->whereRaw('1=0');
            }
        }

        $solicitudesInt = $queryInt->orderBy('created_at', 'desc')->paginate(15, ['*'], 'int_page');
        $solicitudesExt = $queryExt->orderBy('created_at', 'desc')->paginate(15, ['*'], 'ext_page');
        $plantas = Planta::where('activa', true)->orderBy('nombre')->get();

        return view('solicitudes.index', compact('solicitudesInt', 'solicitudesExt', 'plantas'));
    }

    /**
     * API: buscar empleado por ID o nombre (para anfitrión o interplantas grupal).
     * Ruta: GET /solicitudes/buscar-empleado?q=...&planta_id=...
     *
     * FIX 2g: Lógica estricta de planta:
     *   - Aprobador/Empleado: SIEMPRE filtran por su propia planta.
     *   - Admin/Super: filtran por la planta_origen_id seleccionada en el form.
     *     Si no la envían, devuelven error explicativo.
     */
    public function buscarEmpleado(Request $request)
    {
        $request->validate(['q' => 'required|string|min:2']);
        $q = $request->q;
        $user = $request->user();

        $query = Empleado::where('estatus', 'Alta');

        if (ctype_digit($q)) {
            // Búsqueda por ID (numérico exacto)
            $query->where('id_empleado', (int) $q);
        } else {
            // FIX H-2: Búsqueda por palabras no consecutivas
            // Usar LOWER para ignorar mayúsculas/minúsculas
            $palabras = explode(' ', trim($q));
            $palabras = array_filter($palabras, fn($p) => strlen($p) >= 2);
            
            if (!empty($palabras)) {
                $query->where(function($sub) use ($palabras) {
                    foreach ($palabras as $palabra) {
                        $sub->where('nombre_completo', 'like', '%' . $palabra . '%');
                    }
                });
            }
        }

        // FIX 2g: Determinar planta de filtro según el rol
        $plantaFiltrada = null;
        $motivoRestriccion = null;

        if ($user->isAprobador() || $user->isEmpleado()) {
            // Aprobador/Empleado: SIEMPRE filtran por su propia planta
            $plantaFiltrada = $user->planta_id;
            if (!$plantaFiltrada) {
                return response()->json([
                    'empleados'          => [],
                    'restringido'        => true,
                    'motivo_restriccion' => 'No tiene planta asignada en el sistema. Contacte al administrador.',
                    'total'              => 0,
                ]);
            }
            $motivoRestriccion = 'Solo puede seleccionar empleados de su planta asignada: ' . $user->planta_nombre;
        } elseif ($user->isAdmin() || $user->isSuperadmin()) {
            // Admin/Super: filtran por la planta_origen_id seleccionada en el form
            $plantaInput = $request->query('planta_id');
            if (!$plantaInput) {
                return response()->json([
                    'empleados'          => [],
                    'restringido'        => true,
                    'motivo_restriccion' => 'No se pudo determinar la planta origen. Seleccione una opción en "¿Para quién es?" o, si es "Solo para otras personas", seleccione la Planta Origen en el formulario.',
                    'total'              => 0,
                ]);
            }
            $plantaFiltrada = (int) $plantaInput;
            $motivoRestriccion = 'Anfitrión filtrado por la planta destino seleccionada: ' .
                \App\Services\PlantaCacheService::nombrePlanta($plantaFiltrada);
        }

        // Cargar candidatos (con margen amplio para filtrar por planta en PHP)
        $candidatos = $query->with(['puesto', 'departamento'])
            ->limit(50)
            ->get();

        // Filtrar por planta derivada
        if ($plantaFiltrada !== null) {
            $candidatos = $candidatos->filter(function ($e) use ($plantaFiltrada) {
                return $e->plantaId() === $plantaFiltrada;
            })->values(); // FIX: Reiniciar índices para que JSON sea un array, no un objeto
        }

        $candidatos = $candidatos->take(10);

            $resultados = $candidatos->map(function ($e) {
            // FIX A-3: Verificar si el empleado está actualmente en planta (entrada sin salida hoy)
            $ultimaBitacora = \App\Models\Bitacora::where('persona_id', (string) $e->id_empleado)
                ->whereDate('fecha_hora', today())
                ->whereNotIn('estado_evento', ['denegado'])
                ->latest('fecha_hora')
                ->first();
            $estaEnPlanta = $ultimaBitacora && $ultimaBitacora->tipo_evento === 'entrada';

            return [
                'id'           => $e->id_empleado,
                'nombre'       => $e->nombre_completo,
                'puesto'       => $e->puesto?->nombre,
                'departamento' => $e->departamento?->nombre,
                'planta'       => $e->plantaNombre(),
                'planta_id'    => $e->plantaId(),
                'turno_id'      => $e->turno?->id_turno,
                'turno_entrada' => $e->turno?->entrada,
                'turno_salida'  => $e->turno?->salida,
                'tipo_emp'      => $e->tipoEmpleado(), // corporativo, administrativo, operativo
                'esta_en_planta'=> $estaEnPlanta,
            ];
        })->values(); // FIX: Reiniciar índices del map también

        return response()->json([
            'empleados'          => $resultados,
            'restringido'        => $plantaFiltrada !== null,
            'motivo_restriccion' => $motivoRestriccion,
            'total'              => $resultados->count(),
        ]);
    }

    /**
     * FIX 3: Valida que un conjunto de empleados tengan turno asignado.
     * Ruta: GET /solicitudes/validar-turno-pase?empleados=1,2,3
     *
     * Retorna si todos tienen turno y, si no, quién falta.
     */
    public function validarTurnoPase(Request $request)
    {
        $request->validate([
            'empleados' => ['required', 'string'], // formato: "1,2,3"
        ]);

        $ids = array_filter(explode(',', $request->empleados), fn($x) => ctype_digit(trim($x)));
        $ids = array_map('intval', $ids);

        if (empty($ids)) {
            return response()->json([
                'todos_tienen_turno' => true,
                'empleados_sin_turno' => [],
            ]);
        }

        $empleados = Empleado::with('turno')->whereIn('id_empleado', $ids)->get();
        $sinTurno = [];

        foreach ($empleados as $emp) {
            if (!$emp->turno || !$emp->turno->entrada || !$emp->turno->salida) {
                $sinTurno[] = [
                    'id' => $emp->id_empleado,
                    'nombre' => $emp->nombre_completo,
                ];
            }
        }

        return response()->json([
            'todos_tienen_turno' => empty($sinTurno),
            'empleados_sin_turno' => $sinTurno,
        ]);
    }

    /**
     * API: buscar externo por CURP (para precargar datos en el formulario).
     * Ruta: GET /solicitudes/buscar-externo?curp=...
     *
     * Si el externo ya existe (por CURP), devuelve sus datos para
     * precargar el formulario y evitar reingresarlos.
     *
     * CORRECCIÓN: Devuelve un objeto explícito con 'encontrado' => true/false
     * para que el frontend no tenga que adivinar si la respuesta es null.
     */
        public function buscarExternoPorCurp(Request $request)
    {
        // FIX G-1: Validación regex estricta de CURP + uppercase.
        $request->validate([
            'curp' => ['required', 'string', 'size:18', 'regex:/^[A-Z]{4}\d{6}[HM][A-Z]{5}[A-Z0-9]\d$/'],
        ]);

        $curp = strtoupper($request->curp);
        $externo = Externo::where('curp', $curp)->first();

        if ($externo) {
            $ultimaSolicitud = \App\Models\SolicitudExternoDetalle::where('id_externos', $externo->id_externos)
                ->with('solicitudExt')
                ->latest('id_solicitud_externo_detalle')
                ->first();

            return response()->json([
                'encontrado' => true,
                'id'         => $externo->id_externos,
                'nombre'     => $externo->nombre_completo,
                'telefono'   => $externo->telefono,
                'empresa'    => $ultimaSolicitud?->solicitudExt?->empresa,
                'tipo'       => $ultimaSolicitud?->solicitudExt?->tipo,
            ]);
        }

        return response()->json([
            'encontrado' => false,
        ]);
    }

    /**
     * FIX: Buscar externo por pasaporte.
     * Ruta: GET /solicitudes/buscar-externo-pasaporte?pasaporte=...
     */
    public function buscarExternoPorPasaporte(Request $request)
    {
        $request->validate([
            'pasaporte' => ['required', 'string', 'min:5', 'max:50'],
        ]);

        $pasaporte = strtoupper(trim($request->pasaporte));
        $externo = Externo::where('pasaporte', $pasaporte)->first();

        if ($externo) {
            $ultimaSolicitud = \App\Models\SolicitudExternoDetalle::where('id_externos', $externo->id_externos)
                ->with('solicitudExt')
                ->latest('id_solicitud_externo_detalle')
                ->first();

            return response()->json([
                'encontrado' => true,
                'id'         => $externo->id_externos,
                'nombre'     => $externo->nombre_completo,
                'telefono'   => $externo->telefono,
                'empresa'    => $ultimaSolicitud?->solicitudExt?->empresa,
                'tipo'       => $ultimaSolicitud?->solicitudExt?->tipo,
            ]);
        }

        return response()->json([
            'encontrado' => false,
        ]);
    }

    // ============================================================
    // HELPER PRIVADO
    // ============================================================

    private function tiposPermitidos($user): array
    {
        // FIX A-3: Los guardias NO crean solicitudes (su rol es operar el
        // kiosko/panel). Antes se retornaban todos los tipos para todos los
        // roles, lo que permitía a un guardia entrar al form y romper en
        // storeSolicitudInt porque (int)"G-0001" = 0.
        if ($user->isGuardia()) {
            return [];
        }
        return ['pase_salida', 'interplantas', 'visita', 'contratista'];
    }
}