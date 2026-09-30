<?php
// app/Models/ClasificacionPlanta.php
//
// Mapa sitec.clasificaciones → planta.
// Permite derivar la planta de un empleado automáticamente desde su
// clasificación en sitec (NO se edita manualmente por empleado).
//
// CAMBIOS V4:
//   - 'clasificacion_sitec' (string) → 'id_clasificacion' (int, FK lógica)
//     + 'nombre_clasificacion' (string, denormalizado por seguridad).
//   - PK: id_clasificacion (no autoincrement; viene de sitec).
//   - 'planta_id' → 'id_planta'.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClasificacionPlanta extends Model
{
    protected $table = 'clasificacion_planta';
    protected $primaryKey = 'id_clasificacion';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['id_clasificacion', 'nombre_clasificacion', 'id_planta'];

    public function planta()
    {
        return $this->belongsTo(Planta::class, 'id_planta', 'id_planta');
    }
}