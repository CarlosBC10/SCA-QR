{{-- resources/views/errors/404.blade.php --}}
@extends('layouts.app')

@section('title', 'Página no encontrada')

@section('content')
<div class="d-flex align-items-center justify-content-center" style="min-height: 70vh;">
    <div class="text-center">
        <h1 class="display-1 fw-bold text-success">404</h1>
        <h3 class="fw-bold mb-2">Página no encontrada</h3>
        <p class="text-muted mb-4">La página que buscas no existe o fue movida.</p>
        <a href="{{ Auth::check() ? url('/') : route('login') }}" class="btn btn-success">
            <i class="bi bi-arrow-left"></i> Volver al Inicio
        </a>
    </div>
</div>
@endsection