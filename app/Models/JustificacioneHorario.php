<?php
// app/Models/JustificacioneHorario.php
//
// NUEVO: catálogo de justificaciones de horario (reemplaza al antiguo
// par anomalia_horario bool + estado_anomalia).
//
// Cuando un acceso ocurre fuera de turno, bitacora.estado_evento pasa a
// 'revision_horario' y el guardia debe seleccionar una de estas
// justificaciones del catálogo. Al hacerlo, bitacora.id_justificacion
// se llena y el estado pasa a 'autorizado' (o 'pendiente_revision' si
// también hubo F10).
//
// No hay texto libre (D1): solo opciones predefinidas de este catálogo.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JustificacioneHorario extends Model
{
    protected $table = 'justificaciones_horario';
    protected $primaryKey = 'id_justificacion';

    protected $fillable = ['nombre', 'descripcion'];

    // Todas las bitácoras que usaron esta justificación
    public function bitacoras()
    {
        return $this->hasMany(Bitacora::class, 'id_justificacion', 'id_justificacion');
    }

    /** Lookup por nombre ('Fuera de turno', 'Fin de semana', …). */
    public static function porNombre(string $nombre): ?self
    {
        return self::where('nombre', $nombre)->first();
    }
}