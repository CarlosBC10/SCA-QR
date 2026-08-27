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

            // FIX: Contar pendientes internos (incluye aprobado_parcial si le toca al aprobador)
            $pendientesInt = SolicitudInt::where(function ($q) use ($plantaId) {
                // Pendientes en origen (o parcial donde origen no aprobó)
                $q->where(function ($q2) use ($plantaId) {
                    $q2->where('id_planta_origen', $plantaId)
                       ->whereIn('estado', ['pendiente_origen', 'aprobado_parcial'])
                       ->whereNull('fecha_aprobacion_origen');
                })
                // Pendientes en destino (o parcial donde destino no aprobó)
                ->orWhere(function ($q2) use ($plantaId) {
                    $q2->where('id_planta_destino', $plantaId)
                       ->whereIn('estado', ['pendiente_destino', 'aprobado_parcial'])
                       ->whereNull('fecha_aprobacion_destino');
                });
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
                ->with('sca_modal', sca_modal_flash('error', 'Acción no permitida', 'Su rol no puede crear solicitudes.'));
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

        // ============================================================
        // ESTADO INICIAL Y AUTO-APROBACIÓN
        // ============================================================
        // Reglas (alineadas con AprobacionController::aprobarInt):
        //
        //   - Empleado normal (no aprobador): estado = 'pendiente_origen'.
        //     Ningún aprobador se marca. La solicitud queda esperando a que
        //     el Aprobador de la planta origen la revise.
        //
        //   - Aprobador (de la planta origen) crea la solicitud:
        //     · Marca aprobador_origen + fecha.
        //     · pase_salida  → estado='aprobado' (no hay destino que aprobar).
        //     · interplantas → estado='pendiente_destino'. ⚠️ El Aprobador de
        //       la planta DESTINO debe revisar — el creador NO puede auto-
        //       aprobar el destino porque sería un bypass de seguridad
        //       (un Aprobador de Jardín inyectando gente en Partidas sin
        //       consentimiento del Aprobador de Partidas).
        //
        //   - Admin / Superadmin: SÍ pueden auto-aprobar ambos pasos (origen
        //     y destino) porque son super-usuarios con poder total.
        //     · pase_salida  → estado='aprobado'.
        //     · interplantas → estado='aprobado' (marcan ambos aprobadores).
        //
        // ⚠️ FIX CRÍTICO C4 (auditoría V6):
        //   Antes este bloque auto-aprobaba el destino SIEMPRE que el creador
        //   fuera Aprobador+ (incluyendo Aprobadores comunes), así que un
        //   Aprobador de planta A podía inyectar empleados en planta B sin
        //   que el Aprobador de B consintiera. Eso rompía el control de
        //   doble aprobación del SRS para interplantas.
        //   Ahora separamos: solo Admin/Super auto-aprueban destino.

        $estado            = 'pendiente_origen';
        $aprobadorOrigen   = null;
        $fechaAprobOrigen  = null;
        $aprobadorDestino  = null;
        $fechaAprobDestino = null;

        if ($esAprobadorOSuperior) {
            // Marca aprobación de origen (el creador siempre aprueba su planta)
            $aprobadorOrigen  = $userId;
            $fechaAprobOrigen = $ahora;

            // ¿Es Admin/Super? → puede auto-aprobar el destino también.
            $esSuperior = $user->isAdmin() || $user->isSuperadmin();

            if ($tipo === 'pase_salida') {
                // Pase de salida: solo requiere aprobación de origen.
                $estado = 'aprobado';
            } elseif ($esSuperior) {
                // Admin/Super + interplantas: aprueba ambos pasos.
                $aprobadorDestino  = $userId;
                $fechaAprobDestino = $ahora;
                $estado            = 'aprobado';
            } else {
                // Aprobador común + interplantas: solo aprueba origen.
                // El Aprobador de la planta destino debe revisar después.
                $estado = 'pendiente_destino';
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
            // FIX 3: En interplantas, si el creador es Aprobador (no Admin),
            // el empleado queda 'aprobado_origen' (falta destino).
            foreach ($empleados as $empId) {
                $estadoEmp = 'pendiente';
                if ($esAprobadorOSuperior) {
                    if ($tipo === 'interplantas' && !$user->isAdmin() && !$user->isSuperadmin()) {
                        $estadoEmp = 'aprobado_origen';
                    } else {
                        $estadoEmp = 'aprobado';
                    }
                }
                SolicitudEmpleado::create([
                    'id_solicitud_int' => $solicitud->id_solicitud_int,
                    'empleado_id'      => $empId,
                    'estado'           => $estadoEmp,
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

        // ============================================================
        // CAMBIO V7: Enviar correo a aprobadores/admin/super al crear
        // ============================================================
        try {
            $aprobadores = \App\Models\UsuarioSca::whereHas('rol', fn($q) => 
                $q->whereIn('nombre', ['Aprobador', 'Admin', 'Superadmin'])
            )->whereNull('fecha_baja')->get();

            $creadorNombre = $user->nombre_completo;
            $empleadosIds = $solicitud->empleados->map(fn($se) => $se->empleado_id)->toArray();
            $nombresEmpleados = \App\Models\Sitec\Empleado::whereIn('id_empleado', $empleadosIds)->pluck('nombre_completo')->toArray();

            foreach ($aprobadores as $aprobador) {
                if ($aprobador->email_emp) {
                    \Mail::to($aprobador->email_emp)->send(new \App\Mail\NotificacionSolicitudMail(
                        $solicitud,
                        $aprobador->nombre_completo,
                        'Nueva solicitud creada - #' . $solicitud->id_solicitud_int,
                        '🔔 Nueva Solicitud Pendiente',
                        'Se ha creado una nueva solicitud que requiere su revisión.',
                        null,
                        null, // aprobados
                        null, // rechazados
                        $nombresEmpleados, // personas (neutral)
                        $creadorNombre
                    ));
                }
            }
        } catch (\Exception $e) {
            LogSistema::registrar('ERROR_NOTIF', 'Error al notificar nueva solicitud INT: ' . $e->getMessage());
        }

        // M2 [ScaModal] — Mensaje detallado de creación.
        $mensaje = "<div class='text-start'>";
        $mensaje .= "<div class='mb-2'><strong>Solicitud creada:</strong> #INT-{$solicitud->id_solicitud_int}</div>";
        $mensaje .= "<div class='mb-2'><strong>Tipo:</strong> " . ($solicitud->tipo === 'pase_salida' ? 'Pase de salida' : 'Interplantas') . "</div>";
        $mensaje .= "<div class='alert alert-info mt-2 mb-0 py-2'><i class='bi bi-info-circle-fill'></i> La solicitud fue enviada a aprobación. Recibirás una notificación cuando sea procesada.</div>";
        $mensaje .= "</div>";

        return redirect()->route('solicitudes.show.int', $solicitud->id_solicitud_int)
            ->with('sca_modal', sca_modal_flash('success', 'Solicitud creada', $mensaje));
    }

    /**
     * Crea una solicitud externa (visita o contratista).
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

        // V5: empresa a nivel de solicitud
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
        // ============================================================
        $nuevaFechaInicio = \Carbon\Carbon::parse($request->fecha_inicio);
        $nuevaFechaFin = \Carbon\Carbon::parse($request->fecha_fin)->endOfDay();
        $nuevaPlantaId = $plantaDestinoId;
        
        $externosInput = $request->input('externos', []);
        foreach ($externosInput as $extData) {
            $externoExistente = null;
            $tipoId = strtolower(trim($extData['tipo_identificacion'] ?? ''));
            $valorId = trim($extData['identificacion'] ?? '');
            if ($tipoId === 'curp') {
                $valorId = strtoupper($valorId);
            }

            if ($tipoId && $valorId) {
                $externoExistente = Externo::where('tipo_identificacion', $tipoId)
                    ->where('identificacion', $valorId)
                    ->first();
            }

            if ($externoExistente) {
                $detallesAnteriores = \App\Models\SolicitudExternoDetalle::where('id_externos', $externoExistente->id_externos)
                    ->with('solicitudExt')
                    ->get();

                foreach ($detallesAnteriores as $detalleAnt) {
                    $solAnt = $detalleAnt->solicitudExt;
                    if (!$solAnt) continue;

                    if (in_array($solAnt->estado, ['pendiente', 'aprobado', 'aprobado_parcial'])) {
                        if ($solAnt->id_planta_destino == $nuevaPlantaId) {
                            $solAntInicio = \Carbon\Carbon::parse($solAnt->fecha_inicio);
                            $solAntFin = \Carbon\Carbon::parse($solAnt->fecha_fin)->endOfDay();

                            if ($nuevaFechaInicio < $solAntFin && $nuevaFechaFin > $solAntInicio) {
                                throw new \Exception("El externo {$externoExistente->nombre_completo} ya tiene un permiso vigente (Solicitud #{$solAnt->id_solicitud_ext}) para esta planta en las fechas seleccionadas. Debe cancelar el permiso anterior o esperar a que expire.");
                            }
                        }
                    }
                }
            }
        }

        // Crear la solicitud externa (con empresa)
        $solicitud = SolicitudExt::create([
            'creador_empleado_id'              => $userId,
            'tipo'                             => $tipoExterno,
            'anfitrion_empleado_id'            => $anfitrionId,
            'empresa'                          => $empresa,
            'id_planta_destino'                => $plantaDestinoId,
            'fecha_inicio'                     => $request->fecha_inicio,
            'fecha_fin'                        => $request->fecha_fin,
            'motivo'                           => $request->motivo,
            'estado'                           => $estado,
            'aprobador_destino_empleado_id'   => $aprobadorDestino,
            'fecha_aprobacion_destino'         => $fechaAprobDestino,
        ]);

        // N+ externos: crear/buscar cada uno
        $externos = $request->input('externos', []);
        foreach ($externos as $extData) {
            $externo = Externo::buscarOcrear([
                'tipo_identificacion' => $extData['tipo_identificacion'] ?? null,
                'identificacion'      => $extData['identificacion'] ?? null,
                'nombre_completo'     => $extData['nombre'],
                'telefono'            => $extData['telefono'] ?? null,
            ]);

            SolicitudExternoDetalle::create([
                'id_solicitud_ext' => $solicitud->id_solicitud_ext,
                'id_externos'      => $externo->id_externos,
                'codigo_qr'        => null,
                'estado_qr'        => 'pendiente',
            ]);
        }

        // ============================================================
        // AUTO-APROBACIÓN: Generar QRs y enviar correo si aplica
        // ============================================================
        if ($estado === 'aprobado') {
            $prefijoQr = config('sca_qr.prefijo_qr_externo');
            foreach ($solicitud->externos as $detalle) {
                $detalle->codigo_qr = $prefijoQr . Str::uuid()->toString();
                $detalle->estado_qr = 'autorizado';
                $detalle->save();
            }

            // FIX V7: enviar credenciales por correo al creador (auto-aprobación)
            try {
                $usuarioCreador = \App\Models\UsuarioSca::where('username', (string) $userId)->first();
                if ($usuarioCreador && $usuarioCreador->email_emp) {
                    $autorizados = $solicitud->externos->filter(function($d) {
                        return $d->estado_qr === 'autorizado' && $d->codigo_qr;
                    })->count();

                    if ($autorizados > 0) {
                        \Mail::to($usuarioCreador->email_emp)
                            ->send(new \App\Mail\CredencialExternoMail(
                                $solicitud,
                                $usuarioCreador->nombre_completo
                            ));
                    }
                }
            } catch (\Exception $e) {
                LogSistema::registrar('ERROR_NOTIF', 'Error al enviar credenciales por correo: ' . $e->getMessage());
            }
        }

        DB::commit();
        LogSistema::registrar('CREAR_SOLICITUD', "Solicitud EXT #{$solicitud->id_solicitud_ext} tipo={$tipoExterno} estado={$estado}");

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

        // ============================================================
        // CAMBIO V7: Enviar correo a aprobadores/admin/super al crear
        // ============================================================
        try {
            $aprobadores = \App\Models\UsuarioSca::whereHas('rol', fn($q) => 
                $q->whereIn('nombre', ['Aprobador', 'Admin', 'Superadmin'])
            )->whereNull('fecha_baja')->get();

            $creadorNombre = $user->nombre_completo;
            $nombresExternos = $solicitud->externos->map(fn($d) => $d->externo?->nombre_completo ?? 'N/A')->toArray();

            foreach ($aprobadores as $aprobador) {
                if ($aprobador->email_emp) {
                    \Mail::to($aprobador->email_emp)->send(new \App\Mail\NotificacionSolicitudMail(
                        $solicitud,
                        $aprobador->nombre_completo,
                        'Nueva solicitud creada - #' . $solicitud->id_solicitud_ext,
                        '🔔 Nueva Solicitud Pendiente',
                        'Se ha creado una nueva solicitud que requiere su revisión.',
                        null,
                        null, // aprobados
                        null, // rechazados
                        $nombresExternos, // personas (neutral)
                        $creadorNombre
                    ));
                }
            }
        } catch (\Exception $e) {
            LogSistema::registrar('ERROR_NOTIF', 'Error al notificar nueva solicitud EXT: ' . $e->getMessage());
        }

        // M2 [ScaModal] — Mensaje detallado de creación.
        $mensaje = "<div class='text-start'>";
        $mensaje .= "<div class='mb-2'><strong>Solicitud creada:</strong> #EXT-{$solicitud->id_solicitud_ext}</div>";
        $mensaje .= "<div class='mb-2'><strong>Tipo:</strong> " . ($solicitud->tipo === 'visita' ? 'Visita' : 'Contratista') . "</div>";
        $mensaje .= "<div class='alert alert-info mt-2 mb-0 py-2'><i class='bi bi-info-circle-fill'></i> La solicitud fue enviada a aprobación. Recibirás una notificación cuando sea procesada.</div>";
        $mensaje .= "</div>";

        return redirect()->route('solicitudes.show.ext', $solicitud->id_solicitud_ext)
            ->with('sca_modal', sca_modal_flash('success', 'Solicitud creada', $mensaje));
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

        // Permiso: el creador, o Admin/Superadmin, o Aprobador de la planta
        if (!$esSuperior 
            && $solicitud->creador_empleado_id !== (int) $user->username
            && !($user->isAprobador() && ($solicitud->id_planta_origen === $user->planta_id || $solicitud->id_planta_destino === $user->planta_id))
        ) {
            abort(403, 'No tiene permisos para cancelar esta solicitud.');
        }

        if ($solicitud->estado === 'rechazado') {
            return back()->withErrors(['error' => 'Esta solicitud ya fue cancelada/rechazada.']);
        }

        $solicitud->estado = 'rechazado';
        $solicitud->motivo_rechazo = $request->motivo_rechazo ?? 'Cancelada por el creador';
        $solicitud->save();

        // Si era grupal, marcar todos como rechazados
        if ($solicitud->esGrupal()) {
            SolicitudEmpleado::where('id_solicitud_int', $solicitud->id_solicitud_int)
                ->update(['estado' => 'rechazado']);
        }

        LogSistema::registrar('CANCELAR_SOLICITUD', "Solicitud INT #{$solicitud->id_solicitud_int} cancelada por {$user->username}");

        // ============================================================
        // CAMBIO V7: Enviar correo al creador con lista de afectados
        // ============================================================
        try {
            $usuarioCreador = \App\Models\UsuarioSca::where('username', (string) $solicitud->creador_empleado_id)->first();
            if ($usuarioCreador && $usuarioCreador->email_emp) {
                // Listar todos los empleados afectados
                $empleadosIds = $solicitud->empleados->map(fn($se) => $se->empleado_id)->toArray();
                $nombresAfectados = \App\Models\Sitec\Empleado::whereIn('id_empleado', $empleadosIds)
                    ->pluck('nombre_completo')->toArray();
                
                // Si no hay empleados (individual), el afectado es el creador
                if (empty($nombresAfectados)) {
                    $creadorEmp = \App\Models\Sitec\Empleado::find($solicitud->creador_empleado_id);
                    $nombresAfectados = [$creadorEmp?->nombre_completo ?? 'Empleado'];
                }

                \Mail::to($usuarioCreador->email_emp)->send(new \App\Mail\NotificacionSolicitudMail(
                    $solicitud,
                    $usuarioCreador->nombre_completo,
                    'Solicitud cancelada - #' . $solicitud->id_solicitud_int,
                    '❌ Solicitud Cancelada',
                    'Tu solicitud ha sido cancelada. Se cancelaron los permisos de ' . count($nombresAfectados) . ' persona(s).',
                    $request->motivo_rechazo ?? 'Cancelada por el usuario',
                    null,               // aprobados
                    $nombresAfectados,  // rechazados (todos los afectados)
                    null,               // personas
                    null                // creadorNombre
                ));
            }
        } catch (\Exception $e) {
            LogSistema::registrar('ERROR_NOTIF', 'Error al enviar correo de cancelación: ' . $e->getMessage());
        }

        // M2 [ScaModal] — Mensaje detallado de cancelación.
        $mensaje = "<div class='text-start'>";
        $mensaje .= "<div class='mb-2'><strong>Solicitud cancelada:</strong> #INT-{$solicitud->id_solicitud_int}</div>";
        $mensaje .= "<div class='alert alert-warning mt-2 mb-0 py-2'><i class='bi bi-exclamation-triangle-fill'></i> La solicitud fue cancelada por <strong>{$user->nombre_completo}</strong>. Los accesos asociados quedan invalidados.</div>";
        $mensaje .= "</div>";

        // M2: Setear flash en sesión ANTES de devolver JSON (Option E)
        session()->flash('sca_modal', sca_modal_flash('success', 'Solicitud cancelada', $mensaje));
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'titulo' => 'Solicitud cancelada',
                'mensaje' => $mensaje,
                'redirect' => route('solicitudes.hub'),
            ]);
        }

        return redirect()->route('solicitudes.hub')
            ->with('sca_modal', sca_modal_flash('success', 'Solicitud cancelada', $mensaje));
    }



    /**
     * Cancela un empleado específico de una solicitud grupal interna.
     * Ruta: POST /solicitudes/int/{solicitud}/cancel-empleado
     *
     * Body:
     *   - empleado_id: int
     *   - motivo_cancelacion: string (viene del modal JS)
     *
     * CAMBIO V7: guarda el motivo y envía correo al creador.
     */
    public function cancelEmpleadoInt(SolicitudInt $solicitud, Request $request)
    {
        $user = $request->user();
        $request->validate([
            'empleado_id' => 'required|integer',
            'motivo_cancelacion' => 'required|string|max:500',
        ]);

        $empleadoId = $request->empleado_id;
        $motivo = $request->motivo_cancelacion;

        $se = SolicitudEmpleado::where('id_solicitud_int', $solicitud->id_solicitud_int)
            ->where('empleado_id', $empleadoId)
            ->first();

        if (!$se) {
            return back()->withErrors(['error' => 'Empleado no encontrado en esta solicitud.']);
        }

        if ($se->estado === 'rechazado') {
            return back()->withErrors(['error' => 'Este empleado ya fue rechazado.']);
        }

        $se->estado = 'rechazado';
        $se->save();

        // CAMBIO V7: Verificar si quedan aprobados
        $quedanAprobados = SolicitudEmpleado::where('id_solicitud_int', $solicitud->id_solicitud_int)
            ->where('estado', 'aprobado')
            ->exists();

        $estadoAnterior = $solicitud->estado;
        
        if (!$quedanAprobados && in_array($solicitud->estado, ['aprobado', 'aprobado_parcial'])) {
            // Si no quedan aprobados, la solicitud se rechaza
            $solicitud->estado = 'rechazado';
            $solicitud->motivo_rechazo = 'Todos los empleados fueron cancelados. Último motivo: ' . $motivo;
            $solicitud->save();
        } elseif ($solicitud->estado === 'aprobado') {
            // Si quedan aprobados pero se canceló uno, pasa a parcial
            $solicitud->estado = 'aprobado_parcial';
            $solicitud->motivo_rechazo = 'Empleado cancelado: ' . $motivo;
            $solicitud->save();
        }

        // Enviar correo al creador
        try {
            $usuarioCreador = \App\Models\UsuarioSca::where('username', (string) $solicitud->creador_empleado_id)->first();
            if ($usuarioCreador && $usuarioCreador->email_emp) {
                $empleado = \App\Models\Sitec\Empleado::find($empleadoId);
                $nombreEmp = $empleado?->nombre_completo ?? 'Empleado #' . $empleadoId;

                \Mail::to($usuarioCreador->email_emp)->send(new \App\Mail\NotificacionSolicitudMail(
                    $solicitud,
                    $usuarioCreador->nombre_completo,
                    'Permiso cancelado - ' . $nombreEmp,
                    '❌ Permiso Cancelado',
                    "Se ha cancelado el permiso de {$nombreEmp} en la solicitud #{$solicitud->id_solicitud_int}.",
                    $motivo,
                    null, // aprobados
                    [$nombreEmp], // rechazados
                    null, // personas
                    null
                ));
            }
        } catch (\Exception $e) {
            LogSistema::registrar('ERROR_NOTIF', 'Error al enviar correo de cancelación individual: ' . $e->getMessage());
        }

        LogSistema::registrar('CANCELAR_EMPLEADO', "Empleado {$empleadoId} cancelado de solicitud INT #{$solicitud->id_solicitud_int} por {$user->username}");

        // M2 [ScaModal] — Forzar el nombre del empleado desde sitec.
        $empleadoSitec = \App\Models\Sitec\Empleado::on('sitec')->find($empleadoId);
        $nombreEmp = $empleadoSitec?->nombre_completo ?? "Empleado #{$empleadoId}";

        if ($solicitud->estado === 'rechazado') {
            // Caso total: no quedan más aprobados → solicitud cancelada
            $mensaje = "<div class='text-start'>";
            $mensaje .= "<div class='mb-2'><strong>Empleado cancelado:</strong> {$nombreEmp}</div>";
            $mensaje .= "<div class='alert alert-warning mt-2 mb-0 py-2'><i class='bi bi-exclamation-triangle-fill'></i> Como no quedan más empleados aprobados, <strong>la solicitud #INT-{$solicitud->id_solicitud_int} fue cancelada completamente</strong>.</div>";
            $mensaje .= "</div>";
            
            // M2: Setear flash en sesión ANTES de devolver JSON (Option E)
            session()->flash('sca_modal', sca_modal_flash('warning', 'Solicitud cancelada', $mensaje));
            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'redirect' => route('solicitudes.show.int', $solicitud->id_solicitud_int)]);
            }
            
            return back()->with('sca_modal', sca_modal_flash('warning', 'Solicitud cancelada', $mensaje));
        } else {
            // Caso parcial: quedan más aprobados
            $mensaje = "<div class='text-start'>";
            $mensaje .= "<div class='mb-2'><strong>Empleado cancelado:</strong> {$nombreEmp}</div>";
            $mensaje .= "<div class='mb-2'><strong>Solicitud:</strong> #INT-{$solicitud->id_solicitud_int}</div>";
            if ($solicitud->estado === 'aprobado_parcial') {
                $mensaje .= "<div class='alert alert-warning mt-2 mb-0 py-2'><i class='bi bi-info-circle-fill'></i> La solicitud pasó a estado <strong>Aprobada parcialmente</strong>.</div>";
            }
            $mensaje .= "</div>";
            
            // M2: Setear flash en sesión ANTES de devolver JSON (Option E)
            session()->flash('sca_modal', sca_modal_flash('success', 'Empleado cancelado', $mensaje));
            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'redirect' => route('solicitudes.show.int', $solicitud->id_solicitud_int)]);
            }
            
            return back()->with('sca_modal', sca_modal_flash('success', 'Empleado cancelado', $mensaje));
        }
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

        $solicitud->estado = 'rechazado';
        $solicitud->motivo_rechazo = $request->motivo_rechazo ?? 'Cancelada por el creador';
        $solicitud->save();

        // V5: Invalidar TODOS los QRs de los externos (ya no pueden entrar)
        foreach ($solicitud->externos as $detalle) {
            $detalle->estado_qr = 'cancelado';
            $detalle->save();
        }

        LogSistema::registrar('CANCELAR_SOLICITUD', "Solicitud EXT #{$solicitud->id_solicitud_ext} cancelada por {$user->username}. QRs invalidados.");

        // ============================================================
        // CAMBIO V7: Enviar correo al creador con lista de afectados
        // ============================================================
        try {
            $usuarioCreador = \App\Models\UsuarioSca::where('username', (string) $solicitud->creador_empleado_id)->first();
            if ($usuarioCreador && $usuarioCreador->email_emp) {
                // Listar todos los externos afectados
                $nombresAfectados = $solicitud->externos->map(fn($d) => $d->externo?->nombre_completo ?? 'Externo')->toArray();

                \Mail::to($usuarioCreador->email_emp)->send(new \App\Mail\NotificacionSolicitudMail(
                    $solicitud,
                    $usuarioCreador->nombre_completo,
                    'Solicitud cancelada - #' . $solicitud->id_solicitud_ext,
                    '❌ Solicitud Cancelada',
                    'Tu solicitud ha sido cancelada. Se cancelaron los permisos de ' . count($nombresAfectados) . ' persona(s).',
                    $request->motivo_rechazo ?? 'Cancelada por el usuario',
                    null,               // aprobados
                    $nombresAfectados,  // rechazados (todos los afectados)
                    null,               // personas
                    null                // creadorNombre
                ));
            }
        } catch (\Exception $e) {
            LogSistema::registrar('ERROR_NOTIF', 'Error al enviar correo de cancelación: ' . $e->getMessage());
        }

        // M2 [ScaModal] — Mensaje detallado de cancelación EXT.
        $mensaje = "<div class='text-start'>";
        $mensaje .= "<div class='mb-2'><strong>Solicitud cancelada:</strong> #EXT-{$solicitud->id_solicitud_ext}</div>";
        $mensaje .= "<div class='alert alert-warning mt-2 mb-0 py-2'><i class='bi bi-qr-code'></i> Los QRs de acceso han sido invalidados. Los externos ya no podrán ingresar.</div>";
        $mensaje .= "</div>";

        // M2: Setear flash en sesión ANTES de devolver JSON (Option E)
        session()->flash('sca_modal', sca_modal_flash('success', 'Solicitud cancelada', $mensaje));
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'redirect' => route('solicitudes.hub')]);
        }

        return redirect()->route('solicitudes.hub')
            ->with('sca_modal', sca_modal_flash('success', 'Solicitud cancelada', $mensaje));
    }

    /**
     * Cancela un externo específico de una solicitud externa.
     * Ruta: POST /solicitudes/ext/{solicitud}/cancel-externo
     *
     * Body:
     *   - id_solicitud_externo_detalle: int
     *   - motivo_cancelacion: string (viene del modal JS)
     *
     * CAMBIO V7: guarda el motivo y envía correo al creador.
     */
    public function cancelExternoIndividual(SolicitudExt $solicitud, Request $request)
    {
        $user = $request->user();
        $request->validate([
            'id_solicitud_externo_detalle' => 'required|integer',
            'motivo_cancelacion' => 'required|string|max:500',
        ]);

        $detalleId = $request->id_solicitud_externo_detalle;
        $motivo = $request->motivo_cancelacion;

        $detalle = \App\Models\SolicitudExternoDetalle::where('id_solicitud_ext', $solicitud->id_solicitud_ext)
            ->where('id_solicitud_externo_detalle', $detalleId)
            ->first();

        if (!$detalle) {
            return back()->withErrors(['error' => 'Externo no encontrado en esta solicitud.']);
        }

        if ($detalle->estado_qr === 'cancelado') {
            return back()->withErrors(['error' => 'Este externo ya fue cancelado.']);
        }

        $detalle->estado_qr = 'cancelado';
        $detalle->codigo_qr = null;
        $detalle->save();

        // CAMBIO V7: Verificar si quedan autorizados
        $quedanAutorizados = \App\Models\SolicitudExternoDetalle::where('id_solicitud_ext', $solicitud->id_solicitud_ext)
            ->where('estado_qr', 'autorizado')
            ->exists();

        if (!$quedanAutorizados && in_array($solicitud->estado, ['aprobado', 'aprobado_parcial'])) {
            $solicitud->estado = 'rechazado';
            $solicitud->motivo_rechazo = 'Todos los externos fueron cancelados. Último motivo: ' . $motivo;
            $solicitud->save();
        } elseif ($solicitud->estado === 'aprobado') {
            $solicitud->estado = 'aprobado_parcial';
            $solicitud->motivo_rechazo = 'Externo cancelado: ' . $motivo;
            $solicitud->save();
        }

        // Enviar correo al creador
        try {
            $usuarioCreador = \App\Models\UsuarioSca::where('username', (string) $solicitud->creador_empleado_id)->first();
            if ($usuarioCreador && $usuarioCreador->email_emp) {
                $externo = $detalle->externo;
                $nombreExt = $externo?->nombre_completo ?? 'Externo';

                \Mail::to($usuarioCreador->email_emp)->send(new \App\Mail\NotificacionSolicitudMail(
                    $solicitud,
                    $usuarioCreador->nombre_completo,
                    'Permiso cancelado - ' . $nombreExt,
                    '❌ Permiso Cancelado',
                    "Se ha cancelado el permiso de {$nombreExt} en la solicitud #{$solicitud->id_solicitud_ext}.",
                    $motivo,
                    null, // aprobados
                    [$nombreExt], // rechazados
                    null, // personas
                    null
                ));
            }
        } catch (\Exception $e) {
            LogSistema::registrar('ERROR_NOTIF', 'Error al enviar correo de cancelación individual: ' . $e->getMessage());
        }

        LogSistema::registrar('CANCELAR_EXTERNO', "Externo {$detalleId} cancelado de solicitud EXT #{$solicitud->id_solicitud_ext} por {$user->username}");

        // M2 [ScaModal] — Mensaje detallado. Si la solicitud quedó rechazada (no quedan autorizados),
        // mostramos warning; si quedó aprobado_parcial, mostramos success con aviso.
        $externo = $detalle->externo;
        $nombreExt = $externo?->nombre_completo ?? "Externo #{$detalleId}";

        if ($solicitud->estado === 'rechazado') {
            // Caso total: no quedan más autorizados → solicitud cancelada
            $mensaje = "<div class='text-start'>";
            $mensaje .= "<div class='mb-2'><strong>Externo cancelado:</strong> {$nombreExt}</div>";
            $mensaje .= "<div class='alert alert-warning mt-2 mb-0 py-2'><i class='bi bi-exclamation-triangle-fill'></i> Como no quedan más personas autorizadas, <strong>la solicitud #EXT-{$solicitud->id_solicitud_ext} fue cancelada completamente</strong>.</div>";
            $mensaje .= "</div>";
            // M2: Setear flash en sesión ANTES de devolver JSON (Option E)
            session()->flash('sca_modal', sca_modal_flash('warning', 'Solicitud cancelada', $mensaje));
            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'redirect' => route('solicitudes.show.ext', $solicitud->id_solicitud_ext)]);
            }
            return back()->with('sca_modal', sca_modal_flash('warning', 'Solicitud cancelada', $mensaje));
        } else {
            // Caso parcial: quedan más autorizados
            $mensaje = "<div class='text-start'>";
            $mensaje .= "<div class='mb-2'><strong>Externo cancelado:</strong> {$nombreExt}</div>";
            $mensaje .= "<div class='mb-2'><strong>Solicitud:</strong> #EXT-{$solicitud->id_solicitud_ext}</div>";
            if ($solicitud->estado === 'aprobado_parcial') {
                $mensaje .= "<div class='alert alert-warning mt-2 mb-0 py-2'><i class='bi bi-info-circle-fill'></i> La solicitud pasó a estado <strong>Aprobada parcialmente</strong>.</div>";
            }
            $mensaje .= "</div>";
            // M2: Setear flash en sesión ANTES de devolver JSON (Option E)
            session()->flash('sca_modal', sca_modal_flash('success', 'Externo cancelado', $mensaje));
            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'redirect' => route('solicitudes.show.ext', $solicitud->id_solicitud_ext)]);
            }
            return back()->with('sca_modal', sca_modal_flash('success', 'Externo cancelado', $mensaje));
        }
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

        // CAMBIO V7: filtrar también por fecha_fin >= now() para que las
        // solicitudes vencidas no aparezcan en la bandeja (aunque su
        // estado en BD todavía diga 'pendiente*' porque nadie las consultó).
        // El accessor estado_actual las expirará cuando alguien las consulte,
        // pero no queremos que se muestren como "pendientes" en la bandeja.

        // FIX 7: Incluir 'aprobado_parcial' en las queries de origen y destino.
        // - Para ORIGEN: mostramos las aprobado_parcial donde origen aún no aprobó (fecha_aprobacion_origen IS NULL).
        // - Para DESTINO: mostramos las aprobado_parcial donde destino aún no aprobó (fecha_aprobacion_destino IS NULL).

        // Internos pendientes en origen (y no vencidos)
        $queryOrigen = SolicitudInt::with(['plantaOrigen', 'plantaDestino', 'creador'])
            ->whereIn('estado', ['pendiente_origen', 'aprobado_parcial'])
            ->whereNull('fecha_aprobacion_origen')
            ->where('fecha_fin', '>=', now());

        // Internos pendientes en destino (y no vencidos)
        $queryDestino = SolicitudInt::with(['plantaOrigen', 'plantaDestino', 'creador'])
            ->whereIn('estado', ['pendiente_destino', 'aprobado_parcial'])
            ->whereNull('fecha_aprobacion_destino')
            ->where('fecha_fin', '>=', now());

        // Externos pendientes (y no vencidos)
        $queryExt = SolicitudExt::with(['plantaDestino', 'creador', 'anfitrion', 'externos.externo'])
            ->where('estado', 'pendiente')
            ->where('fecha_fin', '>=', now());

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
            ->whereIn('estado', ['aprobado', 'rechazado', 'aprobado_parcial', 'expirado']);

        // Query para solicitudes externas resueltas
        $queryExt = SolicitudExt::with(['plantaDestino', 'creador', 'anfitrion', 'aprobadorDestino', 'externos.externo'])
            ->whereIn('estado', ['aprobado', 'rechazado', 'aprobado_parcial', 'expirado']);

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
            // Admin/Super: filtran por la planta_origen_id seleccionada en el form.
            // FIX V7: el mensaje viejo decía "Anfitrión filtrado por la planta
            // destino" lo cual era incorrecto (anfitrión es para externas, y la
            // planta es origen, no destino). Además, se mostraba SIEMPRE aunque
            // hubiera resultados. Ahora no seteamos motivoRestriccion para
            // Admin/Super: ellos pueden buscar en cualquier planta, no hay
            // restricción que informar. El JS ya muestra un mensaje simple
            // cuando no hay resultados.
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
            // No seteamos $motivoRestriccion: no hay restricción para Admin/Super.
        }

        // FIX: Filtrar por planta directamente en la query de la BD (más eficiente)
        // y sacar el limit(50) y take(10) para que muestre TODOS los resultados.
        if ($plantaFiltrada !== null) {
            // Obtener los IDs de empleados que pertenecen a esta planta
            $idsPlanta = \App\Models\EmpleadoExtSca::where('id_planta', $plantaFiltrada)
                ->where('planta_forzada', false)
                ->pluck('empleado_id')
                ->toArray();
            
            // Si no hay empleados en esa planta, devolver vacío
            if (empty($idsPlanta)) {
                return response()->json([
                    'empleados'          => [],
                    'restringido'        => true,
                    'motivo_restriccion' => $motivoRestriccion,
                    'total'              => 0,
                ]);
            }
            
            $query->whereIn('id_empleado', $idsPlanta);
        }

        // Cargar candidatos (sin límite, ya filtrados por planta en la BD)
        $candidatos = $query->with(['puesto', 'departamento'])
            ->limit(100) // Límite de seguridad razonable
            ->get();

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
     * API: Buscar externo por tipo + número de identificación.
     * Ruta: GET /solicitudes/buscar-externo-identificacion?tipo_identificacion=...&identificacion=...
     *
     * CAMBIO V7: unifica las viejas buscarExternoPorCurp y buscarExternoPorPasaporte.
     *
     * Precarga los datos del externo si ya existe en el sistema, para que el
     * formulario no pida volver a capturar nombre/teléfono.
     */
    public function buscarExternoPorIdentificacion(Request $request)
    {
        $request->validate([
            'tipo_identificacion' => ['required', 'in:curp,credencial_votar,cedula_profesional,pasaporte'],
            'identificacion'      => ['required', 'string', 'max:50'],
        ]);

        $tipo  = strtolower(trim($request->tipo_identificacion));
        $valor = trim($request->identificacion);
        if ($tipo === 'curp') {
            $valor = strtoupper($valor);
        }

        $externo = Externo::where('tipo_identificacion', $tipo)
            ->where('identificacion', $valor)
            ->first();

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

    public function descargarCredencialPng($idDetalle)
    {
        $detalle = \App\Models\SolicitudExternoDetalle::with(['solicitudExt', 'externo'])
            ->findOrFail($idDetalle);

        if ($detalle->estado_qr !== 'autorizado' || !$detalle->codigo_qr) {
            return back()->withErrors(['error' => 'Credencial no autorizada o expirada.']);
        }

        $pngData = \App\Services\CredencialService::generarPngIndividual($detalle->solicitudExt, $detalle);
        $nombre = 'credencial_' . \Str::slug($detalle->externo?->nombre_completo ?? 'externo') . '.png';

        return response($pngData, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="' . $nombre . '"',
        ]);
    }

    /**
     * Reenvía las credenciales por correo.
     */
    public function reenviarCredenciales(SolicitudExt $solicitud, Request $request)
    {
        $user = $request->user();
        
        $puedeReenviar = ($solicitud->creador_empleado_id === (int) $user->username)
            || $user->isAprobador() || $user->isAdmin() || $user->isSuperadmin();

        if (!$puedeReenviar) {
            return back()->withErrors(['error' => 'No tiene permisos para reenviar estas credenciales.']);
        }

        if (!in_array($solicitud->estado, ['aprobado', 'aprobado_parcial'])) {
            return back()->withErrors(['error' => 'La solicitud no está aprobada.']);
        }

        try {
            $usuarioCreador = \App\Models\UsuarioSca::where('username', (string) $solicitud->creador_empleado_id)->first();
            if (!$usuarioCreador || !$usuarioCreador->email_emp) {
                return back()->withErrors(['error' => 'El creador no tiene correo institucional registrado.']);
            }

            // Contar autorizados
            $autorizados = $solicitud->externos->filter(function($d) {
                return $d->estado_qr === 'autorizado' && $d->codigo_qr;
            })->count();

            if ($autorizados === 0) {
                return back()->withErrors(['error' => 'No hay credenciales autorizadas para enviar.']);
            }

            // Enviar 1 solo correo con todo
            \Mail::to($usuarioCreador->email_emp)
                ->send(new \App\Mail\CredencialExternoMail(
                    $solicitud,
                    $usuarioCreador->nombre_completo
                ));

            \App\Models\LogSistema::registrar('REENVIAR_CREDENCIALES', "Reenvío de {$autorizados} credencial(es) de la solicitud EXT #{$solicitud->id_solicitud_ext} por {$user->username}");

            // M2 [ScaModal] — Mensaje detallado de reenvío.
            $mensaje = "<div class='text-start'>";
            $mensaje .= "<div class='mb-2'><strong>Credenciales reenviadas:</strong> {$autorizados}</div>";
            $mensaje .= "<div class='mb-2'><strong>Correo destino:</strong> {$usuarioCreador->email_emp}</div>";
            $mensaje .= "<div class='alert alert-info mt-2 mb-0 py-2'><i class='bi bi-envelope-fill'></i> Se enviaron {$autorizados} credencial(es) con los QRs de acceso al creador de la solicitud.</div>";
            $mensaje .= "</div>";

            // M2: Setear flash en sesión ANTES de devolver JSON (Option E)
            session()->flash('sca_modal', sca_modal_flash('success', 'Credenciales reenviadas', $mensaje));
            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'redirect' => route('solicitudes.show.ext', $solicitud->id_solicitud_ext)]);
            }
            return back()->with('sca_modal', sca_modal_flash('success', 'Credenciales reenviadas', $mensaje));

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al enviar: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Genera y descarga un PDF con TODAS las credenciales de la solicitud.
     * Ruta: GET /solicitudes/ext/{solicitud}/descargar-credenciales
     */
 public function descargarCredenciales(SolicitudExt $solicitud)
    {
        $pdfData = \App\Services\CredencialService::generarPdfTodas($solicitud);
        return response($pdfData, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="credenciales_' . $solicitud->id_solicitud_ext . '.pdf"',
        ]);
    }
}