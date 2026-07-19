<?php
// app/Models/LogSistema.php
//
// Representa la tabla logs_sistema.
// Registra acciones relevantes de los usuarios (login, logout, crear
// solicitud, aprobar, rechazar, etc.) para auditoría (SRS OT-01).
//
// SOLO created_at, no updated_at (los logs no se modifican).

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogSistema extends Model
{
    protected $table = 'logs_sistema';

    public const UPDATED_AT = null;

    protected $fillable = ['usuario_id', 'tipo_usuario', 'accion', 'descripcion', 'ip'];

    public function usuario() { return $this->belongsTo(UsuarioSca::class, 'usuario_id'); }

    /**
     * Helper estático: registra una acción de auditoría en una sola línea.
     * Toma el usuario actual automáticamente con auth()->user().
     *
     * Uso:
     *   LogSistema::registrar('LOGIN', 'Inicio de sesión de 1001');
     *   LogSistema::registrar('CREAR_SOLICITUD', 'Solicitud #45 creada');
     */
    public static function registrar(string $accion, ?string $descripcion = null): void
    {
        $user = auth()->user();
        if (!$user) return;   // si no hay sesión, no logueamos

        self::create([
            'usuario_id'   => $user->id,
            'tipo_usuario' => $user->guardia_id ? 'guardia' : 'empleado',
            'accion'       => $accion,
            'descripcion'  => $descripcion,
            'ip'           => request()->ip(),
        ]);
    }
}