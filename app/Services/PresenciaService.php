<?php

namespace App\Services;

use App\Models\Bitacora;
use Illuminate\Support\Collection;

class PresenciaService
{
    /**
     * Devuelve el último evento AUTORIZADO de una persona.
     *
     * Importante:
     * - Solo 'autorizado' representa un movimiento consumado.
     * - 'revision_horario' NO cambia presencia.
     * - 'pendiente_revision' NO cambia presencia.
     * - 'denegado' NO cambia presencia.
     *
     * El ID se utiliza como segundo criterio únicamente para desempatar
     * registros con exactamente la misma fecha/hora.
     */
    public static function ultimoEventoAutorizado(
        string $personaId
    ): ?Bitacora {
        return Bitacora::query()
            ->where('persona_id', $personaId)
            ->where('estado_evento', 'autorizado')
            ->orderByDesc('fecha_hora')
            ->orderByDesc('id_bitacora')
            ->first();
    }

    /**
     * Indica si la persona está actualmente dentro.
     *
     * Si se proporciona $plantaId, además verifica que la última entrada
     * autorizada corresponda a esa planta.
     */
    public static function estaDentro(
        string $personaId,
        ?int $plantaId = null
    ): bool {
        $ultimo = self::ultimoEventoAutorizado($personaId);

        if (!$ultimo) {
            return false;
        }

        if ($ultimo->tipo_evento !== 'entrada') {
            return false;
        }

        if (
            $plantaId !== null
            && (int) $ultimo->id_planta !== (int) $plantaId
        ) {
            return false;
        }

        return true;
    }

    /**
     * Devuelve la planta en la que la persona se encuentra actualmente.
     */
    public static function plantaActual(
        string $personaId
    ): ?int {
        $ultimo = self::ultimoEventoAutorizado($personaId);

        if (
            !$ultimo
            || $ultimo->tipo_evento !== 'entrada'
        ) {
            return null;
        }

        return (int) $ultimo->id_planta;
    }

    /**
     * Devuelve la fecha/hora de la entrada actualmente vigente.
     */
    public static function fechaEntradaActual(
        string $personaId
    ) {
        $ultimo = self::ultimoEventoAutorizado($personaId);

        if (
            !$ultimo
            || $ultimo->tipo_evento !== 'entrada'
        ) {
            return null;
        }

        return $ultimo->fecha_hora;
    }

    /**
     * Devuelve los últimos eventos autorizados de cada persona.
     *
     * Solo devuelve personas cuyo último movimiento autorizado fue:
     * entrada + planta indicada.
     */
    public static function personasDentroDePlanta(
        int $plantaId
    ): Collection {

        $idsUltimosEventos = Bitacora::query()
            ->selectRaw('MAX(id_bitacora)')
            ->where('estado_evento', 'autorizado')
            ->groupBy('persona_id');

        return Bitacora::query()
            ->whereIn(
                'id_bitacora',
                $idsUltimosEventos
            )
            ->where('tipo_evento', 'entrada')
            ->where('id_planta', $plantaId)
            ->orderByDesc('fecha_hora')
            ->orderByDesc('id_bitacora')
            ->get();
    }
}