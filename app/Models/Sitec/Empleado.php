<?php
// app/Models/Sitec/Empleado.php

namespace App\Models\Sitec;

use Illuminate\Database\Eloquent\Model;

class Empleado extends Model
{
    protected $connection = 'sitec';
    protected $table = 'empleados';
    protected $primaryKey = 'id_empleado';
    public $timestamps = true;
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'nombre_completo', 'estatus', 'fecha_alta', 'fecha_nacimiento',
        'estado_civil', 'rfc', 'curp', 'domicilio', 'beneficiario_nombre',
        'beneficiario_porcentaje', 'fecha_baja', 'nss', 'email', 'foto',
        'id_departamento', 'id_puesto', 'id_clasificacion', 'id_turno',
        'semilla', 'id_linea', 'sindicalizado', 'dias_vacaciones', 'motivo_rechazo',
    ];

    protected $casts = [
        'id_empleado'             => 'integer',
        'beneficiario_porcentaje' => 'integer',
        'id_departamento'         => 'integer',
        'id_puesto'               => 'integer',
        'id_clasificacion'        => 'integer',
        'id_turno'                => 'integer',
        'id_linea'                => 'integer',
        'sindicalizado'           => 'integer',
        'dias_vacaciones'         => 'integer',
        'fecha_baja'              => 'date',
    ];

    public function estaActivo(): bool
    {
        return $this->estatus === 'Alta';
    }

    /**
     * OPTIMIZADO: usa caché para el mapeo puesto→tipo.
     */
    public function tipoEmpleado(): ?string
    {
        // 1. Override en ext_sca
        $ext = $this->extRecord();
        if ($ext && $ext->tipo_empleado) {
            return $ext->tipo_empleado;
        }
        // 2. Mapeo por puesto (cacheado)
        return \App\Services\TipoEmpleadoCacheService::tipoDePuesto($this->id_puesto);
    }

    public function esCorporativo(): bool    { return $this->tipoEmpleado() === 'corporativo'; }
    public function esOperativo(): bool      { return $this->tipoEmpleado() === 'operativo'; }
    public function esAdministrativo(): bool { return $this->tipoEmpleado() === 'administrativo'; }

        // ============================================================
    // ACCESSOR para ext (reemplaza al hasOne)
    // ============================================================
    // ELIMINAMOS el método ext() hasOne porque Laravel ejecuta el query
    // en la conexión del padre (sitec) y el usuario no tiene permisos.
    // En su lugar, este accessor usa extRecord() que fuerza la conexión mysql.
    //
    // Así, $empleado->ext funciona en cualquier contexto (vistas, controladores)
    // sin riesgo de ejecutar queries en la BD equivocada.

    /**
     * Devuelve el registro ext (usa conexión correcta mysql).
     * Si fue precargado con setRelation('ext', ...), lo devuelve sin query.
     */
    public function getExtAttribute()
    {
        return $this->extRecord();
    }

    /**
     * Helper: obtiene el registro ext sin re-ejecutar query si ya fue cargado.
     */
    public function extRecord(): ?\App\Models\EmpleadoExtSca
    {
        if (array_key_exists('ext', $this->relations)) {
            return $this->getRelation('ext');
        }
        return \App\Models\EmpleadoExtSca::on('mysql')
            ->where('empleado_id', $this->id_empleado)
            ->first();
    }

    // ============================================================
    // RELACIÓN CON CLASIFICACIONES
    // ============================================================
    public function clasificacion()
    {
        return $this->belongsTo(Clasificacion::class, 'id_clasificacion', 'id_clasificacion');
    }

    /**
     * OPTIMIZADO: usa caché del mapeo clasificacion→planta.
     *
     * FIX E: La planta ahora se lee directamente del campo físico 
     * 'id_planta' en 'empleados_ext_sca'. Ya no se deriva en tiempo real 
     * de la tabla de mapeo (eso era lento y causaba inconsistencias).
     *
     * La sincronización de la planta se realiza de forma automática al:
     * 1. Mapear clasificaciones masivamente.
     * 2. Editar al empleado manualmente.
     * 3. Escanear al empleado (si se detecta que RH cambió su clasificación en sitec).
     */
    public function plantaId(): ?int
    {
        // Simplemente leemos el valor guardado en ext_sca.
        // Si es NULL, significa que el empleado aún no ha sido configurado 
        // (su clasificación no está mapeada y el admin no le ha asignado una).
        $ext = $this->extRecord();
        return $ext && $ext->id_planta !== null ? (int) $ext->id_planta : null;
    }

    /**
     * OPTIMIZADO: usa caché de plantas.
     */
    public function plantaNombre(): string
    {
        return \App\Services\PlantaCacheService::nombrePlanta($this->plantaId());
    }

    public function departamento()
    {
        return $this->belongsTo(Departamento::class, 'id_departamento');
    }

    public function puesto()
    {
        return $this->belongsTo(Puesto::class, 'id_puesto');
    }

    public function turno()
    {
        return $this->belongsTo(Turno::class, 'id_turno');
    }

    // ============================================================
    // SCOPES OPTIMIZADOS
    // ============================================================

    public function scopeActivos($query)
    {
        return $query->where('estatus', 'Alta');
    }

    public function scopeBuscar($query, string $q)
    {
        if (!$q) return $query;
        return $query->where(function ($sub) use ($q) {
            $sub->where('id_empleado', 'like', "%{$q}%")
                 ->orWhere('nombre_completo', 'like', "%{$q}%");
        });
    }
}