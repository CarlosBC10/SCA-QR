{{-- resources/views/dashboards/superadmin.blade.php --}}
{{--
    Dashboard de Superadmin.
    PRESENTACIÓN V3: elimina accesos rápidos duplicados respecto al sidebar y deja sólo
    indicadores de acceso + personas dentro.
    Lógica de datos intacta.
--}}
@extends('layouts.app')

@section('title', 'Resumen del sistema')

@section('content')
<div class="sca-page-head sca-dashboard-head">
    <div>
        <div class="sca-page-eyebrow"><i class="bi bi-shield-check" aria-hidden="true"></i> Supervisión</div>
        <h1 class="sca-page-title">Resumen del sistema</h1>
        <p class="sca-page-subtitle">Visión general de accesos, solicitudes y estado de configuración de las plantas.</p>
    </div>
    
</div>

@php
    // Conteo de puestos clasificados vs sin clasificar
    $puestosTotales = \App\Models\Sitec\Puesto::count();
    $puestosClasificados = \App\Models\PuestoTipoEmpleado::count();
    $puestosSinClasificar = $puestosTotales - $puestosClasificados;

    // V4: contar QRs activos en solicitud_externo_detalle
    $credencialesActivas = \App\Models\SolicitudExternoDetalle::where('estado_qr', 'autorizado')->count();
@endphp

<section class="sca-kpi-grid mb-4" aria-label="Indicadores principales">
    <article class="sca-kpi-card">
        <div class="sca-kpi-top"><span class="sca-kpi-label">Usuarios activos</span><span class="sca-kpi-icon"><i class="bi bi-people-fill" aria-hidden="true"></i></span></div>
        <div class="sca-kpi-value">{{ \App\Models\UsuarioSca::whereNull('fecha_baja')->count() }}</div>
        <div class="sca-kpi-note">Cuentas habilitadas para el sistema</div>
    </article>
    <article class="sca-kpi-card">
        <div class="sca-kpi-top"><span class="sca-kpi-label">Empleados sincronizados</span><span class="sca-kpi-icon is-orange"><i class="bi bi-person-vcard-fill" aria-hidden="true"></i></span></div>
        <div class="sca-kpi-value">{{ \App\Models\EmpleadoExtSca::count() }}</div>
        <div class="sca-kpi-note">Registros provenientes de Sitec</div>
    </article>
    <article class="sca-kpi-card">
        <div class="sca-kpi-top"><span class="sca-kpi-label">Accesos de hoy</span><span class="sca-kpi-icon is-blue"><i class="bi bi-door-open-fill" aria-hidden="true"></i></span></div>
        <div class="sca-kpi-value">{{ \App\Models\Bitacora::whereDate('fecha_hora', today())->count() }}</div>
        <div class="sca-kpi-note">Entradas y salidas registradas</div>
    </article>
    <article class="sca-kpi-card">
        <div class="sca-kpi-top"><span class="sca-kpi-label">QR externos activos</span><span class="sca-kpi-icon is-red"><i class="bi bi-qr-code" aria-hidden="true"></i></span></div>
        <div class="sca-kpi-value">{{ $credencialesActivas }}</div>
        <div class="sca-kpi-note">Credenciales externas autorizadas</div>
    </article>
</section>

<div class="row g-3 g-lg-4 mb-4">
    <div class="col-12">
        @include('partials._personas_dentro', ['id' => 'superadmin', 'plantas' => \App\Models\Planta::where('activa', true)->orderBy('nombre')->get()])
    </div>
</div>

@endsection
