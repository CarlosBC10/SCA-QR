<?php
// app/Models/Sitec/Puesto.php
//
// Representa sitec.puestos (solo lectura).
// Catálogo de puestos (Ayudante General, Auxiliar CH, Director de Compras, etc.)
// Lo usamos para:
//   1. Mostrar el puesto del empleado en su perfil
//   2. Sugerir el tipo_empleado al alta (vía puesto_tipo_empleado)

namespace App\Models\Sitec;

use Illuminate\Database\Eloquent\Model;

class Puesto extends Model
{
    protected $connection = 'sitec';
    protected $table = 'puestos';

    protected $primaryKey = 'id_puesto';
    public $timestamps = true;
    public $incrementing = true;
    protected $keyType = 'int';

    // No incluimos salario_* por seguridad (datos sensibles de nómina)
    protected $fillable = ['nombre'];

    public function empleados()
    {
        return $this->hasMany(Empleado::class, 'id_puesto');
    }
}