<?php
// app/Models/SolicitudExternoDetalle.php
//
// NUEVO: reemplaza a Credencial. Cada externo en una solicitud_ext
// tiene su propio codigo_qr y estado_qr (autorizado|cancelado).
//
// Lógica clave:
//   - Al CREAR la solicitud, se insertan los externos con codigo_qr=NULL
//     y estado_qr='cancelado' (no válido hasta aprobación).
//   - Al APROBAR la solicitud, se genera el codigo_qr único por persona
//     y se cambia estado_qr a 'autorizado'.
//   - Cancelar un externo individual = estado_qr='cancelado' (no borra).
//
// Esto permite N+ personas por solicitud y cancelación individual.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudExternoDetalle extends Model
{
    protected $table = 'solicitud_externo_detalle';
    protected $primaryKey = 'id_solicitud_externo_detalle';

    protected $fillable = [
        'id_solicitud_ext', 'id_externos', 'codigo_qr', 'estado_qr',
    ];

    public function solicitudExt()
    {
        return $this->belongsTo(SolicitudExt::class, 'id_solicitud_ext', 'id_solicitud_ext');
    }

    public function externo()
    {
        return $this->belongsTo(Externo::class, 'id_externos', 'id_externos');
    }

    /** ¿El QR de este detalle está autorizado? */
    public function qrActivo(): bool
    {
        return $this->estado_qr === 'autorizado';
    }

    /**
     * RA-04: vigencia del QR. Depende de la solicitud ext padre:
     *   estado_qr='autorizado' AND solicitud aprobada AND fecha_fin >= now.
     */
    public function estaVigente(): bool
    {
        // FIX C-3: Unificar validación de vigencia.
        $ahora = now();
        $fechaFin = $this->solicitudExt->fecha_fin ? \Carbon\Carbon::parse($this->solicitudExt->fecha_fin)->endOfDay() : null;

        return $this->qrActivo()
            && $this->solicitudExt !== null
            && in_array($this->solicitudExt->estado, ['aprobado', 'aprobado_parcial'], true)
            && $fechaFin >= $ahora
            && $this->solicitudExt->fecha_inicio <= $ahora;
    }
}