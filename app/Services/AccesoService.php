<?php
// app/Services/AccesoService.php

namespace App\Services;

use App\Models\Solicitud;
use App\Models\Sitec\Empleado;
use Carbon\Carbon;

class AccesoService
{
    const TOLERANCIA_ENTRADA_ANTES = 30 * 60;
    const TOLERANCIA_ENTRADA_DESPUES = 15 * 60;
    const TOLERANCIA_SALIDA_DESPUES = 30 * 60;

    public function validarAccesoPlanta(Empleado $empleado, int $plantaId): array
    {
        $tipoEmpleado = $empleado->tipoEmpleado();
        if (!$tipoEmpleado) {
            return [
                'permitido' => false,
                'motivo' => 'Empleado sin clasificar. Acuda con Recursos Humanos.',
                'motivo_codigo' => 'sin_clasificar',
                'notificar' => true,
                'planta_origen' => $empleado->plantaId(),
                'es_interplantas' => false,
            ];
        }

        if ($tipoEmpleado === 'corporativo') {
            return [
                'permitido' => true,
                'motivo' => null,
                'motivo_codigo' => null,
                'notificar' => false,
                'planta_origen' => $empleado->plantaId(),
                'es_interplantas' => false,
            ];
        }

        $plantaBase = $empleado->plantaId();
        if (!$plantaBase) {
            return [
                'permitido' => false,
                'motivo' => 'Su planta no está configurada. Acuda con su jefe.',
                'motivo_codigo' => 'sin_planta',
                'notificar' => true,
                'planta_origen' => null,
                'es_interplantas' => false,
            ];
        }

        if ($plantaBase === $plantaId) {
            return [
                'permitido' => true,
                'motivo' => null,
                'motivo_codigo' => null,
                'notificar' => false,
                'planta_origen' => $plantaBase,
                'es_interplantas' => false,
            ];
        }

        // OPTIMIZADO: una sola query para interplantas
        if ($this->tieneInterplantasVigente($empleado->id_empleado, $plantaId)) {
            return [
                'permitido' => true,
                'motivo' => null,
                'motivo_codigo' => null,
                'notificar' => false,
                'planta_origen' => $plantaBase,
                'es_interplantas' => true,
            ];
        }

        return [
            'permitido' => false,
            'motivo' => 'Planta no autorizada. Diríjase a su planta base.',
            'motivo_codigo' => 'planta_no_autorizada',
            'notificar' => false,
            'planta_origen' => $plantaBase,
            'es_interplantas' => false,
        ];
    }

    /**
     * OPTIMIZADO: una sola query (directa OR grupal).
     */
    public function tieneInterplantasVigente(int $empleadoId, int $plantaDestinoId): bool
    {
        $ahora = Carbon::now();

        return Solicitud::where('tipo', 'interplantas')
            ->whereIn('estado', ['aprobado', 'aprobado_parcial'])
            ->where('planta_destino_id', $plantaDestinoId)
            ->where('fecha_inicio', '<=', $ahora)
            ->where('fecha_fin', '>=', $ahora)
            ->where(function ($q) use ($empleadoId) {
                $q->where('creador_empleado_id', $empleadoId)
                  ->orWhereHas('empleados', function ($q2) use ($empleadoId) {
                      $q2->where('empleado_id', $empleadoId)
                         ->where('estado', 'aprobado');
                  });
            })
            ->exists();
    }

