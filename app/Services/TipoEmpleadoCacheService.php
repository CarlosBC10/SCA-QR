<?php
// app/Services/TipoEmpleadoCacheService.php
//
// Caché del mapeo puesto → tipo_empleado.
// Se invalida cuando se reclasifica un puesto (PuestoTipoEmpleadoController::store).

namespace App\Services;

use App\Models\PuestoTipoEmpleado;
use Illuminate\Support\Facades\Cache;

class TipoEmpleadoCacheService
{
    const KEY = 'sca:mapeo_puesto_tipo';
    const TTL = 3600;

    /**
     * Devuelve el tipo de empleado para un puesto dado.
     */
    public static function tipoDePuesto(?int $idPuesto): ?string
    {
        if (!$idPuesto) return null;
        $mapeo = self::getMapeo();
        return $mapeo[$idPuesto] ?? null;
    }

    /**
     * Devuelve el mapeo completo [id_puesto => tipo_empleado].
     */
    public static function getMapeo(): array
    {
        return Cache::remember(self::KEY, self::TTL, function () {
            return PuestoTipoEmpleado::pluck('tipo_empleado', 'id_puesto')->toArray();
        });
    }

    /**
     * Invalida el caché. Llamar al reclasificar un puesto.
     */
    public static function invalidar(): void
    {
        Cache::forget(self::KEY);
    }
}