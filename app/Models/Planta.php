<?php
// app/Models/Planta.php
//
// Catálogo de plantas físicas (Jardin, Sauces, Partidas).
// PK: id_planta. Sin cambios estructurales; solo alineamos las relaciones
// a los nuevos nombres de FK (id_planta en lugar de planta_id).

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Planta extends Model
{
    protected $table = 'plantas';
    protected $primaryKey = 'id_planta';

    protected $fillable = ['nombre', 'direccion', 'activa'];
    protected $casts = ['activa' => 'boolean'];

    // Guardias asignados a esta planta
    public function guardias()
    {
        return $this->hasMany(Guardia::class, 'id_planta', 'id_planta');
    }

    // Extensiones SCA de empleados cuya planta derivada es esta
    public function empleadosExt()
    {
        return $this->hasMany(EmpleadoExtSca::class, 'id_planta', 'id_planta');
    }

    // Solicitudes internas donde esta planta es origen
    public function solicitudesIntOrigen()
    {
        return $this->hasMany(SolicitudInt::class, 'id_planta_origen', 'id_planta');
    }

    // Solicitudes internas donde esta planta es destino
    public function solicitudesIntDestino()
    {
        return $this->hasMany(SolicitudInt::class, 'id_planta_destino', 'id_planta');
    }

    // Solicitudes externas donde esta planta es destino (la visita)
    public function solicitudesExtDestino()
    {
        return $this->hasMany(SolicitudExt::class, 'id_planta_destino', 'id_planta');
    }

    // Eventos de bitácora registrados en esta planta
    public function bitacoras()
    {
        return $this->hasMany(Bitacora::class, 'id_planta', 'id_planta');
    }
}