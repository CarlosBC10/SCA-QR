{{-- errors/404.blade.php --}}
@extends('layouts.app')

@section('title', 'Página no encontrada')

@section('content')
<div class="sca-error-screen">
    <div class="sca-error-card">
        <div class="sca-error-icon"><i class="bi bi-compass" aria-hidden="true"></i></div>
        <div class="sca-kicker">Sistema de Control de Accesos</div>
        <div class="sca-error-code">404</div>
        <h1>Página no encontrada</h1>
        <p>La página que buscas no existe o ya no está disponible.</p>
        <a href="{{ Auth::check() ? url('/') : route('login') }}" class="btn btn-success">
            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Volver al inicio
        </a>
    </div>
</div>
@endsection
