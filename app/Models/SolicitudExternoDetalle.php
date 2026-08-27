<?php
// app/Models/SolicitudExternoDetalle.php
//
// NUEVO: reemplaza a Credencial. Cada externo en una solicitud_ext
// tiene su propio codigo_qr y estado_qr.
//
// Lógica clave:
//   - Al CREAR la solicitud, se insertan los externos con codigo_qr=NULL
//     y estado_qr='pendiente' (no válido hasta aprobación).
//   - Al APROBAR la solicitud, se genera el codigo_qr único por persona
//     y se cambia estado_qr a 'autorizado'.
//   - Cancelar un externo individual = estado_qr='cancelado'.
//   - Expirar la solicitud = estado_qr='expirado'.
//
// CAMBIO V7: expiración lazy. Si la solicitud madre venció pero nadie
// la consultó, el QR puede seguir diciendo 'autorizado' en BD. El
// accessor estado_qr_actual lo detecta y expira en el momento de la
// consulta.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudExternoDetalle extends Model
{
    protected $table = 'solicitud_externo_detalle';
    protected $primaryKey = 'id_solicitud_externo_detalle';

    protected $fillable = [
        'id_solicitud_ext', 'id_externos', 'codigo_qr', 'estado_qr',
    ];

    // ============================================================
    // RELACIONES
    // ============================================================

    public function solicitudExt()
    {
        return $this->belongsTo(SolicitudExt::class, 'id_solicitud_ext', 'id_solicitud_ext');
    }

    public function externo()
    {
        return $this->belongsTo(Externo::class, 'id_externos', 'id_externos');
    }

    // ============================================================
    // EXPIRACIÓN LAZY DEL QR (Sprint 2 - CAMBIO V7)
    // ============================================================
    //
    // Si la solicitud madre venció pero nadie la consultó, el QR puede
    // seguir diciendo 'autorizado' en BD. Este accessor valida y expira
    // el QR en el momento en que se consulta (lazy).

    /**
     * ACCESSOR: estado_qr real del externo, considerando expiración lazy.
     *
     * FIX V7 (Problema C): si el externo está DENTRO de planta (última
     * bitácora = entrada autorizada), NO invalidar el QR aunque la solicitud
     * haya vencido. El externo podrá escanear su QR para salir.
     * El comando sca:expirar-solicitudes es el que se encarga de invalidar
     * los QRs de externos que NO están dentro.
     */
    public function getEstadoQrActualAttribute(): string
    {
        if (in_array($this->estado_qr, ['cancelado', 'expirado'])) {
            return $this->estado_qr;
        }

        $solicitud = $this->solicitudExt;

        if ($solicitud && $solicitud->fecha_fin && $solicitud->fecha_fin < now()) {
            // ¿El externo está dentro de planta?
            $personaId = 'E-' . $this->id_externos;
            $ultimaBitacora = \App\Models\Bitacora::where('persona_id', $personaId)
                ->whereNotIn('estado_evento', ['denegado'])
                ->latest('fecha_hora')
                ->first();

            $estaDentro = $ultimaBitacora
                && $ultimaBitacora->tipo_evento === 'entrada'
                && $ultimaBitacora->estado_evento === 'autorizado';

            if (!$estaDentro) {
                // No está dentro → invalidar el QR
                $this->estado_qr = 'expirado';
                $this->codigo_qr = null;
                $this->saveQuietly();

                if (!in_array($solicitud->estado, ['rechazado', 'expirado'])) {
                    $solicitud->estado = 'expirado';
                    $solicitud->saveQuietly();
                }
                return 'expirado';
            }
            // Si está dentro → el QR sigue 'autorizado' para que pueda salir.
        }

        return $this->estado_qr;
    }

    /**
     * ¿El QR de este externo está activo y se puede usar para entrar?
     *
     * CAMBIO V7: usa estado_qr_actual (accessor lazy).
     * Solo 'autorizado' con codigo_qr no null es activo.
     */
    public function qrActivo(): bool
    {
        return $this->estado_qr_actual === 'autorizado'
            && $this->codigo_qr !== null;
    }
}