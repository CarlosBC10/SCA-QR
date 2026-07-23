<?php
// app/Models/UsuarioSca.php

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

    /**
     * OPTIMIZADO: cachea el empleado en la instancia para no repetir queries.
     */
    public function getNombreCompletoAttribute(): string
    {
        if ($this->guardia_id) {
            return $this->guardia?->nombre_completo ?? 'Guardia sin nombre';
        }
        // Cargar empleado una sola vez por instancia
        if (!array_key_exists('_empleado_cached', $this->relations)) {
            $emp = Empleado::find($this->username);
            $this->setRelation('_empleado_cached', $emp);
        }
        return $this->getRelation('_empleado_cached')?->nombre_completo ?? 'Empleado no encontrado';
    }

    /**
     * OPTIMIZADO: planta derivada de sitec con caché del mapeo.
     */
    public function getPlantaIdAttribute(): ?int
    {
        if ($this->guardia_id) {
            return $this->guardia?->planta_id;
        }
        // Cargar empleado cacheado
        if (!array_key_exists('_empleado_cached', $this->relations)) {
            $this->setRelation('_empleado_cached', Empleado::find($this->username));
        }
        return $this->getRelation('_empleado_cached')?->plantaId();
    }

    public function getPlantaNombreAttribute(): string
    {
        if ($this->guardia_id && $this->guardia?->planta) {
            return $this->guardia->planta->nombre;
        }
        return \App\Services\PlantaCacheService::nombrePlanta($this->planta_id);
    }

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