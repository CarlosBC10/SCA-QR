<?php
// app/Models/Bitacora.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bitacora extends Model
{
    protected $table = 'bitacora';

    // Solo created_at, no updated_at (un evento de bitácora no se modifica)
    public const UPDATED_AT = null;

    // Agregamos 'estado_anomalia' para rastrear si el guardia ya revisó la justificación
    protected $fillable = [
        'persona_tipo', 'persona_id', 'guardia_id', 'turno_guardia_id',
        'tipo_evento', 'estado_evento', 'origen_escaneo', 'fecha_hora', 
        'planta_id', 'revision_exhaustiva', 'anomalia_horario', 'estado_anomalia', 
        'observaciones',
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
        'revision_exhaustiva' => 'boolean',
        'anomalia_horario' => 'boolean',
    ];

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
}