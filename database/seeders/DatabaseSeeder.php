<?php
// database/seeders/DatabaseSeeder.php
//
// SEEDER MAESTRO del sistema SCA-QR.
//
// ⚠️ FIX (auditoría V6):
//   Antes este archivo era el default de Laravel (creaba User::factory que
//   no sirve aquí). Eso dejaba roles_sca y clasificaciones vacías, así que
//   el sistema no podía inicializarse.
//
//   Ahora encadena los seeders reales del sistema:
//     1. RolesScaSeeder            → 5 roles (Superadmin, Admin, Aprobador, Empleado, Guardia)
//     2. ClasificacionPlantaSeeder → mapeos sitec.clasificacion → plantas (one-time)
//
// Cómo ejecutarlo:
//   php artisan db:seed              # corre este archivo
//   php artisan db:seed --class=X    # corre un seeder específico
//   php artisan migrate:fresh --seed # resetea + siembra (¡Borra datos!)

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesScaSeeder::class,
            ClasificacionPlantaSeeder::class,
        ]);
    }
}