<?php
// app/Models/Notificacion.php
//
// Notificaciones in-app para los usuarios (badge de campana).
//
// CAMBIO V4: 'usuario_id' → 'id_usuario_sca'.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notificacion extends Model
{
    protected $table = 'notificaciones';
    protected $primaryKey = 'id_notificacion';

    protected $fillable = [
        'id_usuario_sca', 'tipo', 'titulo', 'mensaje', 'url', 'leida',
    ];

    protected $casts = ['leida' => 'boolean'];

    public function usuario()
    {
        return $this->belongsTo(UsuarioSca::class, 'id_usuario_sca', 'id_usuario_sca');
    }

    // Scope: notificaciones no leídas
    public function scopeNoLeidas($query)
    {
        return $query->where('leida', false);
    }
}