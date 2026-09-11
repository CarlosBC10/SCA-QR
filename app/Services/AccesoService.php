<?php
// app/Services/AccesoService.php
//
// SERVICIO CENTRAL DE VALIDACIÓN DE ACCESOS (SRS §4.1).
//
// Este servicio NO crea bitácoras (eso lo hace el ScannerController).
// Solo VALIDA y devuelve un array con el resultado. El controlador
// decide qué hacer con ese resultado (crear bitácora, notificar, etc.).
//
// RESPONSABILIDADES:
//   - validarAccesoPlanta(): RN-01 (corporativo), RN-02 (administrativo),
//     RN-03 (operativo). ¿El empleado puede entrar a esta planta?
//   - validarHorario(): anomalías de horario (tarde, día no laboral,
//     sin turno, salida tardía). ¿Requiere justificación del guardia?
//   - tieneInterplantasVigente(): ¿existe un permiso interplantas
//     aprobado y vigente para este empleado hacia esta planta?
//   - tienePermisoParaSalir(): ¿tiene pase_salida o interplantas vigente?
//     (Para que un operativo pueda salir antes de fin de turno.)
//   - sortearRevisionExhaustiva(): F10, 10% de probabilidad.
//
// CAMBIOS V4:
//   - Usa SolicitudInt en lugar de Solicitud (la tabla se dividió).
//   - FKs renombradas: planta_destino_id → id_planta_destino, etc.
//   - ya NO maneja 'bloqueo_manual' (se infiere de usos_manuales_restantes=0).
//   - ya NO maneja 'origen_escaneo' en bitácora (se eliminó esa columna).
//     El control de F11 (1 manual por día) se hace con
//     empleados_ext_sca.fecha_ultimo_manual.
//   - El resultado de validarHorario devuelve 'requiere_justificacion'
//     (bool) para que el ScannerController setee estado='revision_horario'.
//
// ──────────────────────────────────────────────────────────────────────────
// CAMBIOS V10.3 (trazabilidad + permiso_entrada):
//   · CAMBIO #01: NUEVO método obtenerPermisoEntradaVigente() — era
//     LLAMADO por las ramas V10.3 de validarHorario() pero nunca fue
//     definido (bug crítico: "Call to undefined method"). Ahora existe.
//   · CAMBIO #02: fix del hueco "creador-hereda-permiso" en los 3
//     lookups (interplantas vigente/vencido + permiso para salir):
//     el creador solo cuenta como beneficiario si la solicitud es
//     individual (sin lista grupal) o si él está en la lista.
//   · CAMBIO #03/#04: los resultados ahora devuelven 'id_solicitud'
//     (FK lógico a bitacora.id_solicitud) para trazabilidad.
//   · validarHorario(): 4 ramas nuevas de ENTRADA fuera de turno que
//     deniegan con 'entrada_sin_permiso' si no hay permiso_entrada
//     vigente (sin turno, domingo/fin de semana, turno vencido,
//     llegada >30 min temprana → esta última con permiso se autoriza
//     sin justificación; sin permiso se mantiene como hoy: pasa con
//     justificación, decisión D-3 confirmada por el usuario).
//   · 'llegada_temprana' se separa de 'llegada_tardia' (solo etiqueta,
//     el comportamiento no cambia).
// ──────────────────────────────────────────────────────────────────────────

namespace App\Services;

use App\Models\SolicitudInt;
use App\Models\Sitec\Empleado;
use Carbon\Carbon;

class AccesoService
{
    // ============================================================
    // CONSTANTES DE TOLERANCIA DE HORARIO (en segundos)
    // ============================================================
    // Estas tolerancias permiten que un empleado llegue un poco antes
    // o después de su hora sin que se marque como anomalía.
    const TOLERANCIA_ENTRADA_ANTES   = 30 * 60;  // 30 min antes: llegada temprana OK
    const TOLERANCIA_ENTRADA_DESPUES = 15 * 60;  // 15 min después: llegada tardía leve OK
    const TOLERANCIA_SALIDA_DESPUES  = 30 * 60;  // 30 min después: salida tardía leve OK

    // ============================================================
    // RN-01, RN-02, RN-03: VALIDACIÓN DE ACCESO A PLANTA
    // ============================================================
    //
    // ¿El empleado puede entrar a esta planta?
    //
    // Reglas:
    //   - Sin clasificar (tipo_empleado NULL) → DENEGADO (sin_clasificar).
    //     No sabemos qué regla aplicar; debe ir a RRHH.
    //   - Sin planta (id_planta NULL en ext_sca) → DENEGADO (sin_planta).
    //     Algo está mal configurado; debe ir con su jefe.
    //   - Corporativo (RN-01) → AUTORIZADO en cualquier planta.
    //   - Administrativo (RN-02) → propia planta libre; otra requiere
    //     interplantas aprobado vigente.
    //   - Operativo (RN-03) → propia planta en turno; otra requiere
    //     interplantas aprobado vigente.
    //
    // Retorna un array con:
    //   - permitido (bool)
    //   - motivo (string|null): mensaje legible para el guardia/empleado
    //   - motivo_codigo (string|null): código para persistir en bitacora.motivo_denegacion
    //   - planta_origen (int|null): la planta base del empleado
    //   - es_interplantas (bool): si está autorizado por interplantas (para info)

