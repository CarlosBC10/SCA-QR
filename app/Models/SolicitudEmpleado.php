<?php
// app/Models/SolicitudEmpleado.php
//
// Representa la tabla solicitud_empleados.
// Tabla hija de solicitudes. Se usa para interplantas grupales:
// cada empleado de la solicitud tiene su propio estado (pendiente,
// aprobado, rechazado) para permitir aprobación parcial (RN-07).

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudEmpleado extends Model
{
    protected $table = 'solicitud_empleados';

    protected $fillable = ['solicitud_id', 'empleado_id', 'estado'];

    public function solicitud() { return $this->belongsTo(Solicitud::class, 'solicitud_id'); }
}