<?php
// app/Models/SolicitudEmpleado.php
//
// Empleados incluidos en una interplantas grupal.
// Cada empleado tiene su propio estado (pendiente/aprobado/rechazado)
// para permitir aprobación individual. Sin embargo, la lógica de negocio
// es "todos-o-nada": si uno se rechaza, toda la solicitud se rechaza (G2).
//
// CAMBIO V4: 'solicitud_id' → 'id_solicitud_int' (apunta a solicitudes_int).

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudEmpleado extends Model
{
    protected $table = 'solicitud_empleados';
    protected $primaryKey = 'id_solicitud_empleado';

    protected $fillable = ['id_solicitud_int', 'empleado_id', 'estado'];

    public function solicitud()
    {
        return $this->belongsTo(SolicitudInt::class, 'id_solicitud_int', 'id_solicitud_int');
    }
}