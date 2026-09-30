{{-- resources/views/dashboards/aprobador.blade.php --}}
{{--
    Dashboard de Aprobador.
    PRESENTACIÓN V3: prioridad absoluta a la bandeja de trabajo del aprobador.
    Lógica de conteos, scoping y rutas intacta.
--}}
@extends('layouts.app')

@section('title', 'Aprobaciones')

@section('content')
<div class="sca-page-head sca-dashboard-head">
    <div>
        <div class="sca-page-eyebrow"><i class="bi bi-check2-square" aria-hidden="true"></i> Operación</div>
        <h1 class="sca-page-title">Revisión de accesos</h1>
        <p class="sca-page-subtitle">{{ Auth::user()->nombre_completo }} · revisa solicitudes que involucran a tu planta.</p>
    </div>
</div>

@php
    // V4: Solicitud eliminado; contar pendientes en solicitudes_int + solicitudes_ext.
    $plantaId = Auth::user()->planta_id;

    $pendientesOrigen = \App\Models\SolicitudInt::where('id_planta_origen', $plantaId)
        ->where(function($q) {
            $q->where('estado', 'pendiente_origen')
              ->orWhere(function($q2) {
                  $q2->where('estado', 'aprobado_parcial')
                     ->whereNull('fecha_aprobacion_origen');
              });
        })->count();

    $pendientesDestino = \App\Models\SolicitudInt::where('id_planta_destino', $plantaId)
        ->where(function($q) {
            $q->where('estado', 'pendiente_destino')
              ->orWhere(function($q2) {
                  $q2->where('estado', 'aprobado_parcial')
                     ->whereNull('fecha_aprobacion_destino');
              });
        })->count();

    $pendientesExt = \App\Models\SolicitudExt::where('id_planta_destino', $plantaId)
        ->where('estado', 'pendiente')->count();

    $pendientesCount = $pendientesOrigen + $pendientesDestino + $pendientesExt;
@endphp

<div class="sca-dashboard-actions mb-4">
    <div class="sca-dashboard-action-copy">
        <span class="sca-kicker">Trabajo pendiente</span>
        <strong>Revisa tus solicitudes pendientes</strong>
        <small>La bandeja concentra las solicitudes que necesitan una decisión.</small>
    </div>
    <div class="sca-dashboard-action-buttons">
        <a href="{{ route('solicitudes.bandeja') }}" class="btn {{ $pendientesCount > 0 ? "btn-warning" : "btn-outline-success" }}"><i class="bi bi-inbox me-1"></i>Revisar bandeja</a>
        <a href="{{ route('solicitudes.create') }}" class="btn btn-outline-secondary"><i class="bi bi-plus-lg me-1"></i>Nueva solicitud</a>
    </div>
</div>


<div class="sca-section-heading">
    <div>
        <span class="sca-kicker">Control en planta</span>
        <h2>Personas dentro de la planta</h2>
    </div>
</div>

<div class="row">
    <div class="col-12">
        @include('partials._personas_dentro', ['id' => 'aprobador'])
    </div>
</div>
@endsection
