@extends('layouts.app')

@section('title', 'Recuperar Contraseña')

@section('content')
<div class="row justify-content-center align-items-center" style="min-height: 70vh;">
    <div class="col-md-5">
        <div class="card border-0 shadow-lg" style="border-radius: 20px;">
            <div class="card-header bg-transparent text-center py-4 border-0">
                <div class="mb-3">
                    <i class="bi bi-key-fill text-success" style="font-size: 3rem;"></i>
                </div>
                <h3 class="fw-bold mb-1 text-dark">¿Olvidó su contraseña?</h3>
                <p class="text-muted mb-0">No se preocupe, le enviaremos una temporal.</p>
            </div>
            <div class="card-body px-4 pb-4">
                @if($errors->any())
                    <div class="alert alert-danger" style="border-radius: 12px;">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('password.email') }}">
                    @csrf
                    <div class="mb-4">
                        <label class="form-label text-muted small text-uppercase fw-bold">Usuario</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="bi bi-person text-success"></i>
                            </span>
                            <input type="text" name="username" class="form-control border-start-0 ps-0" required autofocus value="{{ old('username') }}" placeholder="Número de empleado o G-XXXX">
                        </div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success btn-lg fw-bold" style="border-radius: 12px;">
                            <i class="bi bi-send-fill me-2"></i> Enviar Correo
                        </button>
                    </div>
                </form>
                <div class="text-center mt-4">
                    <a href="{{ route('login') }}" class="text-muted small text-decoration-none">
                        <i class="bi bi-arrow-left-circle"></i> Volver al Login
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection