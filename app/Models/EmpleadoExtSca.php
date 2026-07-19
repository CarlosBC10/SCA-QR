<?php
// app/Models/EmpleadoExtSca.php
//
// MODELO: EmpleadoExtSca
// TABLA: empleados_ext_sca
//
// PROPÓSITO:
// Es una extensión 1:1 de sitec.empleados. Contiene 25,000+ registros sincronizados.
// Guarda los atributos propios del control de accesos que no existen en sitec.
//
// CAMBIO CLAVE:
// tipo_empleado y planta_id ahora PUEDEN SER NULL.
// - tipo_empleado NULL = "El puesto aún no ha sido clasificado por el admin"
// - planta_id NULL = "El empleado no tiene planta asignada, el guardia la asignará al primer escaneo"

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmpleadoExtSca extends Model
{
    protected $table = 'empleados_ext_sca';

    // La PK es empleado_id (no 'id'), y referencia a sitec.empleados.id_empleado
    // No es auto-incremental porque el ID viene de sitec
    protected $primaryKey = 'empleado_id';
    public $incrementing = false;
    protected $keyType = 'int';

    // tipo_empleado y planta_id pueden ser NULL intencionalmente
    protected $fillable = [
        'empleado_id', 'tipo_empleado', 'planta_id',
        'usos_manuales_restantes', 'bloqueo_manual',
    ];

    protected $casts = [
        'bloqueo_manual'          => 'boolean',
        'usos_manuales_restantes' => 'integer',
    ];

    public function planta()
    {
        return $this->belongsTo(Planta::class, 'planta_id');
    }
}