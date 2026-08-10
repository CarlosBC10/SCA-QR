{{-- resources/views/errors/403.blade.php --}}
@extends('layouts.app')

@section('title', 'Acceso Denegado')

@section('content')
<div class="d-flex align-items-center justify-content-center" style="min-height: 70vh;">
    <div class="text-center">
        <h1 class="display-1 fw-bold text-danger">403</h1>
        <h3 class="fw-bold mb-2">Acceso Denegado</h3>
        <p class="text-muted mb-4">No tiene permisos para ver esta página.</p>
        <a href="{{ Auth::check() ? url('/') : route('login') }}" class="btn btn-success">
            <i class="bi bi-arrow-left"></i> Volver al Inicio
        </a>
    </div>
</div>
@endsection