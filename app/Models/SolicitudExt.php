<?php
// app/Models/SolicitudExt.php
//
// CAMBIO V5:
//   - Agregada columna 'empresa'. Se asocia a la solicitud, no a la persona.
//   - Si es visita: empresa opcional. Si es contratista: empresa obligatoria.
//
// CAMBIO V7 (Sprint 2 - expiración lazy):
//   - Agregado accessor estado_actual que expira la solicitud automáticamente
//     cuando se consulta y ya venció.
//   - Agregado motivoExpiracion() que infiere si expiró por 'no_atendida'
//     o 'vigencia_concluida' sin necesidad de columna extra en BD.
//   - estaVigenteAprobada() ahora usa estado_actual (accessor lazy).

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
        'estado',                       // pendiente | aprobado | rechazado | aprobado_parcial | expirado
        'aprobador_destino_empleado_id',
        'fecha_aprobacion_destino',
        'motivo_rechazo',
    ];

    protected $casts = [
        'fecha_inicio'             => 'datetime',
        'fecha_fin'                => 'datetime',
        'fecha_aprobacion_destino' => 'datetime',
    ];

    // ============================================================
    // RELACIONES
    // ============================================================

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

    // ============================================================
    // EXPIRACIÓN LAZY (Sprint 2 - CAMBIO V7)
    // ============================================================
    //
    // ¿Por qué lazy?
    //   No queremos un cron ni un proceso en background. En lugar de
    //   eso, cada vez que alguien consulta el estado de una solicitud
    //   (escáner, bandeja, show, etc.), este accessor valida si ya
    //   venció y, si es así, la marca como 'expirado' en BD en ese
    //   momento. La siguiente consulta ya la encuentra expirada.

    /**
     * ACCESSOR: estado real de la solicitud, considerando expiración lazy.
     *
     * Uso: $solicitud->estado_actual  (NO $solicitud->estado)
     *
     * Lógica:
     *   - Si ya está 'rechazado' o 'expirado' → devolver tal cual.
     *   - Si fecha_fin < now() y está en pendiente/aprobado/aprobado_parcial:
     *     → actualizar BD a 'expirado'
     *     → invalidar QRs de los externos (los 'autorizado' pasan a 'expirado')
     *     → devolver 'expirado'
     *   - Si no → devolver el estado original.
     */
    /**
     * ACCESSOR: estado real de la solicitud, considerando expiración.
     *
     * FIX Bug 7: Este accessor ahora es "puro" (solo cálculo, sin writes).
     * Antes hacía save() + UPDATE masivo invalidando QRs, con un bug latente:
     * invalidaba QRs de externos DENTRO de planta (el cron sí los respeta).
     *
     * Ahora solo calcula el estado en runtime.
     */
    public function getEstadoActualAttribute(): string
    {
        if (in_array($this->estado, ['rechazado', 'expirado'])) {
            return $this->estado;
        }

        if ($this->fecha_fin && $this->fecha_fin < now()) {
            return 'expirado';
        }

        return $this->estado;
    }

    /**
     * FIX Bug 7: Método explícito para persistir la expiración en BD.
     *
     * Llamar SOLO desde contextos de escritura (ScannerController).
     *
     * FIX Bug latente: Ya NO hace UPDATE masivo ciego. Delega la invalidación
     * de QRs al cron ExpirarSolicitudes, que SÍ respeta externos dentro de planta.
     * Solo marca la solicitud como expirada.
     */
    public function expirarSiVencido(): void
    {
        if (in_array($this->estado, ['rechazado', 'expirado'])) {
            return;
        }

        if ($this->fecha_fin && $this->fecha_fin < now()) {
            $this->estado = 'expirado';
            $this->save();
            // La invalidación de QRs la hace el cron ExpirarSolicitudes,
            // que verifica si el externo está dentro de planta antes de invalidar.
        }
    }

    /**
     * Infiere el motivo de expiración SIN necesidad de una columna extra.
     *
     * Reglas:
     *   - Si no está expirada → NULL
     *   - Si fecha_aprobacion_destino IS NULL → 'no_atendida'
     *     (nadie la aprobó a tiempo)
     *   - Si fecha_aprobacion_destino NOT NULL → 'vigencia_concluida'
     *     (se aprobó, se usó, y la vigencia terminó naturalmente)
     */
    public function motivoExpiracion(): ?string
    {
        if ($this->estado_actual !== 'expirado') {
            return null;
        }

        if (empty($this->fecha_aprobacion_destino)) {
            return 'no_atendida';
        }

        return 'vigencia_concluida';
    }

    /**
     * Etiqueta legible para mostrar en vistas.
     */
    public function getMotivoExpiracionLabelAttribute(): string
    {
        return match($this->motivoExpiracion()) {
            'no_atendida'         => 'No fue atendida a tiempo',
            'vigencia_concluida'  => 'Vigencia concluida',
            default               => '—',
        };
    }

    // ============================================================
    // VALIDACIÓN DE VIGENCIA (para el escáner)
    // ============================================================

    /**
     * ¿Está vigente y aprobada? (para validar acceso en scanner)
     *
     * CAMBIO V7: usa estado_actual (accessor lazy) en vez de estado.
     * Acepta 'aprobado' y 'aprobado_parcial' (los cancelados se filtran
     * con qrActivo()). Rechaza 'expirado' y valida fecha_fin.
     */
    public function estaVigenteAprobada(): bool
    {
        $estado = $this->estado_actual;  // ← accessor lazy
        $ahora = now();

        return in_array($estado, ['aprobado', 'aprobado_parcial'], true)
            && $this->fecha_inicio <= $ahora
            && $this->fecha_fin >= $ahora;
    }
}