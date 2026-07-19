<?php
// app/Models/Sitec/Turno.php
//
// Representa sitec.turnos (solo lectura).
// Catálogo de turnos (1º, 2º, 3º, Mixto).
// Lo usamos para:
//   1. Mostrar el turno del empleado en su perfil
//   2. Validar horario al escanear QR (M3): ¿está dentro de su turno?
//      Con tolerancia de 15 min para no bloquear por llegadas tempranas/salidas tardías.

namespace App\Models\Sitec;

use Illuminate\Database\Eloquent\Model;

class Turno extends Model
{
    protected $connection = 'sitec';
    protected $table = 'turnos';

    protected $primaryKey = 'id_turno';
    public $timestamps = true;
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = ['nombre', 'entrada', 'salida'];

    // entrada y salida son TIME en MySQL, se leen como string "HH:MM:SS"
    // No las casteamos a datetime porque Carbon no maneja bien TIME puro.

    public function empleados()
    {
        return $this->hasMany(Empleado::class, 'id_turno');
    }
}