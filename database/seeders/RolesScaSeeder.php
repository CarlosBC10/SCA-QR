<?php
// database/seeders/RolesScaSeeder.php
//
// SEEDER DE ROLES SCA (ONE-TIME al instalar el sistema).
//
// Por qué existe este seeder:
//   La tabla roles_sca es la primera dependencia del sistema: el registro
//   inicial del Superadmin (LoginController::initialRegistration) consulta
//   RolSca::where('nombre', 'Superadmin')->first() y si no existe, falla.
//   Antes no había seeder, así que en una instalación limpia el sistema
//   nunca podía arrancar (bug C2 del audit V6).
//
// Cómo ejecutarlo:
//   php artisan db:seed --class=RolesScaSeeder
//
// Los 5 roles son los definidos en el SRS §3.2:
//   - Superadmin  : acceso total + gestión de usuarios y configuración.
//   - Admin       : gestión operativa (no configura sistema).
//   - Aprobador   : aprueba/rechaza solicitudes de su planta.
//   - Empleado    : crea sus propias solicitudes, ve su historial.
//   - Guardia     : opera el kiosko y el panel de guardia.

namespace Database\Seeders;

use App\Models\RolSca;
use Illuminate\Database\Seeder;

class RolesScaSeeder extends Seeder
{
    public function run(): void
    {
        // id_rol es AUTO_INCREMENT en el schema, así que solo insertamos 'nombre'.
        // Si ya existe el rol (idempotente), lo saltamos para no duplicar.
        $roles = ['Superadmin', 'Admin', 'Aprobador', 'Empleado', 'Guardia'];

        foreach ($roles as $nombre) {
            RolSca::firstOrCreate(['nombre' => $nombre]);
        }

        // Mostrar en consola los IDs asignados (útil para depurar).
        $this->command->info('Roles SCA sembrados:');
        foreach (RolSca::orderBy('id_rol')->get() as $rol) {
            $this->command->line("  - #{$rol->id_rol}  {$rol->nombre}");
        }
    }
}