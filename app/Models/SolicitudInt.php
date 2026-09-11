<?php
// app/Models/SolicitudInt.php
//
// NUEVO: solicitudes internas (pase_salida + interplantas).
//
// ¿POR QUÉ SEPARAR de SolicitudExt?
// El schema V4 dividió la vieja tabla 'solicitudes' en dos:
//   - solicitudes_int: para tipos internos (pase_salida, interplantas).
//     Siempre tiene id_planta_origen y id_planta_destino (cero NULLs).
//   - solicitudes_ext: para tipos externos (visita, contratista).
//     Siempre tiene anfitrion_empleado_id y id_planta_destino.
// Esto normaliza la BD: cada tabla solo tiene las columnas que aplican
// a sus tipos, sin NULLs semánticos.
//
// LÓGICA DE PLANTA DESTINO (D1-bis):
//   - interplantas: destino != origen (validación obligatoria).
//   - pase_salida: destino = origen (el empleado se ausenta, no va a
//     otra planta). Respetamos el NOT NULL del schema asignando el
//     mismo valor de origen.
//
// LÓGICA DE "PARA_SÍ_MISMO" (D3):
//   NO es columna. Se infiere:
//   - Si NO hay filas en solicitud_empleados → es para el creador únicamente.
//   - Si SÍ hay filas → es grupal (el creador puede o no estar incluido,
//     según si marcó el checkbox "¿Me incluyo?" en el formulario).
//
// ESTADOS (enum del schema):
//   - pendiente_origen: espera aprobación de la planta de origen.
//   - pendiente_destino: aprobado por origen, espera destino (interplantas).
//   - aprobado: ambos aprobaron (o solo origen en pase_salida).
//   - rechazado: rechazado en cualquier paso.
//   - aprobado_parcial: QUEDA EN EL ENUM pero no se usa en la lógica actual
//     (las grupales son todos-o-nada: si uno se rechaza, toda se rechaza).
//
// ────────────────────────────────────────────────────────────────────────
// V10.3 · CAMBIO #23: el enum `tipo` ahora admite 3 valores:
//   - pase_salida:      salir temprano (vigencia = turno, auto-calculada).
//   - interplantas:     ir a OTRA planta (doble aprobación).
//   - permiso_entrada:  NUEVO — entrar a la PROPIA planta fuera de turno
//     (muy temprano, domingo/fin de semana, turno vencido o sin turno).
//     Origen = destino = planta del beneficiario; aprobación simple
//     (solo origen, igual que pase_salida).
// ───────────────────────────────────────────────────────────────────────

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudInt extends Model
{
    protected $table = 'solicitudes_int';
    protected $primaryKey = 'id_solicitud_int';

    protected $fillable = [
        'creador_empleado_id',          // FK lógica a sitec.empleados.id_empleado
        'tipo',                         // pase_salida | interplantas | permiso_entrada (V10.3)
        'id_planta_origen',             // planta de dónde sale
        'id_planta_destino',            // planta a dónde va (= origen si pase_salida/permiso_entrada)
        'fecha_inicio',
        'fecha_fin',
        'motivo',
        'estado',                       // pendiente_origen | pendiente_destino | aprobado | rechazado | aprobado_parcial
        'aprobador_origen_empleado_id', // FK lógica a sitec (quien aprobó en origen)
        'aprobador_destino_empleado_id',// FK lógica a sitec (quien aprobó en destino)
        'fecha_aprobacion_origen',
        'fecha_aprobacion_destino',
        'motivo_rechazo',
    ];

    protected $casts = [
        'fecha_inicio'             => 'datetime',
        'fecha_fin'                => 'datetime',
        'fecha_aprobacion_origen'  => 'datetime',
        'fecha_aprobacion_destino' => 'datetime',
    ];

    // ============================================================
    // RELACIONES
    // ============================================================

    // Planta de origen (de dónde sale el empleado)
    public function plantaOrigen()
    {
        return $this->belongsTo(Planta::class, 'id_planta_origen', 'id_planta');
    }

    // Planta de destino (a dónde va; = origen si pase_salida)
    public function plantaDestino()
    {
        return $this->belongsTo(Planta::class, 'id_planta_destino', 'id_planta');
    }

    // El empleado de sitec que creó la solicitud (cross-DB, usar con cuidado)
    public function creador()
    {
        return $this->belongsTo(\App\Models\Sitec\Empleado::class, 'creador_empleado_id', 'id_empleado');
    }

    public function aprobadorOrigen()
    {
        return $this->belongsTo(\App\Models\Sitec\Empleado::class, 'aprobador_origen_empleado_id', 'id_empleado');
    }

    public function aprobadorDestino()
    {
        return $this->belongsTo(\App\Models\Sitec\Empleado::class, 'aprobador_destino_empleado_id', 'id_empleado');
    }

    // N+ empleados en interplantas grupal (solo si es grupal)
    public function empleados()
    {
        return $this->hasMany(SolicitudEmpleado::class, 'id_solicitud_int', 'id_solicitud_int');
    }

    // ============================================================
    // HELPERS
    // ============================================================

    /**
     * ¿Es una solicitud grupal (N+ empleados)?
     * Se infiere de si hay filas en solicitud_empleados.
     */
    public function esGrupal(): bool
    {
        return $this->empleados()->exists();
    }

    /**
     * ¿Es un pase de salida? (vs interplantas)
     */
    public function esPaseSalida(): bool
    {
        return $this->tipo === 'pase_salida';
    }

    // V10.3 · CAMBIO #23: helper para el nuevo tipo.
    /**
     * ¿Es un permiso de entrada? (entrada fuera de turno a planta propia)
     */
    public function esPermisoEntrada(): bool
    {
        return $this->tipo === 'permiso_entrada';
    }

    /**
     * ¿Está vigente y aprobada? (para validar acceso en scanner)
     *
     * CAMBIO V7: usa estado_actual (accessor lazy) en vez de estado.
     * Acepta 'aprobado' y 'aprobado_parcial' (los rechazados se filtran
     * con incluyeEmpleado()). Rechaza 'expirado' y valida fecha_fin.
     */
    public function estaVigenteAprobada(): bool
    {
        $estado = $this->estado_actual;  // ← accessor lazy
        $ahora = now();
        return in_array($estado, ['aprobado', 'aprobado_parcial'])
            && $this->fecha_inicio <= $ahora
            && $this->fecha_fin >= $ahora;
    }

    /**
     * ¿Un empleado específico está incluido en esta solicitud?
     * - Si es grupal: busca en solicitud_empleados con estado 'aprobado'.
     * - Si es individual: el creador_empleado_id debe coincidir.
     */
    public function incluyeEmpleado(int $empleadoId): bool
    {
        if ($this->esGrupal()) {
            return $this->empleados()
                ->where('empleado_id', $empleadoId)
                ->where('estado', 'aprobado')
                ->exists();
        }
        return $this->creador_empleado_id === $empleadoId;
    }

    // ============================================================
    // EXPIRACIÓN LAZY (Sprint 2)
    // ============================================================
    //
    // ¿Por qué lazy?
    //   No queremos un cron ni un proceso en background. En lugar de
    //   eso, cada vez que alguien consulta el estado de una solicitud
    //   (escáner, bandeja, show, etc.), este accessor valida si ya
    //   venció y, si es así, la marca como 'expirado' en BD en ese
    //   momento. La siguiente consulta ya la encuentra expirada.
    //
    // ¿Por qué un accessor y no el campo estado directo?
    //   Porque el campo estado en BD puede estar desactualizado si
    //   nadie consultó la solicitud desde que venció. El accessor
    //   siempre devuelve el estado REAL, considerando la fecha.

    /**
     * ACCESSOR: estado real de la solicitud, considerando expiración lazy.
     *
     * Uso: $solicitud->estado_actual  (NO $solicitud->estado)
     *
     * Lógica:
     *   - Si ya está 'rechazado' o 'expirado' → devolver tal cual.
     *   - Si fecha_fin < now() y está en pendiente* o aprobado*:
     *     → actualizar BD a 'expirado'
     *     → expirar también los solicitud_empleados
     *     → devolver 'expirado'
     *   - Si no → devolver el estado original.
     */
    /**
     * ACCESSOR: estado real de la solicitud, considerando expiración.
     *
     * FIX Bug 7: Este accessor ahora es "puro" (solo cálculo, sin writes).
     * Antes hacía save() + UPDATE masivo a solicitud_empleados, causando
     * writes silenciosos en contextos de solo lectura (show_int, show_ext).
     *
     * Ahora solo calcula el estado en runtime. La persistencia en BD la hace:
     * 1. El cron ExpirarSolicitudes (cada 5 min).
     * 2. El método explícito expirarSiVencido() (llamado desde ScannerController).
     *
     * Uso: $solicitud->estado_actual  (NO $solicitud->estado)
     */
    public function getEstadoActualAttribute(): string
    {
        // Si ya está en un estado terminal, no hacer nada
        if (in_array($this->estado, ['rechazado', 'expirado'])) {
            return $this->estado;
        }

        // Si la vigencia ya pasó, devolver 'expirado' (sin escribir BD)
        if ($this->fecha_fin && $this->fecha_fin < now()) {
            return 'expirado';
        }

        return $this->estado;
    }

    /**
     * FIX Bug 7: Método explícito para persistir la expiración en BD.
     *
     * Llamar SOLO desde contextos de escritura (ScannerController, AprobacionController).
     * NO llamar desde vistas ni listados.
     *
     * Hace lo que el accessor hacía antes:
     * 1. Marca la solicitud como 'expirado' en BD.
     * 2. Expira los empleados de la solicitud grupal.
     */
    public function expirarSiVencido(): void
    {
        // Si ya está terminal, no hacer nada
        if (in_array($this->estado, ['rechazado', 'expirado'])) {
            return;
        }

        // Si venció, persistir
        if ($this->fecha_fin && $this->fecha_fin < now()) {
            $this->estado = 'expirado';
            $this->save();

            // Expirar también los empleados de esta solicitud grupal.
            \App\Models\SolicitudEmpleado::where('id_solicitud_int', $this->id_solicitud_int)
                ->whereIn('estado', ['pendiente', 'aprobado', 'aprobado_origen'])
                ->update(['estado' => 'expirado']);
        }
    }

    /**
     * Infiere el motivo de expiración SIN necesidad de una columna extra.
     *
     * Reglas:
     *   - Si no está expirada → NULL
     *   - Si fecha_aprobacion_origen IS NULL → 'no_atendida'
     *     (nadie la aprobó a tiempo)
     *   - Si es interplantas y fecha_aprobacion_destino IS NULL → 'no_atendida'
     *     (solo se aprobó origen, destino nunca la atendió)
     *   - Si ambas aprobaciones están llenas → 'vigencia_concluida'
     *     (se aprobó, se usó, y la vigencia terminó naturalmente)
     */
    public function motivoExpiracion(): ?string
    {
        if ($this->estado_actual !== 'expirado') {
            return null;
        }

        // ¿Se aprobó en origen?
        $aproboOrigen = !empty($this->fecha_aprobacion_origen);

        // ¿Se aprobó en destino? (solo aplica a interplantas)
        $aproboDestino = !empty($this->fecha_aprobacion_destino);

        // Si nunca se aprobó en origen → no atendida
        if (!$aproboOrigen) {
            return 'no_atendida';
        }

        // Si es interplantas y no se aprobó en destino → no atendida
        // (el aprobador de destino nunca la atendió)
        if ($this->tipo === 'interplantas' && !$aproboDestino) {
            return 'no_atendida';
        }

        // Si se aprobaron todos los pasos necesarios → vigencia concluida
        return 'vigencia_concluida';
    }

    /**
     * Etiqueta legible para mostrar en vistas.
     */
    public function getMotivoExpiracionLabelAttribute(): string
    {
        return match($this->motivoExpiracion()) {
            'no_atendida'         => 'No fue atendida a tiempo',
            'vigencia_concluida'  => 'Vigencia concluida',
            default               => '—',
        };
    }
}