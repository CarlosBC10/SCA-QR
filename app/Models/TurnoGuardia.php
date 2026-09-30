<?php
// app/Models/TurnoGuardia.php
//
// CAMBIO V5: id_guardia ahora es VARCHAR (apunta a guardias.id_guardia).

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TurnoGuardia extends Model
{
    protected $table = 'turnos_guardia';
    protected $primaryKey = 'id_turno_guardia';

    protected $fillable = [
        'id_guardia', 'id_planta', 'modo',
        'fecha_hora_inicio', 'fecha_hora_fin',
    ];

    protected $casts = [
        'fecha_hora_inicio' => 'datetime',
        'fecha_hora_fin'    => 'datetime',
    ];

    // Relación con Guardia ahora por id_guardia (string)
    public function guardia()
    {
        return $this->belongsTo(Guardia::class, 'id_guardia', 'id_guardia');
    }

    public function planta()
    {
        return $this->belongsTo(Planta::class, 'id_planta', 'id_planta');
    }

    public function getActivoAttribute(): bool
    {
        return $this->fecha_hora_fin === null;
    }

    public function scopeActivos($query)
    {
        return $query->whereNull('fecha_hora_fin');
    }

    // V5: El parámetro ahora es string (id_guardia)
    public function scopeDeGuardia($query, string $guardiaId)
    {
        return $query->where('id_guardia', $guardiaId);
    }
}