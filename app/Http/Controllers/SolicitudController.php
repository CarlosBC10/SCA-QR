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
            // V10.3 · CAMBIO #12: 'permiso_entrada' se enruta a la rama INTERNA
            // (solicitudes_int). El enum del Script 2 de BD ya lo admite.
            if (in_array($tipo, ['pase_salida', 'interplantas', 'permiso_entrada'])) {
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

        // ============================================================
        // V10.3.2-fix (restaurado de V10.3.1, hallazgo 3 del usuario):
        // regla de planta origen según "para quién" es la solicitud:
        //   - Admin/Super + "otros"            → cualquier planta activa.
        //   - Admin/Super + incluye al creador → SOLO su planta asignada.
        // El formulario ya bloquea el selector (JS, ver create.blade),
        // pero un POST artesanal podría mandar otra planta: esto lo taja
        // en el servidor. Defensa en profundidad. (La revisión de ChatGPT
        // se basó en el zip previo a V10.3.1 y no traía esta validación.)
        // ============================================================
        $incluyeCreador = $request->boolean('incluirme')
            || in_array($request->input('para_quien', 'mi'), ['mi', 'mi_y_otros']);

        if (($user->isAdmin() || $user->isSuperadmin()) && $incluyeCreador) {
            if (empty($user->planta_id)) {
                throw new \Exception(
                    'Su usuario no tiene planta asignada, no puede crear solicitudes que lo incluyan. ' .
                    'Use "Solo para otras personas" y seleccione la planta correspondiente.'
                );
            }
            if ((int) $plantaOrigenId !== (int) $user->planta_id) {
                throw new \Exception(
                    'Cuando la solicitud lo incluye a usted (solo para mí / para mí y otros), la planta origen ' .
                    'debe ser su planta asignada (' . $user->planta_nombre . '). ' .
                    'Para tramitar personas de otra planta, use "Solo para otras personas".'
                );
            }
        }

        // Planta destino
        // V10.3 · CAMBIO #13: permiso_entrada se comporta como pase_salida:
        // destino = origen (autoriza entrar a SU propia planta fuera de
        // turno). Ir a OTRA planta sigue siendo exclusivo de interplantas
        // (doble aprobación), para que nadie use permiso_entrada como
        // atajo para colarse en plantas ajenas.
        if (in_array($tipo, ['pase_salida', 'permiso_entrada'])) {
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

            // V10.3 · CAMBIO #14: permiso_entrada, igual que pase_salida,
            // solo requiere aprobación de ORIGEN (una sola planta interviene:
            // la del beneficiario). Aprobador+ que lo crea → queda 'aprobado'.
            if (in_array($tipo, ['pase_salida', 'permiso_entrada'])) {
                // Pase de salida / Permiso de entrada: solo requieren
                // aprobación de origen.
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
            // V10.3-fix: la selección server-side de beneficiarios también
            // controla el cálculo de fechas del Pase de Salida; no confiamos
            // en el checkbox oculto "incluirme" del navegador.
            $empleadosInput = array_values(array_unique(array_map('intval', (array) $request->input('empleados', []))));
            $paraQuien = $request->input('para_quien', 'mi');

            if ($paraQuien === 'mi') {
                $empleadosIds = [$userId];
            } elseif ($paraQuien === 'mi_y_otros') {
                $empleadosIds = array_values(array_unique(array_merge($empleadosInput, [$userId])));
            } elseif ($paraQuien === 'otros') {
                $empleadosIds = $empleadosInput;
            } else {
                throw new \Exception("Selección de beneficiarios inválida.");
            }

            // Para el cálculo de fechas del pase, solo importa si el creador
            // es beneficiario real, no el valor manipulable de "incluirme".
            $incluirme = in_array($userId, $empleadosIds, true);

            // Validar que haya al menos un empleado
            if (empty($empleadosIds)) {
                throw new \Exception("Debe agregar al menos un empleado al Pase de Salida.");
            }

            // Validar que todos tengan turno asignado
            $empleadoresConTurno = Empleado::with('turno')->whereIn('id_empleado', $empleadosIds)->get();
            $sinTurno = [];
            foreach ($empleadoresConTurno as $emp) {
                if (!$emp->turno || !$emp->turno->entrada || !$emp->turno->salida) {
                    $sinTurno[] = $emp->nombre_completo;
                }
            }

            // FIX 6 (Opción 3): si hay empleados sin turno, bloquear.
            if (!empty($sinTurno)) {
                $nombres = implode(', ', $sinTurno);
                throw new \Exception("Los siguientes empleados no tienen turno asignado en sitec: {$nombres}. No se puede crear un Pase de Salida para empleados sin turno. Contacte a RR.HH. para que les asignen un turno.");
            }

            // ============================================================
            // FIX 6: Cálculo de fechas según "¿Para quién es?"
            // ============================================================
            if ($incluirme) {
                // "Para mí" o "Para mí y otros": cálculo automático desde el turno del creador
                $empleadoReferencia = $empleadoresConTurno->firstWhere('id_empleado', $userId);
                if (!$empleadoReferencia) {
                    $empleadoReferencia = $empleadoresConTurno->first();
                }

                $turno = $empleadoReferencia->turno;
                $horaSalida = $turno->salida;
                $horaEntrada = $turno->entrada;

                $fechaBase = \Carbon\Carbon::parse($fechaInicio)->startOfDay();
                $hoy = now()->startOfDay();

                if ($fechaBase->isSameDay($hoy)) {
                    // HOY → inicio = la hora que el usuario capturó en el input.
                    $fechaInicio = \Carbon\Carbon::parse($request->fecha_inicio);
                    
                    // FIX 6: Calcular fecha_fin considerando turnos nocturnos.
                    // Si el turno cruza medianoche (entrada > salida, ej: 21:30-06:00),
                    // y la hora actual es antes de medianoche (ej: 10pm), la fecha_fin
                    // es MAÑANA a la hora de salida. Si es después de medianoche (ej: 2am),
                    // la fecha_fin es HOY a la hora de salida.
                    $entradaTs = strtotime($horaEntrada);
                    $salidaTs = strtotime($horaSalida);
                    $esTurnoNocturno = $entradaTs > $salidaTs;
                    
                    if ($esTurnoNocturno) {
                        $horaActual = now();
                        $horaSalidaCarbon = $hoy->copy()->setTimeFromTimeString($horaSalida);
                        // Si la hora actual es mayor que la hora de salida, significa
                        // que estamos en la primer parte del turno (antes de medianoche),
                        // así que la fecha_fin es mañana.
                        if ($horaActual->format('H:i:s') > $horaSalida) {
                            $fechaFin = $hoy->copy()->addDay()->setTimeFromTimeString($horaSalida);
                        } else {
                            // Estamos después de medianoche, fecha_fin es hoy
                            $fechaFin = $hoy->copy()->setTimeFromTimeString($horaSalida);
                        }
                    } else {
                        // Turno normal, fecha_fin es hoy
                        $fechaFin = $hoy->copy()->setTimeFromTimeString($horaSalida);
                    }
                } else {
                    // FUTURO (aunque ocultamos "Otra fecha", por defensa)
                    $horaManual = $request->input('hora_inicio_manual');
                    if (!empty($horaManual)) {
                        $fechaInicio = $fechaBase->copy()->setTimeFromTimeString($horaManual);
                    } else {
                        $fechaInicio = $fechaBase->copy()->setTimeFromTimeString($horaEntrada);
                    }
                    $fechaFin = $fechaBase->copy()->setTimeFromTimeString($horaSalida);
                }
            } else {
                // "Solo para otras personas": calcular fechas desde el turno
                // del primer empleado (igual que cuando es "incluirme", pero
                // usando el primer empleado de la lista en vez del creador).
                $empleadoReferencia = $empleadoresConTurno->first();
                if (!$empleadoReferencia) {
                    throw new \Exception("No se encontraron empleados válidos para calcular el Pase de Salida.");
                }

                $turno = $empleadoReferencia->turno;
                $horaSalida = $turno->salida;
                $horaEntrada = $turno->entrada;

                $fechaBase = \Carbon\Carbon::parse($fechaInicio)->startOfDay();
                $hoy = now()->startOfDay();

                if ($fechaBase->isSameDay($hoy)) {
                    // HOY → inicio = la hora que el usuario capturó
                    $fechaInicio = \Carbon\Carbon::parse($request->fecha_inicio);

                    // Calcular fecha_fin considerando turnos nocturnos
                    $entradaTs = strtotime($horaEntrada);
                    $salidaTs = strtotime($horaSalida);
                    $esTurnoNocturno = $entradaTs > $salidaTs;

                    if ($esTurnoNocturno) {
                        $horaActual = now();
                        $horaSalidaCarbon = $hoy->copy()->setTimeFromTimeString($horaSalida);
                        if ($horaActual->format('H:i:s') > $horaSalida) {
                            $fechaFin = $hoy->copy()->addDay()->setTimeFromTimeString($horaSalida);
                        } else {
                            $fechaFin = $hoy->copy()->setTimeFromTimeString($horaSalida);
                        }
                    } else {
                        $fechaFin = $hoy->copy()->setTimeFromTimeString($horaSalida);
                    }
                } else {
                    // FUTURO
                    $horaManual = $request->input('hora_inicio_manual');
                    if (!empty($horaManual)) {
                        $fechaInicio = $fechaBase->copy()->setTimeFromTimeString($horaManual);
                    } else {
                        $fechaInicio = $fechaBase->copy()->setTimeFromTimeString($horaEntrada);
                    }
                    $fechaFin = $fechaBase->copy()->setTimeFromTimeString($horaSalida);
                }
            }
        } // ← esta llave cierra el if ($tipo === 'pase_salida') de la línea 265

        // ============================================================
        // FIX A-3: VALIDACIÓN DE DÍA LABORAL, INTERSECCIÓN Y "ESTÁ EN PLANTA"
        // ============================================================
        // V10.3 · CAMBIO #16: permiso_entrada TAMBIÉN pasa por la validación
        // de existencia/actividad de empleados (FIX #12), pero NO por la
        // restricción de día laboral: entrar fuera de día/horario es justo
        // su razón de ser (domingos de operativos, fines de semana de
        // administrativos, antes/después de turno).
        if (in_array($tipo, ['pase_salida', 'interplantas', 'permiso_entrada'])) {
            // V10.3-fix: lista canónica de beneficiarios.
            // La selección "para_quien" manda en servidor; no confiamos en
            // campos ocultos/JS manipulables. Esta misma lista se usa para
            // validar, anti-traslape e insertar solicitud_empleados, evitando
            // que una parte del flujo considere beneficiarios distintos a otra.
            $empleadosInput = array_values(array_unique(array_map('intval', (array) $request->input('empleados', []))));
            $paraQuien = $request->input('para_quien', 'mi');

            if ($paraQuien === 'mi') {
                // Solo para mí: jamás aceptar beneficiarios ajenos aunque el
                // cliente haya manipulado el POST.
                $empleadosIds = [$userId];
            } elseif ($paraQuien === 'mi_y_otros') {
                $empleadosIds = $empleadosInput;
                $empleadosIds[] = $userId;
            } elseif ($paraQuien === 'otros') {
                $empleadosIds = $empleadosInput;
            } else {
                throw new \Exception('Selección de beneficiarios inválida.');
            }

            $empleadosIds = array_values(array_unique(array_map('intval', $empleadosIds)));

            if (empty($empleadosIds)) {
                throw new \Exception('Debe seleccionar al menos un empleado beneficiario.');
            }

            $empleadosAValidar = Empleado::with(['turno', 'clasificacion'])->whereIn('id_empleado', $empleadosIds)->get();

        // FIX #12: validar que TODOS los empleados solicitados existen y están activos.
        // Antes: si se pedían [1001, 9999] y 9999 no existía, se ignoraba silenciosamente.
        // Ahora: lanzamos excepción con los IDs faltantes o inactivos.
        $idsSolicitados = collect($empleadosIds)->map(fn ($id) => (int) $id)->unique()->values();
        $idsEncontrados = $empleadosAValidar->pluck('id_empleado')->map(fn ($id) => (int) $id)->values();
        $idsNoEncontrados = $idsSolicitados->diff($idsEncontrados);
        if ($idsNoEncontrados->isNotEmpty()) {
            throw new \Exception('Uno o más empleados seleccionados ya no existen en SITEC.');
        }
        $empleadosInactivos = $empleadosAValidar->filter(fn ($emp) => !$emp->estaActivo());
        if ($empleadosInactivos->isNotEmpty()) {
            $nombres = $empleadosInactivos->pluck('nombre_completo')->implode(', ');
            throw new \Exception("Los siguientes empleados ya no están activos en SITEC: {$nombres}.");
        }

        // ============================================================
        // V10.3.7-fix: QUIEN NO NECESITA EL PERMISO NO PUEDE SER
        // BENEFICIARIO — y el creador es un beneficiario más (llega en
        // $empleadosAValidar cuando para_quien = mi / mi_y_otros).
        //
        // Confirmado con el usuario (regla única, aplica a cualquier rol):
        //   - Pase de Salida → solo lo necesita un OPERATIVO. Corporativos
        //     y administrativos salen de su planta libremente durante su
        //     turno: el escáner jamás consulta un pase para ellos, así que
        //     sería un "permiso muerto" (misma clase de bug que V10.3.3).
        //   - Permiso de Entrada / Interplantas → no los necesita un
        //     CORPORATIVO (RN-01: entra a cualquier planta a cualquier hora).
        //   - Sin clasificar → el escáner lo rechaza SIEMPRE
        //     (sin_clasificar, validarAccesoPlanta caso 1): cualquier
        //     permiso interno es papel mojado; debe ir a RR.HH.
        //
        // La UI ya bloquea estos casos (create.blade, mismo marcador);
        // esta es la defensa autoritativa ante POST artesanales.
        // ============================================================
        $noNecesitan = [];
        foreach ($empleadosAValidar as $emp) {
            $tipoEmpBeneficiario = $emp->tipoEmpleado();
            if ($tipo === 'pase_salida') {
                if ($tipoEmpBeneficiario !== 'operativo') {
                    $noNecesitan[] = $emp->nombre_completo
                        . ($tipoEmpBeneficiario ? " ({$tipoEmpBeneficiario})" : ' (sin clasificar en RR.HH.)');
                }
            } elseif ($tipoEmpBeneficiario === 'corporativo' || $tipoEmpBeneficiario === null) {
                $noNecesitan[] = $emp->nombre_completo
                    . ($tipoEmpBeneficiario === 'corporativo' ? ' (corporativo)' : ' (sin clasificar en RR.HH.)');
            }
        }
        if (!empty($noNecesitan)) {
            $lista = implode(', ', $noNecesitan);
            if ($tipo === 'pase_salida') {
                throw new \Exception(
                    "No se puede crear el Pase de Salida: {$lista}. " .
                    'Corporativos y administrativos no necesitan pase para salir (pueden salir de su planta libremente ' .
                    'durante su turno) y un empleado sin clasificar es rechazado siempre por el escáner. ' .
                    'Solo los operativos requieren Pase de Salida. Si cree que la clasificación es incorrecta, acuda a RR.HH.'
                );
            }
            $nombrePermiso = $tipo === 'interplantas' ? 'Interplantas' : 'Permiso de Entrada';
            throw new \Exception(
                "No se puede crear el {$nombrePermiso}: {$lista}. " .
                'Un corporativo entra libremente a cualquier planta a cualquier hora (no necesita permiso) ' .
                'y un empleado sin clasificar es rechazado siempre por el escáner (debe acudir a RR.HH.).'
            );
        }

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
                        $estaEnPlanta = \App\Services\PresenciaService::estaDentro(
                            (string) $emp->id_empleado,
                            (int) $emp->plantaId()
                        );

                        if ($esHoy && $estaEnPlanta) {
                            // Permite crear, pero no hacemos nada (el guardia justificará al salir)
                        } else {
                            $nombresDias = [1=>'Lunes',2=>'Martes',3=>'Miércoles',4=>'Jueves',5=>'Viernes',6=>'Sábado',7=>'Domingo'];
                            throw new \Exception("No es posible crear el Pase de Salida para {$emp->nombre_completo} porque {$nombresDias[$diaSemana]} no es día laboral.");
                        }
                    } elseif ($tipo === 'interplantas') {
                        // Interplantas: advertir pero permitir
                        LogSistema::registrar('WARN_INTERPLANTAS_DIA', "Solicitud INT para {$emp->nombre_completo} en día no laboral. Se permite con advertencia.");
                    }
                    // V10.3 · CAMBIO #16 (FIX A-3 ajustado): si el tipo es
                    // permiso_entrada y el día no es laboral, NO se bloquea
                    // ni se advierte: es su caso de uso natural. Así se
                    // pueden crear permisos de entrada para domingo (regla
                    // acordada en el plan D-2).
                }
            }
        }

        // ============================================================
        // V10.3.3-fix (bug 1 reportado por el usuario): un Permiso de
        // Entrada solo tiene efecto FUERA del turno del beneficiario.
        // El escáner (AccesoService) lo consulta ÚNICAMENTE en:
        //   1) día no laboral (domingo operativo / fin de semana admin),
        //   2) empleado sin turno asignado,
        //   3) primera entrada MÁS de 30 min antes del turno, o
        //   4) entrada después de que termina el turno (salidaMax).
        // Una vigencia que queda COMPLETAMENTE dentro del turno es un
        // permiso que jamás se consulta (p. ej. el fin prellenado con la
        // hora de SALIDA del turno del creador, que era el bug reportado).
        // Se bloquea explicando la regla; el solape PARCIAL (p. ej.
        // 04:00-12:00 con turno 06:00-14:00) se permite porque la parte
        // fuera del turno sí es consultada y la de dentro es inofensiva.
        // ============================================================
        if ($tipo === 'permiso_entrada') {
            $inicioPermiso = \Carbon\Carbon::parse($fechaInicio);
            $finPermiso    = \Carbon\Carbon::parse($fechaFin);
            $ventanaDentroTurno = [];

            foreach ($empleadosAValidar as $emp) {
                // Sin turno → SIEMPRE está "fuera de turno": no se valida.
                if (!$emp->turno || !$emp->turno->entrada || !$emp->turno->salida) {
                    continue;
                }

                // Día no laboral del beneficiario → todo el día cuenta como
                // fuera de turno: el permiso es válido aunque cubra esas horas.
                $tipoEmp  = $emp->tipoEmpleado();
                $diaPermiso = $inicioPermiso->copy()->startOfDay()->dayOfWeekIso; // 1=Lun...7=Dom
                $laboral   = ($tipoEmp === 'administrativo') ? ($diaPermiso <= 5) : ($diaPermiso <= 6);
                if (!$laboral) {
                    continue;
                }

                // Intervalo del turno que podría contener la vigencia.
                $base = $inicioPermiso->copy()->startOfDay();
                $contiene = false;
                if ($emp->turno->entrada > $emp->turno->salida) {
                    // Turno nocturno (cruza medianoche): el permiso puede caer
                    // en el turno de la noche anterior o en el de la propia noche.
                    $iniA = $base->copy()->subDay()->setTimeFromTimeString($emp->turno->entrada);
                    $finA = $base->copy()->setTimeFromTimeString($emp->turno->salida);
                    $iniB = $base->copy()->setTimeFromTimeString($emp->turno->entrada);
                    $finB = $base->copy()->addDay()->setTimeFromTimeString($emp->turno->salida);
                    $contiene = ($inicioPermiso->gte($iniA) && $finPermiso->lte($finA))
                             || ($inicioPermiso->gte($iniB) && $finPermiso->lte($finB));
                } else {
                    $iniT = $base->copy()->setTimeFromTimeString($emp->turno->entrada);
                    $finT = $base->copy()->setTimeFromTimeString($emp->turno->salida);
                    $contiene = ($inicioPermiso->gte($iniT) && $finPermiso->lte($finT));
                }

                if ($contiene) {
                    $ventanaDentroTurno[] = $emp->nombre_completo
                        . ' (turno ' . substr($emp->turno->entrada, 0, 5) . '-' . substr($emp->turno->salida, 0, 5) . ')';
                }
            }

            if (!empty($ventanaDentroTurno)) {
                $nombresDentro = implode(', ', $ventanaDentroTurno);
                throw new \Exception(
                    "La vigencia del Permiso de Entrada ({$inicioPermiso->format('d/m/Y H:i')} a {$finPermiso->format('d/m/Y H:i')}) " .
                    "queda completamente DENTRO del turno de: {$nombresDentro}. " .
                    'Un Permiso de Entrada solo tiene efecto FUERA del turno: llegada temprana (antes de su hora de entrada), ' .
                    'entradas después de que termina su turno, o días no laborales. ' .
                    'Para una llegada temprana, termine la vigencia a más tardar a la hora de entrada del turno.'
                );
            }
        }

        // ============================================================
        // V10.3 · CAMBIO #17: ANTI-TRASLAPE INTERNO (regla "1 permiso
        // activo por empleado")
        // ============================================================
        // Un interno NO puede figurar en 2 solicitudes_int con vigencias
        // traslapadas — ni como creador-individual ni como integrante —
        // SIN IMPORTAR EL TIPO (pase_salida, interplantas o
        // permiso_entrada). Esto evita ambigüedades en el escáner (¿qué
        // permiso aplica?) y refleja la regla de negocio acordada.
        //
        // Estados que BLOQUEAN (aún vigentes):
        //   - Solicitud: pendiente_origen, pendiente_destino, aprobado,
        //     aprobado_parcial (se liberan con rechazado/expirado).
        //   - Integrante (solicitud_empleados): pendiente, aprobado,
        //     aprobado_origen.
        //
        // Fórmula de traslape (la MISMA que usa la validación de
        // externos, líneas arriba):
        //   nueva.inicio < existente.fin AND nueva.fin > existente.inicio
        //
        // Los EXTERNOS NO cambían: pueden tener 2 permisos simultáneos
        // en plantas DISTINTAS (su validación ya existe y solo bloquea
        // la misma planta).
        // ============================================================
        $nuevaInicioTraslap = \Carbon\Carbon::parse($fechaInicio);
        $nuevaFinTraslap    = \Carbon\Carbon::parse($fechaFin);

        foreach ($empleadosAValidar as $empTraslap) {
            $empIdTraslap = $empTraslap->id_empleado;
            $conflicto = SolicitudInt::query()
                ->whereIn('estado', ['pendiente_origen', 'pendiente_destino', 'aprobado', 'aprobado_parcial'])
                ->where('fecha_inicio', '<', $nuevaFinTraslap)
                ->where('fecha_fin', '>', $nuevaInicioTraslap)
                ->where(function ($q) use ($empIdTraslap) {
                    // Mismo criterio del CAMBIO #02 (AccesoService):
                    // individual (creador sin lista) o integrante activo.
                    $q->where(function ($qIndividual) use ($empIdTraslap) {
                        $qIndividual->where('creador_empleado_id', $empIdTraslap)
                                    ->whereDoesntHave('empleados');
                    })->orWhereHas('empleados', function ($q2) use ($empIdTraslap) {
                        $q2->where('empleado_id', $empIdTraslap)
                           ->whereIn('estado', ['pendiente', 'aprobado', 'aprobado_origen']);
                    });
                })
                ->orderBy('fecha_fin', 'asc')
                ->first();

            if ($conflicto) {
                $tiposLabel = [
                    'pase_salida'     => 'Pase de Salida',
                    'interplantas'    => 'Interplantas',
                    'permiso_entrada' => 'Permiso de Entrada',
                ];
                $tipoConflicto = $tiposLabel[$conflicto->tipo] ?? $conflicto->tipo;
                throw new \Exception("{$empTraslap->nombre_completo} ya tiene un permiso interno activo ({$tipoConflicto} #INT-{$conflicto->id_solicitud_int}, vigente del {$conflicto->fecha_inicio->format('d/m/Y H:i')} al {$conflicto->fecha_fin->format('d/m/Y H:i')}) que se traslapa con las fechas de esta solicitud. Regla anti-traslape: un empleado solo puede tener un permiso interno activo a la vez. Espere a que expire o cancele el permiso anterior.");
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

        // V10.3-fix: usar exactamente la lista canónica de beneficiarios
        // calculada y validada arriba. Así no existe una segunda interpretación
        // de "para_quien" al momento de insertar solicitud_empleados.
        $empleados = $empleadosIds;

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

        // ============================================================
        // V10.3 · CAMBIO #18: AVISO A LOS BENEFICIARIOS
        // ============================================================
        // Cuando la solicitud incluye a otras personas ("mi_y_otros" u
        // "otros"), cada beneficiario recibe una notificación in-app
        // informando que se creó un permiso A SU NOMBRE (transparencia:
        // nadie debe enterarse en el kiosko de que tiene —o perdió— un
        // permiso). Solo llega quienes tienen cuenta en ScaQR.
        // Defensivo: si falla, no rompe la creación.
        // ============================================================
        try {
            $beneficiariosAviso = $solicitud->empleados()
                ->where('empleado_id', '!=', $userId)
                ->pluck('empleado_id');

            foreach ($beneficiariosAviso as $benId) {
                $usuarioBen = \App\Models\UsuarioSca::where('username', (string) $benId)
                    ->whereNull('fecha_baja')
                    ->first();
                if ($usuarioBen) {
                    \App\Services\NotificacionService::enviar(
                        $usuarioBen->id_usuario_sca,
                        'solicitud_beneficiario',
                        'Permiso creado a su nombre',
                        "{$user->nombre_completo} creó la solicitud #INT-{$solicitud->id_solicitud_int} que lo incluye como beneficiario. Vigencia: {$solicitud->fecha_inicio->format('d/m/Y H:i')} a {$solicitud->fecha_fin->format('d/m/Y H:i')}.",
                        route('solicitudes.show.int', $solicitud->id_solicitud_int)
                    );
                }
            }
        } catch (\Exception $e) {
            LogSistema::registrar('ERROR_NOTIF', 'Error al notificar beneficiarios: ' . $e->getMessage());
        }

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
        // CAMBIO V7 → V10.3.5-fix: Enviar correo al crear solicitud INT.
        // ANTES: se enviaba a TODOS los Aprobador/Admin/Super del sistema
        // (aprobadores de plantas ajenas incluidos) y con el mismo texto
        // "requiere su revisión" aunque estuviera auto-aprobada.
        // AHORA:
        //   · Pendiente (creador Empleado) → solo aprobadores de la planta
        //     responsable de aprobar (origen si pendiente_origen; destino
        //     en el resto) + Admin/Superadmin.
        //   · Auto-aprobada (creador Aprobador+) → aprobadores de las
        //     plantas involucradas + Admin/Super (sin el creador), con
        //     texto de auto-aprobación.
        // ============================================================
        try {
            $creadorNombre = $user->nombre_completo;
            $empleadosIds = $solicitud->empleados->map(fn($se) => $se->empleado_id)->toArray();
            $nombresEmpleados = \App\Models\Sitec\Empleado::whereIn('id_empleado', $empleadosIds)->pluck('nombre_completo')->toArray();

            if ($estado === 'aprobado' && $esAprobadorOSuperior) {
                // --- V10.3.5-fix: auto-aprobada al crearla ---
                $destinatarios = \App\Services\NotificacionService::destinatariosCorreoAutoAprobada($solicitud, $user);
                foreach ($destinatarios as $dest) {
                    if ($dest->email_emp) {
                        \Mail::to($dest->email_emp)->send(new \App\Mail\NotificacionSolicitudMail(
                            $solicitud,
                            $dest->nombre_completo,
                            'Solicitud auto-aprobada - #' . $solicitud->id_solicitud_int,
                            '✅ Solicitud Auto-Aprobada',
                            "{$creadorNombre} ha generado y aprobado esta solicitud. No requiere revisión adicional.",
                            null,
                            null, // aprobados
                            null, // rechazados
                            $nombresEmpleados, // personas (neutral)
                            $creadorNombre
                        ));
                    }
                }
            } else {
                // --- V10.3.5-fix: pendiente → planta responsable + Admin/Super ---
                $destinatarios = \App\Services\NotificacionService::destinatariosCorreoNuevaPendiente($solicitud);
                foreach ($destinatarios as $dest) {
                    if ($dest->email_emp) {
                        \Mail::to($dest->email_emp)->send(new \App\Mail\NotificacionSolicitudMail(
                            $solicitud,
                            $dest->nombre_completo,
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
            }
        } catch (\Exception $e) {
            LogSistema::registrar('ERROR_NOTIF', 'Error al notificar nueva solicitud INT: ' . $e->getMessage());
        }

        // M2 [ScaModal] — Mensaje detallado de creación.
        // V10.3 · CAMBIO #20: etiqueta correcta para el nuevo tipo en el
        // modal de confirmación.
        $tipoLabelInt = match($solicitud->tipo) {
            'pase_salida'     => 'Pase de salida',
            'permiso_entrada' => 'Permiso de entrada',
            default           => 'Interplantas',
        };
        $mensaje = "<div class='text-start'>";
        $mensaje .= "<div class='mb-2'><strong>Solicitud creada:</strong> #INT-{$solicitud->id_solicitud_int}</div>";
        $mensaje .= "<div class='mb-2'><strong>Tipo:</strong> " . $tipoLabelInt . "</div>";
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
        // CAMBIO V7 → V10.3.5-fix: Enviar correo al crear solicitud EXT.
        // Mismo fix de alcance que el flujo INT: antes llegaba a TODOS
        // los aprobadores del sistema; ahora solo a los de la planta
        // destino (la responsable de aprobar la entrada del externo) +
        // Admin/Superadmin, o a las plantas involucradas si quedó
        // auto-aprobada (creador Aprobador+), sin el creador.
        // ============================================================
        try {
            $creadorNombre = $user->nombre_completo;
            $nombresExternos = $solicitud->externos->map(fn($d) => $d->externo?->nombre_completo ?? 'N/A')->toArray();

            if ($estado === 'aprobado' && $esAprobadorOSuperior) {
                // --- V10.3.5-fix: auto-aprobada al crearla ---
                $destinatarios = \App\Services\NotificacionService::destinatariosCorreoAutoAprobada($solicitud, $user);
                foreach ($destinatarios as $dest) {
                    if ($dest->email_emp) {
                        \Mail::to($dest->email_emp)->send(new \App\Mail\NotificacionSolicitudMail(
                            $solicitud,
                            $dest->nombre_completo,
                            'Solicitud auto-aprobada - #' . $solicitud->id_solicitud_ext,
                            '✅ Solicitud Auto-Aprobada',
                            "{$creadorNombre} ha generado y aprobado esta solicitud. No requiere revisión adicional.",
                            null,
                            null, // aprobados
                            null, // rechazados
                            $nombresExternos, // personas (neutral)
                            $creadorNombre
                        ));
                    }
                }
            } else {
                // --- V10.3.5-fix: pendiente → planta destino + Admin/Super ---
                $destinatarios = \App\Services\NotificacionService::destinatariosCorreoNuevaPendiente($solicitud);
                foreach ($destinatarios as $dest) {
                    if ($dest->email_emp) {
                        \Mail::to($dest->email_emp)->send(new \App\Mail\NotificacionSolicitudMail(
                            $solicitud,
                            $dest->nombre_completo,
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

    // FIX #13: validar permisos antes de cancelar.
    $esSuperior = $user->isAdmin() || $user->isSuperadmin();
    $esCreador = (int) $solicitud->creador_empleado_id === (int) $user->username;
    $esAprobadorResponsable = $user->isAprobador() && (
        (int) $solicitud->id_planta_origen === (int) $user->planta_id
        || (int) $solicitud->id_planta_destino === (int) $user->planta_id
    );
    if (!$esSuperior && !$esCreador && !$esAprobadorResponsable) {
        abort(403, 'No tiene permisos para cancelar empleados de esta solicitud.');
    }

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

        // FIX #14: validar permisos antes de cancelar.
        $esSuperior = $user->isAdmin() || $user->isSuperadmin();
        $esCreador = (int) $solicitud->creador_empleado_id === (int) $user->username;
        $esAprobadorDestino = $user->isAprobador()
            && (int) $solicitud->id_planta_destino === (int) $user->planta_id;
        if (!$esSuperior && !$esCreador && !$esAprobadorDestino) {
            abort(403, 'No tiene permisos para cancelar personas externas de esta solicitud.');
        }

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
            if (in_array($request->tipo, ['pase_salida', 'interplantas', 'permiso_entrada'])) {
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
            if (in_array($request->tipo, ['pase_salida', 'interplantas', 'permiso_entrada'])) {
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
            if (in_array($request->tipo, ['pase_salida', 'interplantas', 'permiso_entrada'])) {
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

        // FIX 6: Para Pase de Salida, ocultar empleados sin turno asignado.
        // El Pase de Salida calcula las fechas automáticamente desde el turno,
        // así que no puede aplicarse a empleados sin turno.
        // (Para interplantas y otros tipos, se permiten empleados sin turno
        //  con confirmación manual.)
        $tipo = $request->query('tipo');
        if ($tipo === 'pase_salida') {
            $query->whereHas('turno', function ($qT) {
                $qT->whereNotNull('entrada')->whereNotNull('salida');
            });
        }

        // Cargar candidatos (sin límite, ya filtrados por planta en la BD)
        $candidatos = $query->with(['puesto', 'departamento'])
            ->limit(100) // Límite de seguridad razonable
            ->get();

            $resultados = $candidatos->map(function ($e) {

            $estaEnPlanta = \App\Services\PresenciaService::estaDentro(
                (string) $e->id_empleado,
                (int) $e->plantaId()
            );

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
        // V10.3 · CAMBIO #19: se agrega 'permiso_entrada' al catálogo.
        // Alcances por rol (sin cambios): Empleado → su planta; Aprobador
        // → su planta (con auto-aprobación); Admin/Super → cualquier planta.
        return ['pase_salida', 'interplantas', 'permiso_entrada', 'visita', 'contratista'];
    }

    public function descargarCredencialPng($idDetalle)
    {
        // FIX 8: Cargar anfitrión y plantaDestino con eager loading para que la credencial los tenga
        $detalle = \App\Models\SolicitudExternoDetalle::with([
            'solicitudExt.plantaDestino', 
            'solicitudExt.anfitrion.puesto', 
            'externo',
        ])->findOrFail($idDetalle);

        // FIX #15: usar el accessor estado_qr_actual (no el crudo) para que
        // detecte expiración incluso si el cron aún no ha persistido.
        $solicitud = $detalle->solicitudExt;
        if (!$solicitud) {
            return back()->withErrors(['error' => 'La solicitud asociada no existe.']);
        }
        if (!in_array($solicitud->estado_actual, ['aprobado', 'aprobado_parcial'], true)) {
            return back()->withErrors(['error' => 'La solicitud no tiene una autorización vigente.']);
        }
        if ($detalle->estado_qr_actual !== 'autorizado' || !$detalle->codigo_qr) {
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
        
        // FIX #16: un Aprobador solo puede reenviar credenciales de su propia planta.
        $puedeReenviar = ($solicitud->creador_empleado_id === (int) $user->username)
            || $user->isAdmin()
            || $user->isSuperadmin()
            || ($user->isAprobador() && (int) $solicitud->id_planta_destino === (int) $user->planta_id);

        if (!$puedeReenviar) {
            return back()->withErrors(['error' => 'No tiene permisos para reenviar estas credenciales.']);
        }

        // FIX #16: usar estado_actual (accessor) en vez de estado crudo.
        if (!in_array($solicitud->estado_actual, ['aprobado', 'aprobado_parcial'], true)) {
            return back()->withErrors(['error' => 'La solicitud no está aprobada o ya expiró.']);
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
    // FIX 8: Cargar relaciones para que las credenciales tengan anfitrión y planta
    $solicitud->load(['plantaDestino', 'anfitrion.puesto', 'externos.externo']);
    $pdfData = \App\Services\CredencialService::generarPdfTodas($solicitud);
        return response($pdfData, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="credenciales_' . $solicitud->id_solicitud_ext . '.pdf"',
        ]);
    }
}