<?php
// app/Services/PlantaCacheService.php
//
// Caché en memoria del mapeo clasificacion → planta y del catálogo de plantas.
// CORRECCIÓN V5: Se corrigen los nombres de columnas en los pluck().

namespace App\Services;

use App\Models\ClasificacionPlanta;
use App\Models\Planta;
use Illuminate\Support\Facades\Cache;

class PlantaCacheService
{
    const KEY_MAPEO = 'sca:mapeo_clasif_planta';
    const KEY_PLANTAS = 'sca:plantas_activas';
    const TTL = 3600; // 1 hora

    public static function plantaDeClasificacion(?int $idClasificacion): ?int
    {
        if (!$idClasificacion) return null;
        $mapeo = self::getMapeo();
        return $mapeo[$idClasificacion] ?? null;
    }

    /**
     * Devuelve el mapeo completo [id_clasificacion => id_planta].
     * CORRECCIÓN V5: pluck('id_planta', 'id_clasificacion')
     */
    public static function getMapeo(): array
    {
        return Cache::remember(self::KEY_MAPEO, self::TTL, function () {
            return ClasificacionPlanta::pluck('id_planta', 'id_clasificacion')->toArray();
        });
    }

    /**
     * Devuelve todas las plantas activas cacheadas [id_planta => nombre].
     * CORRECCIÓN V5: pluck('nombre', 'id_planta')
     */
    public static function getPlantas(): array
    {
        return Cache::remember(self::KEY_PLANTAS, self::TTL, function () {
            return Planta::where('activa', true)->pluck('nombre', 'id_planta')->toArray();
        });
    }

    public static function nombrePlanta(?int $plantaId): string
    {
        if (!$plantaId) return 'Sin planta';
        $plantas = self::getPlantas();
        return $plantas[$plantaId] ?? 'Sin planta';
    }

    public static function invalidar(): void
    {
        Cache::forget(self::KEY_MAPEO);
        Cache::forget(self::KEY_PLANTAS);
    }
}