<?php
// app/Models/PuestoTipoEmpleado.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PuestoTipoEmpleado extends Model
{
    protected $table = 'puesto_tipo_empleado';
    protected $primaryKey = 'id_puesto';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['id_puesto', 'tipo_empleado'];

    /**
     * OPTIMIZADO: usa caché.
     */
    public static function tipoDePuesto(int $idPuesto): ?string
    {
        return \App\Services\TipoEmpleadoCacheService::tipoDePuesto($idPuesto);
    }

    /**
     * OPTIMIZADO: usa upsert masivo en vez de SELECT + UPDATE + INSERT separados.
     */
    public static function reclasificarPuesto(int $idPuesto, string $nuevoTipo): int
    {
        // 1. Guardar clasificación del puesto
        self::updateOrCreate(
            ['id_puesto' => $idPuesto],
            ['tipo_empleado' => $nuevoTipo]
        );

        // 2. Invalidar caché
        \App\Services\TipoEmpleadoCacheService::invalidar();

        // 3. Obtener IDs de empleados con ese puesto (1 query a sitec)
        $empleados = \App\Models\Sitec\Empleado::on('sitec')
            ->where('id_puesto', $idPuesto)
            ->pluck('id_empleado')
            ->toArray();

        if (empty($empleados)) {
            return 0;
        }

        $totalEmpleados = count($empleados);

        // 4. UPDATE masivo de los que YA existen en ext_sca (1 query)
        $actualizados = EmpleadoExtSca::whereIn('empleado_id', $empleados)
            ->update(['tipo_empleado' => $nuevoTipo, 'updated_at' => now()]);

        // 5. Para los que NO existen, usar upsert masivo (1 query por chunk)
        $idsExistentes = EmpleadoExtSca::whereIn('empleado_id', $empleados)
            ->pluck('empleado_id')
            ->toArray();

        $idsFaltantes = array_diff($empleados, $idsExistentes);

        if (!empty($idsFaltantes)) {
            $now = now();
            $insertData = [];
            foreach ($idsFaltantes as $id) {
                $insertData[] = [
                    'empleado_id' => $id,
                    'tipo_empleado' => $nuevoTipo,
                    'planta_id' => null,
                    'usos_manuales_restantes' => 4,
                    'bloqueo_manual' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            // Upsert masivo (insert ignore para evitar duplicados por seguridad)
            foreach (array_chunk($insertData, 1000) as $chunk) {
                EmpleadoExtSca::insertOrIgnore($chunk);
            }
            $actualizados += count($idsFaltantes);
        }

        return $totalEmpleados;
    }





    
    
    public static function contarEmpleadosConPuesto(int $idPuesto): int
    {
        return \App\Models\Sitec\Empleado::on('sitec')
            ->where('id_puesto', $idPuesto)
            ->count();
    }
}