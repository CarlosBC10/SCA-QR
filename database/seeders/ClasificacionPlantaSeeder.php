<?php
// database/seeders/ClasificacionPlantaSeeder.php
//
//Seeder ONE-TIME: puebla clasificacion_planta con los mapeos confirmados.
// Se ejecuta UNA SOLA VEZ al instalar. Después, todo es automático:
// los cambios en sitec se reflejan en tiempo real al derivar la planta
// en cada escaneo.
//
// EJECUTAR (una sola vez):
//   php artisan db:seed --class=ClasificacionPlantaSeeder
//
// MAPEOS:
//   1 AC DE FRUT  → Jardín
//   2 ALMACEN     → Jardín
//   3 CORPORATIV  → NO MAPEAR (acceso por tipo_empleado, RN-01)
//   4 JARDIN      → Jardín
//   5 PARTIDAS    → Partidas

namespace Database\Seeders;

use App\Models\ClasificacionPlanta;
use App\Models\Planta;
use App\Models\Sitec\Clasificacion;
use Illuminate\Database\Seeder;

class ClasificacionPlantaSeeder extends Seeder
{
    public function run(): void
    {
        // Mapeos: 'nombre_en_sitec' => 'nombre_en_sca'
        // CORPORATIV se omite intencionalmente.
        $mapeos = [
            'AC DE FRUT' => 'Jardín',
            'ALMACEN'    => 'Jardín',
            'JARDIN'     => 'Jardín',
            'PARTIDAS'   => 'Partidas',
            // 'SAUCES'  => 'Sauces',  // Descomenta cuando llegue la BD de Sauces
        ];

        foreach ($mapeos as $nombreClasif => $nombrePlanta) {
            // 1. Buscar la clasificación en sitec por nombre
            $clasif = Clasificacion::where('nombre', $nombreClasif)->first();
            if (!$clasif) {
                $this->command->warn("Clasificación '{$nombreClasif}' no encontrada en sitec. Omite.");
                continue;
            }

            // 2. Buscar la planta en sca por nombre
            $planta = Planta::where('nombre', $nombrePlanta)->first();
            if (!$planta) {
                $this->command->warn("Planta '{$nombrePlanta}' no encontrada en sca. Omite.");
                continue;
            }

            // 3. Crear/actualizar el mapeo
            ClasificacionPlanta::updateOrCreate(
                ['id_clasificacion' => $clasif->id_clasificacion],
                [
                    'nombre_clasificacion' => $clasif->nombre,
                    'planta_id'            => $planta->id,
                ]
            );

            $this->command->info("Mapeo: '{$nombreClasif}' → '{$nombrePlanta}' OK");
        }

        $this->command->info('Seeder completado. CORPORATIV no se mapea (RN-01).');
    }
}