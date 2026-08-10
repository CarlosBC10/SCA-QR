<?php
// app/Models/UsuarioSca.php
//
// CAMBIO V5:
//   - Eliminada columna 'id_guardias'.
//   - La relación con Guardia ahora es por 'username' (que coincide con guardias.id_guardia).
//   - Si el rol es 'Guardia', el username tendrá formato "G-0001".

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Sitec\Empleado;

class UsuarioSca extends Authenticatable
{
    use Notifiable;

    protected $table = 'usuarios_sca';
    protected $primaryKey = 'id_usuario_sca';
    public $timestamps = false; // La tabla no tiene created_at/updated_at

    protected $fillable = [
        'username', 'email_emp', 'password', 'id_rol',
        'remember_token', 'fecha_alta', 'fecha_baja',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'fecha_alta' => 'date',
        'fecha_baja' => 'date',
    ];

    public function getActivoAttribute(): bool
    {
        return $this->fecha_alta !== null && $this->fecha_baja === null;
    }

    // ¿Es un usuario guardia? (su rol es Guardia)
    public function esGuardia(): bool
    {
        return $this->rol && $this->rol->nombre === 'Guardia';
    }

    // Devuelve el id_empleado de sitec si el usuario es interno
    public function empleadoId(): ?int
    {
        if ($this->esGuardia()) return null;
        $id = (int) $this->username;
        return $id > 0 ? $id : null;
    }

    public function darDeBaja(): void
    {
        $this->fecha_baja = now()->toDateString();
        $this->save();
    }

    public function rol()
    {
        return $this->belongsTo(RolSca::class, 'id_rol', 'id_rol');
    }

    // V5: Relación con Guardia ahora por 'username' apuntando a 'id_guardia'
    public function guardia()
    {
        return $this->belongsTo(Guardia::class, 'username', 'id_guardia');
    }

    public function logs()
    {
        return $this->hasMany(LogSistema::class, 'id_usuario_sca', 'id_usuario_sca');
    }

    public function notificaciones()
    {
        return $this->hasMany(Notificacion::class, 'id_usuario_sca', 'id_usuario_sca');
    }

    // --- Accessors para obtener datos del empleado o guardia según el caso ---

    public function getNombreCompletoAttribute(): string
    {
        if ($this->esGuardia()) {
            return $this->guardia?->nombre_completo ?? 'Guardia sin nombre';
        }
        // Cachea el empleado para evitar N+1
        if (!array_key_exists('_empleado_cached', $this->relations)) {
            $emp = Empleado::find($this->username);
            $this->setRelation('_empleado_cached', $emp);
        }
        return $this->getRelation('_empleado_cached')?->nombre_completo ?? 'Empleado no encontrado';
    }

    public function getPlantaIdAttribute(): ?int
    {
        if ($this->esGuardia()) {
            return $this->guardia?->id_planta;
        }
        if (!array_key_exists('_empleado_cached', $this->relations)) {
            $this->setRelation('_empleado_cached', Empleado::find($this->username));
        }
        return $this->getRelation('_empleado_cached')?->plantaId();
    }

    public function getPlantaNombreAttribute(): string
    {
        if ($this->esGuardia() && $this->guardia?->planta) {
            return $this->guardia->planta->nombre;
        }
        return \App\Services\PlantaCacheService::nombrePlanta($this->planta_id);
    }

    // --- Helpers de rol ---
    public function hasRole(string $rol): bool
    {
        return $this->rol?->nombre === $rol;
    }
    public function isSuperadmin(): bool { return $this->hasRole('Superadmin'); }
    public function isAdmin(): bool      { return $this->hasRole('Admin'); }
    public function isAprobador(): bool  { return $this->hasRole('Aprobador'); }
    public function isEmpleado(): bool   { return $this->hasRole('Empleado'); }
    public function isGuardia(): bool    { return $this->hasRole('Guardia'); }
}