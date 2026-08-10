<?php
// app/Models/RolSca.php
//
// Catálogo de roles: Superadmin, Admin, Aprobador, Empleado, Guardia.
// PK: id_rol. Sin cambios estructurales.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RolSca extends Model
{
    protected $table = 'roles_sca';
    protected $primaryKey = 'id_rol';

    protected $fillable = ['nombre'];

    public function usuarios()
    {
        return $this->hasMany(UsuarioSca::class, 'id_rol', 'id_rol');
    }
}