{{-- resources/views/dashboards/superadmin.blade.php --}}
@extends('layouts.app')

@section('title', 'Panel Superadmin')

@section('content')
<div class="mb-4 animate-fade-in-up">
    <h3 class="fw-bold mb-0" style="letter-spacing: -0.5px;">Panel de Control</h3>
    <p class="text-muted mb-0">Vista global del sistema · Plantas: Jardín, Sauces, Partidas</p>
</div>

@php
    $puestosTotales = \App\Models\Sitec\Puesto::count();
    $puestosClasificados = \App\Models\PuestoTipoEmpleado::count();
    $puestosSinClasificar = $puestosTotales - $puestosClasificados;
@endphp

{{-- Stats Cards --}}
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-md-6 animate-fade-in-up" style="animation-delay: 0.1s;">
        <div class="card card-hover border-0 h-100" style="border-radius: 16px; overflow: hidden;">
            <div class="card-body p-4 position-relative">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold mb-2" style="font-size: 0.75rem; letter-spacing: 0.5px;">Usuarios Web</div>
                        <div class="h2 fw-bold mb-0">{{ \App\Models\UsuarioSca::count() }}</div>
                    </div>
                    <div class="rounded-3 p-3" style="background: rgba(46,125,50,0.1); color: #2e7d32;">
                        <i class="bi bi-people-fill fs-4"></i>
                    </div>
                </div>
                <div class="mt-3 small text-muted">Total de cuentas activas</div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 animate-fade-in-up" style="animation-delay: 0.2s;">
        <div class="card card-hover border-0 h-100" style="border-radius: 16px; overflow: hidden;">
            <div class="card-body p-4 position-relative">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold mb-2" style="font-size: 0.75rem; letter-spacing: 0.5px;">Empleados Sync</div>
                        <div class="h2 fw-bold mb-0">{{ \App\Models\EmpleadoExtSca::count() }}</div>
                    </div>
                    <div class="rounded-3 p-3" style="background: rgba(245,124,0,0.1); color: #f57c00;">
                        <i class="bi bi-person-badge-fill fs-4"></i>
                    </div>
                </div>
                <div class="mt-3 small text-muted">Sincronizados de sitec</div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 animate-fade-in-up" style="animation-delay: 0.3s;">
        <div class="card card-hover border-0 h-100" style="border-radius: 16px; overflow: hidden;">
            <div class="card-body p-4 position-relative">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold mb-2" style="font-size: 0.75rem; letter-spacing: 0.5px;">Accesos Hoy</div>
                        <div class="h2 fw-bold mb-0">{{ \App\Models\Bitacora::whereDate('fecha_hora', today())->count() }}</div>
                    </div>
                    <div class="rounded-3 p-3" style="background: rgba(23,162,184,0.1); color: #17a2b8;">
                        <i class="bi bi-door-open-fill fs-4"></i>
                    </div>
                </div>
                <div class="mt-3 small text-muted">Entradas y salidas</div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 animate-fade-in-up" style="animation-delay: 0.4s;">
        <div class="card card-hover border-0 h-100" style="border-radius: 16px; overflow: hidden;">
            <div class="card-body p-4 position-relative">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold mb-2" style="font-size: 0.75rem; letter-spacing: 0.5px;">Credenciales</div>
                        <div class="h2 fw-bold mb-0">{{ \App\Models\Credencial::where('activa', true)->count() }}</div>
                    </div>
                    <div class="rounded-3 p-3" style="background: rgba(220,53,69,0.1); color: #dc3545;">
                        <i class="bi bi-qr-code fs-4"></i>
                    </div>
                </div>
                <div class="mt-3 small text-muted">Activas para externos</div>
            </div>
        </div>
    </div>
</div>

