<?php
// app/Models/TurnoGuardia.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TurnoGuardia extends Model
{
    protected $table = 'turnos_guardia';

    // Incluimos 'modo' para saber si el kiosco es de entradas, salidas o mixto
    protected $fillable = [
        'guardia_id', 'planta_id', 'modo', 'fecha_hora_inicio', 'fecha_hora_fin',
    ];

    protected $casts = [
        'fecha_hora_inicio' => 'datetime',
        'fecha_hora_fin'    => 'datetime',
    ];

    public function guardia()
    {
        return $this->belongsTo(Guardia::class, 'guardia_id');
    }

    public function planta()
    {
        return $this->belongsTo(Planta::class, 'planta_id');
    }

    public function bitacoras()
    {
        return $this->hasMany(Bitacora::class, 'turno_guardia_id');
    }

    public function getActivoAttribute(): bool
    {
        return $this->fecha_hora_fin === null;
    }
}