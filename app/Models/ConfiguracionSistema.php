<?php

// app/Models/ConfiguracionSistema.php
//
// Modelo de la tabla clave/valor de configuración operativa del
// sistema (probabilidad F10, tolerancias de horario, exentos, etc.).
//
// ⚠️ NADIE debe leer/escribir este modelo directamente desde
// controladores o servicios: TODO pasa por ConfigSistemaService,
// que agrega: defaults, validación de rangos, cache y auditoría
// (LogSistema). Usarlo directo evita el cache (valores stale) y
// el log de auditoría.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfiguracionSistema extends Model
{
    protected $table = 'configuracion_sistema';
    protected $primaryKey = 'id_configuracion';

    protected $fillable = ['clave', 'valor', 'actualizado_por'];

    // Relación con el usuario que modificó el valor. Solo informativa
    // (para la vista: "Modificado por jperez el 18/09/2026 15:42").
    // Sin FK física en la migración — ver comentario ahí.
    public function actualizador(): BelongsTo
    {
        return $this->belongsTo(UsuarioSca::class, 'actualizado_por', 'id_usuario_sca');
    }
}