<?php
// app/Models/Solicitud.php
//
// MODELO: Solicitud
// TABLA: solicitudes
//
// CAMBIO CLAVE:
// Agregamos 'planta_origen_id' al $fillable y creamos la relación plantaOrigen().
// 
// ¿POR QUÉ?
// En el flujo de Interplantas, el Aprobador de Origen necesita saber si la solicitud
// sale de su planta. Si no guardamos la planta de origen al momento de crear la solicitud,
// y el empleado cambia de planta después, la solicitud se asignaría al Aprobador equivocado.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Solicitud extends Model
{
    protected $table = 'solicitudes';

    protected $fillable = [
        'creador_empleado_id', 'planta_origen_id', 'tipo', 'persona_id',
        'planta_destino_id', 'fecha_inicio', 'fecha_fin', 'motivo', 'estado',
        'aprobador_origen_empleado_id', 'aprobador_destino_empleado_id',
        'fecha_aprobacion_origen', 'fecha_aprobacion_destino', 'motivo_rechazo',
    ];

    protected $casts = [
        'fecha_inicio'             => 'datetime',
        'fecha_fin'                => 'datetime',
        'fecha_aprobacion_origen'  => 'datetime',
        'fecha_aprobacion_destino' => 'datetime',
    ];

    public function persona()
    {
        return $this->belongsTo(Persona::class, 'persona_id');
    }

    // NUEVO: relación con la planta de origen (para interplantas)
    public function plantaOrigen()
    {
        return $this->belongsTo(Planta::class, 'planta_origen_id');
    }

    public function plantaDestino()
    {
        return $this->belongsTo(Planta::class, 'planta_destino_id');
    }

    public function empleados()
    {
        return $this->hasMany(SolicitudEmpleado::class, 'solicitud_id');
    }

    public function credencial()
    {
        return $this->hasOne(Credencial::class, 'solicitud_id');
    }
}