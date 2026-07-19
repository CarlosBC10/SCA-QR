<?php
// app/Models/RolSca.php
//
// Representa la tabla roles_sca.
// Catálogo fijo de 5 roles: Superadmin, Admin, Aprobador, Empleado, Guardia.
// Estos roles determinan los permisos del usuario (SRS §2.3.1).

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RolSca extends Model
{
    // Tabla real (Laravel asumiría 'rol_scas' por convención)
    protected $table = 'roles_sca';

    protected $fillable = ['nombre'];

    // Un rol tiene muchos usuarios
    public function usuarios()
    {
        return $this->hasMany(UsuarioSca::class, 'id_rol');
    }
}