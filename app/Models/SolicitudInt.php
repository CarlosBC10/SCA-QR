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

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudInt extends Model
{
    protected $table = 'solicitudes_int';
    protected $primaryKey = 'id_solicitud_int';

    protected $fillable = [
        'creador_empleado_id',          // FK lógica a sitec.empleados.id_empleado
        'tipo',                         // pase_salida | interplantas
        'id_planta_origen',             // planta de dónde sale
        'id_planta_destino',            // planta a dónde va (= origen si pase_salida)
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

    /**
     * ¿Está vigente y aprobada? (para validar acceso en scanner)
     */
    public function estaVigenteAprobada(): bool
    {
        $ahora = now();
        return $this->estado === 'aprobado'
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
}