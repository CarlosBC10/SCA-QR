<?php
// app/Models/Planta.php
//
// Representa la tabla plantas.
// Catálogo fijo de las 3 plantas físicas de Fruitex: Jardin, Sauces, Partidas.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Planta extends Model
{
    protected $table = 'plantas';

    protected $fillable = ['nombre', 'direccion', 'activa'];

    // 'activa' se lee como boolean (true/false), no 1/0
    protected $casts = ['activa' => 'boolean'];

    // --- Relaciones: una planta tiene muchos... ---
    public function guardias()     { return $this->hasMany(Guardia::class, 'planta_id'); }
    public function empleadosExt() { return $this->hasMany(EmpleadoExtSca::class, 'planta_id'); }
    public function personas()     { return $this->hasMany(Persona::class, 'planta_id'); }
    public function solicitudes()  { return $this->hasMany(Solicitud::class, 'planta_destino_id'); }
    public function bitacoras()    { return $this->hasMany(Bitacora::class, 'planta_id'); }
}