    public function validarAccesoPlanta(Empleado $empleado, int $plantaId, string $tipoEvento = 'entrada'): array
    {
        $tipoEmpleado = $empleado->tipoEmpleado();

        // Caso 1: empleado sin clasificar
        if (!$tipoEmpleado) {
            return [
                'permitido'        => false,
                'motivo'           => 'Empleado sin clasificar. Acuda con Recursos Humanos.',
                'motivo_codigo'    => 'sin_clasificar',
                'planta_origen'    => $empleado->plantaId(),
                'es_interplantas'  => false,
            ];
        }

        // Caso 2: corporativo → autorizado en cualquier planta (RN-01)
        if ($tipoEmpleado === 'corporativo') {
            return [
                'permitido'        => true,
                'motivo'           => null,
                'motivo_codigo'    => null,
                'planta_origen'    => $empleado->plantaId(),
                'es_interplantas'  => false,
            ];
        }

        // Para administrativo y operativo necesitamos la planta base
        $plantaBase = $empleado->plantaId();

        // Caso 3: sin planta configurada
        if (!$plantaBase) {
            return [
                'permitido'        => false,
                'motivo'           => 'Su planta no está configurada. Acuda con su jefe.',
                'motivo_codigo'    => 'sin_planta',
                'planta_origen'    => null,
                'es_interplantas'  => false,
            ];
        }

        // Caso 4: planta propia → autorizado (RN-02 y RN-03)
        if ($plantaBase === $plantaId) {
            return [
                'permitido'        => true,
                'motivo'           => null,
                'motivo_codigo'    => null,
                'planta_origen'    => $plantaBase,
                'es_interplantas'  => false,
            ];
        }

        // Caso 5: planta distinta → requiere interplantas aprobado vigente.
        //
        // FIX interplantas: para ENTRADA, validamos que ahora esté dentro del
        // rango [fecha_inicio, fecha_fin] del permiso. Si llega antes o después,
        // se DENIEGA con el mismo mensaje genérico "Planta no autorizada"
        // (no revelamos detalles del permiso al empleado).
        //
        // Para SALIDA: no validamos rango aquí (la salida se valida en
        // validarHorario()). Solo verificamos que exista un permiso que haya
        // iniciado (para autorizar la salida) — aunque haya vencido, el
        // empleado podría seguir dentro y validarHorario() pedirá justificación.
        if ($tipoEvento === 'entrada') {
            $permiso = $this->obtenerInterplantasVigente($empleado->id_empleado, $plantaId);
            if ($permiso) {
                // Dentro del rango del permiso → autorizado
                return [
                    'permitido'         => true,
                    'motivo'            => null,
                    'motivo_codigo'     => null,
                    'planta_origen'     => $plantaBase,
                    'es_interplantas'   => true,
                    'planta_destino_id' => $plantaId,
                    'id_solicitud'      => (string) $permiso->id_solicitud_int, // V10.3: trazabilidad
                ];
            }
            // No hay permiso vigente (no existe, no inició, o ya venció).
            // Devolvemos el mismo mensaje que alguien sin acceso (Caso 6).
            return [
                'permitido'        => false,
                'motivo'           => 'Planta no autorizada. Diríjase a su planta base.',
                'motivo_codigo'    => 'planta_no_autorizada',
                'planta_origen'    => $plantaBase,
                'es_interplantas'  => false,
            ];
        }

        // tipoEvento === 'salida': buscamos un permiso que haya iniciado
        // (aunque haya vencido). Si existe, autorizamos la salida; el rango
        // se valida en validarHorario() para pedir justificación si venció.
        $permisoVigente = $this->obtenerInterplantasVigente($empleado->id_empleado, $plantaId);
        if ($permisoVigente) {
            return [
                'permitido'         => true,
                'motivo'            => null,
                'motivo_codigo'     => null,
                'planta_origen'     => $plantaBase,
                'es_interplantas'   => true,
                'planta_destino_id' => $plantaId,
                'id_solicitud'      => (string) $permisoVigente->id_solicitud_int, // V10.3
            ];
        }
        // ¿Hay un permiso que ya venció pero el empleado entró cuando estaba vigente?
        // Lo dejamos salir (con justificación en validarHorario). Si no hay NI permiso
        // vigente NI permiso vencido, denegamos (no debería estar en esta planta).
        $permisoVencido = $this->obtenerInterplantasVencidoSinSalir($empleado->id_empleado, $plantaId);
        if ($permisoVencido) {
            return [
                'permitido'         => true,
                'motivo'            => null,
                'motivo_codigo'     => null,
                'planta_origen'     => $plantaBase,
                'es_interplantas'   => true,
                'planta_destino_id' => $plantaId,
                'id_solicitud'      => (string) $permisoVencido->id_solicitud_int, // V10.3
            ];
        }

        // Caso 6: planta distinta sin interplantas → denegado
        return [
            'permitido'        => false,
            'motivo'           => 'Planta no autorizada. Diríjase a su planta base.',
            'motivo_codigo'    => 'planta_no_autorizada',
            'planta_origen'    => $plantaBase,
            'es_interplantas'  => false,
        ];
    }

    // ============================================================
    // VALIDACIÓN DE HORARIO (anomalías)
    // ============================================================
    //
    // ¿Este acceso ocurrió fuera de horario? Si sí, requiere que el
    // guardia seleccione una justificación del catálogo.
    //
    // Reglas:
    //   - Corporativo → sin validación de turno (RN-01).
    //   - Operativo/Administrativo sin turno → anomalía (sin_turno).
    //   - Operativo: trabaja Lunes a Sábado.
    //   - Administrativo: trabaja Lunes a Viernes.
    //   - Día no laboral → anomalía (dia_no_laboral).
    //   - Entrada: solo se valida la PRIMERA entrada del día (llegada a tiempo).
    //     Regresos durante el día no se validan.
    //   - Salida operativa: no puede salir antes de fin de turno,
    //     A MENOS que tenga pase_salida o interplantas vigente.
    //     Si no tiene permiso → DENEGADO (salida_temprana).
    //   - Salida tardía (después de tolerancia) → anomalía (salida_tardia).
    //
    // Retorna un array con:
    //   - permitido (bool): false si se DENIEGA (salida_temprana sin permiso),
    //     true en los demás casos (incluso con anomalía, entra pero justifica).
    //   - requiere_justificacion (bool): true si hay anomalía de horario.
    //     El ScannerController debe crear la bitácora con estado='revision_horario'.
    //   - motivo (string|null): mensaje para mostrar.
    //   - motivo_codigo (string|null): código para bitacora.motivo_denegacion
    //     o para identificar el tipo de anomalía.

