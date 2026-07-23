<?php
// app/Models/ClasificacionPlanta.php
//
// MODELO: ClasificacionPlanta
// TABLA:  sca.clasificacion_planta
//
// PROPÓSITO:
//   Mapea cada id_clasificacion de sitec a una planta de sca.
//   Es el puente que permite DERIVAR la planta del empleado:
//
//     sitec.empleados.id_clasificacion
//       → sca.clasificacion_planta.id_clasificacion  (esta tabla)
//       → sca.clasificacion_planta.planta_id
//       → sca.plantas.id
//
//   El admin configura este mapeo una sola vez en /clasificaciones.
//   NOTA: CORPORATIV (id=3) NO se mapea — los corporativos acceden
//   por tipo_empleado (RN-01), no por planta física.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClasificacionPlanta extends Model
{
    protected $table = 'clasificacion_planta';

    // La PK es id_clasificacion (no 'id'), y no es auto-incremental
    // porque el valor viene de sitec.clasificaciones.
    protected $primaryKey = 'id_clasificacion';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id_clasificacion',
        'nombre_clasificacion',
        'planta_id',
    ];

    public $timestamps = true;

    /**
     * Planta de sca a la que mapea esta clasificación.
     */
    public function planta()
    {
        return $this->belongsTo(Planta::class, 'planta_id');
    }
}