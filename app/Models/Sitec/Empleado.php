<?php
// app/Models/Sitec/Empleado.php
//
// Representa sitec.empleados (solo lectura).
// Permisos de lectura ACTUALES: empleados, puestos, turnos, departamentos.

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

    // ¿el empleado está activo (puede login)?
    public function estaActivo(): bool
    {
        return $this->estatus === 'Alta';
    }

    // tipo de empleado: 1) override en ext, 2) mapeo por puesto, 3) null
    public function tipoEmpleado(): ?string
    {
        $ext = $this->ext();
        if ($ext && $ext->tipo_empleado) {
            return $ext->tipo_empleado;
        }
        return \App\Models\PuestoTipoEmpleado::tipoDePuesto($this->id_puesto);
    }

    public function esCorporativo(): bool    { return $this->tipoEmpleado() === 'corporativo'; }
    public function esOperativo(): bool      { return $this->tipoEmpleado() === 'operativo'; }
    public function esAdministrativo(): bool { return $this->tipoEmpleado() === 'administrativo'; }


    // ============================================================
    // RELACIONES CON CATÁLOGOS DE sitec (ACTIVAS en M2)
    // ============================================================
    // Ahora tenemos permiso de lectura sobre puestos, turnos y departamentos.
    // Estas relaciones SÍ funcionan con belongsTo porque están en la
    // misma conexión (sitec).

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
}