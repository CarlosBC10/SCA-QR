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

    // ============================================================
    // EXPIRACIÓN LAZY (Sprint 2)
    // ============================================================

    /**
     * ACCESSOR: estado real del empleado en la solicitud, considerando
     * expiración lazy.
     *
     * CAMBIO V7 (fix): antes este accessor solo expiraba si el estado era
     * 'pendiente' o 'aprobado'. Pero si la solicitud madre ya estaba
     * expirada en BD (porque otro proceso la expiró), los empleados
     * quedaban con su estado viejo y la vista mostraba 'pendiente'.
     * Ahora verificamos también si la solicitud madre ya está expirada.
     */
    public function getEstadoActualAttribute(): string
    {
        // Si ya está rechazado o expirado, se queda como está
        if (in_array($this->estado, ['rechazado', 'expirado'])) {
            return $this->estado;
        }

        $solicitud = $this->solicitud;

        // Si la solicitud madre ya está expirada en BD, o si venció su fecha_fin,
        // expirar al empleado también.
        if ($solicitud && (
            $solicitud->estado === 'expirado'
            || ($solicitud->fecha_fin && $solicitud->fecha_fin < now())
        )) {
            $this->estado = 'expirado';
            $this->save();

            // Asegurar que la solicitud madre también quede expirada
            if (!in_array($solicitud->estado, ['rechazado', 'expirado'])) {
                $solicitud->estado = 'expirado';
                $solicitud->save();
            }

            return 'expirado';
        }

        return $this->estado;
    }
}