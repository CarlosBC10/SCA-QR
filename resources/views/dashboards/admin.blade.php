{{-- resources/views/dashboards/admin.blade.php --}}
{{--
    Panel de Admin.
    Muestra bandeja de aprobaciones + gestión de usuarios + historial.

    CAMBIOS V4:
      - Eliminada la referencia a \App\Models\Solicitud (modelo eliminado).
      - Ahora se cuentan pendientes en SolicitudInt + SolicitudExt por separado.
--}}
@extends('layouts.app')

@section('title', 'Panel Admin')

@section('content')
<div class="mb-4">
    <h3 class="fw-bold mb-0">Panel de Administrador</h3>
    <p class="text-muted mb-0">Bienvenido, {{ Auth::user()->nombre_completo }}.</p>
</div>

@php
    // V4: Solicitud eliminado; contar pendientes en solicitudes_int + solicitudes_ext
    //
    // FIX 3-g (Admin): ahora contamos también las solicitudes en 'aprobado_parcial'
    // que siguen esperando revisión de algún aprobador (origen o destino).
    // El Admin ve TODAS las plantas, así que no hay scoping.
    $pendientesInt = \App\Models\SolicitudInt::where(function($q) {
        $q->whereIn('estado', ['pendiente_origen', 'pendiente_destino'])
          ->orWhere(function($q2) {
              $q2->where('estado', 'aprobado_parcial')
                 ->where(function($q3) {
                     $q3->whereNull('fecha_aprobacion_origen')
                        ->orWhereNull('fecha_aprobacion_destino');
                 });
          });
    })->count();
    // Externas: pendiente (una sola aprobación)
    $pendientesExt = \App\Models\SolicitudExt::where('estado', 'pendiente')->count();
    $pendientesCount = $pendientesInt + $pendientesExt;
@endphp

<div class="row g-4 mb-4">
    {{-- Bandeja de aprobaciones --}}
    <div class="col-xl-4 col-md-6">
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

    {{-- Gestión de usuarios --}}
    <div class="col-xl-4 col-md-6">
        <a href="{{ route('usuarios.index') }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 sca-card card-hover" style="border-radius: 16px; border-left: 4px solid #2e7d32 !important;">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-3 p-3 me-3" style="background: rgba(46,125,50,0.15);">
                        <i class="bi bi-people-fill fs-2 text-success"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-bold text-dark">Gestionar Usuarios</h5>
                        <p class="text-muted small mb-0">Cuentas, roles y guardias</p>
                    </div>
                </div>
            </div>
        </a>
    </div>

    {{-- Historial general (solo Admin/Super) --}}
    <div class="col-xl-4 col-md-6">
        <a href="{{ route('solicitudes.historial') }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 sca-card card-hover" style="border-radius: 16px; border-left: 4px solid #17a2b8 !important;">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-3 p-3 me-3" style="background: rgba(23,162,184,0.15);">
                        <i class="bi bi-clock-history fs-2" style="color: #17a2b8;"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-bold text-dark">Historial General</h5>
                        <p class="text-muted small mb-0">Todas las solicitudes</p>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

{{-- Personas dentro (widget reutilizable) --}}
<div class="row g-4">
    <div class="col-lg-8">
        @include('partials._personas_dentro', ['id' => 'admin', 'plantas' => \App\Models\Planta::where('activa', true)->orderBy('nombre')->get()])
    </div>
</div>
@endsection