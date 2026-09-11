{{-- errors/403.blade.php --}}
@extends('layouts.app')

@section('title', 'Acceso denegado')

@section('content')
<div class="sca-error-screen">
    <div class="sca-error-card">
        <div class="sca-error-icon"><i class="bi bi-shield-lock" aria-hidden="true"></i></div>
        <div class="sca-kicker">Sistema de Control de Accesos mediante QR</div>
        <div class="sca-error-code">403</div>
        <h1>Acceso restringido</h1>
        <p>No tienes permisos para consultar este recurso.</p>
        <a href="{{ Auth::check() ? url('/') : route('login') }}" class="btn btn-success">
            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Volver al inicio
        </a>
    </div>
</div>
@endsection
