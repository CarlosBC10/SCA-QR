{{-- resources/views/dashboards/empleado.blade.php --}}
{{--
    Dashboard de empleado.
    PRESENTACIÓN V3: prioriza las dos acciones principales del usuario.
    Lógica/rutas originales intactas.
--}}
@extends('layouts.app')

@section('title', 'Mis accesos')

@section('content')
<div class="sca-page-head sca-dashboard-head">
    <div>
        <div class="sca-page-eyebrow"><i class="bi bi-person-check" aria-hidden="true"></i> Mi espacio</div>
        <h1 class="sca-page-title">Mis accesos</h1>
        <p class="sca-page-subtitle">Hola, {{ Auth::user()->nombre_completo }}. Aquí puedes gestionar tus solicitudes de acceso.</p>
    </div>
</div>

<div class="sca-dashboard-actions mb-4">
    <div class="sca-dashboard-action-copy">
        <span class="sca-kicker">Acciones</span>
        <strong>Gestiona tus solicitudes</strong>
        <small>Crea una nueva solicitud o consulta su estado.</small>
    </div>
    <div class="sca-dashboard-action-buttons">
        <a href="{{ route('solicitudes.create') }}" class="btn btn-success"><i class="bi bi-plus-lg me-1"></i>Nueva solicitud</a>
        <a href="{{ route('solicitudes.mis') }}" class="btn btn-outline-secondary"><i class="bi bi-list-check me-1"></i>Ver mis solicitudes</a>
    </div>
</div>


@endsection
