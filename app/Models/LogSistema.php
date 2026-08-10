<?php
// app/Models/LogSistema.php
//
// Log de auditoría (SRS OT-01). Solo created_at (no se modifican).
//
// CAMBIOS V4:
//   - 'usuario_id' → 'id_usuario_sca'
//   - ELIMINADO 'tipo_usuario' (la tabla V4 no lo tiene).

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogSistema extends Model
{
    protected $table = 'logs_sistema';
    protected $primaryKey = 'id_log';

    public const UPDATED_AT = null;

    // OJO: sin 'tipo_usuario' — se eliminó del schema V4
    protected $fillable = ['id_usuario_sca', 'accion', 'descripcion', 'ip'];

    public function usuario()
    {
        return $this->belongsTo(UsuarioSca::class, 'id_usuario_sca', 'id_usuario_sca');
    }

    /**
     * Helper: registra una acción de auditoría en una sola línea.
     * Uso:
     *   LogSistema::registrar('LOGIN', 'Inicio de sesión de 1001');
     */
    public static function registrar(string $accion, ?string $descripcion = null): void
    {
        $user = auth()->user();
        if (!$user) return;

        self::create([
            'id_usuario_sca' => $user->id_usuario_sca,
            'accion'         => $accion,
            'descripcion'    => $descripcion,
            'ip'             => request()->ip(),
        ]);
    }
}