    public function validarHorario(Empleado $empleado, string $tipoEvento, bool $esPrimeraEntrada, ?int $plantaDestinoId = null): array
    {
        $tipo = $empleado->tipoEmpleado();

        // Corporativos: sin validación de turno (RN-01)
        if (!in_array($tipo, ['operativo', 'administrativo'])) {
            return [
                'permitido'              => true,
                'requiere_justificacion' => false,
                'motivo'                 => null,
                'motivo_codigo'          => null,
            ];
        }

        // ============================================================
        // FIX interplantas: si el empleado está operando en una planta
        // destino por un permiso interplantas VIGENTE, NO se valida el
        // turno normal. El rango del permiso ya fue validado en
        // validarAccesoPlanta() para entradas. Para salidas, si sale
        // dentro del rango → OK; si sale después de fecha_fin → justificar.
        // ============================================================
        if ($plantaDestinoId !== null && $plantaDestinoId !== $empleado->plantaId()) {
            $permisoVigente = $this->obtenerInterplantasVigente($empleado->id_empleado, $plantaDestinoId);
            if ($permisoVigente) {
                // Hay permiso vigente en esta planta destino.
                // - Entrada: ya se validó el rango en validarAccesoPlanta → OK.
                // - Salida: si now() <= fecha_fin → OK; si now() > fecha_fin no
                //   aplicaría porque el permiso YA NO sería vigente.
                return [
                    'permitido'              => true,
                    'requiere_justificacion' => false,
                    'motivo'                 => null,
                    'motivo_codigo'          => null,
                    'id_solicitud'           => (string) $permisoVigente->id_solicitud_int, // V10.3
                ];
            }

            // ¿Hay un permiso que ya VENCIÓ pero el empleado entró cuando estaba vigente?
            // (Caso: Juan entró a Jardín 3pm, permiso vencía 5pm, son las 6pm y Juan sale).
            // Lo dejamos salir pero con justificación de salida tardía.
            if ($tipoEvento === 'salida') {
                $permisoVencido = $this->obtenerInterplantasVencidoSinSalir($empleado->id_empleado, $plantaDestinoId);
                if ($permisoVencido) {
                    return [
                        'permitido'              => true,
                        'requiere_justificacion' => true,
                        'motivo'                 => 'Salida tardía. Su permiso interplantas venció, justifique su salida.',
                        'motivo_codigo'          => 'salida_tardia',
                        'id_solicitud'           => (string) $permisoVencido->id_solicitud_int, // V10.3
                    ];
                }
            }
            // Si es entrada y no hay permiso vigente, validarAccesoPlanta() ya
            // lo denegó. Llegar aquí con entrada no debería ocurrir, pero por
            // defensa dejamos que continúe la validación normal abajo.
        }

        // Sin turno asignado → anomalía (todos deben tener horario)
        if (!$empleado->turno || !$empleado->turno->entrada || !$empleado->turno->salida) {
            // ============================================================
            // V10.3 (permiso_entrada): una ENTRADA sin turno es un acceso
            // fuera de turno → requiere permiso_entrada vigente; si no lo
            // tiene, se DENIEGA (antes pasaba con justificación).
            // Las SALIDAS siguen IGUAL: siempre permitidas + justificación
            // (a nadie se le niega la salida por no tener turno).
            // ============================================================
            if ($tipoEvento === 'entrada') {
                $permisoEntrada = $this->obtenerPermisoEntradaVigente($empleado->id_empleado, $plantaDestinoId);
                if ($permisoEntrada) {
                    return [
                        'permitido'              => true,
                        'requiere_justificacion' => false,
                        'motivo'                 => null,
                        'motivo_codigo'          => null,
                        'id_solicitud'           => (string) $permisoEntrada->id_solicitud_int,
                    ];
                }
                return [
                    'permitido'              => false,
                    'requiere_justificacion' => false,
                    'motivo'                 => 'Sin turno asignado. Entrada denegada: requiere permiso de entrada vigente. Acuda a RR.HH. para que le asignen turno.',
                    'motivo_codigo'          => 'entrada_sin_permiso',
                ];
            }
            return [
                'permitido'              => true,
                'requiere_justificacion' => true,
                'motivo'                 => 'Sin turno asignado. Justifique su acceso.',
                'motivo_codigo'          => 'sin_turno',
            ];
        }

        // Validar día de la semana
        // dayOfWeekIso: 1=Lun, 2=Mar, ..., 6=Sáb, 7=Dom
        $dia = now()->dayOfWeekIso;

        if ($tipo === 'operativo') {
            // Operativos: Lunes a Sábado (1-6)
            if ($dia > 6) {
                // ========================================================
                // V10.3 (permiso_entrada): domingo → ENTRADA requiere
                // permiso_entrada vigente; si no, se DENIEGA.
                // SALIDA → igual que hoy (permitida + justificación).
                // ========================================================
                if ($tipoEvento === 'entrada') {
                    $permisoEntrada = $this->obtenerPermisoEntradaVigente($empleado->id_empleado, $plantaDestinoId);
                    if ($permisoEntrada) {
                        return [
                            'permitido'              => true,
                            'requiere_justificacion' => false,
                            'motivo'                 => null,
                            'motivo_codigo'          => null,
                            'id_solicitud'           => (string) $permisoEntrada->id_solicitud_int,
                        ];
                    }
                    return [
                        'permitido'              => false,
                        'requiere_justificacion' => false,
                        'motivo'                 => 'Domingo no laboral. Entrada denegada: requiere permiso de entrada vigente.',
                        'motivo_codigo'          => 'entrada_sin_permiso',
                    ];
                }
                return [
                    'permitido'              => true,
                    'requiere_justificacion' => true,
                    'motivo'                 => 'Domingo no laboral. Justifique su acceso.',
                    'motivo_codigo'          => 'dia_no_laboral',
                ];
            }
        } else {
            // Administrativos: Lunes a Viernes (1-5)
            if ($dia > 5) {
                // ========================================================
                // V10.3 (permiso_entrada): fin de semana → ENTRADA requiere
                // permiso_entrada vigente; si no, se DENIEGA.
                // SALIDA → igual que hoy (permitida + justificación).
                // ========================================================
                if ($tipoEvento === 'entrada') {
                    $permisoEntrada = $this->obtenerPermisoEntradaVigente($empleado->id_empleado, $plantaDestinoId);
                    if ($permisoEntrada) {
                        return [
                            'permitido'              => true,
                            'requiere_justificacion' => false,
                            'motivo'                 => null,
                            'motivo_codigo'          => null,
                            'id_solicitud'           => (string) $permisoEntrada->id_solicitud_int,
                        ];
                    }
                    return [
                        'permitido'              => false,
                        'requiere_justificacion' => false,
                        'motivo'                 => 'Fin de semana no laboral. Entrada denegada: requiere permiso de entrada vigente.',
                        'motivo_codigo'          => 'entrada_sin_permiso',
                    ];
                }
                return [
                    'permitido'              => true,
                    'requiere_justificacion' => true,
                    'motivo'                 => 'Fin de semana no laboral. Justifique su acceso.',
                    'motivo_codigo'          => 'dia_no_laboral',
                ];
            }
        }

        // Convertir horas a timestamps para comparación
        $ahoraTs    = strtotime(now()->format('H:i:s'));
        $entradaTs  = strtotime($empleado->turno->entrada);
        $salidaTs   = strtotime($empleado->turno->salida);

        // ============================================================
        // FIX turnos nocturnos (CORREGIDO Y COMPLETO):
        // Si el turno cruza medianoche (ej: 22:00-06:00), entradaTs > salidaTs.
        // Las comparaciones matemáticas simples fallan porque "ahora" puede
        // estar antes o después de medianoche.
        //
        // Hay 2 escenarios dentro de un turno nocturno:
        //
        //   Escenario A: Después de medianoche (ej: 02:00am)
        //     - ahoraTs (7200) < entradaTs (79200)
        //     - Necesitamos ajustar AMBOS: ahoraTs y salidaTs sumando 86400.
        //     - PERO solo si NO es primera entrada (si el empleado llega a las
        //       21:55 a un turno de 22:00, ahoraTs < entradaTs pero NO debemos
        //       ajustar porque es antes del turno, no después de medianoche).
        //
        //   Escenario B: Antes de medianoche (ej: 23:00)
        //     - ahoraTs (82800) >= entradaTs (79200)
        //     - Necesitamos ajustar SOLO salidaTs sumando 86400, para que
        //       la comparación "ahoraTs < salidaTs" funcione correctamente
        //       (82800 < 86400+21600=108000 → "durante el turno" ✅).
        //       Sin este ajuste, salidaTs=21600 < ahoraTs=82800 → "salida_tardia" ❌
        //
        // Casos de prueba (turno 22:00-06:00):
        //   1. Primera entrada a las 21:55 (5 min antes):
        //      - No se ajusta nada (es primera entrada).
        //      - entradaMin=75600, entradaMax=80100, ahoraTs=78900.
        //      - 75600 <= 78900 <= 80100 → "dentro de tolerancia" ✅
        //
        //   2. Salida a las 23:00 (antes de medianoche, durante turno):
        //      - Escenario B: salidaTs += 86400 → salidaTs=108000.
        //      - salidaMax=109800. ahoraTs=82800 < 108000 → "durante el turno" ✅
        //
        //   3. Salida a las 01:30 (después de medianoche, durante turno):
        //      - Escenario A (no es primera entrada): ambos se ajustan.
        //      - ahoraTs=91800, salidaTs=108000. 91800 < 108000 → "durante el turno" ✅
        //
        //   4. Salida a las 07:00 (después de medianoche, turno vencido):
        //      - Escenario A: ahoraTs=111600, salidaMax=109800.
        //      - 111600 > 109800 → "salida_tardia" ✅
        // ============================================================
        $esTurnoNocturno = $entradaTs > $salidaTs;
        if ($esTurnoNocturno) {
            if ($ahoraTs < $entradaTs && !$esPrimeraEntrada) {
                // Escenario A: Después de medianoche (y NO es primera entrada).
                // Sumar 86400 a ambos para que las comparaciones de salida funcionen.
                $ahoraTs  += 86400;
                $salidaTs += 86400;
            } else {
                // Escenario B: Antes de medianoche (primera mitad del turno),
                // O es primera entrada (llegada temprana, no ajustar ahoraTs).
                // Solo ajustar salidaTs para que la comparación de salida funcione.
                $salidaTs += 86400;
            }
        }

        $entradaMin = $entradaTs - self::TOLERANCIA_ENTRADA_ANTES;    // ej. 5:30
        $entradaMax = $entradaTs + self::TOLERANCIA_ENTRADA_DESPUES;  // ej. 6:15
        $salidaMax  = $salidaTs + self::TOLERANCIA_SALIDA_DESPUES;    // ej. 14:30 (o 06:30+86400 si nocturno)

        // ============================================================
        // ENTRADA
        // ============================================================
        if ($tipoEvento === 'entrada') {
            // ============================================================
            // V10.3 (permiso_entrada): entrada DESPUÉS del turno + tolerancia
            // (ahoraTs > salidaMax) = fuera de turno → requiere permiso
            // vigente; si no lo tiene → DENEGADO (antes pasaba con
            // justificación). Aplica a primera entrada Y regresos.
            // ============================================================
            if ($ahoraTs > $salidaMax) {
                $permisoEntrada = $this->obtenerPermisoEntradaVigente($empleado->id_empleado, $plantaDestinoId);
                if ($permisoEntrada) {
                    return [
                        'permitido'              => true,
                        'requiere_justificacion' => false,
                        'motivo'                 => null,
                        'motivo_codigo'          => null,
                        'id_solicitud'           => (string) $permisoEntrada->id_solicitud_int,
                    ];
                }
                return [
                    'permitido'              => false,
                    'requiere_justificacion' => false,
                    'motivo'                 => 'Entrada fuera de turno. Denegada: requiere permiso de entrada vigente.',
                    'motivo_codigo'          => 'entrada_sin_permiso',
                ];
            }

            // Solo validar la PRIMERA entrada del día (llegada a tiempo).
            // Regresos durante el día no se validan (el empleado ya llegó).
            if ($esPrimeraEntrada) {
                // ========================================================
                // V10.3: llegada MÁS de 30 min temprano (antes de entradaMin).
                // Con permiso_entrada vigente → autorizado POR PERMISO
                // (precedencia: el permiso es el mecanismo autorizador, sin
                // justificación adicional).
                // V10.3.2-fix (restaura el fix V10.3.1, hallazgo 2 del
                // usuario / caso B8): sin permiso → DENEGADO con
                // entrada_sin_permiso. La revisión de ChatGPT se basó en el
                // zip previo a V10.3.1 y revirtió esto a "autorizado con
                // justificación", reabriendo la puerta de entrada fuera de
                // turno sin permiso.
                // ========================================================
                if ($ahoraTs < $entradaMin) {
                    $permisoEntrada = $this->obtenerPermisoEntradaVigente($empleado->id_empleado, $plantaDestinoId);
                    if ($permisoEntrada) {
                        return [
                            'permitido'              => true,
                            'requiere_justificacion' => false,
                            'motivo'                 => null,
                            'motivo_codigo'          => null,
                            'id_solicitud'           => (string) $permisoEntrada->id_solicitud_int,
                        ];
                    }
                    return [
                        'permitido'              => false,
                        'requiere_justificacion' => false,
                        'motivo'                 => 'Llegada fuera de turno (antes de hora). Denegada: requiere permiso de entrada vigente.',
                        'motivo_codigo'          => 'entrada_sin_permiso', // V10.3.2-fix
                    ];
                }
                // Tarde de más de 15 min, pero dentro del día laboral
                // (todavía antes de salidaMax) → pasa con justificación.
                if ($ahoraTs > $entradaMax) {
                    return [
                        'permitido'              => true,
                        'requiere_justificacion' => true,
                        'motivo'                 => 'Llegada fuera de horario. Justifique su acceso.',
                        'motivo_codigo'          => 'llegada_tardia',
                    ];
                }
            }
            // Entrada dentro de horario (o regreso) → OK
            return [
                'permitido'              => true,
                'requiere_justificacion' => false,
                'motivo'                 => null,
                'motivo_codigo'          => null,
            ];
        }

        // ============================================================
        // SALIDA
        // ============================================================
        if ($tipo === 'operativo') {
            // CAMBIO V7 (fix): si la hora actual es ANTES de que inicie el
            // turno (ahoraTs < entradaTs), el empleado puede salir, pero
            // requiere justificación (es una salida fuera de horario).
            // Caso: empleado que entró temprano (ej. 1am) con turno 6am-2pm
            // y quiere salir a las 5am (antes de que inicie el turno).
            // Antes el sistema lo denegaba con "salida_temprana" porque solo
            // comparaba con salidaTs. Ahora: si está antes del turno, permitir
            // pero con justificación (el guardia valida por qué sale temprano).
            if ($ahoraTs < $entradaTs) {
                // ¿Tiene permiso de pase_salida o interplantas? Si sí, no justifica.
                // FIX Bug 6: Pasar $plantaDestinoId para filtrar permisos por planta
                // V10.3 · CAMBIO #04: usamos obtenerPermisoParaSalir() (devuelve el
                // permiso completo) en vez del wrapper booleano, para poder inyectar
                // id_solicitud en la bitácora (trazabilidad de qué pase autorizó).
                $permisoSalida = $this->obtenerPermisoParaSalir($empleado->id_empleado, $plantaDestinoId);
                if ($permisoSalida) {
                    return [
                        'permitido'              => true,
                        'requiere_justificacion' => false,
                        'motivo'                 => null,
                        'motivo_codigo'          => null,
                        'id_solicitud'           => $permisoSalida['id_solicitud'] ?? null, // V10.3: trazabilidad
                    ];
                }
                // V10.3-FIX: si existe un permiso interno ya vencido para esta
                // persona/planta, la salida nunca se bloquea; se permite con
                // justificación y se conserva la trazabilidad del permiso.
                $permisoVencido = $this->obtenerPermisoParaSalirVencido($empleado->id_empleado, $plantaDestinoId);
                if ($permisoVencido) {
                    return [
                        'permitido'              => true,
                        'requiere_justificacion' => true,
                        'motivo'                 => 'Salida fuera de horario. Existe un permiso interno vencido; justifique su salida.',
                        'motivo_codigo'          => 'salida_antes_turno',
                        'id_solicitud'           => $permisoVencido['id_solicitud'],
                    ];
                }

                // Sin permiso vigente/vencido: permite salir pero con justificación.
                return [
                    'permitido'              => true,
                    'requiere_justificacion' => true,
                    'motivo'                 => 'Salida antes de su turno. Justifique su acceso.',
                    'motivo_codigo'          => 'salida_antes_turno',
                ];
            }

            // Operativo: no puede salir antes de fin de turno (durante el turno)...
            if ($ahoraTs < $salidaTs) {
                // ...A MENOS que tenga pase_salida o interplantas vigente
                // FIX Bug 6: Pasar $plantaDestinoId para filtrar permisos por planta
                // V10.3 · CAMBIO #04: igual que arriba, obtenemos el permiso
                // completo para vincular id_solicitud en la bitácora.
                $permisoSalida = $this->obtenerPermisoParaSalir($empleado->id_empleado, $plantaDestinoId);
                if ($permisoSalida) {
                    return [
                        'permitido'              => true,
                        'requiere_justificacion' => false,
                        'motivo'                 => null,
                        'motivo_codigo'          => null,
                        'id_solicitud'           => $permisoSalida['id_solicitud'] ?? null, // V10.3: trazabilidad
                    ];
                }
                // V10.3-FIX: un permiso interno vencido no bloquea la salida.
                $permisoVencido = $this->obtenerPermisoParaSalirVencido($empleado->id_empleado, $plantaDestinoId);
                if ($permisoVencido) {
                    return [
                        'permitido'              => true,
                        'requiere_justificacion' => true,
                        'motivo'                 => 'El permiso interno ya venció. Justifique su salida.',
                        'motivo_codigo'          => 'salida_temprana',
                        'id_solicitud'           => $permisoVencido['id_solicitud'],
                    ];
                }

                // Sin permiso → DENEGADO: regla original de Operativo sin pase.
                return [
                    'permitido'              => false,
                    'requiere_justificacion' => false,
                    'motivo'                 => 'No puede salir antes de su hora de salida (' . $empleado->turno->salida . ').',
                    'motivo_codigo'          => 'salida_temprana',
                ];
            }
            // Después de la tolerancia → anomalía (puede salir, pero justifica)
            if ($ahoraTs > $salidaMax) {
                return [
                    'permitido'              => true,
                    'requiere_justificacion' => true,
                    'motivo'                 => 'Salida tardía. Justifique su acceso.',
                    'motivo_codigo'          => 'salida_tardia',
                ];
            }
            // Salida normal (dentro de tolerancia) → OK
            return [
                'permitido'              => true,
                'requiere_justificacion' => false,
                'motivo'                 => null,
                'motivo_codigo'          => null,
            ];
        }

        // Administrativo: puede salir durante el turno libremente
        if ($ahoraTs > $salidaMax) {
            return [
                'permitido'              => true,
                'requiere_justificacion' => true,
                'motivo'                 => 'Salida tardía. Justifique su acceso.',
                'motivo_codigo'          => 'salida_tardia',
            ];
        }

        // Salida administrativa normal → OK
        return [
            'permitido'              => true,
            'requiere_justificacion' => false,
            'motivo'                 => null,
            'motivo_codigo'          => null,
        ];
    }

