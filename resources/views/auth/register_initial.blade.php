<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro inicial · Sistema de Control de Accesos mediante</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ asset('css/sca-ui.css') }}">
    <link rel="stylesheet" href="{{ asset('css/sca-prototype.css') }}">
    <style>
        :root {
            --fruit-green: #2e7d32;
            --fruit-orange: #f57c00;
            --fruit-dark: #1b5e20;
            --text-dark: #333333;
            --card-bg: rgba(255, 255, 255, 0.95);
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: url('https://as2.ftcdn.net/jpg/08/73/27/69/1000_F_873276956_SObp4RjRFZF9kj1G1EucQ8wXl40wtTTh.jpg') no-repeat center center/cover;
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        body::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(0,0,0,0.65) 0%, rgba(0,0,0,0.4) 100%);
            z-index: 0;
        }
        .register-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 500px;
            padding: 2rem;
        }
        .register-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .card-header {
            background: linear-gradient(135deg, var(--fruit-orange) 0%, #e65100 100%);
            padding: 2.5rem 2rem 1.5rem;
            text-align: center;
            color: white;
        }
        .logo-container {
            width: 90px;
            height: 90px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.2rem;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .logo-container img {
            width: 70%;
            height: 70%;
            object-fit: contain;
            border-radius: 50%;
        }
        .card-header h4 {
            font-weight: 700;
            letter-spacing: 0.5px;
            font-size: 1.6rem;
        }
        .card-header p {
            opacity: 0.9;
            font-size: 0.95rem;
            margin-bottom: 0;
        }
        .card-body {
            padding: 2.5rem 2.5rem 2rem;
        }
        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .input-group-text {
            background: #fff3e0;
            border: 2px solid #e0e0e0;
            border-right: none;
            border-radius: 14px 0 0 14px;
            color: var(--fruit-orange);
            font-size: 1.2rem;
            padding: 0.8rem 1rem;
        }
        .form-control {
            border: 2px solid #e0e0e0;
            border-left: none;
            border-radius: 0 14px 14px 0;
            padding: 0.8rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: var(--fruit-orange);
            box-shadow: none;
        }
        .input-group:focus-within .input-group-text {
            border-color: var(--fruit-orange);
        }
        .btn-register {
            background: linear-gradient(135deg, var(--fruit-green) 0%, var(--fruit-dark) 100%);
            border: none;
            border-radius: 14px;
            padding: 0.85rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            color: white;
            transition: all 0.3s ease;
            font-size: 1.1rem;
            text-transform: uppercase;
        }
        .btn-register:hover {
            background: linear-gradient(135deg, var(--fruit-dark) 0%, #0a3d0a 100%);
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(46,125,50,0.3);
        }
        .alert {
            border-radius: 14px;
        }
        .footer-text {
            text-align: center;
            color: #888;
            margin-top: 1.5rem;
            font-size: 0.9rem;
        }
        .info-banner {
            background: #e8f5e9;
            border: 1px solid #a5d6a7;
            border-radius: 14px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            color: var(--fruit-green);
            font-size: 0.95rem;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="card-header">
                <div class="register-brand-stack" aria-label="SCA">
                    <div class="register-sca-logo"><img src="{{ asset('logo-sca-qr.svg') }}" alt="SCA · Sistema de Control de Accesos"></div>
                    <div class="register-fruitex-logo"><img src="{{ asset('logo-fruitex.png') }}" alt="Fruitex de México"></div>
                </div>
                <h4>Sistema de Control de Accesos mediante</h4>
                <p>Registro del Superadmin</p>
            </div>
            <div class="card-body">
                <div class="info-banner d-flex align-items-start">
                    <i class="bi bi-info-circle-fill me-2 mt-1"></i>
                    <div>No hay usuarios en el sistema. Cree el primer <strong>Superadmin</strong> para comenzar.</div>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <div>{{ $errors->first() }}</div>
                    </div>
                @endif

                <form method="POST" action="{{ route('register.initial') }}">
                    @csrf
                    <div class="mb-4">
                        <label class="form-label">Número de Empleado</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person-badge-fill"></i></span>
                            <input type="text" name="id_empleado" class="form-control" placeholder="Ingrese ID de empleado" required autofocus>
                        </div>
                        <div class="form-text ms-1">Debe existir en la base de datos de empleados.</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" name="password" class="form-control" placeholder="Mínimo 8 caracteres" required minlength="8">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-register w-100">
                        <i class="bi bi-person-check-fill me-2"></i>Crear Superadmin
                    </button>
                </form>
                <div class="footer-text">
                    <small>&copy; {{ date('Y') }} Fruitex de México. Todos los derechos reservados.</small>
                </div>
            </div>
        </div>
    </div>
</body>
</html>