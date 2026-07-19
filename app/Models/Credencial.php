<?php
// app/Models/Credencial.php
//
// Representa la tabla credenciales.
// Almacena los códigos QR generados para visitantes y contratistas.
// Se crea un registro cuando una solicitud de tipo visita/contratista
// es aprobada. El QR es único (UUID o hash) para evitar falsificaciones (SE-05).

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Credencial extends Model
{
    protected $table = 'credenciales';

    protected $fillable = ['solicitud_id', 'codigo_qr', 'tipo', 'fecha_expiracion', 'activa'];

    protected $casts = [
        'fecha_expiracion' => 'datetime',
        'activa'           => 'boolean',
    ];

    public function solicitud() { return $this->belongsTo(Solicitud::class, 'solicitud_id'); }
}