    // ============================================================
    // INTERPLANTAS VIGENTE
    // ============================================================
    //
    // ¿Existe un permiso interplantas aprobado y vigente para este
    // empleado hacia la planta destino?
    //
    // Busca en solicitudes_int donde:
    //   - tipo = 'interplantas'
    //   - estado IN ('aprobado', 'aprobado_parcial')
    //     (aprobado_parcial queda en el enum pero la lógica actual es
    //      todos-o-nada; lo incluimos por compatibilidad futura)
    //   - id_planta_destino = $plantaDestinoId
    //   - fecha_inicio <= ahora <= fecha_fin
    //   - El empleado es el creador O está en solicitud_empleados con estado='aprobado'

    public function tieneInterplantasVigente(int $empleadoId, int $plantaDestinoId): bool
    {
        return $this->obtenerInterplantasVigente($empleadoId, $plantaDestinoId) !== null;
    }

    /**
     * FIX interplantas: devuelve el modelo SolicitudInt vigente hacia la planta
     * destino, o null si no existe. A diferencia de tieneInterplantasVigente()
     * (que solo devuelve bool), este método entrega las fechas del permiso
     * (fecha_inicio, fecha_fin) para que el scanner pueda:
     *   - Denegar la entrada si llega antes de fecha_inicio o después de fecha_fin.
     *   - Justificar la salida si sale después de fecha_fin (permiso vencido).
     *
     * Criterio de "vigente": fecha_inicio <= ahora <= fecha_fin.
     * Si el permiso ya venció (fecha_fin < ahora), NO se devuelve aquí.
     * Para saber si hubo un permiso que ya venció (ej: para permitir la salida
     * tardía), usar obtenerInterplantasVencidoSinSalir() abajo.
     *
     * @return SolicitudInt|null
     */
    public function obtenerInterplantasVigente(int $empleadoId, int $plantaDestinoId): ?SolicitudInt
    {
        $ahora = Carbon::now();

        return SolicitudInt::where('tipo', 'interplantas')
            ->whereIn('estado', ['aprobado', 'aprobado_parcial'])
            ->where('id_planta_destino', $plantaDestinoId)
            ->where('fecha_inicio', '<=', $ahora)
            ->where('fecha_fin', '>=', $ahora)
            ->where(function ($q) use ($empleadoId) {
                // ────────────────────────────────────────────────────────
                // V10.3 · CAMBIO #02 (hueco "creador-hereda-permiso"):
                // El creador SOLO cuenta como beneficiario cuando la
                // solicitud es INDIVIDUAL (sin lista grupal → él es el
                // beneficiario implícito) o cuando él MISMO aparece en
                // la lista grupal. Así, quien crea una solicitud "solo
                // para otras personas" ya NO hereda el permiso por el
                // simple hecho de ser el creador (riesgo crítico con
                // permiso_entrada: un aprobador que crea permisos para
                // su gente ya no se otorga entrada a sí mismo).
                // Semántica idéntica a SolicitudInt::incluyeEmpleado().
                // ────────────────────────────────────────────────────────
                $q->where(function ($qIndividual) use ($empleadoId) {
                    // Caso A: solicitud individual (creador = beneficiario)
                    $qIndividual->where('creador_empleado_id', $empleadoId)
                                ->whereDoesntHave('empleados');
                })
                  // Caso B: el empleado está en la lista grupal con estado 'aprobado'
                  ->orWhereHas('empleados', function ($q2) use ($empleadoId) {
                      $q2->where('empleado_id', $empleadoId)
                         ->where('estado', 'aprobado');
                  });
            })
            ->orderBy('fecha_fin', 'asc')  // si hay varios, el que vence primero
            ->first();
    }

