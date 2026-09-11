<?php

namespace App\Services;

use Carbon\CarbonInterface;

/**
 * Contexto de filtros de Bitácora ya normalizados.
 *
 * Se mantiene como objeto independiente para que PSR-4 pueda autoloadearlo
 * correctamente y para reutilizar exactamente el mismo conjunto de filtros
 * en la pantalla y en la exportación. No modifica ninguna tabla.
 */
class BitacoraFiltro
{
    public function __construct(
        // V10.3.6-fix: propiedad $estadoPermiso ELIMINADA junto con el filtro
        // "Estado permiso" de la vista (no está en los requisitos: los accesos
        // con permiso ya se identifican con el filtro Tipo de acceso).
        public readonly ?int $plantaOrigen = null,
        public readonly ?int $plantaDestino = null,
        public readonly ?string $empresa = null,
        public readonly ?string $anfitrion = null,
        public readonly ?string $subtipo = null,
        public readonly ?int $plantaEvento = null,
        public readonly ?string $movimiento = null,
        public readonly ?string $estadoEvento = null,
        public readonly ?string $motivo = null,
        public readonly ?string $incidencia = null,
        public readonly ?string $buscar = null,
        public readonly ?CarbonInterface $fechaDesde = null,
        public readonly ?CarbonInterface $fechaHasta = null,
    ) {}
}
