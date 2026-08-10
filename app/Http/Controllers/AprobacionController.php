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
     * NO se usa para grupales (deben usar aprobarGrupalInt).
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

            LogSistema::registrar('APROBAR_SOLICITUD', "Solicitud INT #{$solicitud->id_solicitud_int} aprobada (paso: {$pasoAprobado}) por {$user->username}");

            try {
                if ($pasoAprobado === 'completo') {
                    NotificacionService::solicitudAprobadaTotalmente($solicitud, $user);
                } else {
                    NotificacionService::solicitudAprobadaParcialmente($solicitud, $user, $pasoAprobado);
                }
            } catch (\Exception $e) {
                LogSistema::registrar('ERROR_NOTIF', 'Error al notificar aprobación INT: ' . $e->getMessage());
            }

            return redirect()->route('solicitudes.bandeja')
                ->with('success', "Solicitud #{$solicitud->id_solicitud_int} aprobada correctamente.");

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
     * Body:
     *   - motivo_rechazo: string (obligatorio)
     *
     * Lógica:
     *   - estado = rechazado + motivo_rechazo.
     *   - Si era grupal, marca todos los empleados como rechazado.
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

        LogSistema::registrar('RECHAZAR_SOLICITUD', "Solicitud INT #{$solicitud->id_solicitud_int} rechazada por {$user->username}");

        try {
            NotificacionService::solicitudRechazada($solicitud, $user, "Motivo: {$request->motivo_rechazo}");
        } catch (\Exception $e) {
            LogSistema::registrar('ERROR_NOTIF', 'Error al notificar rechazo INT: ' . $e->getMessage());
        }

        return redirect()->route('solicitudes.bandeja')
            ->with('success', "Solicitud #{$solicitud->id_solicitud_int} rechazada.");
    }

    // ============================================================
    // 3. APROBAR SOLICITUD INTERNA GRUPAL (con aprobado_parcial)
    // ============================================================

    /**
     * Aprueba/rechaza una solicitud grupal (pase_salida o interplantas),
     * empleado por empleado.
     *
     * Ruta: POST /solicitudes/int/{solicitud}/aprobar-grupal
     *
     * Body:
     *   - estados: array [empleado_id => 'aprobado'|'rechazado', ...]
     *
     * Lógica (CON aprobado_parcial):
     *   - El aprobador marca cada empleado como aprobado o rechazado.
     *   - Si TODOS son aprobados:
     *     · pase_salida → aprobado (solo paso origen).
     *     · interplantas en origen (Aprobador) → pendiente_destino.
     *     · interplantas en origen (Admin/Super) → aprobado (ambos pasos).
     *     · interplantas en destino → aprobado.
     *   - Si ALGUNO es rechazado:
     *     · estado = aprobado_parcial.
     *     · Los aprobados pueden entrar/salir.
     *     · Los rechazados NO (el scanner los deniega).
     *
     * Validación en el scanner:
     *   - tieneInterplantasVigente() busca al empleado con estado='aprobado'
     *     en solicitud_empleados de una solicitud aprobado o aprobado_parcial.
     *   - tienePermisoParaSalir() hace lo mismo para pase_salida.
     *   - Los rechazados no aparecen → no tienen acceso.
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

        $estados = $request->input('estados', []);
        $userId = (int) $user->username;
        $ahora = now();

        DB::beginTransaction();
        try {
            // 1. Actualizar el estado de cada empleado
            $aproboTodos = true;
            $empleadosRechazados = [];
            foreach ($estados as $empId => $estadoEmp) {
                SolicitudEmpleado::where('id_solicitud_int', $solicitud->id_solicitud_int)
                    ->where('empleado_id', $empId)
                    ->update(['estado' => $estadoEmp]);

                if ($estadoEmp !== 'aprobado') {
                    $aproboTodos = false;
                    $empleadosRechazados[] = $empId;
                }
            }

            $pasoAprobado = null;

            // 2. Decidir el estado de la solicitud
            if ($aproboTodos) {
                // Todos aprobados
                if ($esAprobadorOrigen) {
                    $solicitud->aprobador_origen_empleado_id = $userId;
                    $solicitud->fecha_aprobacion_origen = $ahora;

                    if ($solicitud->tipo === 'pase_salida') {
                        // Pase de salida grupal: solo necesita origen
                        $solicitud->estado = 'aprobado';
                        $pasoAprobado = 'completo';
                    } elseif ($esSuperior) {
                        // Interplantas + Admin/Super: aprueba también destino
                        $solicitud->aprobador_destino_empleado_id = $userId;
                        $solicitud->fecha_aprobacion_destino = $ahora;
                        $solicitud->estado = 'aprobado';
                        $pasoAprobado = 'completo';
                    } else {
                        // Interplantas + Aprobador: pasa a destino
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
                // Algún empleado fue rechazado → aprobado_parcial
                $solicitud->estado = 'aprobado_parcial';
                $solicitud->motivo_rechazo = count($empleadosRechazados) . ' empleado(s) rechazado(s) en revisión grupal';

                if ($esAprobadorOrigen) {
                    $solicitud->aprobador_origen_empleado_id = $userId;
                    $solicitud->fecha_aprobacion_origen = $ahora;

                    // Si Admin/Super, también marca destino (los aprobados quedan autorizados)
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

            LogSistema::registrar(
                'APROBAR_SOLICITUD_GRUPAL',
                "Solicitud INT #{$solicitud->id_solicitud_int} revisada grupalmente por {$user->username}. Estado: {$solicitud->estado}"
            );

            // Notificaciones (defensivo)
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

            return redirect()->route('solicitudes.bandeja')
                ->with('success', "Solicitud #{$solicitud->id_solicitud_int} revisada correctamente.");

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
     * Body (opcional, para aprobación parcial):
     *   - estados: array [id_solicitud_externo_detalle => 'autorizado'|'cancelado', ...]
     *
     * Lógica (CON aprobado_parcial):
     *   - Si no se envía 'estados' → se aprueban todos (auto-aprobación total).
     *   - Si se envía 'estados' → el aprobador marca cada externo.
     *   - Para cada externo:
     *     · autorizado → se genera QR, estado_qr = autorizado.
     *     · cancelado → no se genera QR, estado_qr = cancelado.
     *   - Si TODOS autorizados → estado = aprobado.
     *   - Si ALGUNO cancelado → estado = aprobado_parcial.
     *
     * Validación en el scanner:
     *   - procesarExterno() busca por codigo_qr; si no existe o estado_qr=cancelado,
     *     deniega con "QR no válido".
     *   - Los cancelados no tienen QR → no pueden entrar.
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
            $estados = $request->input('estados', []); // [id_solicitud_externo_detalle => 'autorizado'|'cancelado']

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
                    // Cancelar: sin QR (o anular si ya tenía)
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
                $solicitud->motivo_rechazo = $externosCancelados . ' externo(s) cancelado(s) en aprobación';
            }

            $solicitud->save();
            DB::commit();

            LogSistema::registrar(
                'APROBAR_SOLICITUD',
                "Solicitud EXT #{$solicitud->id_solicitud_ext} aprobada por {$user->username}. Estado: {$solicitud->estado}"
            );

            try {
                if ($solicitud->estado === 'aprobado_parcial') {
                    NotificacionService::solicitudAprobadaParcialmente($solicitud, $user, 'destino_parcial');
                } else {
                    NotificacionService::solicitudAprobadaTotalmente($solicitud, $user);
                }
            } catch (\Exception $e) {
                LogSistema::registrar('ERROR_NOTIF', 'Error al notificar aprobación EXT: ' . $e->getMessage());
            }

            $mensaje = $solicitud->estado === 'aprobado_parcial'
                ? "Solicitud #{$solicitud->id_solicitud_ext} aprobada parcialmente. {$externosCancelados} externo(s) sin acceso."
                : "Solicitud #{$solicitud->id_solicitud_ext} aprobada correctamente. Se generaron los QRs de acceso.";

            return redirect()->route('solicitudes.bandeja')->with('success', $mensaje);

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
     * Lógica:
     *   - estado = rechazado + motivo_rechazo.
     *   - Cancela cualquier QR existente (estado_qr = cancelado, codigo_qr = null).
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

        // Cancelar todos los QR
        foreach ($solicitud->externos as $detalle) {
            $detalle->codigo_qr = null;
            $detalle->estado_qr = 'cancelado';
            $detalle->save();
        }

        LogSistema::registrar('RECHAZAR_SOLICITUD', "Solicitud EXT #{$solicitud->id_solicitud_ext} rechazada por {$user->username}");

        try {
            NotificacionService::solicitudRechazada($solicitud, $user, "Motivo: {$request->motivo_rechazo}");
        } catch (\Exception $e) {
            LogSistema::registrar('ERROR_NOTIF', 'Error al notificar rechazo EXT: ' . $e->getMessage());
        }

        return redirect()->route('solicitudes.bandeja')
            ->with('success', "Solicitud #{$solicitud->id_solicitud_ext} rechazada.");
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
    private function puedeAprobarOrigenInt(SolicitudInt $solicitud, $user, bool $esSuperior): bool
    {
        if ($solicitud->estado !== 'pendiente_origen') {
            return false;
        }
        if ($esSuperior) {
            return true;
        }
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
    private function puedeAprobarDestinoInt(SolicitudInt $solicitud, $user, bool $esSuperior): bool
    {
        if ($solicitud->estado !== 'pendiente_destino') {
            return false;
        }
        if ($esSuperior) {
            return true;
        }
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
}