    /**
     * FIX interplantas: devuelve un permiso interplantas APROBADO cuyo rango
     * ya inició (fecha_inicio <= ahora) pero que ya VENCIÓ o está por vencer.
     * Se usa para el caso de SALIDA TARDÍA: el empleado entró dentro del rango,
     * pero sale después de fecha_fin. Necesitamos saber que hubo un permiso
     * válido para dejarlo salir (con justificación), en vez de denegar.
     *
     * Busca permisos donde:
     *   - estado IN ('aprobado', 'aprobado_parcial', 'expirado')
     *     (FIX: incluir 'expirado' porque el accessor lazy muta el estado)
     *   - fecha_inicio <= ahora (ya había iniciado)
     *   - fecha_fin < ahora (ya venció)
     *   - El empleado está incluido (creador o en lista grupal aprobada)
     *
     * @return SolicitudInt|null
     */
    public function obtenerInterplantasVencidoSinSalir(int $empleadoId, int $plantaDestinoId): ?SolicitudInt
    {
        $ahora = Carbon::now();

        // FIX: incluir 'expirado' porque el accessor lazy de SolicitudInt muta
        // el estado a 'expirado' cuando fecha_fin < now(). Si no lo incluimos,
        // no encontramos permisos que ya vencieron (ej: empleado entró a las 10:30am
        // con permiso 10am-11am, a las 11:30am quiere salir pero el permiso ya
        // está 'expirado' en BD).
        return SolicitudInt::where('tipo', 'interplantas')
            ->whereIn('estado', ['aprobado', 'aprobado_parcial', 'expirado'])
            ->where('id_planta_destino', $plantaDestinoId)
            ->where('fecha_inicio', '<=', $ahora)
            ->where('fecha_fin', '<', $ahora)  // ya venció
            ->where(function ($q) use ($empleadoId) {
                // V10.3 · CAMBIO #02: mismo fix del hueco creador-hereda.
                $q->where(function ($qIndividual) use ($empleadoId) {
                    $qIndividual->where('creador_empleado_id', $empleadoId)
                                ->whereDoesntHave('empleados');
                })
                  ->orWhereHas('empleados', function ($q2) use ($empleadoId) {
                      // FIX: incluir 'expirado' porque el accessor lazy muta los
                      // SolicitudEmpleado a 'expirado' cuando la solicitud vence.
                      $q2->where('empleado_id', $empleadoId)
                         ->whereIn('estado', ['aprobado', 'expirado']);
                  });
            })
            ->orderBy('fecha_fin', 'desc')  // el más reciente que venció
            ->first();
    }

