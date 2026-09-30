<?php
// app/Models/Sitec/Clasificacion.php
//
// MODELO: Sitec\Clasificacion
// TABLA:  sitec.clasificaciones  (solo lectura, conexión 'sitec')
//
// PROPÓSITO:
//   sitec.clasificaciones es el catálogo de áreas/grupos de Fruitex.
//   Ej: "AC DE FRUT", "ALMACEN", "CORPORATIV", "JARDIN", "PARTIDAS".
//
//   Cada empleado en sitec.empleados tiene un id_clasificacion, y ese id
//   se mapea a una planta de sca mediante sca.clasificacion_planta.
//
//   Así, la PLANTA del empleado se DERIVA AUTOMÁTICAMENTE y ya NO se
//   asigna manualmente empleado por empleado.

namespace App\Models\Sitec;

use Illuminate\Database\Eloquent\Model;

class Clasificacion extends Model
{
    // Conexión a la BD sitec (config/database.php → connections.sitec)
    protected $connection = 'sitec';
    protected $table = 'clasificaciones';
    protected $primaryKey = 'id_clasificacion';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = ['nombre'];

    /**
     * Empleados de sitec que pertenecen a esta clasificación.
     */
    public function empleados()
    {
        return $this->hasMany(Empleado::class, 'id_clasificacion', 'id_clasificacion');
    }
}