{{-- resources/views/dashboards/empleado.blade.php --}}
{{--
    Panel de Empleado.
    Muestra accesos rápidos: nueva solicitud + mis solicitudes.

    CAMBIO V4:
      - El enlace "Mis Solicitudes" apuntaba a solicitudes.historial (ruta que
        es solo para Admin/Superadmin y daría error 403). Ahora apunta a
        solicitudes.mis (que es para cualquier usuario autenticado).
--}}
@extends('layouts.app')

@section('title', 'Panel Empleado')

@section('content')
<div class="mb-4">
    <h3 class="fw-bold mb-0">Bienvenido, {{ Auth::user()->nombre_completo }}</h3>
    <p class="text-muted mb-0">Desde aquí puede gestionar sus solicitudes de acceso.</p>
</div>

<div class="row g-4">

    {{-- Atajo: Nueva Solicitud --}}
    <div class="col-md-6">
        <a href="{{ route('solicitudes.create') }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 sca-card card-hover" style="border-radius: 16px; border-left: 4px solid #2e7d32 !important;">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-3 p-3 me-3" style="background: rgba(46,125,50,0.15);">
                        <i class="bi bi-plus-circle-fill fs-2 text-success"></i>
                    </div>
                    <div>
                        <h5 class="mb-1 fw-bold text-dark">Nueva Solicitud</h5>
                        <p class="text-muted small mb-0">Solicitar un pase de salida, interplantas o registrar una visita.</p>
                    </div>
                </div>
            </div>
        </a>
    </div>

    {{-- Atajo: Mis Solicitudes --}}
    {{-- V4: ruta renombrada de solicitudes.historial a solicitudes.mis --}}
    <div class="col-md-6">
        <a href="{{ route('solicitudes.mis') }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 sca-card card-hover" style="border-radius: 16px; border-left: 4px solid #17a2b8 !important;">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-3 p-3 me-3" style="background: rgba(23,162,184,0.15);">
                        <i class="bi bi-clock-history fs-2" style="color: #17a2b8;"></i>
                    </div>
                    <div>
                        <h5 class="mb-1 fw-bold text-dark">Mis Solicitudes</h5>
                        <p class="text-muted small mb-0">Revisar el estado e historial de sus solicitudes anteriores.</p>
                    </div>
                </div>
            </div>
        </a>
    </div>

</div>
@endsection