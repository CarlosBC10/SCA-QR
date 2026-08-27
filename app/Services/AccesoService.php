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

    public function validarAccesoPlanta(Empleado $empleado, int $plantaId): array
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
        // CAMBIO V7 (N2): agregamos 'planta_destino_id' para que el
        // ScannerController pueda armar el mensaje "hacia {planta}".
        if ($this->tieneInterplantasVigente($empleado->id_empleado, $plantaId)) {
            return [
                'permitido'         => true,
                'motivo'            => null,
                'motivo_codigo'     => null,
                'planta_origen'     => $plantaBase,
                'es_interplantas'   => true,
                'planta_destino_id' => $plantaId,  // nuevo: para el mensaje
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

    public function validarHorario(Empleado $empleado, string $tipoEvento, bool $esPrimeraEntrada): array
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

        // Sin turno asignado → anomalía (todos deben tener horario)
        if (!$empleado->turno || !$empleado->turno->entrada || !$empleado->turno->salida) {
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

        $entradaMin = $entradaTs - self::TOLERANCIA_ENTRADA_ANTES;    // ej. 5:30
        $entradaMax = $entradaTs + self::TOLERANCIA_ENTRADA_DESPUES;  // ej. 6:15
        $salidaMax  = $salidaTs + self::TOLERANCIA_SALIDA_DESPUES;    // ej. 14:30

        // ============================================================
        // ENTRADA
        // ============================================================
        if ($tipoEvento === 'entrada') {
            // Solo validar la PRIMERA entrada del día (llegada a tiempo).
            // Regresos durante el día no se validan (el empleado ya llegó).
            if ($esPrimeraEntrada) {
                if ($ahoraTs < $entradaMin || $ahoraTs > $entradaMax) {
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
                if ($this->tienePermisoParaSalir($empleado->id_empleado)) {
                    return [
                        'permitido'              => true,
                        'requiere_justificacion' => false,
                        'motivo'                 => null,
                        'motivo_codigo'          => null,
                    ];
                }
                // Sin permiso: permite salir pero con justificación.
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
                if ($this->tienePermisoParaSalir($empleado->id_empleado)) {
                    return [
                        'permitido'              => true,
                        'requiere_justificacion' => false,
                        'motivo'                 => null,
                        'motivo_codigo'          => null,
                    ];
                }
                // Sin permiso → DENEGADO (no es anomalía, es denegación directa)
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
        $ahora = Carbon::now();

        return SolicitudInt::where('tipo', 'interplantas')
            ->whereIn('estado', ['aprobado', 'aprobado_parcial'])
            ->where('id_planta_destino', $plantaDestinoId)
            ->where('fecha_inicio', '<=', $ahora)
            ->where('fecha_fin', '>=', $ahora)
            ->where(function ($q) use ($empleadoId) {
                // Caso A: el empleado es el creador (solicitud individual)
                $q->where('creador_empleado_id', $empleadoId)
                  // Caso B: el empleado está en la lista grupal con estado 'aprobado'
                  ->orWhereHas('empleados', function ($q2) use ($empleadoId) {
                      $q2->where('empleado_id', $empleadoId)
                         ->where('estado', 'aprobado');
                  });
            })
            ->exists();
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
    public function obtenerPermisoParaSalir(int $empleadoId): ?array
    {
        $ahora = Carbon::now();

        $solicitud = SolicitudInt::whereIn('tipo', ['pase_salida', 'interplantas'])
            ->whereIn('estado', ['aprobado', 'aprobado_parcial'])
            ->where('fecha_inicio', '<=', $ahora)
            ->where('fecha_fin', '>=', $ahora)
            ->where(function ($q) use ($empleadoId) {
                $q->where('creador_empleado_id', $empleadoId)
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
        ];
    }

    /**
     * Wrapper de compatibilidad: devuelve true/false si tiene permiso.
     * Mantiene el nombre viejo por si algo más lo llama.
     */
    public function tienePermisoParaSalir(int $empleadoId): bool
    {
        return $this->obtenerPermisoParaSalir($empleadoId) !== null;
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