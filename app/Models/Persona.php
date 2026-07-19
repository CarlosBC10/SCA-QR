<?php
// app/Models/Persona.php
//
// MODELO: Persona
// TABLA: personas
//
// CAMBIO CLAVE (NORMALIZACIÓN 3FN):
// Quitamos planta_id, motivo, tiempo_autorizado_inicio y tiempo_autorizado_fin.
//
// ¿POR QUÉ?
// Esos son atributos de la *visita* (que vive en `solicitudes`), no de la *persona*.
// Si el Sr. Juan Pérez viene en enero a Jardín y en septiembre a Sauces,
// al tener esos campos aquí, sobrescribiríamos sus datos y perderíamos historial.
// Ahora, aquí solo guardamos sus datos inherentes (nombre, teléfono, empresa).

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Persona extends Model
{
    protected $table = 'personas';

    // Solo datos inherentes a la persona. 
    // Los datos de la visita están en la tabla 'solicitudes'.
    protected $fillable = [
        'nombre_completo', 'telefono', 'tipo', 'empresa',
    ];

    public function solicitudes()
    {
        return $this->hasMany(Solicitud::class, 'persona_id');
    }
}