{{-- resources/views/auth/login.blade.php --}}
@extends('layouts.app')

@section('title', 'Iniciar Sesión')

@section('content')
<div class="min-vh-100 position-relative d-flex align-items-center justify-content-center" 
     style="background-image: url('https://images.unsplash.com/photo-1581094794329-c8112a89af12?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80'); 
            background-size: cover; 
            background-position: center;">
    
    {{-- Overlay oscuro con verde corporativo para legibilidad --}}
    <div class="position-absolute top-0 start-0 w-100 h-100" 
         style="background: linear-gradient(135deg, rgba(27, 94, 32, 0.88), rgba(46, 125, 50, 0.78));"></div>

    <div class="container position-relative" style="z-index: 1000;">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5 col-xl-4">
                <div class="card border-0 shadow-lg animate-fade-in-up" 
                     style="border-radius: 20px; background: rgba(255, 255, 255, 0.96); backdrop-filter: blur(20px);">
                    <div class="card-body p-5">
                        
                        {{-- LOGO O FALLBACK --}}
                        <div class="text-center mb-5">
                            @if(file_exists(public_path('logo.png')))
                                <img src="{{ asset('logo.png') }}" alt="Fruitex" style="max-height: 60px; max-width: 200px;" class="mb-3">
                            @elseif(file_exists(public_path('logo.svg')))
                                <img src="{{ asset('logo.svg') }}" alt="Fruitex" style="max-height: 60px; max-width: 200px;" class="mb-3">
                            @else
                                <div class="d-inline-flex align-items-center justify-content-center text-white rounded-4 mb-3" 
                                     style="width: 72px; height: 72px; font-size: 1.8rem; font-weight: 800; background: linear-gradient(135deg, #2e7d32, #1b5e20); box-shadow: 0 8px 20px rgba(46, 125, 50, 0.3);">
                                    SCA
                                </div>
                            @endif
                            <h4 class="fw-bold mb-1" style="letter-spacing: -0.5px; color: #333;">SCA-QR</h4>
                            <p class="text-muted small mb-0">Sistema de Control de Accesos</p>
                        </div>
                        
                        {{-- FORMULARIO LOGIN NORMAL --}}
                        <form method="POST" action="{{ route('login') }}">
                            @csrf
                            <div class="mb-4">
                                <label class="form-label small fw-semibold text-muted text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">Usuario</label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-transparent border-end-0 ps-3" style="border-radius: 12px 0 0 12px;">
                                        <i class="bi bi-person text-muted"></i>
                                    </span>
                                    <input type="text" class="form-control border-start-0 ps-0" name="username" required autofocus placeholder="Ej: 1001" style="border-radius: 0 12px 12px 0;">
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label small fw-semibold text-muted text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">Contraseña</label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-transparent border-end-0 ps-3" style="border-radius: 12px 0 0 12px;">
                                        <i class="bi bi-lock text-muted"></i>
                                    </span>
                                    <input type="password" class="form-control border-start-0 ps-0" name="password" required placeholder="••••••••" style="border-radius: 0 12px 12px 0;">
                                </div>
                            </div>

                            @if ($errors->any())
                                <div class="alert py-3 small border-0 d-flex align-items-center animate-fade-in-up" 
                                     style="border-radius: 12px; background: rgba(220, 53, 69, 0.1); color: #721c24;">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ $errors->first() }}
                                </div>
                            @endif
                            
                            {{-- BOTÓN NORMAL (Entrar al panel web) --}}
                            <button type="submit" class="btn btn-success w-100 py-3 fw-bold mt-2" style="border-radius: 12px; text-transform: uppercase; letter-spacing: 1px;">
                                Ingresar <i class="bi bi-arrow-right ms-1"></i>
                            </button>
                            
                            {{-- BOTÓN MODO KIOSKO (Entra a la pantalla de escaneo de la caseta) --}}
                            <div class="text-center mt-3">
                                <button type="button" class="btn btn-link text-muted text-decoration-none small" data-bs-toggle="modal" data-bs-target="#kioskoModal">
                                    <i class="bi bi-tablet"></i> Activar Modo Kiosko
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <p class="text-center text-white-50 mt-4 small">&copy; {{ date('Y') }} Fruitex de México · Todos los derechos reservados</p>
            </div>
        </div>
    </div>
</div>

{{-- MODAL MODO KIOSKO --}}
<div class="modal fade" id="kioskoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px;">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-tablet text-success"></i> Activar Modo Kiosko</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('kiosko.activar') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <p class="text-muted small">Ingrese el PIN de seguridad, seleccione la planta y el modo de operación de este kiosco.</p>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">PIN de Seguridad</label>
                        <input type="password" name="pin" class="form-control form-control-lg text-center" 
                               style="letter-spacing: 10px; font-size: 1.5rem;" required autofocus>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Planta del Kiosco</label>
                        <select name="planta_id" class="form-select form-select-lg" required>
                            @foreach(\App\Models\Planta::where('activa', true)->orderBy('nombre')->get() as $p)
                                <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    {{-- NUEVO: Selector de Modo --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Modo de Operación</label>
                        <select name="modo" class="form-select form-select-lg" required>
                            <option value="mixto">Mixto (Detecta Entrada/Salida)</option>
                            <option value="entrada">Solo Entradas</option>
                            <option value="salida">Solo Salidas</option>
                        </select>
                    </div>
                    
                    <div id="kiosko-error" class="alert alert-danger d-none" style="border-radius: 10px;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-tablet"></i> Activar Kiosko
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Si hay errores del kiosko, abrir el modal automáticamente
    @if(session('kiosko_error'))
        document.addEventListener('DOMContentLoaded', function() {
            new bootstrap.Modal(document.getElementById('kioskoModal')).show();
            const err = document.getElementById('kiosko-error');
            err.textContent = '{{ session("kiosko_error") }}';
            err.classList.remove('d-none');
        });
    @endif
</script>
@endsection