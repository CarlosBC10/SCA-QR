{{-- resources/views/dashboards/aprobador.blade.php --}}
{{--
    Panel de Aprobador.
    Muestra bandeja de aprobaciones (solo de su planta) + nueva solicitud.

    CAMBIOS V4:
      - Eliminada la referencia a \App\Models\Solicitud (modelo eliminado).
      - Ahora se cuentan pendientes en SolicitudInt + SolicitudExt, filtrados por
        la planta del aprobador (id_planta_origen / id_planta_destino).
      - FKs renombradas: planta_origen_id → id_planta_origen, etc.
--}}
@extends('layouts.app')

@section('title', 'Panel Aprobador')

@section('content')
<div class="mb-4">
    <h3 class="fw-bold mb-0">Panel de Aprobador</h3>
    <p class="text-muted mb-0">Bienvenido, {{ Auth::user()->nombre_completo }}.</p>
</div>

@php
    // V4: Solicitud eliminado; contar pendientes en solicitudes_int + solicitudes_ext
    // Scoping: solo los pendientes de la planta del aprobador.
    //
    // FIX 3-a: ahora contamos también las solicitudes en 'aprobado_parcial'
    // que siguen esperando revisión de este aprobador:
    //   - Si falta aprobación de origen  → fecha_aprobacion_origen IS NULL
    //   - Si falta aprobación de destino → fecha_aprobacion_destino IS NULL
    $plantaId = Auth::user()->planta_id;

    // Pendientes en origen: pendiente_origen, O aprobado_parcial sin aprobar origen
    $pendientesOrigen = \App\Models\SolicitudInt::where('id_planta_origen', $plantaId)
        ->where(function($q) {
            $q->where('estado', 'pendiente_origen')
              ->orWhere(function($q2) {
                  $q2->where('estado', 'aprobado_parcial')
                     ->whereNull('fecha_aprobacion_origen');
              });
        })->count();

    // Pendientes en destino: pendiente_destino, O aprobado_parcial sin aprobar destino
    $pendientesDestino = \App\Models\SolicitudInt::where('id_planta_destino', $plantaId)
        ->where(function($q) {
            $q->where('estado', 'pendiente_destino')
              ->orWhere(function($q2) {
                  $q2->where('estado', 'aprobado_parcial')
                     ->whereNull('fecha_aprobacion_destino');
              });
        })->count();

    // Externas: pendiente (si es su planta destino)
    $pendientesExt = \App\Models\SolicitudExt::where('id_planta_destino', $plantaId)
        ->where('estado', 'pendiente')->count();

    $pendientesCount = $pendientesOrigen + $pendientesDestino + $pendientesExt;
@endphp

<div class="row g-4 mb-4">
    {{-- Bandeja de aprobaciones (solo de su planta) --}}
    <div class="col-md-6">
        <a href="{{ route('solicitudes.bandeja') }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 sca-card card-hover" style="border-radius: 16px; border-left: 4px solid #f57c00 !important;">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-3 p-3 me-3" style="background: rgba(245,124,0,0.15);">
                        <i class="bi bi-inbox-fill fs-2" style="color: #f57c00;"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-bold text-dark">Bandeja de Aprobaciones</h5>
                        <p class="text-muted small mb-0">
                            @if($pendientesCount > 0)
                                <span class="badge bg-danger">{{ $pendientesCount }} pendientes</span>
                            @else
                                <span class="badge bg-success">Al día</span>
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        </a>
    </div>

    {{-- Nueva solicitud --}}
    <div class="col-md-6">
        <a href="{{ route('solicitudes.create') }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 sca-card card-hover" style="border-radius: 16px; border-left: 4px solid #2e7d32 !important;">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-3 p-3 me-3" style="background: rgba(46,125,50,0.15);">
                        <i class="bi bi-plus-circle-fill fs-2 text-success"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-bold text-dark">Nueva Solicitud</h5>
                        <p class="text-muted small mb-0">Crear solicitud de acceso</p>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

{{-- Personas dentro (sin selector de planta; el aprobador solo ve su planta) --}}
<div class="row g-4">
    <div class="col-lg-8">
        @include('partials._personas_dentro', ['id' => 'aprobador'])
    </div>
</div>
@endsection