    /**
     * CORREGIDO: validación de horario completa.
     *
     * Reglas:
     *   - Sin turno asignado → anomalía (todos deben tener horario)
     *   - Operativos: trabajan Lunes a Sábado
     *   - Administrativos: trabajan Lunes a Viernes
     *   - Operativo entrada: solo valida la 1ra entrada del día (llegada a tiempo)
     *   - Operativo salida: DENEGADA antes de fin de turno, A MENOS que tenga
     *     pase_salida o interplantas vigente
     *   - Administrativo entrada: solo valida 1ra entrada (llegada a tiempo)
     *   - Administrativo salida: permite durante turno, tardía → anomalía
     */
    public function validarHorario(Empleado $empleado, string $tipoEvento, bool $esPrimeraEntrada): array
    {
        $tipo = $empleado->tipoEmpleado();

        // Corporativos: sin validación de turno (RN-01)
        if (!in_array($tipo, ['operativo', 'administrativo'])) {
            return ['permitido' => true, 'requiere_justificacion' => false, 'motivo' => null, 'motivo_codigo' => null];
        }

        // Sin turno asignado → anomalía (todos deben tener horario)
        if (!$empleado->turno || !$empleado->turno->entrada || !$empleado->turno->salida) {
            return [
                'permitido' => true,
                'requiere_justificacion' => true,
                'motivo' => 'Sin turno asignado. Justifique su acceso.',
                'motivo_codigo' => 'sin_turno',
            ];
        }

        // Días laborales correctos
        // dayOfWeekIso: 1=Lun, 2=Mar, ..., 6=Sáb, 7=Dom
        $dia = now()->dayOfWeekIso;
        if ($tipo === 'operativo') {
            // Operativos: Lunes a Sábado (1-6)
            if ($dia > 6) {
                return [
                    'permitido' => true,
                    'requiere_justificacion' => true,
                    'motivo' => 'Domingo no laboral. Justifique su acceso.',
                    'motivo_codigo' => 'dia_no_laboral',
                ];
            }
        } else {
            // Administrativos: Lunes a Viernes (1-5)
            if ($dia > 5) {
                return [
                    'permitido' => true,
                    'requiere_justificacion' => true,
                    'motivo' => 'Fin de semana no laboral. Justifique su acceso.',
                    'motivo_codigo' => 'dia_no_laboral',
                ];
            }
        }

        $ahoraTs = strtotime(now()->format('H:i:s'));
        $entradaTs = strtotime($empleado->turno->entrada);
        $salidaTs = strtotime($empleado->turno->salida);

        $entradaMin = $entradaTs - self::TOLERANCIA_ENTRADA_ANTES;   // 5:30
        $entradaMax = $entradaTs + self::TOLERANCIA_ENTRADA_DESPUES;  // 6:15
        $salidaMax = $salidaTs + self::TOLERANCIA_SALIDA_DESPUES;     // 14:30

        // ============================================================
        // ENTRADA
        // ============================================================
        if ($tipoEvento === 'entrada') {
            // CORREGIDO: solo validar la PRIMERA entrada del día
            // (llegada a tiempo). Regresos durante el día no se validan.
            if ($esPrimeraEntrada) {
                if ($ahoraTs < $entradaMin || $ahoraTs > $entradaMax) {
                    return [
                        'permitido' => true,
                        'requiere_justificacion' => true,
                        'motivo' => 'Llegada fuera de horario. Justifique su acceso.',
                        'motivo_codigo' => 'llegada_tardia',
                    ];
                }
            }
            return ['permitido' => true, 'requiere_justificacion' => false, 'motivo' => null, 'motivo_codigo' => null];
        }

        // ============================================================
        // SALIDA
        // ============================================================
        if ($tipo === 'operativo') {
            // Operativo: no puede salir antes de fin de turno...
            if ($ahoraTs < $salidaTs) {
                // ...A MENOS que tenga pase_salida o interplantas vigente
                if ($this->tienePermisoParaSalir($empleado->id_empleado)) {
                    return ['permitido' => true, 'requiere_justificacion' => false, 'motivo' => null, 'motivo_codigo' => null];
                }
                return [
                    'permitido' => false,
                    'requiere_justificacion' => false,
                    'motivo' => 'No puede salir antes de su hora de salida (' . $empleado->turno->salida . ').',
                    'motivo_codigo' => 'salida_temprana',
                ];
            }
            // Después de la tolerancia → anomalía
            if ($ahoraTs > $salidaMax) {
                return [
                    'permitido' => true,
                    'requiere_justificacion' => true,
                    'motivo' => 'Salida tardía. Justifique su acceso.',
                    'motivo_codigo' => 'salida_tardia',
                ];
            }
            // Salida normal (dentro de tolerancia)
            return ['permitido' => true, 'requiere_justificacion' => false, 'motivo' => null, 'motivo_codigo' => null];
        }

        // Administrativo: puede salir durante el turno libremente
        if ($ahoraTs > $salidaMax) {
            return [
                'permitido' => true,
                'requiere_justificacion' => true,
                'motivo' => 'Salida tardía. Justifique su acceso.',
                'motivo_codigo' => 'salida_tardia',
            ];
        }

        return ['permitido' => true, 'requiere_justificacion' => false, 'motivo' => null, 'motivo_codigo' => null];
    }

    /**
     * NUEVO: verifica si el empleado tiene un permiso vigente para salir
     * durante su turno (pase_salida o interplantas).
     *
     * - pase_salida: el empleado se ausenta y se va (termina su día)
     * - interplantas: va a otra planta (necesita salir de su planta actual)
     */
    public function tienePermisoParaSalir(int $empleadoId): bool
    {
        $ahora = Carbon::now();

        // pase_salida aprobado y vigente
        $paseSalida = Solicitud::where('tipo', 'pase_salida')
            ->where('estado', 'aprobado')
            ->where('creador_empleado_id', $empleadoId)
            ->where('fecha_inicio', '<=', $ahora)
            ->where('fecha_fin', '>=', $ahora)
            ->exists();

        if ($paseSalida) return true;

        // interplantas aprobado y vigente (individual o grupal)
        $interplantas = Solicitud::where('tipo', 'interplantas')
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
            ->exists();

        return $interplantas;
    }
}