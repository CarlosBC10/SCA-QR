<?php
// app/Models/EmpleadoExtSca.php
//
// Extensión 1:1 de sitec.empleados. Atributos propios del control de accesos.
//
// CAMBIOS V4:
//   - Agregado 'telefono': teléfono del empleado (para futuras notificaciones).
//   - Agregado 'fecha_ultimo_manual': fecha del último escaneo manual.
//     Necesaria para implementar F11 (1 uso manual por día) sin escanear
//     toda la bitácora del día.
//   - ELIMINADO 'bloqueo_manual': redundante. Se infiere de
//     usos_manuales_restantes = 0.
//   - 'planta_id' → 'id_planta' (FK).
//
// tipo_empleado y id_planta pueden ser NULL intencionalmente:
//   - tipo_empleado NULL = puesto sin clasificar.
//   - id_planta NULL     = sin planta asignada (el guardia la asigna al 1er escaneo).

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmpleadoExtSca extends Model
{
    protected $table = 'empleados_ext_sca';
    protected $primaryKey = 'empleado_id';
    public $incrementing = false;
    protected $keyType = 'int';

    // FIX E: Agregamos 'planta_forzada' a los campos asignables masivamente.
    protected $fillable = [
        'empleado_id', 'tipo_empleado', 'telefono', 'id_planta', 'planta_forzada',
        'usos_manuales_restantes', 'fecha_ultimo_manual',
    ];

    protected $casts = [
        'usos_manuales_restantes' => 'integer',
        'fecha_ultimo_manual'     => 'date',
        // FIX E: Castear planta_forzada a booleano (true/false en PHP, 0/1 en BD)
        'planta_forzada'          => 'boolean',
    ];

    public function planta()
    {
        return $this->belongsTo(Planta::class, 'id_planta', 'id_planta');
    }

    // --- Helper: ¿Está bloqueado para búsquedas manuales? ---
    // Redundante con usos_manuales_restantes = 0, pero útil para legibilidad.
    public function getBloqueadoManualAttribute(): bool
    {
        return $this->usos_manuales_restantes <= 0;
    }

    // --- Helper: ¿Ya hizo un escaneo manual hoy? ---
    public function yaUsoManualHoy(): bool
    {
        return $this->fecha_ultimo_manual !== null
            && $this->fecha_ultimo_manual->isToday();
    }

    // --- Helper: Reiniciar el contador (cuando escanea QR exitosamente) ---
    public function reiniciarUsosManuales(): void
    {
        $this->usos_manuales_restantes = 4;
        $this->fecha_ultimo_manual = null;
        $this->save();
    }
}