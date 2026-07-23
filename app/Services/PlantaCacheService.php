<?php
// app/Services/PlantaCacheService.php
//
// Caché en memoria del mapeo clasificacion → planta y del catálogo de plantas.
// Se carga UNA vez y se reutiliza en todos los requests.
// Se invalida automáticamente cuando se modifica un mapeo.

namespace App\Services;

use App\Models\ClasificacionPlanta;
use App\Models\Planta;
use Illuminate\Support\Facades\Cache;

class PlantaCacheService
{
    const KEY_MAPEO = 'sca:mapeo_clasif_planta';
    const KEY_PLANTAS = 'sca:plantas_activas';
    const TTL = 3600; // 1 hora

    /**
     * Devuelve el ID de planta para una clasificación dada.
     */
    public static function plantaDeClasificacion(?int $idClasificacion): ?int
    {
        if (!$idClasificacion) return null;
        $mapeo = self::getMapeo();
        return $mapeo[$idClasificacion] ?? null;
    }

    /**
     * Devuelve el mapeo completo [id_clasificacion => planta_id].
     */
    public static function getMapeo(): array
    {
        return Cache::remember(self::KEY_MAPEO, self::TTL, function () {
            return ClasificacionPlanta::pluck('planta_id', 'id_clasificacion')->toArray();
        });
    }

    /**
     * Devuelve todas las plantas activas cacheadas [id => nombre].
     */
    public static function getPlantas(): array
    {
        return Cache::remember(self::KEY_PLANTAS, self::TTL, function () {
            return Planta::where('activa', true)->pluck('nombre', 'id')->toArray();
        });
    }

    /**
     * Devuelve el nombre de una planta por ID.
     */
    public static function nombrePlanta(?int $plantaId): string
    {
        if (!$plantaId) return 'Sin planta';
        $plantas = self::getPlantas();
        return $plantas[$plantaId] ?? 'Sin planta';
    }

    /**
     * Invalida todos los cachés. Llamar cuando se modifica un mapeo o planta.
     */
    public static function invalidar(): void
    {
        Cache::forget(self::KEY_MAPEO);
        Cache::forget(self::KEY_PLANTAS);
    }
}