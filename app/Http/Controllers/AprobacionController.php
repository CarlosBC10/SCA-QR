<?php
// app/Http/Controllers/AprobacionController.php
//
// CONTROLADOR DE APROBACIONES (M5).
//
// RESPONSABILIDADES:
//   - aprobarInt(): aprobar solicitud interna individual (pase_salida o interplantas).
//   - rechazarInt(): rechazar solicitud interna (toda).
//   - aprobarGrupalInt(): aprobar/rechazar interplantas o pase_salida grupal
//     empleado por empleado, con aprobado_parcial.
//   - aprobarExt(): aprobar solicitud externa, externo por externo, con aprobado_parcial.
//   - rechazarExt(): rechazar solicitud externa (toda).
//
// LÓGICA DE APROBACIÓN PARCIAL:
//   - Internas grupales (pase_salida e interplantas):
//     · El aprobador marca cada empleado como aprobado|rechazado.
//     · Si todos aprobados → estado = aprobado (o pendiente_destino en interplantas).
//     · Si alguno rechazado → estado = aprobado_parcial.
//     · Los rechazados NO pueden entrar/salir (el scanner los deniega).
//   - Externas (visita/contratista):
//     · El aprobador marca cada externo como autorizado|cancelado (via estado_qr).
//     · Si todos autorizados → estado = aprobado, se generan todos los QRs.
//     · Si alguno cancelado → estado = aprobado_parcial, se generan QRs solo
//       para los autorizados.
//     · Los cancelados no tienen QR (o se les cancela), no pueden entrar.
//
// LÓGICA DE AUTO-APROBACIÓN (creador es Aprobador+):
//   - Internas: todos los empleados se marcan aprobado, estado = aprobado.
//   - Externas: todos los externos se marcan autorizado, se generan todos los QRs,
//     estado = aprobado.
//   - No hay aprobado_parcial en auto-aprobación (es todo o nada, automático).
//
// SCOPING POR PLANTA:
//   - Aprobador: solo ve y aprueba solicitudes de su planta
//     (origen o destino, según el estado actual).
//   - Admin/Super: ven y aprueban todo, pueden saltar pasos (marcan ambos).
//
// LÓGICA DE DOBLE FLUJO (interplantas):
//   1. pendiente_origen → aprobador de planta origen aprueba.
//   2. Pasa a pendiente_destino (o aprobado_parcial si hubo rechazos).
//   3. aprobador de planta destino aprueba.
//   4. Pasa a aprobado (o aprobado_parcial si hubo rechazos).
//   - Admin/Super pueden saltar pasos.

namespace App\Http\Controllers;

