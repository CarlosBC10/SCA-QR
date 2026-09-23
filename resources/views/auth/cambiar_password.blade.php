<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- M2 [ScaModal] — Token CSRF para peticiones AJAX --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Cambiar contraseña · Sistema de Control de Accesos mediante</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ asset('css/sca-ui.css') }}">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --k-green-1: #0d3311; --k-green-2: #1b5e20; --k-green-3: #2e7d32;
        }
        html, body { height: 100%; margin: 0; }
        body { 
            font-family: 'Poppins', sans-serif; 
            background: radial-gradient(circle at 30% 20%, var(--k-green-3) 0%, var(--k-green-2) 45%, var(--k-green-1) 100%);
            color: #fff;
            display: flex; 
            flex-direction: column;
            overflow: hidden; /* Evita el scroll de la página principal */
        }
        
        /* Header moderno */
        .mini-header {
            flex-shrink: 0; /* Evita que el header se encoja */
            padding: 12px 24px;
            display: flex; justify-content: space-between; align-items: center;
            background: rgba(0, 0, 0, 0.2);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            z-index: 10;
        }
        .header-info { display: flex; align-items: center; gap: 20px; font-size: 0.95rem; font-weight: 500; }
        .header-info i { margin-right: 6px; opacity: 0.8; }
        .btn-theme {
            background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: #fff;
            width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: 0.2s;
        }
        .btn-theme:hover { background: rgba(255,255,255,0.2); transform: rotate(15deg); }

        /* Contenedor principal que rellena el resto */
        .main-container { 
            flex-grow: 1; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            padding: 20px; 
            min-height: 0; /* FIX mágico de flexbox para que el contenido no empuje */
        }

        /* Tarjeta principal */
        .card-form {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 24px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
            border: none;
            width: 100%; max-width: 420px;
            max-height: 100%; 
            overflow-y: auto; /* Por si en pantallas muy pequeñas necesita scroll interno */
        }
        [data-bs-theme="dark"] .card-form { background: rgba(30, 30, 30, 0.95); color: #f8f9fa; }
        [data-bs-theme="dark"] .form-control { background-color: #2b2b2b; border-color: #495057; color: #fff; }
        [data-bs-theme="dark"] .input-group-text { background-color: #212121; border-color: #495057; color: #adb5bd; }
        [data-bs-theme="dark"] .text-muted { color: #adb5bd !important; }
        [data-bs-theme="dark"] .text-dark { color: #f8f9fa !important; }
        
        /* Compactar márgenes del formulario */
        .compact-input { margin-bottom: 1rem !important; }
        @media (max-width: 575px) {
            .mini-header { padding: 10px 13px; }
            .header-info { gap: 9px; font-size: .73rem; }
            .header-info span:nth-child(2) { display:none; }
            .main-container { padding: 12px; align-items: flex-start; }
            .card-form { max-height: calc(100vh - 76px); border-radius: 19px; }
            .card-form .card-body { padding-left: 18px!important; padding-right: 18px!important; }
        }

    </style>
</head>
<body>

    {{-- Mini Header --}}
    <div class="mini-header">
        <div class="header-info">
            <span><i class="bi bi-clock"></i><span id="reloj">--:--:--</span></span>
            <span style="opacity: 0.3;">|</span>
            <span><i class="bi bi-person-circle"></i>{{ auth()->user()->nombre_completo }}</span>
        </div>
        <button class="btn-theme" onclick="toggleTheme()" title="Cambiar modo">
            <i class="bi bi-moon-stars" id="themeIcon"></i>
        </button>
    </div>

    {{-- Contenido Centrado --}}
    <div class="main-container">
        <div class="card card-form">
            <div class="card-header bg-transparent text-center py-3 border-0">
                <div class="mb-2">
                    <i class="bi bi-shield-lock-fill text-success" style="font-size: 2.5rem;"></i>
                </div>
                <h3 class="fw-bold mb-1 text-dark" style="font-size: 1.3rem;">Cambio de Contraseña</h3>
                <p class="text-muted mb-0" style="font-size: 0.85rem;">Por seguridad, cambie su contraseña temporal.</p>
            </div>

            <div class="card-body px-4 px-md-4 pb-4">
                {{-- M2 [ScaModal] — Lector del flash estructurado 'sca_modal'.
                    Dispara el modal automáticamente cuando un controller hace:
                    ->with('sca_modal', ['tipo'=>'warning', 'titulo'=>'...', 'mensaje'=>'...'])

                    Este bloque es necesario porque cambiar_password.blade.php es standalone
                    (no extiende layouts/app.blade.php), así que el lector del layout no aplica.

                    Mantenemos el lector viejo session('warning') como fallback hasta M7. --}}
                @if (session('sca_modal'))
                    @php
                        $scaModal = session('sca_modal');
                    @endphp
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            if (typeof window.ScaModal === 'undefined') {
                                console.error('[ScaModal] sca-modal.js no cargó. Flash no mostrado:', {{ \Illuminate\Support\Js::from($scaModal) }});
                                return;
                            }
                            const cfg = {{ \Illuminate\Support\Js::from($scaModal) }};
                            if (typeof window.ScaModal[cfg.tipo] === 'function') {
                                window.ScaModal[cfg.tipo](cfg.titulo, cfg.mensaje, { detalles: cfg.detalles || null });
                            } else {
                                window.ScaModal.info(cfg.titulo || 'Aviso', cfg.mensaje || '');
                            }
                        });
                    </script>
                @endif

                {{-- Lector viejo (fallback, se eliminará en M7) --}}
                @if(session('warning'))
                    <div class="alert alert-warning border-0 shadow-sm py-2" style="border-radius: 12px; font-size: 0.85rem;">
                        <i class="bi bi-exclamation-triangle-fill"></i> {{ session('warning') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert alert-danger border-0 shadow-sm py-2" style="border-radius: 12px; font-size: 0.85rem;">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('password.cambiar.update') }}">
                    @csrf
                    
                    <div class="compact-input">
                        <label class="form-label text-muted small text-uppercase fw-bold mb-1">Contraseña Actual (Temporal)</label>
                        <div class="input-group" style="border-radius: 12px; overflow: hidden; height: 45px;">
                            <span class="input-group-text bg-light border-end-0 text-success">
                                <i class="bi bi-lock"></i>
                            </span>
                            <input type="password" name="password_actual" class="form-control border-start-0 ps-0 bg-light" required autofocus>
                        </div>
                    </div>

                    <div class="compact-input">
                        <label class="form-label text-muted small text-uppercase fw-bold mb-1">Nueva Contraseña</label>
                        <div class="input-group" style="border-radius: 12px; overflow: hidden; height: 45px;">
                            <span class="input-group-text bg-light border-end-0 text-success">
                                <i class="bi bi-lock-fill"></i>
                            </span>
                            <input type="password" name="password_nuevo" class="form-control border-start-0 ps-0 bg-light" required minlength="8">
                        </div>
                        <div class="form-text mt-1" style="font-size: 0.7rem;">Mínimo 8 caracteres, 1 mayúscula, 1 minúscula y 1 número.</div>
                    </div>

                    <div class="compact-input">
                        <label class="form-label text-muted small text-uppercase fw-bold mb-1">Confirmar Contraseña</label>
                        <div class="input-group" style="border-radius: 12px; overflow: hidden; height: 45px;">
                            <span class="input-group-text bg-light border-end-0 text-success">
                                <i class="bi bi-check2-all"></i>
                            </span>
                            <input type="password" name="password_nuevo_confirmation" class="form-control border-start-0 ps-0 bg-light" required minlength="8">
                        </div>
                    </div>

                    <div class="d-grid mt-3">
                        <button type="submit" class="btn btn-success fw-bold py-2 shadow-sm" style="border-radius: 12px; font-size: 0.95rem; height: 45px;">
                            <i class="bi bi-check-circle-fill me-2"></i> Cambiar Contraseña
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

    {{-- ============================================================
        M2 [ScaModal] — Cargar ScaModal en esta página standalone.
        Esta página NO extiende layouts/app.blade.php (tiene diseño
        propio con fondo verde gradiente), así que cargamos sca-modal.js
        y el partial manualmente.
        ============================================================ --}}
    <script src="{{ asset('js/sca-modal.js') }}" defer></script>
    @include('partials._sca_modal')

    <script>
        // ===== RELOJ (código existente, sin cambios) =====
        function actualizarReloj() {
            const ahora = new Date();
            const h = String(ahora.getHours()).padStart(2, '0');
            const m = String(ahora.getMinutes()).padStart(2, '0');
            const s = String(ahora.getSeconds()).padStart(2, '0');
            document.getElementById('reloj').textContent = `${h}:${m}:${s}`;
        }
        setInterval(actualizarReloj, 1000);
        actualizarReloj();

        function toggleTheme() {
            const html = document.documentElement;
            const currentTheme = html.getAttribute('data-bs-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-bs-theme', newTheme);
            document.getElementById('themeIcon').className = newTheme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
        }

        // ============================================================
        // M2 [ScaModal] — Intercept form submit for AJAX flow (Option E)
        //
        // FLUJO:
        //   1. Usuario completa form y clic en "Cambiar Contraseña"
        //   2. JS intercepta el submit (e.preventDefault())
        //   3. Muestra modal loading ("Cambiando contraseña...")
        //   4. Hace fetch() al controller (POST con FormData)
        //   5. Controller valida, cambia password, setea flash y devuelve JSON
        //   6. JS hace redirect (loading sigue visible)
        //   7. Dashboard carga + lee flash + muestra success
        // ============================================================
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form[method="POST"]');
            if (!form) return;

            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                // Mostrar loading
                ScaModal.loading('Cambiando contraseña', 'Guardando tu nueva contraseña...');

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                        body: new FormData(form),
                    });

                    const data = await response.json();

                    if (data.success) {
                        // Option E: NO cerramos el loading. Se queda visible durante la navegación.
                        // El success se mostrará en el dashboard vía flash sca_modal (seteado en el controller).
                        window.location.href = data.redirect;
                    } else {
                        // En caso de error, cerrar el loading y mostrar error
                        ScaModal.close();
                        if (data.errors) {
                            const errores = Object.values(data.errors).flat();
                            ScaModal.error('Error de validación', errores.join('<br>'));
                        } else {
                            ScaModal.error('Error', data.mensaje || 'No se pudo cambiar la contraseña.');
                        }
                    }
                } catch (err) {
                    // Error de red/conexión
                    ScaModal.close();
                    ScaModal.error('Error de conexión', 'No se pudo contactar con el servidor. Inténtalo de nuevo.');
                }
            });
        });
    </script>
</body>
</html>