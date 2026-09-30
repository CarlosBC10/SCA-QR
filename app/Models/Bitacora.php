<?php
// app/Models/Bitacora.php
//
// CAMBIO V5:
//   - Unificación de empleado_id e id_externos en 'persona_id' (VARCHAR).
//   - Si persona_id no tiene prefijo, es un empleado (ej: "1001").
//   - Si persona_id empieza con "E-", es un externo (ej: "E-5").
//   - Esto elimina los NULLs de la tabla.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bitacora extends Model
{
    protected $table = 'bitacora';
    protected $primaryKey = 'id_bitacora';
    public const UPDATED_AT = null; // Solo created_at

    protected $fillable = [
        'persona_id',           // V5: unifica empleado_id e id_externos
        'id_guardia',
        'tipo_evento',          // entrada | salida
        'estado_evento',        // autorizado | pendiente_revision | revision_horario | denegado
        'motivo_denegacion',
        'fecha_hora',
        'id_planta',
        'revision_exhaustiva',
        'id_justificacion',
        // V10.3 · CAMBIO #24: FK LÓGICO a la solicitud que autorizó el
        // acceso. Sin FK física (decisión de diseño: bitacora es
        // append-only y debe soportar millones de filas). Ruteo según
        // persona_id (misma convención polimórfica):
        //   persona_id "E-x"  → solicitudes_ext.id_solicitud_ext
        //   persona_id numérico → solicitudes_int.id_solicitud_int
        // NULL = acceso estándar (sin permiso) o registro histórico
        // previo a V10.3. Columna creada con el Script 1 (Etapa 1 BD).
        'id_solicitud',
    ];

    protected $casts = [
        'fecha_hora'          => 'datetime',
        'revision_exhaustiva' => 'boolean',
        'id_guardia'          => 'string', // V5: ahora es VARCHAR
        'id_planta'           => 'integer',
        'id_justificacion'    => 'integer',
    ];

    // ============================================================
    // RELACIONES
    // ============================================================

    public function guardia()
    {
        return $this->belongsTo(Guardia::class, 'id_guardia', 'id_guardia');
    }

    public function planta()
    {
        return $this->belongsTo(Planta::class, 'id_planta', 'id_planta');
    }

    // V5: Relación polimórfica manual para externos
    public function externo()
    {
        // Solo si persona_id empieza con 'E-', extraemos el ID y buscamos en externos
        if (str_starts_with($this->persona_id, 'E-')) {
            return Externo::find(substr($this->persona_id, 2));
        }
        return null;
    }

    public function justificacion()
    {
        return $this->belongsTo(JustificacioneHorario::class, 'id_justificacion', 'id_justificacion');
    }

    // ============================================================
    // V10.3 · CAMBIO #24: resolución del FK lógico id_solicitud
    // ============================================================
    // Devuelve la solicitud referenciada por bitacora.id_solicitud,
    // ruteando por el prefijo de persona_id:
    //   "E-x"     → SolicitudExt (id_solicitud_ext)
    //   numérico  → SolicitudInt (id_solicitud_int)
    // NULL si no hay referencia o si el id no existe (huérfano — el
    // lector de bitácora lo marca como "inconsistente", no lo oculta).
    // No se usa relación Eloquent polimórfica real porque el destino
    // depende del valor de OTRA columna (persona_id), no del formato
    // del id mismo.
    public function solicitudReferenciada(): ?Model
    {
        if (empty($this->id_solicitud) || !preg_match('/^\d+$/', trim((string) $this->id_solicitud))) {
            return null;
        }

        if (str_starts_with((string) $this->persona_id, 'E-')) {
            return \App\Models\SolicitudExt::find((int) $this->id_solicitud);
        }

        return \App\Models\SolicitudInt::find((int) $this->id_solicitud);
    }

    public function hallazgo()
    {
        return $this->hasOne(RevisionHallazgo::class, 'id_bitacora', 'id_bitacora');
    }

    // ============================================================
    // SCOPES (Para consultas rápidas)
    // ============================================================

    public function scopeHoy($query)
    {
        return $query->whereDate('fecha_hora', today());
    }

    public function scopeDePlanta($query, int $plantaId)
    {
        return $query->where('id_planta', $plantaId);
    }

    public function scopePendientesRevision($query)
    {
        return $query->where('estado_evento', 'pendiente_revision');
    }

    public function scopeRevisionHorario($query)
    {
        return $query->where('estado_evento', 'revision_horario');
    }

    public function scopePendientes($query)
    {
        return $query->whereIn('estado_evento', ['pendiente_revision', 'revision_horario']);
    }

    public function scopeDenegados($query)
    {
        return $query->where('estado_evento', 'denegado');
    }

    public function scopeNoDenegados($query)
    {
        return $query->where('estado_evento', '!=', 'denegado');
    }

    // V5: Scopes unificados para persona_id
    public function scopeDeEmpleado($query, int $empleadoId)
    {
        return $query->where('persona_id', (string) $empleadoId);
    }

    public function scopeDeExterno($query, int $externoId)
    {
        return $query->where('persona_id', 'E-' . $externoId);
    }

    // ============================================================
    // HELPERS
    // ============================================================

    /** ¿Este evento es de un empleado (vs externo)? */
    public function esEmpleado(): bool
    {
        return !str_starts_with($this->persona_id, 'E-');
    }

    public function estaPendiente(): bool
    {
        return in_array($this->estado_evento, ['pendiente_revision', 'revision_horario']);
    }

    public function fueAutorizado(): bool
    {
        return $this->estado_evento === 'autorizado';
    }
}