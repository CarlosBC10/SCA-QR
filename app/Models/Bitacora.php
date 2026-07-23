<?php
// app/Models/Bitacora.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bitacora extends Model
{
    protected $table = 'bitacora';
    public const UPDATED_AT = null;

    protected $fillable = [
        'persona_tipo', 'persona_id', 'guardia_id', 'turno_guardia_id',
        'tipo_evento', 'estado_evento', 'origen_escaneo', 'fecha_hora',
        'planta_id', 'revision_exhaustiva', 'anomalia_horario', 'estado_anomalia',
        'observaciones', 'motivo_denegacion',
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
        'revision_exhaustiva' => 'boolean',
        'anomalia_horario' => 'boolean',
    ];

    /**
     * NUEVO: scope para denegados NO vistos por el guardia.
     */
    public function scopeNoVistosGuardia($query)
    {
        return $query->where('visto_guardia', false);
    }

    public function guardia()
    {
        return $this->belongsTo(Guardia::class, 'guardia_id');
    }

    public function turno()
    {
        return $this->belongsTo(TurnoGuardia::class, 'turno_guardia_id');
    }

    public function planta()
    {
        return $this->belongsTo(Planta::class, 'planta_id');
    }

    public function activos()
    {
        return $this->hasMany(BitacoraActivo::class, 'bitacora_id');
    }

    public function hallazgo()
    {
        return $this->hasOne(RevisionHallazgo::class, 'bitacora_id');
    }

    // ============================================================
    // SCOPES OPTIMIZADOS
    // ============================================================

    public function scopeHoy($query)
    {
        return $query->whereDate('fecha_hora', today());
    }

    public function scopeDePlanta($query, int $plantaId)
    {
        return $query->where('planta_id', $plantaId);
    }

    public function scopePendientesRevision($query)
    {
        return $query->where('estado_evento', 'pendiente_revision');
    }

    public function scopeAnomaliasPendientes($query)
    {
        return $query->where('estado_anomalia', 'pendiente');
    }

    public function scopeDenegados($query)
    {
        return $query->where('estado_evento', 'denegado');
    }

    public function scopeNoDenegados($query)
    {
        return $query->where('estado_evento', '!=', 'denegado');
    }

    public function scopeEmpleadoId($query, int $empleadoId)
    {
        return $query->where('persona_tipo', 'empleado')
                     ->where('persona_id', (string) $empleadoId);
    }
}