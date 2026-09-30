{{-- resources/views/solicitudes/hub.blade.php --}}
@extends('layouts.app')

@section('title', 'Solicitudes')

@section('content')
<div class="sca-page-head legacy-page-head">
    <div>
    <div class="sca-page-eyebrow"><i class="bi bi-arrow-left-right" aria-hidden="true"></i> Operación</div>
    <h1 class="sca-page-title">Solicitudes</h1>
    <p class="sca-page-subtitle">Elige la acción que necesitas realizar.</p>
    </div>
</div>

<div class="row g-4">

    {{-- TARJETA: BANDEJA DE APROBACIONES (Solo Aprobador, Admin, Superadmin) --}}
    @if(Auth::user()->isAprobador() || Auth::user()->isAdmin() || Auth::user()->isSuperadmin())
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
                        @if(Auth::user()->isEmpleado())
                            {{-- V10.3 · CAMBIO #36: texto actualizado con el nuevo tipo --}}
                            Pase de salida, interplantas o permiso de entrada
                        @else
                            Crear cualquier tipo de solicitud
                        @endif
                    </p>
                </div>
            </div>
        </a>
    </div>

    {{-- TARJETA: MIS SOLICITUDES (Para todos) --}}
    <div class="col-md-4">
        <a href="{{ route('solicitudes.mis') }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 sca-card card-hover" style="border-radius: 16px; border-top: 4px solid #17a2b8 !important;">
                <div class="card-body p-4 text-center d-flex flex-column align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mb-3" 
                         style="width: 60px; height: 60px; background: rgba(23,162,184,0.15);">
                        <i class="bi bi-clock-history fs-2" style="color: #17a2b8;"></i>
                    </div>
                    <h5 class="fw-bold mb-1">Mis Solicitudes</h5>
                    <p class="text-muted small mb-2">Consulte las solicitudes que ha creado</p>
                </div>
            </div>
        </a>
    </div>

    {{-- TARJETA: MIS APROBACIONES (Solo Aprobador, Admin, Superadmin) --}}
    @if(Auth::user()->isAprobador() || Auth::user()->isAdmin() || Auth::user()->isSuperadmin())
    <div class="col-md-4">
        <a href="{{ route('solicitudes.mis-aprobaciones') }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 sca-card card-hover" style="border-radius: 16px; border-top: 4px solid #6f42c1 !important;">
                <div class="card-body p-4 text-center d-flex flex-column align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mb-3" 
                         style="width: 60px; height: 60px; background: rgba(111,66,193,0.15);">
                        <i class="bi bi-check2-square fs-2" style="color: #6f42c1;"></i>
                    </div>
                    <h5 class="fw-bold mb-1">Mis Aprobaciones</h5>
                    <p class="text-muted small mb-2">Solicitudes que ha aprobado o rechazado</p>
                </div>
            </div>
        </a>
    </div>
    @endif

    {{-- TARJETA: HISTORIAL GENERAL (Solo Admin, Superadmin) --}}
    @if(Auth::user()->isAdmin() || Auth::user()->isSuperadmin())
    <div class="col-md-4">
        <a href="{{ route('solicitudes.historial') }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 sca-card card-hover" style="border-radius: 16px; border-top: 4px solid #dc3545 !important;">
                <div class="card-body p-4 text-center d-flex flex-column align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mb-3" 
                         style="width: 60px; height: 60px; background: rgba(220,53,69,0.15);">
                        <i class="bi bi-archive fs-2" style="color: #dc3545;"></i>
                    </div>
                    <h5 class="fw-bold mb-1">Historial General</h5>
                    <p class="text-muted small mb-2">Todas las solicitudes del sistema</p>
                </div>
            </div>
        </a>
    </div>
    @endif

</div>
@endsection