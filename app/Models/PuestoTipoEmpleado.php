<?php
// app/Models/PuestoTipoEmpleado.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PuestoTipoEmpleado extends Model
{
    protected $table = 'puesto_tipo_empleado';
    protected $primaryKey = 'id_puesto';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['id_puesto', 'tipo_empleado'];

    public static function tipoDePuesto(int $idPuesto): ?string
    {
        return self::where('id_puesto', $idPuesto)->value('tipo_empleado');
    }

    /**
     * Cambia la clasificación de un puesto y actualiza a TODOS los empleados
     * con ese puesto (sin importar su estatus).
     */
    public static function reclasificarPuesto(int $idPuesto, string $nuevoTipo): int
    {
        // 1. Guardar/actualizar la clasificación del puesto
        self::updateOrCreate(
            ['id_puesto' => $idPuesto],
            ['tipo_empleado' => $nuevoTipo]
        );

        // 2. Obtener TODOS los IDs de empleados con ese puesto (sin filtrar por estatus)
        // Usamos la conexión sitec explícitamente
        $empleados = \App\Models\Sitec\Empleado::on('sitec')
            ->where('id_puesto', $idPuesto)
            ->pluck('id_empleado')
            ->toArray();

        if (empty($empleados)) {
            return 0;
        }

        // 3. Actualizar los registros que YA EXISTEN en empleados_ext_sca
        $actualizados = EmpleadoExtSca::whereIn('empleado_id', $empleados)
            ->update(['tipo_empleado' => $nuevoTipo]);

        // 4. Para los que NO existen aún en empleados_ext_sca, crearlos
        $idsExistentes = EmpleadoExtSca::whereIn('empleado_id', $empleados)
            ->pluck('empleado_id')
            ->toArray();

        $idsFaltantes = array_diff($empleados, $idsExistentes);

        if (!empty($idsFaltantes)) {
            $insertData = [];
            $now = now();
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
            // Insertar en lotes
            foreach (array_chunk($insertData, 500) as $chunk) {
                EmpleadoExtSgoa::insert($chunk);
            }
            $actualizados += count($idsFaltantes);
        }

        return $actualizados;
    }

    public static function contarEmpleadosConPuesto(int $idPuesto): int
    {
        return \App\Models\Sitec\Empleado::on('sitec')
            ->where('id_puesto', $idPuesto)
            ->count();
    }
}