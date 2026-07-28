{{-- resources/views/kiosko/activar.blade.php --}}
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activar Kiosko · SCA-QR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
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
                            <p class="text-muted">Ingrese el PIN de seguridad y seleccione la configuración</p>
                        </div>

                        @if(session('kiosko_error'))
                            <div class="alert alert-danger">{{ session('kiosko_error') }}</div>
                        @endif

                        <form method="POST" action="{{ route('kiosko.activar') }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label fw-bold">PIN de Seguridad</label>
                                <input type="password" name="pin" class="form-control form-control-lg text-center" style="letter-spacing: 8px;" required autofocus>
                            </div>
                            
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Planta</label>
                                    <select name="planta_id" class="form-select form-select-lg" required>
                                        <option value="">Seleccionar...</option>
                                        @foreach($plantas as $p)
                                            <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Modo</label>
                                    <select name="modo" class="form-select form-select-lg" required>
                                        <option value="mixto">Mixto (Entradas y Salidas)</option>
                                        <option value="entrada">Solo Entradas</option>
                                        <option value="salida">Solo Salidas</option>
                                    </select>
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