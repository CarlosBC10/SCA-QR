<?php
// app/Models/Guardia.php
//
// Representa la tabla guardias.
// Los guardias son personal EXTERNO a la empresa, no están en sitec.empleados.
// Tienen su propia tabla (SRS RN-09).
//
// El username del guardia se ingresa manualmente al alta en formato
// 'G-XXXXX' (5 dígitos) y se guarda en usuarios_sca.username, no aquí.
// Esta tabla solo guarda los datos personales del guardia.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guardia extends Model
{
    protected $table = 'guardias';

    // SIN numero_guardia. El username se arma en el controlador al alta.
    protected $fillable = ['nombre_completo', 'planta_id', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    // --- Relaciones ---
    public function planta()    { return $this->belongsTo(Planta::class, 'planta_id'); }
    public function usuario()   { return $this->hasOne(UsuarioSca::class, 'guardia_id'); }
    public function bitacoras() { return $this->hasMany(Bitacora::class, 'guardia_id'); }
}