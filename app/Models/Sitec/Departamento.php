<?php
// app/Models/Sitec/Departamento.php
//
// Representa sitec.departamentos (solo lectura).
// Catálogo de departamentos de la empresa (Producción, RH, Mantenimiento, etc.)
// Lo usamos para mostrar el departamento del empleado en su perfil.

namespace App\Models\Sitec;

use Illuminate\Database\Eloquent\Model;

class Departamento extends Model
{
    protected $connection = 'sitec';
    protected $table = 'departamentos';

    protected $primaryKey = 'id_departamento';
    public $timestamps = true;
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = ['nombre'];

    // Un departamento tiene muchos empleados
    public function empleados()
    {
        return $this->hasMany(Empleado::class, 'id_departamento');
    }
}