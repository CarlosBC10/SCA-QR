<?php
// app/Models/UsuarioSca.php
//
// Tabla usuarios_sca (autenticación web).
// Username:
//   - Empleados: id_empleado de sitec (ej: '1001')
//   - Guardias: 'G-XXXXX' (5 dígitos)

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Sitec\Empleado;

class UsuarioSca extends Authenticatable
{
    use Notifiable;

    protected $table = 'usuarios_sca';
    protected $fillable = ['username', 'password', 'id_rol', 'guardia_id', 'activo'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['activo' => 'boolean'];

    public function rol()
    {
        return $this->belongsTo(RolSca::class, 'id_rol');
    }

    public function guardia()
    {
        return $this->belongsTo(Guardia::class, 'guardia_id');
    }

    public function logs()
    {
        return $this->hasMany(LogSistema::class, 'usuario_id');
    }

    // Nombre completo (guardia o empleado)
    public function getNombreCompletoAttribute(): string
    {
        if ($this->guardia_id) {
            return $this->guardia?->nombre_completo ?? 'Guardia sin nombre';
        }
        $empleado = Empleado::find($this->username);
        return $empleado?->nombre_completo ?? 'Empleado no encontrado';
    }

    // Nombre de la planta
    public function getPlantaNombreAttribute(): string
    {
        if ($this->guardia_id && $this->guardia?->planta) {
            return $this->guardia->planta->nombre;
        }
        $ext = EmpleadoExtSca::with('planta')->find($this->username);
        if ($ext && $ext->planta) {
            return $ext->planta->nombre;
        }
        return 'Sin planta';
    }

    /**
     * NUEVO en M2: Obtiene el ID de la planta asignada al usuario.
     * - Guardia: guardias.planta_id
     * - Empleado: empleados_ext_sca.planta_id
     *
     * Se usa para el scoping del Aprobador (solo ve/edita usuarios de su planta).
     */
    public function getPlantaIdAttribute(): ?int
    {
        if ($this->guardia_id) {
            return $this->guardia?->planta_id;
        }
        $ext = EmpleadoExtSca::find($this->username);
        return $ext?->planta_id;
    }

    // Helpers de rol
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