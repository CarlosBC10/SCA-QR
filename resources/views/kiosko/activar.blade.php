{{-- resources/views/kiosko/activar.blade.php --}}
{{--
    Página de LOGIN del kiosko (pública).
    El guardia ingresa sus credenciales (username G-XXXXX + password),
    elige planta y modo, y se crea un turnos_guardia.
    Luego redirige a /kiosko/{turno} (pantalla de escaneo pública).

    CAMBIOS V4:
      - Sin PIN global (se eliminó config/sca_qr.pin_kiosko).
      - Login con username + password de guardia.
      - FK: planta_id → id_planta.
      - Sesión de kiosko: si el guardia cierra su turno, TODOS sus kioskos se cierran
        y cada kiosko muestra "el guardia cerró sesión, inicie de nuevo".
--}}
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activar kiosco · Sistema de Control de Accesos mediante QR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ asset('css/sca-ui.css') }}">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, #1b5e20, #2e7d32); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card { border-radius: 20px; border: none; box-shadow: 0 20px 50px rgba(0,0,0,0.3); }
        .btn-success { background: #2e7d32; border: none; }
        .btn-success:hover { background: #1b5e20; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="bi bi-display-fill text-success" style="font-size: 3rem;"></i>
                            <h3 class="fw-bold mt-3 mb-0">Activar Kiosko</h3>
                            <p class="text-muted">Ingrese sus credenciales de guardia</p>
                        </div>

                        @if(session('kiosko_error'))
                            <div class="alert alert-danger">{{ session('kiosko_error') }}</div>
                        @endif
                        @if(session('kiosko_info'))
                            <div class="alert alert-info">{{ session('kiosko_info') }}</div>
                        @endif
                        @if($errors->any())
                            <div class="alert alert-danger">
                                @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                            </div>
                        @endif

                        <form method="POST" action="{{ route('kiosko.activar') }}">
                            @csrf
                            {{-- Credenciales del guardia --}}
                            <div class="mb-3">
                                <label class="form-label fw-bold">Usuario de Guardia</label>
                                <input type="text" name="username" class="form-control form-control-lg" placeholder="G-0001" required autofocus value="{{ old('username') }}">
                                <small class="text-muted">Formato: G-XXXXX (5 dígitos)</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Contraseña</label>
                                <input type="password" name="password" class="form-control form-control-lg" required>
                            </div>

                            {{-- FIX B-2: Ya no se selecciona la planta aquí. 
                            El backend la asigna automáticamente según el guardia. --}}
                            <div class="row g-3 mb-3">
                                <div class="col-md-12">
                                    <label class="form-label fw-bold">Modo de operación</label>
                                    <select name="modo" class="form-select form-select-lg" required>
                                        <option value="mixto" @if(old('modo')=='mixto') selected @endif>Mixto (Entradas y Salidas)</option>
                                        <option value="entrada" @if(old('modo')=='entrada') selected @endif>Solo Entradas</option>
                                        <option value="salida" @if(old('modo')=='salida') selected @endif>Solo Salidas</option>
                                    </select>
                                    <small class="text-muted">La planta se asignará automáticamente según su guardia al activar.</small>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-success btn-lg w-100 mt-3">
                                <i class="bi bi-power"></i> Activar Kiosko
                            </button>
                        </form>

                        <div class="text-center mt-4">
                            <a href="{{ route('login') }}" class="text-muted small text-decoration-none">
                                <i class="bi bi-arrow-left"></i> Volver al Login
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>