    // ============================================================
    // V10.3 · CAMBIO #01: PERMISO DE ENTRADA VIGENTE (nuevo)
    // ============================================================
    //
    // ¿El empleado tiene un permiso_entrada aprobado y vigente que
    // autorice su ENTRADA a esta planta fuera de turno?
    //
    // ⚠️ HISTORIAL DEL BUG: las ramas V10.3 de validarHorario() ya
    // llamaban a este método, pero la sesión de implementación se
    // cortó ANTES de escribirlo. Tal cual estaba, cualquier escaneo
    // de entrada fuera de turno lanzaba "Call to undefined method
    // App\Services\AccesoService::obtenerPermisoEntradaVigente()".
    // Este método lo corrige de raíz.
    //
    // Escenarios que lo consultan (los 4 resueltos en validarHorario):
    //   1. Entrada SIN turno asignado.
    //   2. Entrada en día no laboral (domingo operativo / sábado-domingo
    //      administrativo).
    //   3. Entrada DESPUÉS del turno + tolerancia de 30 min (turno vencido).
    //   4. Llegada MÁS de 30 min temprana (autoriza sin justificación).
    //
    // Reglas:
    //   - tipo = 'permiso_entrada' (valor NUEVO del enum solicitudes_int.tipo,
    //     agregado con el Script 2 de la Etapa 1 de BD).
    //   - estado IN ('aprobado', 'aprobado_parcial'): solo permisos ya
    //     aprobados autorizan. Los pendientes* NO autorizan entrada.
    //     (Las vencidas quedan fuera por el filtro de fechas; no hace
    //     falta incluir 'expirado' aquí porque no queremos permisos
    //     vencidos — a diferencia de la salida, que sí se permite con
    //     permiso vencido + justificación.)
    //   - fecha_inicio <= ahora <= fecha_fin (vigencia exacta).
    //   - Planta: en permiso_entrada SIEMPRE origen = destino = planta
    //     del beneficiario (así lo fija SolicitudController::store),
    //     por eso filtramos por id_planta_destino. Si $plantaId llega
    //     NULL (llamada sin contexto de planta), no se filtra por planta.
    //   - Beneficiario (mismo criterio del CAMBIO #02): solicitud
    //     individual (creador sin lista grupal) o integrante aprobado
    //     de la lista grupal.
    //
    // Devuelve el modelo SolicitudInt (para que el scanner enlace
    // bitacora.id_solicitud) o null si no hay permiso vigente.

