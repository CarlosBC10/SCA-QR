<?php
// app/Models/Guardia.php
//
// Guardia externo (SRS RN-09: NO son empleados de sitec).
//
// CAMBIO V5:
//   - La llave primaria (PK) ahora es 'id_guardia' (VARCHAR, ej: "G-0001").
//   - Ya no hay un id_guardias numérico autoincrement.
//   - usuarios_sca.username enlaza directamente con guardias.id_guardia.
//   - 'activo' (bool) se elimina; se usan fecha_alta / fecha_baja.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guardia extends Model
{
    protected $table = 'guardias';
    
    // V5: PK ahora es un string (id_guardia)
    protected $primaryKey = 'id_guardia';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_guardia', 'nombre_completo', 'curp', 'foto', 'id_planta',
        'fecha_alta', 'fecha_baja',
    ];

    protected $casts = [
        'fecha_alta' => 'date',
        'fecha_baja' => 'date',
    ];

    // Accessor: un guardia está activo si tiene fecha_alta y NO tiene fecha_baja
    public function getActivoAttribute(): bool
    {
        return $this->fecha_alta !== null && $this->fecha_baja === null;
    }

    // Da de baja al guardia (soft delete: setea fecha_baja, no borra el registro)
    public function darDeBaja(): void
    {
        $this->fecha_baja = now()->toDateString();
        $this->save();
    }

    public function planta(): BelongsTo
    {
        return $this->belongsTo(Planta::class, 'id_planta', 'id_planta');
    }

    // V5: Relación 1:1 con UsuarioSca (ahora enlazada por id_guardia = username)
    public function usuario()
    {
        return $this->hasOne(UsuarioSca::class, 'username', 'id_guardia');
    }

    public function bitacoras(): HasMany
    {
        return $this->hasMany(Bitacora::class, 'id_guardia', 'id_guardia');
    }

    public function turnos(): HasMany
    {
        return $this->hasMany(TurnoGuardia::class, 'id_guardia', 'id_guardia');
    }
}