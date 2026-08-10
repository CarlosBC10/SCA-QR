<?php
// app/Models/SolicitudExt.php
//
// CAMBIO V5:
//   - Agregada columna 'empresa'. Se asocia a la solicitud, no a la persona.
//   - Si es visita: empresa opcional. Si es contratista: empresa obligatoria.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudExt extends Model
{
    protected $table = 'solicitudes_ext';
    protected $primaryKey = 'id_solicitud_ext';

    protected $fillable = [
        'creador_empleado_id',
        'tipo',                         // visitante | contratista
        'anfitrion_empleado_id',
        'empresa',                      // V5: agregada (opcional para visita, obligatoria para contratista)
        'id_planta_destino',
        'fecha_inicio',
        'fecha_fin',
        'motivo',
        'estado',                       // pendiente | aprobado | rechazado
        'aprobador_destino_empleado_id',
        'fecha_aprobacion_destino',
        'motivo_rechazo',
    ];

    protected $casts = [
        'fecha_inicio'             => 'datetime',
        'fecha_fin'                => 'datetime',
        'fecha_aprobacion_destino' => 'datetime',
    ];

    public function plantaDestino()
    {
        return $this->belongsTo(Planta::class, 'id_planta_destino', 'id_planta');
    }

    public function creador()
    {
        return $this->belongsTo(\App\Models\Sitec\Empleado::class, 'creador_empleado_id', 'id_empleado');
    }

    public function anfitrion()
    {
        return $this->belongsTo(\App\Models\Sitec\Empleado::class, 'anfitrion_empleado_id', 'id_empleado');
    }

    public function aprobadorDestino()
    {
        return $this->belongsTo(\App\Models\Sitec\Empleado::class, 'aprobador_destino_empleado_id', 'id_empleado');
    }

    // N+ externos, cada uno con su propio QR
    public function externos()
    {
        return $this->hasMany(SolicitudExternoDetalle::class, 'id_solicitud_ext', 'id_solicitud_ext');
    }

    public function estaVigenteAprobada(): bool
    {
        // FIX C-1: Aprobaciones parciales también deben dejar pasar a los externos autorizados.
        $ahora = now();
        
        // FIX: Si la BD guarda fecha_fin como DATE (sin hora), Carbon lo lee como 00:00:00.
        // Usamos endOfDay() para que la vigencia dure hasta las 23:59:59 de ese día.
        $fechaFin = $this->fecha_fin ? \Carbon\Carbon::parse($this->fecha_fin)->endOfDay() : null;

        return in_array($this->estado, ['aprobado', 'aprobado_parcial'], true)
            && $this->fecha_inicio <= $ahora
            && $fechaFin >= $ahora;
    }
}