    public function obtenerPermisoEntradaVigente(int $empleadoId, ?int $plantaId = null): ?SolicitudInt
    {
        $ahora = Carbon::now();

        $query = SolicitudInt::where('tipo', 'permiso_entrada')
            ->whereIn('estado', ['aprobado', 'aprobado_parcial'])
            ->where('fecha_inicio', '<=', $ahora)
            ->where('fecha_fin', '>=', $ahora);

        // Filtrar por la planta donde ocurre el escaneo (si se conoce).
        if ($plantaId !== null) {
            $query->where('id_planta_destino', $plantaId);
        }

        return $query
            ->where(function ($q) use ($empleadoId) {
                // V10.3 · CAMBIO #02: el creador solo cuenta si la solicitud
                // es individual (sin lista grupal) o si él está en la lista.
                $q->where(function ($qIndividual) use ($empleadoId) {
                    $qIndividual->where('creador_empleado_id', $empleadoId)
                                ->whereDoesntHave('empleados');
                })
                  ->orWhereHas('empleados', function ($q2) use ($empleadoId) {
                      $q2->where('empleado_id', $empleadoId)
                         // 'aprobado' = aprobado total; 'aprobado_origen' se
                         // incluye por defensa (en permiso_entrada la creación
                         // marca 'aprobado', pero así cubrimos cualquier flujo).
                         ->whereIn('estado', ['aprobado', 'aprobado_origen']);
                  });
            })
            ->orderBy('fecha_fin', 'asc')  // si hay varios, el que vence primero
            ->first();
    }

    // ============================================================
    // V10.3-FIX: PERMISO INTERNO VENCIDO PARA SALIDA
    // ============================================================
    // Una salida nunca se bloquea por un permiso interno que ya venció.
    // Devuelve el permiso más reciente compatible para que el scanner pueda
    // conservar id_solicitud en la bitácora.
    public function obtenerPermisoParaSalirVencido(int $empleadoId, ?int $plantaId = null): ?array
    {
        $ahora = Carbon::now();

        $query = SolicitudInt::whereIn('tipo', ['pase_salida', 'interplantas'])
            ->whereIn('estado', ['aprobado', 'aprobado_parcial', 'expirado'])
            ->where('fecha_inicio', '<=', $ahora)
            ->where('fecha_fin', '<', $ahora);

        if ($plantaId !== null) {
            $query->where(function ($q) use ($plantaId) {
                $q->where(function ($q2) use ($plantaId) {
                    $q2->where('tipo', 'pase_salida')
                        ->where('id_planta_origen', $plantaId);
                })->orWhere(function ($q2) use ($plantaId) {
                    $q2->where('tipo', 'interplantas')
                        ->where(function ($q3) use ($plantaId) {
                            $q3->where('id_planta_origen', $plantaId)
                                ->orWhere('id_planta_destino', $plantaId);
                        });
                });
            });
        }

        $solicitud = $query
            ->where(function ($q) use ($empleadoId) {
                $q->where(function ($individual) use ($empleadoId) {
                    $individual->where('creador_empleado_id', $empleadoId)
                        ->whereDoesntHave('empleados');
                })->orWhereHas('empleados', function ($se) use ($empleadoId) {
                    $se->where('empleado_id', $empleadoId)
                        ->whereIn('estado', ['aprobado', 'expirado']);
                });
            })
            ->orderByDesc('fecha_fin')
            ->first();

        if (!$solicitud) {
            return null;
        }

        return [
            'tipo'              => $solicitud->tipo,
            'planta_destino_id' => $solicitud->id_planta_destino,
            'fecha_inicio'      => $solicitud->fecha_inicio,
            'fecha_fin'         => $solicitud->fecha_fin,
            'id_solicitud'      => (string) $solicitud->id_solicitud_int,
        ];
    }