{{-- NUEVA FILA: Personas dentro + Alertas --}}
<div class="row g-4 mb-4">
    <div class="col-lg-8 animate-fade-in-up" style="animation-delay: 0.5s;">
        @include('partials._personas_dentro', ['id' => 'superadmin', 'plantas' => \App\Models\Planta::where('activa', true)->orderBy('nombre')->get()])
    </div>
    <div class="col-lg-4 animate-fade-in-up" style="animation-delay: 0.6s;">
        <div class="card border-0 h-100" style="border-radius: 16px; background: linear-gradient(135deg, #2e7d32, #1b5e20);">
            <div class="card-body p-4 text-white">
                <h5 class="mb-0 fw-bold"><i class="bi bi-bell-fill me-2"></i> Alertas del Sistema</h5>
                <hr class="my-3" style="background: rgba(255,255,255,0.2);">
                @if($puestosSinClasificar > 0)
                <div class="d-flex align-items-start mb-3 animate-fade-in-up">
                    <div class="bg-white bg-opacity-25 rounded-3 p-2 me-3">
                        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    </div>
                    <div>
                        <div class="fw-bold">Puestos sin clasificar</div>
                        <small class="opacity-75">Hay {{ $puestosSinClasificar }} puestos pendientes de configurar.</small>
                    </div>
                </div>
                @else
                <div class="text-center py-4">
                    <i class="bi bi-check-circle fs-1 opacity-50"></i>
                    <p class="mt-2 mb-0 fw-bold">Todo en orden</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Accesos Rápidos --}}
<div class="row g-4">
    <div class="col-12 animate-fade-in-up" style="animation-delay: 0.7s;">
        <div class="card border-0" style="border-radius: 16px;">
            <div class="card-header bg-transparent py-4 px-4 border-0">
                <h5 class="mb-0 fw-bold"><i class="bi bi-lightning-charge-fill text-success me-2"></i> Accesos Rápidos</h5>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="row g-3">
                    <div class="col-md-3">
                        <a href="{{ route('usuarios.index') }}" class="text-decoration-none">
                            <div class="border-0 p-4 text-center bg-light rounded-3 h-100 d-flex flex-column align-items-center justify-content-center transition-all" style="transition: all 0.3s;">
                                <div class="rounded-circle d-flex align-items-center justify-content-center mb-3" style="width: 50px; height: 50px; background: white;">
                                    <i class="bi bi-people text-success fs-4"></i>
                                </div>
                                <span class="fw-bold text-dark">Gestionar Usuarios</span>
                                <small class="text-muted mt-1">Cuentas y roles</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="{{ route('empleados.index') }}" class="text-decoration-none">
                            <div class="border-0 p-4 text-center bg-light rounded-3 h-100 d-flex flex-column align-items-center justify-content-center transition-all" style="transition: all 0.3s;">
                                <div class="rounded-circle d-flex align-items-center justify-content-center mb-3" style="width: 50px; height: 50px; background: white;">
                                    <i class="bi bi-person-badge text-success fs-4"></i>
                                </div>
                                <span class="fw-bold text-dark">Ver Empleados</span>
                                <small class="text-muted mt-1">Listado y clasificación</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="{{ route('puestos.index') }}" class="text-decoration-none">
                            <div class="border-0 p-4 text-center bg-light rounded-3 h-100 d-flex flex-column align-items-center justify-content-center transition-all" style="transition: all 0.3s;">
                                <div class="rounded-circle d-flex align-items-center justify-content-center mb-3" style="width: 50px; height: 50px; background: white;">
                                    <i class="bi bi-list-task text-success fs-4"></i>
                                </div>
                                <span class="fw-bold text-dark">Clasificar Puestos</span>
                                <small class="text-muted mt-1">Configuración masiva</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="{{ route('clasificaciones.index') }}" class="text-decoration-none">
                            <div class="border-0 p-4 text-center bg-light rounded-3 h-100 d-flex flex-column align-items-center justify-content-center transition-all" style="transition: all 0.3s;">
                                <div class="rounded-circle d-flex align-items-center justify-content-center mb-3" style="width: 50px; height: 50px; background: white;">
                                    <i class="bi bi-diagram-3 text-success fs-4"></i>
                                </div>
                                <span class="fw-bold text-dark">Clasificaciones</span>
                                <small class="text-muted mt-1">Mapeo a plantas</small>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .card-hover:hover { transform: translateY(-5px); box-shadow: 0 12px 24px rgba(0,0,0,0.08) !important; }
    .transition-all:hover { transform: scale(1.02); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
</style>
@endsection