use App\Models\SolicitudInt;
use App\Models\SolicitudExt;
use App\Models\SolicitudEmpleado;
use App\Models\SolicitudExternoDetalle;
use App\Models\LogSistema;
use App\Services\NotificacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AprobacionController extends Controller
{
    // ============================================================
    // 1. APROBAR SOLICITUD INTERNA (individual)
    // ============================================================

    /**
     * Aprueba una solicitud interna individual (pase_salida o interplantas).
     * Ruta: POST /solicitudes/int/{solicitud}/aprobar
     *
     * Lógica:
     *   - pendiente_origen:
     *     · Marca aprobador_origen + fecha.
     *     · pase_salida → aprobado (solo necesita origen).
     *     · interplantas + Admin/Super → aprueba también destino, aprobado.
     *     · interplantas + Aprobador → pendiente_destino.
     *   - pendiente_destino:
     *     · Marca aprobador_destino + fecha.
     *     · aprobado.
     *     · Si Admin/Super y origen no aprobado → también aprueba origen.
     *
     * CAMBIO V7 (Notificaciones): envía correo al creador según el resultado.
     */
    public function aprobarInt(SolicitudInt $solicitud, Request $request)
    {
        $user = $request->user();
        $esSuperior = $user->isAdmin() || $user->isSuperadmin();

        $esAprobadorOrigen = $this->puedeAprobarOrigenInt($solicitud, $user, $esSuperior);
        $esAprobadorDestino = $this->puedeAprobarDestinoInt($solicitud, $user, $esSuperior);

        if (!$esAprobadorOrigen && !$esAprobadorDestino) {
            return back()->withErrors(['error' => 'No tiene permisos para aprobar esta solicitud o ya fue procesada.']);
        }

        if ($solicitud->esGrupal()) {
            return back()->withErrors(['error' => 'Esta solicitud es grupal. Use la revisión grupal.']);
        }

        DB::beginTransaction();
        try {
            $userId = (int) $user->username;
            $ahora = now();
            $pasoAprobado = null;

            // ============================================================
            // LÓGICA DE APROBACIÓN (sin cambios, ya funcionaba)
            // ============================================================
            if ($esAprobadorOrigen) {
                $solicitud->aprobador_origen_empleado_id = $userId;
                $solicitud->fecha_aprobacion_origen = $ahora;
                $pasoAprobado = 'origen';

                if ($solicitud->tipo === 'pase_salida') {
                    // Pase de salida: solo necesita origen
                    $solicitud->estado = 'aprobado';
                } elseif ($esSuperior) {
                    // Admin/Super aprueba también destino en interplantas
                    $solicitud->aprobador_destino_empleado_id = $userId;
                    $solicitud->fecha_aprobacion_destino = $ahora;
                    $solicitud->estado = 'aprobado';
                    $pasoAprobado = 'completo';
                } else {
                    // Aprobador normal: pasa a destino
                    $solicitud->estado = 'pendiente_destino';
                }
            } elseif ($esAprobadorDestino) {
                $solicitud->aprobador_destino_empleado_id = $userId;
                $solicitud->fecha_aprobacion_destino = $ahora;
                $solicitud->estado = 'aprobado';
                $pasoAprobado = 'destino';

                if ($esSuperior && !$solicitud->fecha_aprobacion_origen) {
                    $solicitud->aprobador_origen_empleado_id = $userId;
                    $solicitud->fecha_aprobacion_origen = $ahora;
                    $pasoAprobado = 'completo';
                }
            }

            $solicitud->save();
            DB::commit();

            // ============================================================
            // CAMBIO V7: Enviar correo al creador
            // ============================================================
            // Para individual, no hay rechazados
            $this->enviarCorreoAprobacionInt($solicitud, $pasoAprobado, null, 0, [], []);

            LogSistema::registrar('APROBAR_SOLICITUD', "Solicitud INT #{$solicitud->id_solicitud_int} aprobada (paso: {$pasoAprobado}) por {$user->username}");

            // Notificaciones in-app (defensivo)
            try {
                if ($pasoAprobado === 'completo') {
                    NotificacionService::solicitudAprobadaTotalmente($solicitud, $user);
                } else {
                    NotificacionService::solicitudAprobadaParcialmente($solicitud, $user, $pasoAprobado);
                }
            } catch (\Exception $e) {
                LogSistema::registrar('ERROR_NOTIF', 'Error al notificar aprobación INT: ' . $e->getMessage());
            }

            // M2 [ScaModal] — Construir mensaje detallado según el paso aprobado.
            $tipoLabel = $solicitud->tipo === 'pase_salida' ? 'Pase de salida' : 'Interplantas';
            $estadoLabel = $solicitud->estado === 'aprobado' ? 'Aprobada' : 'Pendiente de destino';
            $pasoLabel = match($pasoAprobado) {
                'origen'   => 'Origen',
                'destino'  => 'Destino',
                'completo' => 'Completo (origen + destino)',
                default    => ucfirst($pasoAprobado ?? ''),
            };

            $mensaje = "<div class='text-start'>";
            $mensaje .= "<div class='mb-2'><strong>Solicitud:</strong> #INT-{$solicitud->id_solicitud_int}</div>";
            $mensaje .= "<div class='mb-2'><strong>Tipo:</strong> {$tipoLabel}</div>";
            $mensaje .= "<div class='mb-2'><strong>Paso aprobado:</strong> {$pasoLabel}</div>";
            $mensaje .= "<div class='mb-2'><strong>Estado:</strong> ";
            if ($solicitud->estado === 'aprobado') {
                $mensaje .= "<span class='badge bg-success'>{$estadoLabel}</span>";
            } else {
                $mensaje .= "<span class='badge bg-warning text-dark'>{$estadoLabel}</span>";
            }
            $mensaje .= "</div>";

            // Si quedó pendiente de destino, agregar aviso
            if ($solicitud->estado === 'pendiente_destino') {
                $mensaje .= "<div class='alert alert-info mt-2 mb-0 py-2'><i class='bi bi-info-circle-fill'></i> La solicitud fue aprobada en origen. Queda pendiente la aprobación de destino.</div>";
            }
            $mensaje .= "</div>";

            // Option E: Setear flash en sesión.
            session()->flash('sca_modal', sca_modal_flash('success', 'Solicitud aprobada', $mensaje));

            if ($request->expectsJson()) {
                return response()->json([
                    'success'  => true,
                    'redirect' => route('solicitudes.bandeja'),
                ]);
            }

            return redirect()->route('solicitudes.bandeja')->with(
                'sca_modal',
                sca_modal_flash('success', 'Solicitud aprobada', $mensaje)
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Error al aprobar: ' . $e->getMessage()]);
        }
    }

    // ============================================================
    // 2. RECHAZAR SOLICITUD INTERNA (toda)
    // ============================================================

    /**
     * Rechaza una solicitud interna (individual o grupal, toda la solicitud).
     * Ruta: POST /solicitudes/int/{solicitud}/rechazar
     *
     * CAMBIO V7: envía correo al creador con el motivo del rechazo.
     */
    public function rechazarInt(SolicitudInt $solicitud, Request $request)
    {
        $user = $request->user();
        $esSuperior = $user->isAdmin() || $user->isSuperadmin();

        $esAprobadorOrigen = $this->puedeAprobarOrigenInt($solicitud, $user, $esSuperior);
        $esAprobadorDestino = $this->puedeAprobarDestinoInt($solicitud, $user, $esSuperior);

        if (!$esAprobadorOrigen && !$esAprobadorDestino) {
            return back()->withErrors(['error' => 'No tiene permisos para rechazar esta solicitud.']);
        }

        $request->validate([
            'motivo_rechazo' => ['required', 'string', 'max:500'],
        ]);

        $solicitud->estado = 'rechazado';
        $solicitud->motivo_rechazo = $request->motivo_rechazo;
        $solicitud->save();

        // Si era grupal, marcar todos los empleados como rechazados
        if ($solicitud->esGrupal()) {
            SolicitudEmpleado::where('id_solicitud_int', $solicitud->id_solicitud_int)
                ->update(['estado' => 'rechazado']);
        }

        // ============================================================
        // CAMBIO V7: Enviar correo al creador
        // ============================================================
        $this->enviarCorreoRechazo($solicitud, $request->motivo_rechazo);

        LogSistema::registrar('RECHAZAR_SOLICITUD', "Solicitud INT #{$solicitud->id_solicitud_int} rechazada por {$user->username}");

        try {
            NotificacionService::solicitudRechazada($solicitud, $user, "Motivo: {$request->motivo_rechazo}");
        } catch (\Exception $e) {
            LogSistema::registrar('ERROR_NOTIF', 'Error al notificar rechazo INT: ' . $e->getMessage());
        }

        // M2 [ScaModal] — Construir mensaje detallado.
        $mensaje = "<div class='text-start'>";
        $mensaje .= "<div class='mb-2'><strong>Solicitud:</strong> #INT-{$solicitud->id_solicitud_int}</div>";
        $mensaje .= "<div class='mb-2'><strong>Estado:</strong> <span class='badge bg-danger'>Rechazada</span></div>";
        $mensaje .= "<div class='mb-2'><strong>Motivo:</strong></div>";
        $mensaje .= "<div class='p-2 bg-light rounded'><em>" . nl2br(e($request->motivo_rechazo)) . "</em></div>";
        $mensaje .= "</div>";

        // Option E: Setear flash en sesión.
        session()->flash('sca_modal', sca_modal_flash('success', 'Solicitud rechazada', $mensaje));

        if ($request->expectsJson()) {
            return response()->json([
                'success'  => true,
                'redirect' => route('solicitudes.bandeja'),
            ]);
        }

        return redirect()->route('solicitudes.bandeja')->with(
            'sca_modal',
            sca_modal_flash('success', 'Solicitud rechazada', $mensaje)
        );
    }

    // ============================================================
    // 3. APROBAR SOLICITUD INTERNA GRUPAL (con aprobado_parcial)
    // ============================================================

    /**
     * Aprueba/rechaza una solicitud grupal, empleado por empleado.
     * Ruta: POST /solicitudes/int/{solicitud}/aprobar-grupal
     *
     * Body:
     *   - estados: array [empleado_id => 'aprobado'|'rechazado', ...]
     *   - motivo_rechazo_general: string (si hay rechazos, viene del modal JS)
     *
     * CAMBIO V7: si hay rechazos, se guarda el motivo general y se envía correo.
     */
    public function aprobarGrupalInt(SolicitudInt $solicitud, Request $request)
    {
        $user = $request->user();
        $esSuperior = $user->isAdmin() || $user->isSuperadmin();

        $esAprobadorOrigen = $this->puedeAprobarOrigenInt($solicitud, $user, $esSuperior);
        $esAprobadorDestino = $this->puedeAprobarDestinoInt($solicitud, $user, $esSuperior);

        if (!$esAprobadorOrigen && !$esAprobadorDestino) {
            return back()->withErrors(['error' => 'No tiene permisos para revisar esta solicitud.']);
        }

        if (!$solicitud->esGrupal()) {
            return back()->withErrors(['error' => 'Esta función solo aplica para solicitudes grupales.']);
        }

        // CAMBIO V7: recibir el motivo general del modal JS
        $estados = $request->input('estados', []);
        $motivoRechazo = $request->input('motivo_rechazo_general');
        $userId = (int) $user->username;
        $ahora = now();

        DB::beginTransaction();
        try {
            // 1. Actualizar el estado de cada empleado
            $aproboTodos = true;
            $empleadosRechazados = [];
            foreach ($estados as $empId => $estadoEmp) {
                
                // FIX 3: Si el aprobador es de DESTINO y marca "Aprobar",
                // el empleado debe pasar a 'aprobado' (no 'aprobado_origen').
                // Si el aprobador es de ORIGEN en interplantas, queda 'aprobado_origen'.
                if ($estadoEmp === 'rechazado') {
                    $estadoFinal = 'rechazado';
                    $aproboTodos = false;
                    $empleadosRechazados[] = $empId;
                } else {
                    // Marcó "Aprobar"
                    if ($esAprobadorDestino) {
                        $estadoFinal = 'aprobado';
                    } elseif ($solicitud->tipo === 'interplantas' && $esAprobadorOrigen && !$esSuperior) {
                        $estadoFinal = 'aprobado_origen';
                    } else {
                        $estadoFinal = 'aprobado';
                    }
                }

                SolicitudEmpleado::where('id_solicitud_int', $solicitud->id_solicitud_int)
                    ->where('empleado_id', $empId)
                    ->update(['estado' => $estadoFinal]);
            }

            // FIX: Revisar si ya había rechazados de un paso anterior (origen).
            // Si los hay, la solicitud NO puede pasar a 'aprobado', debe quedar 'aprobado_parcial'.
            $yaHabiaRechazados = SolicitudEmpleado::where('id_solicitud_int', $solicitud->id_solicitud_int)
                ->where('estado', 'rechazado')
                ->exists();
            
            if ($yaHabiaRechazados) {
                $aproboTodos = false;
            }

            $pasoAprobado = null;

            // 2. Decidir el estado de la solicitud
            if ($aproboTodos) {
                // Todos aprobados
                if ($esAprobadorOrigen) {
                    $solicitud->aprobador_origen_empleado_id = $userId;
                    $solicitud->fecha_aprobacion_origen = $ahora;

                    if ($solicitud->tipo === 'pase_salida') {
                        $solicitud->estado = 'aprobado';
                        $pasoAprobado = 'completo';
                    } elseif ($esSuperior) {
                        $solicitud->aprobador_destino_empleado_id = $userId;
                        $solicitud->fecha_aprobacion_destino = $ahora;
                        $solicitud->estado = 'aprobado';
                        $pasoAprobado = 'completo';
                    } else {
                        $solicitud->estado = 'pendiente_destino';
                        $pasoAprobado = 'origen';
                    }
                } elseif ($esAprobadorDestino) {
                    $solicitud->aprobador_destino_empleado_id = $userId;
                    $solicitud->fecha_aprobacion_destino = $ahora;
                    $solicitud->estado = 'aprobado';
                    $pasoAprobado = 'destino';

                    if ($esSuperior && !$solicitud->fecha_aprobacion_origen) {
                        $solicitud->aprobador_origen_empleado_id = $userId;
                        $solicitud->fecha_aprobacion_origen = $ahora;
                        $pasoAprobado = 'completo';
                    }
                }
            } else {
                // Hay rechazados (en este formulario o en pasos previos) → aprobado_parcial
                // CAMBIO V7: guardar el motivo general que vino del modal
                // FIX 3-e: si el motivo viene null pero hay rechazados previos,
                // conservar el motivo_rechazo existente para no perder el historial.
                // (Ej: destino aprueba los restantes sin rechazar; el motivo que dejó
                //  origen no debe sobrescribirse con "0 empleado(s) rechazado(s)...")
                if (!$motivoRechazo && $solicitud->motivo_rechazo) {
                    // No escribió motivo nuevo, pero ya había uno → conservarlo
                    $motivoRechazo = $solicitud->motivo_rechazo;
                } elseif (!$motivoRechazo) {
                    // No escribió motivo y no había previo → mensaje default
                    $motivoRechazo = count($empleadosRechazados) . ' empleado(s) rechazado(s) en revisión grupal';
                }
                $solicitud->estado = 'aprobado_parcial';
                $solicitud->motivo_rechazo = $motivoRechazo;

                if ($esAprobadorOrigen) {
                    $solicitud->aprobador_origen_empleado_id = $userId;
                    $solicitud->fecha_aprobacion_origen = $ahora;

                    if ($esSuperior && $solicitud->tipo === 'interplantas') {
                        $solicitud->aprobador_destino_empleado_id = $userId;
                        $solicitud->fecha_aprobacion_destino = $ahora;
                    }
                    $pasoAprobado = 'origen_parcial';
                } elseif ($esAprobadorDestino) {
                    $solicitud->aprobador_destino_empleado_id = $userId;
                    $solicitud->fecha_aprobacion_destino = $ahora;
                    $pasoAprobado = 'destino_parcial';

                    if ($esSuperior && !$solicitud->fecha_aprobacion_origen) {
                        $solicitud->aprobador_origen_empleado_id = $userId;
                        $solicitud->fecha_aprobacion_origen = $ahora;
                    }
                }
            }

            $solicitud->save();
            DB::commit();

            // ============================================================
            // CAMBIO V7: Enviar correo al creador con listas de aprobados/rechazados
            // ============================================================
            // Construir listas de nombres
            $nombresAprobados = [];
            $nombresRechazados = [];
            foreach ($estados as $empId => $estadoEmp) {
                $emp = \App\Models\Sitec\Empleado::find($empId);
                $nombre = $emp?->nombre_completo ?? 'Empleado #' . $empId;
                if ($estadoEmp === 'aprobado') {
                    $nombresAprobados[] = $nombre;
                } else {
                    $nombresRechazados[] = $nombre;
                }
            }

            $this->enviarCorreoAprobacionInt(
                $solicitud,
                $pasoAprobado,
                $motivoRechazo,
                count($empleadosRechazados),
                $nombresAprobados,
                $nombresRechazados
            );

            LogSistema::registrar(
                'APROBAR_SOLICITUD_GRUPAL',
                "Solicitud INT #{$solicitud->id_solicitud_int} revisada grupalmente por {$user->username}. Estado: {$solicitud->estado}"
            );

            // Notificaciones in-app (defensivo)
            try {
                if ($solicitud->estado === 'aprobado_parcial') {
                    NotificacionService::solicitudAprobadaParcialmente($solicitud, $user, $pasoAprobado ?? 'parcial');
                } elseif ($pasoAprobado === 'completo') {
                    NotificacionService::solicitudAprobadaTotalmente($solicitud, $user);
                } else {
                    NotificacionService::solicitudAprobadaParcialmente($solicitud, $user, $pasoAprobado);
                }
            } catch (\Exception $e) {
                LogSistema::registrar('ERROR_NOTIF', 'Error al notificar revisión grupal: ' . $e->getMessage());
            }

            // M2 [ScaModal] — Construir mensaje detallado con listas de aprobados/rechazados.
            // FIX: 'aprobado_origen' también cuenta como aprobado para el mensaje
            $empleadosAprobados = [];
            $empleadosRechazados = [];
            foreach ($solicitud->empleados as $emp) {
                $nombre = $emp->empleado?->nombre_completo ?? "Empleado {$emp->empleado_id}";
                if (in_array($emp->estado, ['aprobado', 'aprobado_origen'])) {
                    $empleadosAprobados[] = $nombre;
                } else {
                    $empleadosRechazados[] = $nombre;
                }
            }

            $totalAprobados = count($empleadosAprobados);
            $totalRechazados = count($empleadosRechazados);

            $mensaje = "<div class='text-start'>";
            $mensaje .= "<div class='mb-2'><strong>Solicitud:</strong> #INT-{$solicitud->id_solicitud_int}</div>";
            $mensaje .= "<div class='mb-2'><strong>Estado:</strong> ";
            if ($solicitud->estado === 'aprobado') {
                $mensaje .= "<span class='badge bg-success'>Aprobada</span>";
            } else {
                $mensaje .= "<span class='badge bg-warning text-dark'>Aprobada parcialmente</span>";
            }
            $mensaje .= "</div>";

            // Bloques de detalles con listas
            $detalles = [];
            if ($totalAprobados > 0) {
                $detalles[] = [
                    'titulo' => "Aprobados ({$totalAprobados})",
                    'tipo'   => 'success',
                    'items'  => $empleadosAprobados,
                ];
            }
            if ($totalRechazados > 0) {
                $detalles[] = [
                    'titulo' => "Rechazados ({$totalRechazados})",
                    'tipo'   => 'error',
                    'items'  => $empleadosRechazados,
                ];
            }

            // Option E: Setear flash en sesión (con detalles si los hay).
            session()->flash('sca_modal', sca_modal_flash('success', 'Solicitud revisada', $mensaje, $detalles ?: null));

            if ($request->expectsJson()) {
                return response()->json([
                    'success'  => true,
                    'redirect' => route('solicitudes.bandeja'),
                ]);
            }

            return redirect()->route('solicitudes.bandeja')->with(
                'sca_modal',
                sca_modal_flash('success', 'Solicitud revisada', $mensaje, $detalles ?: null)
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Error: ' . $e->getMessage()]);
        }
    }

    // ============================================================
    // 4. APROBAR SOLICITUD EXTERNA (con aprobado_parcial)
    // ============================================================

    /**
     * Aprueba una solicitud externa (visita o contratista).
     * Ruta: POST /solicitudes/ext/{solicitud}/aprobar
     *
     * Body:
     *   - estados: array [id_solicitud_externo_detalle => 'autorizado'|'cancelado', ...]
     *   - motivo_rechazo_general: string (si hay cancelados, viene del modal JS)
     *
     * CAMBIO V7: envía correo con PDF + PNGs al creador. Guarda motivo de rechazo.
     */
    public function aprobarExt(SolicitudExt $solicitud, Request $request)
    {
        $user = $request->user();
        $esSuperior = $user->isAdmin() || $user->isSuperadmin();

        if (!$this->puedeAprobarExt($solicitud, $user, $esSuperior)) {
            return back()->withErrors(['error' => 'No tiene permisos para aprobar esta solicitud o ya fue procesada.']);
        }

        DB::beginTransaction();
        try {
            $userId = (int) $user->username;
            $ahora = now();
            $estados = $request->input('estados', []);
            // CAMBIO V7: motivo general del modal JS
            $motivoRechazo = $request->input('motivo_rechazo_general');

            // Marcar aprobador de la solicitud
            $solicitud->aprobador_destino_empleado_id = $userId;
            $solicitud->fecha_aprobacion_destino = $ahora;

            // Procesar cada externo
            $aproboTodos = true;
            $externosCancelados = 0;
            $prefijoQr = config('sca_qr.prefijo_qr_externo');

            foreach ($solicitud->externos as $detalle) {
                $decision = $estados[$detalle->id_solicitud_externo_detalle] ?? 'autorizado';

                if ($decision === 'autorizado') {
                    // Generar QR si no lo tiene
                    if (!$detalle->codigo_qr) {
                        $detalle->codigo_qr = $prefijoQr . Str::uuid()->toString();
                    }
                    $detalle->estado_qr = 'autorizado';
                } else {
                    // Cancelar: sin QR
                    $detalle->codigo_qr = null;
                    $detalle->estado_qr = 'cancelado';
                    $aproboTodos = false;
                    $externosCancelados++;
                }
                $detalle->save();
            }

            // Decidir estado de la solicitud
            if ($aproboTodos) {
                $solicitud->estado = 'aprobado';
            } else {
                $solicitud->estado = 'aprobado_parcial';
                // CAMBIO V7: usar el motivo del modal, o un default
                $solicitud->motivo_rechazo = $motivoRechazo ?? $externosCancelados . ' externo(s) cancelado(s) en aprobación';
            }

            $solicitud->save();
            DB::commit();

            // ============================================================
            // CAMBIO V7: Enviar 1 solo correo (Notificación + Credenciales)
            // ============================================================
            $credencialesEnviadas = 0;
            try {
                $usuarioCreador = \App\Models\UsuarioSca::where('username', (string) $solicitud->creador_empleado_id)->first();
                if ($usuarioCreador && $usuarioCreador->email_emp) {
                    // Construir listas de nombres
                    $nombresAprobados = [];
                    $nombresRechazados = [];
                    foreach ($solicitud->externos as $detalle) {
                        $nombre = $detalle->externo?->nombre_completo ?? 'Externo';
                        if ($detalle->estado_qr === 'autorizado') {
                            $nombresAprobados[] = $nombre;
                        } else {
                            $nombresRechazados[] = $nombre;
                        }
                    }

                    $autorizados = count($nombresAprobados);

                    if ($autorizados > 0 || $solicitud->estado === 'aprobado_parcial') {
                        \Mail::to($usuarioCreador->email_emp)
                            ->send(new \App\Mail\CredencialExternoMail(
                                $solicitud,
                                $usuarioCreador->nombre_completo,
                                $motivoRechazo,     // motivo
                                $nombresAprobados,  // aprobados
                                $nombresRechazados  // rechazados
                            ));
                        $credencialesEnviadas = $autorizados;
                    }
                }
            } catch (\Exception $e) {
                LogSistema::registrar('ERROR_NOTIF', 'Error al enviar correo de aprobación EXT: ' . $e->getMessage());
            }

            LogSistema::registrar(
                'APROBAR_SOLICITUD',
                "Solicitud EXT #{$solicitud->id_solicitud_ext} aprobada por {$user->username}. Estado: {$solicitud->estado}"
            );

            // Notificaciones in-app (defensivo)
            try {
                if ($solicitud->estado === 'aprobado_parcial') {
                    NotificacionService::solicitudAprobadaParcialmente($solicitud, $user, 'destino_parcial');
                } else {
                    NotificacionService::solicitudAprobadaTotalmente($solicitud, $user);
                }
            } catch (\Exception $e) {
                LogSistema::registrar('ERROR_NOTIF', 'Error al notificar aprobación EXT: ' . $e->getMessage());
            }

            // M2 [ScaModal] — Construir mensaje detallado con listas de autorizados/cancelados.
            $externosAutorizados = [];
            $externosCanceladosLista = [];
            foreach ($solicitud->externos as $detalle) {
                $nombre = $detalle->externo?->nombre_completo ?? $detalle->nombre ?? "Externo {$detalle->id_solicitud_externo_detalle}";
                if ($detalle->estado_qr === 'autorizado') {
                    $externosAutorizados[] = $nombre;
                } else {
                    $externosCanceladosLista[] = $nombre;
                }
            }

            $totalAutorizados = count($externosAutorizados);
            $totalCancelados = count($externosCanceladosLista);

            $mensaje = "<div class='text-start'>";
            $mensaje .= "<div class='mb-2'><strong>Solicitud:</strong> #EXT-{$solicitud->id_solicitud_ext}</div>";
            $mensaje .= "<div class='mb-2'><strong>Estado:</strong> ";
            if ($solicitud->estado === 'aprobado') {
                $mensaje .= "<span class='badge bg-success'>Aprobada</span>";
                $mensaje .= "</div><div class='alert alert-success mt-2 mb-0 py-2'><i class='bi bi-qr-code'></i> Se generaron {$totalAutorizados} QR(s) de acceso.</div>";
            } else {
                $mensaje .= "<span class='badge bg-warning text-dark'>Aprobada parcialmente</span>";
                $mensaje .= "</div><div class='alert alert-warning mt-2 mb-0 py-2'><i class='bi bi-exclamation-triangle-fill'></i> {$totalCancelados} externo(s) sin acceso.</div>";
            }
            // Aviso de credenciales enviadas por correo (si las hubo)
            if ($credencialesEnviadas > 0) {
                $mensaje .= "<div class='alert alert-info mt-2 mb-0 py-2'><i class='bi bi-envelope-fill'></i> Se enviaron {$credencialesEnviadas} credencial(es) por correo al creador.</div>";
            }
            $mensaje .= "</div>";

            // Bloques de detalles con listas
            $detalles = [];
            if ($totalAutorizados > 0) {
                $detalles[] = [
                    'titulo' => "Autorizados ({$totalAutorizados})",
                    'tipo'   => 'success',
                    'items'  => $externosAutorizados,
                ];
            }
            if ($totalCancelados > 0) {
                $detalles[] = [
                    'titulo' => "Cancelados ({$totalCancelados})",
                    'tipo'   => 'error',
                    'items'  => $externosCanceladosLista,
                ];
            }

            // Option E: Setear flash en sesión.
            session()->flash('sca_modal', sca_modal_flash('success', 'Solicitud aprobada', $mensaje, $detalles ?: null));

            if ($request->expectsJson()) {
                return response()->json([
                    'success'  => true,
                    'redirect' => route('solicitudes.bandeja'),
                ]);
            }

            return redirect()->route('solicitudes.bandeja')->with(
                'sca_modal',
                sca_modal_flash('success', 'Solicitud aprobada', $mensaje, $detalles ?: null)
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Error al aprobar: ' . $e->getMessage()]);
        }
    }

    // ============================================================
    // 5. RECHAZAR SOLICITUD EXTERNA (toda)
    // ============================================================

    /**
     * Rechaza una solicitud externa (toda).
     * Ruta: POST /solicitudes/ext/{solicitud}/rechazar
     *
     * CAMBIO V7: envía correo al creador con el motivo del rechazo.
     */
    public function rechazarExt(SolicitudExt $solicitud, Request $request)
    {
        $user = $request->user();
        $esSuperior = $user->isAdmin() || $user->isSuperadmin();

        if (!$this->puedeAprobarExt($solicitud, $user, $esSuperior)) {
            return back()->withErrors(['error' => 'No tiene permisos para rechazar esta solicitud.']);
        }

        $request->validate([
            'motivo_rechazo' => ['required', 'string', 'max:500'],
        ]);

        $solicitud->estado = 'rechazado';
        $solicitud->motivo_rechazo = $request->motivo_rechazo;
        $solicitud->save();

        // Cancelar todos los QRs
        \App\Models\SolicitudExternoDetalle::where('id_solicitud_ext', $solicitud->id_solicitud_ext)
            ->update([
                'estado_qr' => 'cancelado',
                'codigo_qr' => null,
            ]);

        // ============================================================
        // CAMBIO V7: Enviar correo al creador
        // ============================================================
        $this->enviarCorreoRechazo($solicitud, $request->motivo_rechazo);

        LogSistema::registrar('RECHAZAR_SOLICITUD', "Solicitud EXT #{$solicitud->id_solicitud_ext} rechazada por {$user->username}");

        try {
            NotificacionService::solicitudRechazada($solicitud, $user, "Motivo: {$request->motivo_rechazo}");
        } catch (\Exception $e) {
            LogSistema::registrar('ERROR_NOTIF', 'Error al notificar rechazo EXT: ' . $e->getMessage());
        }

        // M2 [ScaModal] — Contar QRs cancelados.
        $qrsCancelados = \App\Models\SolicitudExternoDetalle::where('id_solicitud_ext', $solicitud->id_solicitud_ext)
            ->whereNotNull('codigo_qr')
            ->count();

        // Construir mensaje detallado.
        $mensaje = "<div class='text-start'>";
        $mensaje .= "<div class='mb-2'><strong>Solicitud:</strong> #EXT-{$solicitud->id_solicitud_ext}</div>";
        $mensaje .= "<div class='mb-2'><strong>Estado:</strong> <span class='badge bg-danger'>Rechazada</span></div>";
        $mensaje .= "<div class='mb-2'><strong>Motivo:</strong></div>";
        $mensaje .= "<div class='p-2 bg-light rounded'><em>" . nl2br(e($request->motivo_rechazo)) . "</em></div>";
        if ($qrsCancelados > 0) {
            $mensaje .= "<div class='mt-2 alert alert-warning py-2 mb-0'><i class='bi bi-qr-code'></i> Se cancelaron <strong>{$qrsCancelados}</strong> QR(s) de acceso.</div>";
        }
        $mensaje .= "</div>";

        // Option E: Setear flash en sesión.
        session()->flash('sca_modal', sca_modal_flash('success', 'Solicitud rechazada', $mensaje));

        if ($request->expectsJson()) {
            return response()->json([
                'success'  => true,
                'redirect' => route('solicitudes.bandeja'),
            ]);
        }

        return redirect()->route('solicitudes.bandeja')->with(
            'sca_modal',
            sca_modal_flash('success', 'Solicitud rechazada', $mensaje)
        );
    }

    // ============================================================
    // HELPERS PRIVADOS: VALIDACIÓN DE PERMISOS (scoping por planta)
    // ============================================================

    /**
     * ¿El usuario puede aprobar el paso ORIGEN de una solicitud interna?
     *
     * Reglas:
     *   - La solicitud debe estar en pendiente_origen.
     *   - Admin/Super: siempre pueden (ven todas las plantas).
     *   - Aprobador: solo si id_planta_origen = su planta.
     */
    protected function puedeAprobarOrigenInt(SolicitudInt $solicitud, $user, bool $esSuperior): bool
    {
        // FIX 7: Permitir aprobar si está pendiente_origen, o si está aprobado_parcial y destino ya aprobó
        if (!in_array($solicitud->estado, ['pendiente_origen', 'aprobado_parcial'])) return false;
        if ($solicitud->estado === 'aprobado_parcial' && $solicitud->fecha_aprobacion_origen !== null) return false;
        
        if ($esSuperior) return true;
        return $solicitud->id_planta_origen === $user->planta_id;
    }


    /**
     * ¿El usuario puede aprobar el paso DESTINO de una solicitud interna?
     *
     * Reglas:
     *   - La solicitud debe estar en pendiente_destino.
     *   - Admin/Super: siempre pueden.
     *   - Aprobador: solo si id_planta_destino = su planta.
     */
    protected function puedeAprobarDestinoInt(SolicitudInt $solicitud, $user, bool $esSuperior): bool
    {
        // FIX 7: Permitir aprobar si está pendiente_destino, o si está aprobado_parcial y origen ya aprobó
        if (!in_array($solicitud->estado, ['pendiente_destino', 'aprobado_parcial'])) return false;
        if ($solicitud->estado === 'aprobado_parcial' && $solicitud->fecha_aprobacion_destino !== null) return false;

        if ($esSuperior) return true;
        return $solicitud->id_planta_destino === $user->planta_id;
    }


    /**
     * ¿El usuario puede aprobar una solicitud externa?
     *
     * Reglas:
     *   - La solicitud debe estar en pendiente.
     *   - Admin/Super: siempre pueden.
     *   - Aprobador: solo si id_planta_destino = su planta.
     */
    private function puedeAprobarExt(SolicitudExt $solicitud, $user, bool $esSuperior): bool
    {
        if ($solicitud->estado !== 'pendiente') {
            return false;
        }
        if ($esSuperior) {
            return true;
        }
        return $solicitud->id_planta_destino === $user->planta_id;
    }

    // ============================================================
    // HELPERS PRIVADOS: ENVÍO DE CORREOS DE NOTIFICACIÓN
    // ============================================================

    /**
     * Envía correo al creador según el resultado de la aprobación interna.
     * - aprobado → "Aprobada"
     * - aprobado_parcial → "Aprobada parcialmente" + motivo
     * - pendiente_destino → "Aprobada en origen"
     */
    private function enviarCorreoAprobacionInt($solicitud, $pasoAprobado, $motivoRechazo = null, $rechazadosCount = 0, $nombresAprobados = [], $nombresRechazados = [])
    {
        try {
            $usuarioCreador = \App\Models\UsuarioSca::where('username', (string) $solicitud->creador_empleado_id)->first();
            if (!$usuarioCreador || !$usuarioCreador->email_emp) return;

            if ($solicitud->estado === 'aprobado') {
                \Mail::to($usuarioCreador->email_emp)->send(new \App\Mail\NotificacionSolicitudMail(
                    $solicitud,
                    $usuarioCreador->nombre_completo,
                    'Solicitud aprobada - #' . $solicitud->id_solicitud_int,
                    '✅ Solicitud Aprobada',
                    'Tu solicitud de ' . ($solicitud->tipo === 'pase_salida' ? 'Pase de Salida' : 'Interplantas') . ' ha sido aprobada completamente.',
                    null,
                    $nombresAprobados, // aprobados
                    null, // rechazados
                    null, // personas
                    null
                ));
            } elseif ($solicitud->estado === 'aprobado_parcial') {
                $mensaje = 'Tu solicitud ha sido aprobada parcialmente. ' . $rechazadosCount . ' empleado(s) fueron rechazados.';
                \Mail::to($usuarioCreador->email_emp)->send(new \App\Mail\NotificacionSolicitudMail(
                    $solicitud,
                    $usuarioCreador->nombre_completo,
                    'Solicitud aprobada parcialmente - #' . $solicitud->id_solicitud_int,
                    '⚠️ Solicitud Aprobada Parcialmente',
                    $mensaje,
                    $motivoRechazo,
                    $nombresAprobados, // aprobados
                    $nombresRechazados, // rechazados
                    null, // personas
                    null
                ));
            } elseif ($pasoAprobado === 'origen') {
                \Mail::to($usuarioCreador->email_emp)->send(new \App\Mail\NotificacionSolicitudMail(
                    $solicitud,
                    $usuarioCreador->nombre_completo,
                    'Solicitud aprobada en origen - #' . $solicitud->id_solicitud_int,
                    '✅ Aprobación de Origen Completada',
                    'Tu solicitud de interplantas fue aprobada en la planta de origen. Falta la aprobación de destino.',
                    null,
                    $nombresAprobados,
                    null,
                    null,
                    null
                ));
            }
        } catch (\Exception $e) {
            LogSistema::registrar('ERROR_NOTIF', 'Error al enviar correo de aprobación INT: ' . $e->getMessage());
        }
    }

    /**
     * Envía correo al creador cuando se rechaza una solicitud (int o ext).
     */
    private function enviarCorreoRechazo($solicitud, $motivo)
    {
        try {
            $usuarioCreador = \App\Models\UsuarioSca::where('username', (string) $solicitud->creador_empleado_id)->first();
            if (!$usuarioCreador || !$usuarioCreador->email_emp) return;

            // Determinar el ID según el tipo de solicitud
            $id = $solicitud instanceof \App\Models\SolicitudInt
                ? $solicitud->id_solicitud_int
                : $solicitud->id_solicitud_ext;

            \Mail::to($usuarioCreador->email_emp)->send(new \App\Mail\NotificacionSolicitudMail(
                $solicitud,
                $usuarioCreador->nombre_completo,
                'Solicitud rechazada - #' . $id,
                '❌ Solicitud Rechazada',
                'Tu solicitud ha sido rechazada.',
                $motivo
            ));
        } catch (\Exception $e) {
            LogSistema::registrar('ERROR_NOTIF', 'Error al enviar correo de rechazo: ' . $e->getMessage());
        }
    }
}