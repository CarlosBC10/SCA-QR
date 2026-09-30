{{-- resources/views/dashboards/admin.blade.php --}}
{{--
    Dashboard de Admin.
    PRESENTACIÓN V3: el dashboard responde primero a la pregunta "¿qué requiere mi atención?".
    Lógica de conteo y permisos intacta.
--}}
@extends('layouts.app')

@section('title', 'Administración')

@section('content')
<div class="sca-page-head sca-dashboard-head">
    <div>
        <div class="sca-page-eyebrow"><i class="bi bi-speedometer2" aria-hidden="true"></i> Administración</div>
        <h1 class="sca-page-title">Resumen de accesos</h1>
        <p class="sca-page-subtitle">Supervisa lo que requiere atención y consulta quién se encuentra dentro de las plantas.</p>
    </div>
</div>

@php
    // V4: Solicitud eliminado; contar pendientes en solicitudes_int + solicitudes_ext
    // FIX 3-g (Admin): se incluyen también solicitudes en aprobado_parcial que siguen esperando revisión.
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
    $pendientesExt = \App\Models\SolicitudExt::where('estado', 'pendiente')->count();
    $pendientesCount = $pendientesInt + $pendientesExt;
@endphp

<div class="sca-focus-card mb-4 {{ $pendientesCount > 0 ? 'has-alert' : 'is-clear' }}">
    <div class="sca-focus-icon"><i class="bi {{ $pendientesCount > 0 ? 'bi-inbox-fill' : 'bi-check2-circle' }}" aria-hidden="true"></i></div>
    <div class="sca-focus-copy">
        <span class="sca-kicker">Atención requerida</span>
        <strong>{{ $pendientesCount }}</strong>
        <p>{{ $pendientesCount > 0 ? 'solicitudes requieren revisión.' : 'No hay solicitudes pendientes en este momento.' }}</p>
    </div>
    <a href="{{ route('solicitudes.bandeja') }}" class="btn {{ $pendientesCount > 0 ? 'btn-warning' : 'btn-outline-success' }} ms-auto">
        <i class="bi bi-arrow-right-circle me-1" aria-hidden="true"></i>
        {{ $pendientesCount > 0 ? 'Revisar bandeja' : 'Abrir bandeja' }}
    </a>
</div>

<div class="sca-section-heading">
    <div>
        <span class="sca-kicker">En este momento</span>
        <h2>Personas dentro de las plantas</h2>
    </div>
</div>

<div class="row">
    <div class="col-12">
        @include('partials._personas_dentro', ['id' => 'admin', 'plantas' => \App\Models\Planta::where('activa', true)->orderBy('nombre')->get()])
    </div>
</div>
@endsection
