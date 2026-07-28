{{-- resources/views/errors/500.blade.php --}}
@extends('layouts.app')

@section('title', 'Error del Servidor')

@section('content')
<div class="d-flex align-items-center justify-content-center" style="min-height: 70vh;">
    <div class="text-center">
        <h1 class="display-1 fw-bold text-danger">500</h1>
        <h3 class="fw-bold mb-2">Error del Servidor</h3>
        <p class="text-muted mb-4">Ocurrió un problema interno. Inténtalo de nuevo más tarde.</p>
        <a href="{{ Auth::check() ? url('/') : route('login') }}" class="btn btn-success">
            <i class="bi bi-arrow-left"></i> Volver al Inicio
        </a>
    </div>
</div>
@endsection