{{-- resources/views/solicitudes/hub.blade.php --}}
@extends('layouts.app')

@section('title', 'Solicitudes')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-0 fw-bold">Módulo de Solicitudes</h1>
    <p class="text-muted mb-0">Seleccione una opción para continuar</p>
</div>

<div class="row g-4">

    {{-- TARJETA: BANDEJA DE APROBACIONES (Solo Aprobador, Admin, Superadmin) --}}
    @if($user->isAprobador() || $user->isAdmin() || $user->isSuperadmin())
    <div class="col-md-4">
        <a href="{{ route('solicitudes.bandeja') }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 sca-card card-hover" style="border-radius: 16px; border-top: 4px solid #f57c00 !important;">
                <div class="card-body p-4 text-center d-flex flex-column align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mb-3" 
                         style="width: 60px; height: 60px; background: rgba(245,124,0,0.15);">
                        <i class="bi bi-inbox-fill fs-2" style="color: #f57c00;"></i>
                    </div>
                    <h5 class="fw-bold mb-1">Bandeja de Aprobaciones</h5>
                    <p class="text-muted small mb-2">Revise y apruebe las solicitudes pendientes</p>
                    @if($pendientesAprobar > 0)
                    <span class="badge bg-danger rounded-pill px-3 py-2">
                        {{ $pendientesAprobar }} pendiente{{ $pendientesAprobar == 1 ? '' : 's' }}
                    </span>
                    @else
                    <span class="badge bg-success rounded-pill px-3 py-2">Al día</span>
                    @endif
                </div>
            </div>
        </a>
    </div>
    @endif

    {{-- TARJETA: NUEVA SOLICITUD (Para todos) --}}
    <div class="col-md-4">
        <a href="{{ route('solicitudes.create') }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 sca-card card-hover" style="border-radius: 16px; border-top: 4px solid #2e7d32 !important;">
                <div class="card-body p-4 text-center d-flex flex-column align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mb-3" 
                         style="width: 60px; height: 60px; background: rgba(46,125,50,0.15);">
                        <i class="bi bi-plus-circle-fill fs-2 text-success"></i>
                    </div>
                    <h5 class="fw-bold mb-1">Nueva Solicitud</h5>
                    <p class="text-muted small mb-2">
                        @if($user->isEmpleado())
                            Pase de salida o interplantas
                        @else
                            Crear cualquier tipo de solicitud
                        @endif
                    </p>
                </div>
            </div>
        </a>
    </div>

    {{-- TARJETA: HISTORIAL / MIS SOLICITUDES (Para todos) --}}
    <div class="col-md-4">
        <a href="{{ route('solicitudes.historial') }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 sca-card card-hover" style="border-radius: 16px; border-top: 4px solid #17a2b8 !important;">
                <div class="card-body p-4 text-center d-flex flex-column align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mb-3" 
                         style="width: 60px; height: 60px; background: rgba(23,162,184,0.15);">
                        <i class="bi bi-clock-history fs-2" style="color: #17a2b8;"></i>
                    </div>
                    <h5 class="fw-bold mb-1">
                        @if($user->isEmpleado())
                            Mis Solicitudes
                        @elseif($user->isAprobador())
                            Solicitudes de mi Planta
                        @else
                            Todas las Solicitudes
                        @endif
                    </h5>
                    <p class="text-muted small mb-2">Consulte el historial y estado de solicitudes</p>
                </div>
            </div>
        </a>
    </div>

</div>
@endsection