    // ============================================================
    // PERMISO PARA SALIR (pase_salida o interplantas vigente)
    // ============================================================
    //
    // ¿El empleado tiene un permiso vigente que le permita salir
    // durante su turno?
    //
    //   - pase_salida: el empleado se ausenta y se va (termina su día).
    //   - interplantas: va a otra planta (necesita salir de su planta actual).
    //
    // Ambos deben estar aprobados y vigentes (fecha_inicio <= ahora <= fecha_fin).

    /**
     * ¿El empleado tiene un permiso vigente que le permita salir
     * durante su turno?
     *
     *   - pase_salida: el empleado se ausenta y se va (termina su día).
     *   - interplantas: va a otra planta (necesita salir de su planta actual).
     *
     * CAMBIO V7 (N2): ahora devuelve un ARRAY con el tipo de permiso y
     * la planta destino, para que el ScannerController pueda armar el
     * mensaje correcto ("Salida autorizada con permiso de PASE DE SALIDA"
     * o "Salida autorizada con permiso INTERPLANTAS hacia Partidas").
     *
     * @return array|null  ['tipo' => 'pase_salida'|'interplantas', 'planta_destino_id' => int|null]
     *                     null si no tiene permiso vigente.
     */
    /**
     * FIX Bug 6: Agregado parámetro opcional $plantaId para filtrar permisos
     * por la planta donde ocurre el escaneo.
     *
     * ¿Por qué? Sin este filtro, si un empleado tiene múltiples interplantas
     * vigentes (ej: A→B y A→C), el método devuelve el primero que encuentra
     * (ordenado por fecha_fin ASC), que puede no ser el correcto.
     *
     * Impacto:
     * - Cosmético: el mensaje del kiosko indicará la planta destino correcta.
     * - Seguridad: en validarHorario(), solo permisos de la planta actual
     *   contarán para autorizar salida temprana sin justificación.
     *
     * El filtro OR cubre:
     * - Pase de Salida (origen = destino = planta base del empleado)
     * - Interplantas donde el empleado sale DESDE la planta actual (origen)
     * - Interplantas donde el empleado sale DE la planta destino (destino)
     *
     * @param int $empleadoId
     * @param int|null $plantaId Planta del escaneo. Si es null, no filtra (compatibilidad).
     * @return array|null
     */
    public function obtenerPermisoParaSalir(int $empleadoId, ?int $plantaId = null): ?array
    {
        $ahora = Carbon::now();

        $query = SolicitudInt::whereIn('tipo', ['pase_salida', 'interplantas'])
            ->whereIn('estado', ['aprobado', 'aprobado_parcial'])
            ->where('fecha_inicio', '<=', $ahora)
            ->where('fecha_fin', '>=', $ahora);

        // FIX Bug 6: Filtrar por planta del escaneo si se proporciona.
        // Usamos closure para que el OR no contamine las demás cláusulas WHERE.
        // Patrón consistente con SolicitudController.php:1376.
        if ($plantaId !== null) {
            $query->where(function ($q) use ($plantaId) {
                $q->where('id_planta_origen', $plantaId)
                  ->orWhere('id_planta_destino', $plantaId);
            });
        }

        $solicitud = $query
            ->where(function ($q) use ($empleadoId) {
                // V10.3 · CAMBIO #02: mismo fix del hueco creador-hereda
                // (ver obtenerInterplantasVigente). El creador solo cuenta
                // si es individual o está en la lista grupal.
                $q->where(function ($qIndividual) use ($empleadoId) {
                    $qIndividual->where('creador_empleado_id', $empleadoId)
                                ->whereDoesntHave('empleados');
                })
                  ->orWhereHas('empleados', function ($q2) use ($empleadoId) {
                      $q2->where('empleado_id', $empleadoId)
                         ->where('estado', 'aprobado');
                  });
            })
            ->orderBy('fecha_fin', 'asc')  // si hay varios, el que vence primero
            ->first();

        if (!$solicitud) {
            return null;
        }

        return [
            'tipo'              => $solicitud->tipo,           // 'pase_salida' | 'interplantas'
            'planta_destino_id' => $solicitud->id_planta_destino,
            'fecha_inicio'      => $solicitud->fecha_inicio,   // FIX interplantas: para validar rango
            'fecha_fin'         => $solicitud->fecha_fin,      // FIX interplantas: para validar salida tardía
            // V10.3 · CAMBIO #03: id de la solicitud para que el scanner
            // lo inyecte en bitacora.id_solicitud (trazabilidad).
            'id_solicitud'      => (string) $solicitud->id_solicitud_int,
        ];
    }

    /**
     * Wrapper de compatibilidad: devuelve true/false si tiene permiso.
     * Mantiene el nombre viejo por si algo más lo llama.
     */
    /**
     * FIX Bug 6: Wrapper actualizado para propagar $plantaId.
     * Sin esto, el bug persistiría en validarHorario() que usa este wrapper.
     */
    public function tienePermisoParaSalir(int $empleadoId, ?int $plantaId = null): bool
    {
        return $this->obtenerPermisoParaSalir($empleadoId, $plantaId) !== null;
    }

    // ============================================================
    // F10: REVISIÓN EXHAUSTIVA ALEATORIA
    // ============================================================
    //
    // El SRS F10 indica que el 10% de los accesos de empleados
    // operativo/administrativo deben ser revisados físicamente por
    // el guardia. Este método sortea esa probabilidad.
    //
    // La lista de exentos y la probabilidad se configuran en
    // config/sca_qr.php ('exentos_revision' y 'probabilidad_revision').
    //
    // Retorna true si el acceso debe ser revisado.

    public function sortearRevisionExhaustiva(Empleado $empleado): bool
    {
        $tipoEmp = $empleado->tipoEmpleado();

        // Solo aplica a operativo y administrativo (NO corporativo)
        if (!in_array($tipoEmp, ['operativo', 'administrativo'])) {
            return false;
        }

        // Empleados exentos (ej. directivos que siempre pasan)
        if (in_array($empleado->id_empleado, config('sca_qr.exentos_revision', []))) {
            return false;
        }

        // Sorteo: 10% de probabilidad (configurable)
        $probabilidad = config('sca_qr.probabilidad_revision', 10);
        return rand(1, 100) <= $probabilidad;
    }
}