{{-- resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SCA-QR') · Fruitex de México</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* ============================================================
           GUÍA PARA CAMBIAR COLORES:
           Todos los colores del sistema se controlan desde aquí.
           Solo cambia los valores HEX (#...) y todo se actualizará.
           
           --sca-primary: Color principal (verde corporativo)
           --sca-primary-dark: Versión oscura del principal
           --sca-secondary: Color secundario (naranja)
           ============================================================ */
        :root {
            --sca-primary: #2e7d32;
            --sca-primary-dark: #1b5e20;
            --sca-secondary: #f57c00;
            
            /* MODO CLARO */
            --sca-bg: #f0f2f5;
            --sca-card-bg: #ffffff;
            --sca-sidebar-bg: rgba(255, 255, 255, 0.85);
            --sca-topbar-bg: rgba(255, 255, 255, 0.8);
            --sca-text: #333333;
            --sca-text-muted: #6c757d;
            --sca-border: rgba(0, 0, 0, 0.08);
            --sca-hover: rgba(46, 125, 50, 0.06);
            --sca-input-bg: #ffffff;
            --sca-input-border: #e0e0e0;
            --sca-shadow: rgba(0, 0, 0, 0.06);
        }
        
        /* MODO OSCURO */
        [data-bs-theme="dark"] {
            --sca-bg: #0f1419;
            --sca-card-bg: #1a1f2e;
            --sca-sidebar-bg: rgba(26, 31, 46, 0.85);
            --sca-topbar-bg: rgba(26, 31, 46, 0.8);
            --sca-text: #e0e0e0;
            --sca-text-muted: #8b949e;
            --sca-border: rgba(255, 255, 255, 0.08);
            --sca-hover: rgba(102, 187, 106, 0.1);
            --sca-input-bg: #242838;
            --sca-input-border: #3a3f4f;
            --sca-shadow: rgba(0, 0, 0, 0.3);
            
            color-scheme: dark;
        }
        
        * {
            font-family: 'Sweet Ale Two', 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        body {
            background-color: var(--sca-bg);
            color: var(--sca-text);
            font-size: 0.95rem;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--sca-text-muted); border-radius: 10px; opacity: 0.3; }
        ::-webkit-scrollbar-thumb:hover { background: var(--sca-primary); }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
        
        /* ===== SIDEBAR ===== */
        .sidebar {
            width: 260px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background: var(--sca-sidebar-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            box-shadow: 4px 0 24px var(--sca-shadow);
            z-index: 1000;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            border-right: 1px solid var(--sca-border);
        }
        
        .sidebar-header {
            padding: 1.25rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-bottom: 1px solid var(--sca-border);
        }
        
        .sidebar-logo-img {
            height: 42px;
            width: auto;
            max-width: 180px;
            object-fit: contain;
        }
        
        .sidebar-logo-fallback {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, var(--sca-primary), var(--sca-primary-dark));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 800;
            font-size: 1.1rem;
            box-shadow: 0 4px 12px rgba(46, 125, 50, 0.3);
        }
        
        .sidebar-brand-text h5 { margin: 0; font-size: 1.1rem; font-weight: 700; color: var(--sca-text); }
        .sidebar-brand-text small { color: var(--sca-text-muted); font-size: 0.75rem; font-weight: 500; }
        
        .sidebar-nav { flex: 1; overflow-y: auto; padding: 1rem 0.75rem; }
        
        .nav-section-title {
            font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.8px;
            color: var(--sca-text-muted); margin: 1.5rem 0.75rem 0.5rem 0.75rem; font-weight: 700;
        }
        
        .sidebar-nav .nav-link {
            color: var(--sca-text); padding: 0.75rem 1rem; border-radius: 10px;
            margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.85rem;
            font-weight: 500; transition: all 0.25s ease; font-size: 0.9rem;
            text-decoration: none;
        }
        
        .sidebar-nav .nav-link:hover {
            background-color: var(--sca-hover); color: var(--sca-primary);
            transform: translateX(4px);
        }
        
        .sidebar-nav .nav-link.active {
            background: linear-gradient(135deg, var(--sca-primary), var(--sca-primary-dark));
            color: white; box-shadow: 0 4px 12px rgba(46, 125, 50, 0.25);
        }
        
        .sidebar-nav .nav-link i { font-size: 1.15rem; }
        .sidebar-footer { padding: 1rem; border-top: 1px solid var(--sca-border); font-size: 0.75rem; color: var(--sca-text-muted); text-align: center; }
        
        /* ===== MAIN CONTENT ===== */
        .main-content { margin-left: 260px; min-height: 100vh; display: flex; flex-direction: column; }
        
        .topbar {
            background: var(--sca-topbar-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            height: 68px;
            box-shadow: 0 1px 12px var(--sca-shadow);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 2rem; position: sticky; top: 0; z-index: 999;
            border-bottom: 1px solid var(--sca-border);
        }
        
        .content-wrapper { padding: 2rem; flex: 1; animation: fadeInUp 0.4s ease-out; }
        
        /* ===== CARDS & UI ===== */
        .card {
            border: 1px solid var(--sca-border); border-radius: 16px; 
            box-shadow: 0 2px 8px var(--sca-shadow);
            background: var(--sca-card-bg);
            transition: all 0.3s ease;
        }
        
        .card-hover:hover { transform: translateY(-5px); box-shadow: 0 12px 24px var(--sca-shadow) !important; }
        
        .card-header { 
            background: transparent !important; border-bottom: 1px solid var(--sca-border); 
            font-weight: 600; padding: 1.25rem 1.5rem; color: var(--sca-text);
        }
        
        .btn { font-weight: 500; border-radius: 10px; padding: 0.6rem 1.2rem; transition: all 0.2s; }
        .btn-success { background: linear-gradient(135deg, var(--sca-primary), var(--sca-primary-dark)); border: none; box-shadow: 0 4px 12px rgba(46, 125, 50, 0.2); color: white; }
        .btn-success:hover { box-shadow: 0 6px 16px rgba(46, 125, 50, 0.3); transform: translateY(-2px); color: white; }
        .btn-outline-primary { color: var(--sca-primary); border-color: var(--sca-primary); }
        .btn-outline-primary:hover { background-color: var(--sca-primary); border-color: var(--sca-primary); color: white; }
        
        .form-control, .form-select { 
        border-radius: 10px; padding: 0.7rem 1rem; 
        border: 1px solid var(--sca-input-border); 
        background: var(--sca-input-bg); 
        color: var(--sca-text) !important; /* Fuerza el color del texto */
        transition: all 0.2s;
        }
        .form-control:focus, .form-select:focus { 
            border-color: var(--sca-primary); 
            box-shadow: 0 0 0 4px rgba(46, 125, 50, 0.12); 
            background: var(--sca-input-bg); 
            color: var(--sca-text) !important;
        }
        .form-control::placeholder { color: var(--sca-text-muted); opacity: 0.6; }
        
        /* Estilo especial para el input oculto del kiosko */
        #scanner-input { color: transparent !important; background: transparent !important; border: none !important; }
        
        /* ===== BADGES ===== */
        .badge { font-weight: 500; padding: 0.4em 0.7em; border-radius: 6px; }
        
        /* ===== THEME TOGGLE ===== */
        .theme-toggle {
            background: transparent; border: 1px solid var(--sca-border); 
            border-radius: 10px; width: 40px; height: 40px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: all 0.3s; color: var(--sca-text);
        }
        .theme-toggle:hover { background: var(--sca-hover); border-color: var(--sca-primary); color: var(--sca-primary); }
        .theme-toggle .bi-sun { display: none; }
        .theme-toggle .bi-moon { display: block; }
        [data-bs-theme="dark"] .theme-toggle .bi-sun { display: block; }
        [data-bs-theme="dark"] .theme-toggle .bi-moon { display: none; }
        
        /* ===== MISC ===== */
        .text-muted { color: var(--sca-text-muted) !important; }
        .text-success { color: var(--sca-primary) !important; }
        .bg-light { background-color: var(--sca-hover) !important; }
        .modal-content { background: var(--sca-card-bg); color: var(--sca-text); border: 1px solid var(--sca-border); }
        .modal-header { border-bottom: 1px solid var(--sca-border); }
        .modal-footer { border-top: 1px solid var(--sca-border); }
        .nav-tabs { border-bottom: 1px solid var(--sca-border); }
        .nav-tabs .nav-link { color: var(--sca-text-muted); border: none; border-bottom: 2px solid transparent; }
        .nav-tabs .nav-link.active { color: var(--sca-primary); border-bottom: 2px solid var(--sca-primary); background: transparent; }
        .nav-tabs .nav-link:hover { border-color: transparent; color: var(--sca-primary); }
        
        .sidebar-toggle { display: none; background: transparent; border: none; font-size: 1.6rem; color: var(--sca-text); }
        
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .sidebar-toggle { display: block; }
        }

                /* ===== DARK MODE GLOBAL — Bootstrap overrides ===== */
        [data-bs-theme="dark"] body { background-color: var(--sca-bg); color: var(--sca-text); }

        [data-bs-theme="dark"] .table { --bs-table-bg: transparent; --bs-table-color: var(--sca-text); }
        [data-bs-theme="dark"] .table-light { --bs-table-bg: rgba(255,255,255,0.05); --bs-table-color: var(--sca-text); background-color: rgba(255,255,255,0.05) !important; }
        [data-bs-theme="dark"] .table-hover > tbody > tr:hover > * { background-color: rgba(255,255,255,0.04); }
        [data-bs-theme="dark"] .table td, [data-bs-theme="dark"] .table th { border-color: var(--sca-border); }

        [data-bs-theme="dark"] .bg-light { background-color: rgba(255,255,255,0.05) !important; color: var(--sca-text) !important; }
        [data-bs-theme="dark"] .text-dark { color: var(--sca-text) !important; }
        [data-bs-theme="dark"] .text-muted { color: var(--sca-text-muted) !important; }
        [data-bs-theme="dark"] .form-text { color: var(--sca-text-muted) !important; }

        [data-bs-theme="dark"] .card { background: var(--sca-card-bg); color: var(--sca-text); }
        [data-bs-theme="dark"] .card-header { background: transparent !important; border-color: var(--sca-border); color: var(--sca-text); }
        [data-bs-theme="dark"] .card-body { color: var(--sca-text); }

        [data-bs-theme="dark"] .modal-content { background: var(--sca-card-bg); color: var(--sca-text); border-color: var(--sca-border); }
        [data-bs-theme="dark"] .modal-header { border-color: var(--sca-border); }
        [data-bs-theme="dark"] .modal-footer { border-color: var(--sca-border); }
        [data-bs-theme="dark"] .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }

        [data-bs-theme="dark"] .form-control, [data-bs-theme="dark"] .form-select {
            background: var(--sca-input-bg); color: var(--sca-text) !important; border-color: var(--sca-input-border);
        }
        [data-bs-theme="dark"] .form-control::placeholder { color: var(--sca-text-muted); }
        [data-bs-theme="dark"] .input-group-text { background: rgba(255,255,255,0.05); border-color: var(--sca-input-border); color: var(--sca-text-muted); }

        [data-bs-theme="dark"] .dropdown-menu { background: var(--sca-card-bg); border-color: var(--sca-border); }
        [data-bs-theme="dark"] .dropdown-item { color: var(--sca-text); }
        [data-bs-theme="dark"] .dropdown-item:hover { background: var(--sca-hover); }

        [data-bs-theme="dark"] .nav-tabs { border-color: var(--sca-border); }
        [data-bs-theme="dark"] .nav-tabs .nav-link { color: var(--sca-text-muted); }
        [data-bs-theme="dark"] .nav-tabs .nav-link.active { color: var(--sca-primary); border-bottom-color: var(--sca-primary); }

        [data-bs-theme="dark"] .alert-info { background: rgba(23,162,184,0.15); color: #5dcfe8; border-color: rgba(23,162,184,0.3); }
        [data-bs-theme="dark"] .alert-success { background: rgba(40,167,69,0.15); color: #6dd07f; border-color: rgba(40,167,69,0.3); }
        [data-bs-theme="dark"] .alert-warning { background: rgba(255,193,7,0.15); color: #ffdb6d; border-color: rgba(255,193,7,0.3); }
        [data-bs-theme="dark"] .alert-danger { background: rgba(220,53,69,0.15); color: #f08490; border-color: rgba(220,53,69,0.3); }

        [data-bs-theme="dark"] .badge.bg-light { background: rgba(255,255,255,0.1) !important; color: var(--sca-text) !important; }
        [data-bs-theme="dark"] .badge.bg-secondary { background: rgba(255,255,255,0.15) !important; color: var(--sca-text) !important; }
        [data-bs-theme="dark"] .badge.bg-warning { background: #f57c00 !important; color: #fff !important; }
        [data-bs-theme="dark"] .badge.bg-info { background: #17a2b8 !important; color: #fff !important; }

        [data-bs-theme="dark"] .progress { background: rgba(255,255,255,0.08); }
        [data-bs-theme="dark"] .list-group-item { background: var(--sca-card-bg); color: var(--sca-text); border-color: var(--sca-border); }
        [data-bs-theme="dark"] .list-group-item-action:hover { background: var(--sca-hover); }

        [data-bs-theme="dark"] .btn-outline-secondary { color: var(--sca-text-muted); border-color: var(--sca-border); }
        [data-bs-theme="dark"] .btn-outline-secondary:hover { background: var(--sca-hover); color: var(--sca-text); }
        [data-bs-theme="dark"] .btn-outline-primary { color: var(--sca-primary); border-color: var(--sca-primary); }
        [data-bs-theme="dark"] .btn-outline-danger { color: #f08490; border-color: rgba(220,53,69,0.5); }
        [data-bs-theme="dark"] .btn-outline-danger:hover { background: #dc3545; color: #fff; }
        [data-bs-theme="dark"] .btn-outline-warning { color: #ffdb6d; border-color: rgba(255,193,7,0.5); }
        [data-bs-theme="dark"] .btn-outline-info { color: #5dcfe8; border-color: rgba(23,162,184,0.5); }
        [data-bs-theme="dark"] .btn-outline-success { color: #6dd07f; border-color: rgba(40,167,69,0.5); }

        [data-bs-theme="dark"] .sca-alert-warning { background: rgba(255,193,7,0.12); color: #ffdb6d; border: 1px solid rgba(255,193,7,0.2); }
        [data-bs-theme="dark"] .sca-card { background: var(--sca-card-bg); }
        [data-bs-theme="dark"] .sca-modal-content { background: var(--sca-card-bg); color: var(--sca-text); }

        [data-bs-theme="dark"] .form-check-input { background-color: var(--sca-input-bg); border-color: var(--sca-input-border); }
        [data-bs-theme="dark"] .form-check-input:checked { background-color: var(--sca-primary); border-color: var(--sca-primary); }

        [data-bs-theme="dark"] .page-link { background: var(--sca-card-bg); border-color: var(--sca-border); color: var(--sca-primary); }
        [data-bs-theme="dark"] .page-item.disabled .page-link { background: var(--sca-card-bg); border-color: var(--sca-border); color: var(--sca-text-muted); }
        [data-bs-theme="dark"] .page-item.active .page-link { background: var(--sca-primary); border-color: var(--sca-primary); color: #fff; }

        [data-bs-theme="dark"] hr { border-color: var(--sca-border); opacity: 0.5; }
        [data-bs-theme="dark"] .border { border-color: var(--sca-border) !important; }
        [data-bs-theme="dark"] .border-top { border-top-color: var(--sca-border) !important; }
        [data-bs-theme="dark"] .border-bottom { border-bottom-color: var(--sca-border) !important; }
        [data-bs-theme="dark"] .border-start { border-left-color: var(--sca-border) !important; }
        [data-bs-theme="dark"] .border-end { border-right-color: var(--sca-border) !important; }

    </style>
    
    @stack('styles')
</head>
<body>

    @auth
    <!-- ===== SIDEBAR ===== -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            {{-- LOGO: si existe el archivo /logo.png lo usa, si no, muestra el fallback --}}
            @if(file_exists(public_path('logo.png')))
                <img src="{{ asset('logo-fruitex.png') }}" alt="Fruitex" class="sidebar-logo-img">
            @elseif(file_exists(public_path('logo.svg')))
                <img src="{{ asset('logo.svg') }}" alt="Fruitex" class="sidebar-logo-img">
            @else
                <div class="sidebar-logo-fallback">SCA</div>
                <div class="sidebar-brand-text">
                    <h5>SCA-QR</h5>
                    <small>Fruitex de México</small>
                </div>
            @endif
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-section-title">Principal</div>
            <a href="{{ url('/') }}" class="nav-link">
                <i class="bi bi-grid"></i> Dashboard
            </a>

            {{-- NUEVO: Solicitudes visible para TODOS los roles --}}
            <a href="{{ route('solicitudes.hub') }}" class="nav-link">
                <i class="bi bi-file-earmark-text"></i> Solicitudes
            </a>
            
            @if(Auth::user()->isSuperadmin() || Auth::user()->isAdmin() || Auth::user()->isAprobador())
            <div class="nav-section-title">Gestión</div>
            <a href="{{ route('usuarios.index') }}" class="nav-link">
                <i class="bi bi-people"></i> Usuarios
            </a>
            <a href="{{ route('empleados.index') }}" class="nav-link">
                <i class="bi bi-person-badge"></i> Empleados
            </a>
            <a href="{{ route('puestos.index') }}" class="nav-link">
                <i class="bi bi-list-task"></i> Clasificar Puestos
            </a>
            <a href="{{ route('clasificaciones.index') }}" class="nav-link">
                <i class="bi bi-diagram-3"></i> Clasificaciones → Plantas
            </a>
            @endif
            
            @if(Auth::user()->isSuperadmin())
            <div class="nav-section-title">Administración</div>
            <a href="#" class="nav-link">
                <i class="bi bi-shield-lock"></i> Logs Sistema
            </a>
            @endif
        </nav>
        
        <div class="sidebar-footer">
            &copy; {{ date('Y') }} SCA-QR v1.0
        </div>
    </div>

    <!-- ===== MAIN CONTENT ===== -->
    <div class="main-content">
        <header class="topbar">
            <button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button>
            
            <div class="d-flex align-items-center gap-3 ms-auto">
                <div class="d-flex align-items-center gap-3 ms-auto">
                {{-- NUEVO: Reloj en tiempo real --}}
                @include('partials._reloj', ['id' => 'topbar'])
                {{-- BOTÓN MODO OSCURO --}}
                <button class="theme-toggle" id="themeToggle" title="Cambiar tema">
                    <i class="bi bi-sun"></i>
                    <i class="bi bi-moon"></i>
                </button>
                
                <div class="text-end d-none d-md-block">
                    <div style="font-size: 0.9rem; font-weight: 600; line-height: 1.2; color: var(--sca-text);">
                        {{ Auth::user()->nombre_completo }}
                    </div>
                    <div style="font-size: 0.75rem; color: var(--sca-text-muted);">
                        <span class="badge" style="background: var(--sca-hover); color: var(--sca-primary);">{{ Auth::user()->rol->nombre }}</span>
                        @if(Auth::user()->planta_nombre && Auth::user()->planta_nombre !== 'Sin planta')
                        · {{ Auth::user()->planta_nombre }}
                        @endif
                    </div>
                </div>
                {{-- NUEVO: los guardias NO ven este botón (usan Terminar Turno en su dashboard) --}}
                @if(!Auth::user()->isGuardia())
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Cerrar sesión">
                        <i class="bi bi-box-arrow-right"></i>
                    </button>
                </form>
                @endif
            </div>
        </header>

        <main class="content-wrapper">
            @auth
                @php
                    $puestosTotales = \App\Models\Sitec\Puesto::count();
                    $puestosClasificados = \App\Models\PuestoTipoEmpleado::count();
                    $puestosSinClasificar = $puestosTotales - $puestosClasificados;
                @endphp
                @if((Auth::user()->isSuperadmin() || Auth::user()->isAdmin() || Auth::user()->isAprobador()) && $puestosSinClasificar > 0)
                    <div class="alert d-flex align-items-center mb-4 animate-fade-in-up" 
                         style="border-radius: 12px; background: rgba(255, 193, 7, 0.12); color: #856404; border: 1px solid rgba(255, 193, 7, 0.2);">
                        <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
                        <div class="flex-grow-1">
                            <strong>{{ $puestosSinClasificar }} puestos sin clasificar.</strong> 
                            Los empleados con esos puestos pasarán como "No clasificados".
                        </div>
                        <a href="{{ route('puestos.index') }}" class="btn btn-sm btn-warning ms-3">Clasificar</a>
                    </div>
                @endif
            @endauth

            @if (session('success'))
                <div class="alert d-flex align-items-center mb-4 animate-fade-in-up" 
                     style="border-radius: 12px; background: rgba(40, 167, 69, 0.12); color: #155724; border: 1px solid rgba(40, 167, 69, 0.2);">
                    <i class="bi bi-check-circle-fill me-3 fs-4"></i>
                    <div class="flex-grow-1">{{ session('success') }}</div>
                </div>
            @endif

            @if (session('error'))
                <div class="alert d-flex align-items-center mb-4 animate-fade-in-up" 
                     style="border-radius: 12px; background: rgba(220, 53, 69, 0.12); color: #721c24; border: 1px solid rgba(220, 53, 69, 0.2);">
                    <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
                    <div class="flex-grow-1">{{ session('error') }}</div>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
    @endauth

    @guest
        @yield('content')
    @endguest

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ===== TOGGLE SIDEBAR (móvil) =====
        document.getElementById('sidebarToggle')?.addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('show');
        });
        
        // ===== MODO OSCURO =====
        // Al cargar la página, aplicar el tema guardado
        (function() {
            const savedTheme = localStorage.getItem('sca-theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
        
        // Toggle del tema
        document.getElementById('themeToggle')?.addEventListener('click', () => {
            const current = document.documentElement.getAttribute('data-bs-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-bs-theme', next);
            localStorage.setItem('sca-theme', next);
        });
    </script>
    @stack('scripts')
</body>
</html>