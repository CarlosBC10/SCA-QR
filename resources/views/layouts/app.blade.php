{{-- resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sistema de Control de Accesos')</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous" rel="stylesheet">
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
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
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

/* ============================================================
   BRANDING SIDEBAR — SCA-QR protagonista + Fruitex al pie
   ============================================================
   Estructura:
   .sidebar-brand          (header)
     └─ .sidebar-brand-link
        └─ .sidebar-sca-brand
           └─ img.scaqr_logo.png
   .sidebar-bottom         (footer)
     ├─ .sidebar-company   (card con logo Fruitex)
     ├─ .sidebar-session   (estado sesión)
     └─ .sidebar-footer    (copyright)
   ============================================================ */

.sidebar-brand {
    padding: 1.05rem 1rem 0.95rem;
    border-bottom: 1px solid var(--sca-border);
    background: linear-gradient(180deg, rgba(46, 125, 50, 0.045), transparent 80%);
    text-align: center;
}

.sidebar-brand-link {
    display: block;
    text-decoration: none;
    color: inherit;
}

.sidebar-sca-brand {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    margin: 0 0.75rem;
    padding: 0.85rem 0.75rem;
    border: 1px solid var(--sca-border);
    border-radius: 14px;
    /* Fondo sutil para que el logo resalte */
    background: color-mix(in srgb, var(--sca-card-bg) 70%, transparent);
    box-sizing: border-box;
    transition: all 0.25s ease;
}

.sidebar-brand-link:hover .sidebar-sca-brand {
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
    border-color: rgba(46, 125, 50, 0.25);
}

.sidebar-sca-logo-main {
    display: block;
    width: 76px;
    height: 76px;
    object-fit: contain;
    filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.12));
    transition: filter 0.25s ease;
}

.sidebar-brand-link:hover .sidebar-sca-logo-main {
    filter: drop-shadow(0 7px 12px rgba(0, 0, 0, 0.18));
}

/* Empresa (al pie del sidebar) */
.sidebar-company {
    padding: 0.85rem 0.75rem 0.7rem;
    margin: 0 0.75rem 0.8rem;
    border: 1px solid var(--sca-border);
    border-radius: 14px;
    background: color-mix(in srgb, var(--sca-card-bg) 70%, transparent);
    text-align: center;
}

.sidebar-company-label {
    display: block;
    margin-bottom: 0.5rem;
    font-size: 0.6rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.95px;
    color: var(--sca-text-muted);
    opacity: 0.7;
}

.sidebar-company-logo-wrap {
    min-height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.sidebar-company-logo {
    display: block;
    width: auto;
    max-width: 132px;
    height: 46px;
    object-fit: contain;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.08));
    opacity: 0.96;
    transition: transform 0.25s ease, opacity 0.25s ease;
}

.sidebar-company:hover .sidebar-company-logo {
    transform: scale(1.02);
    opacity: 1;
}

/* Sesión */
.sidebar-session {
    padding: 0 1rem 0.75rem;
}

.sidebar-status {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--sca-text-muted);
}

.status-dot {
    width: 7px;
    height: 7px;
    flex-shrink: 0;
    border-radius: 50%;
    background: #2e7d32;
    box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.12);
}

/* Footer */
.sidebar-footer {
    padding: 0.65rem 1rem 0.85rem;
    border-top: 1px solid var(--sca-border);
    display: flex;
    flex-direction: column;
    gap: 0.18rem;
    text-align: center;
    font-size: 0.75rem;
    color: var(--sca-text-muted);
}

.sidebar-footer span {
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--sca-text-muted);
    opacity: 0.95;
}

.sidebar-footer small {
    font-size: 0.62rem;
    color: var(--sca-text-muted);
    opacity: 0.85;
}

/* Dark mode */
[data-bs-theme="dark"] .sidebar-brand {
    background: linear-gradient(180deg, rgba(102, 187, 106, 0.06), transparent 80%);
}

/* En dark mode, las cards de SCA-QR y Fruitex usan fondo un poco más claro */
[data-bs-theme="dark"] .sidebar-sca-brand {
    background: rgba(255, 255, 255, 0.04);
    border-color: rgba(255, 255, 255, 0.1);
}

[data-bs-theme="dark"] .sidebar-sca-brand:hover {
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(102, 187, 106, 0.3);
}

[data-bs-theme="dark"] .sidebar-company {
    background: rgba(255, 255, 255, 0.04);
    border-color: rgba(255, 255, 255, 0.1);
}

/* En dark mode, el footer necesita más opacidad para ser legible */
[data-bs-theme="dark"] .sidebar-footer span {
    opacity: 1;
    color: rgba(232, 232, 232, 0.95);
}

[data-bs-theme="dark"] .sidebar-footer small {
    opacity: 0.9;
    color: rgba(180, 180, 180, 0.95);
}

[data-bs-theme="dark"] .sidebar-sca-logo-main {
    filter: drop-shadow(0 5px 10px rgba(0, 0, 0, 0.32));
}

[data-bs-theme="dark"] .sidebar-company-logo {
    filter: drop-shadow(0 3px 6px rgba(0, 0, 0, 0.32));
}

/* Responsive */
@media (max-width: 991.98px) {
    .sidebar-brand { padding: 0.85rem 0.9rem; }
    .sidebar-sca-logo-main { width: 64px; height: 64px; }
    .sidebar-company { margin: 0 0.6rem 0.65rem; padding: 0.7rem; }
    .sidebar-company-logo { max-width: 118px; height: 40px; }
}
        
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

        /* Submenú sidebar */
        #submenuSolicitudes .nav-link {
            font-size: 0.85rem;
            padding-left: 2rem;
        }
        #submenuSolicitudes .nav-link.active {
            background: none;
            color: var(--sca-primary);
            font-weight: 600;
        }

    </style>
    <link rel="stylesheet" href="{{ asset('css/sca-ui.css') }}">
    <link rel="stylesheet" href="{{ asset('css/sca-prototype.css') }}">

    {{-- ===== ScaQR Branding v6 — favicon set ===== --}}
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('icons/favicon-16x16.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('icons/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="48x48" href="{{ asset('icons/favicon-48x48.png') }}">
    <link rel="apple-touch-icon" sizes="120x120" href="{{ asset('icons/apple-touch-icon-120x120.png') }}">
    <link rel="apple-touch-icon" sizes="152x152" href="{{ asset('icons/apple-touch-icon-152x152.png') }}">
    <link rel="apple-touch-icon" sizes="167x167" href="{{ asset('icons/apple-touch-icon-167x167.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('icons/site.webmanifest') }}">
    <meta name="theme-color" content="#0F5132">
    <meta name="msapplication-TileColor" content="#0F5132">
    <meta name="msapplication-config" content="{{ asset('icons/browserconfig.xml') }}">

    @stack('styles')

    <script>
        (function () {
            try {
                const theme = localStorage.getItem('sca-theme') || 'light';
                document.documentElement.setAttribute('data-bs-theme', theme === 'dark' ? 'dark' : 'light');
            } catch (e) {
                document.documentElement.setAttribute('data-bs-theme', 'light');
            }
        })();
    </script>
</head>
<body>

    @auth
    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar" id="sidebar" aria-label="Navegación principal">
        <div class="sidebar-brand">
            <a href="{{ url('/') }}" class="sidebar-brand-link" aria-label="SCA-QR - Ir al inicio">
                {{-- Logo principal del sistema (el PNG ya trae texto "SCA-QR / Sistema de Control") --}}
                <div class="sidebar-sca-brand">
                    <img src="{{ asset('logos/scaqr_logo.png') }}" alt="SCA-QR · Sistema de Control de Accesos" class="sidebar-sca-logo-main">
                </div>
            </a>
        </div>

        <nav class="sidebar-nav" aria-label="Módulos del sistema">
            <div class="nav-section-title"><span>Inicio</span></div>
            <a href="{{ url('/') }}" class="nav-link {{ request()->routeIs('*.dashboard') ? 'active' : '' }}" aria-current="{{ request()->routeIs('*.dashboard') ? 'page' : 'false' }}" title="Dashboard">
                <span class="nav-icon"><i class="bi bi-grid-1x2-fill" aria-hidden="true"></i></span>
                <span class="nav-label">Dashboard</span>
            </a>

            @if(!Auth::user()->isGuardia())
            <div class="nav-section-title"><span>Operación</span></div>
            <div class="nav-group">
                <a href="#" class="nav-link nav-parent {{ request()->routeIs('solicitudes.*') ? 'active' : '' }}" data-bs-toggle="collapse" data-bs-target="#submenuSolicitudes" aria-expanded="{{ request()->routeIs('solicitudes.*') ? 'true' : 'false' }}" title="Solicitudes">
                    <span class="nav-main"><span class="nav-icon"><i class="bi bi-arrow-left-right" aria-hidden="true"></i></span><span class="nav-label">Solicitudes</span></span>
                    <i class="bi bi-chevron-down nav-chevron" aria-hidden="true"></i>
                </a>
                <div class="collapse nav-submenu {{ request()->routeIs('solicitudes.*') ? 'show' : '' }}" id="submenuSolicitudes">
                    <a href="{{ route('solicitudes.create') }}" class="nav-link {{ request()->routeIs('solicitudes.create') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="bi bi-plus-lg" aria-hidden="true"></i></span><span class="nav-label">Nueva solicitud</span>
                    </a>
                    <a href="{{ route('solicitudes.mis') }}" class="nav-link {{ request()->routeIs('solicitudes.mis') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="bi bi-file-earmark-person" aria-hidden="true"></i></span><span class="nav-label">Mis solicitudes</span>
                    </a>
                    @if(Auth::user()->isAprobador() || Auth::user()->isAdmin() || Auth::user()->isSuperadmin())
                    <a href="{{ route('solicitudes.bandeja') }}" class="nav-link {{ request()->routeIs('solicitudes.bandeja') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="bi bi-inbox-fill" aria-hidden="true"></i></span><span class="nav-label">Bandeja</span>
                    </a>
                    <a href="{{ route('solicitudes.mis-aprobaciones') }}" class="nav-link {{ request()->routeIs('solicitudes.mis-aprobaciones') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="bi bi-check2-square" aria-hidden="true"></i></span><span class="nav-label">Mis aprobaciones</span>
                    </a>
                    @endif
                    @if(Auth::user()->isAdmin() || Auth::user()->isSuperadmin())
                    <a href="{{ route('solicitudes.historial') }}" class="nav-link {{ request()->routeIs('solicitudes.historial') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="bi bi-clock-history" aria-hidden="true"></i></span><span class="nav-label">Historial</span>
                    </a>
                    @endif
                </div>
            </div>

            @if(Auth::user()->isAdmin() || Auth::user()->isSuperadmin() || Auth::user()->isAprobador())
            <a href="{{ route('bitacora.index') }}" class="nav-link {{ request()->routeIs('bitacora.*') ? 'active' : '' }}" title="Bitácora de accesos">
                <span class="nav-icon"><i class="bi bi-journal-check" aria-hidden="true"></i></span><span class="nav-label">Bitácora de accesos</span>
            </a>
            @endif
            @endif

            @if(Auth::user()->isSuperadmin() || Auth::user()->isAdmin() || Auth::user()->isAprobador())
            <div class="nav-section-title"><span>Gestión</span></div>
            <a href="{{ route('usuarios.index') }}" class="nav-link {{ request()->routeIs('usuarios.*') ? 'active' : '' }}" title="Usuarios">
                <span class="nav-icon"><i class="bi bi-people-fill" aria-hidden="true"></i></span><span class="nav-label">Usuarios</span>
            </a>
            <a href="{{ route('empleados.index') }}" class="nav-link {{ request()->routeIs('empleados.*') ? 'active' : '' }}" title="Empleados">
                <span class="nav-icon"><i class="bi bi-person-vcard-fill" aria-hidden="true"></i></span><span class="nav-label">Empleados</span>
            </a>
            <a href="{{ route('puestos.index') }}" class="nav-link {{ request()->routeIs('puestos.*') ? 'active' : '' }}" title="Puestos">
                <span class="nav-icon"><i class="bi bi-diagram-2-fill" aria-hidden="true"></i></span><span class="nav-label">Puestos</span>
            </a>
            <a href="{{ route('clasificaciones.index') }}" class="nav-link {{ request()->routeIs('clasificaciones.*') ? 'active' : '' }}" title="Clasificación por planta">
                <span class="nav-icon"><i class="bi bi-diagram-3-fill" aria-hidden="true"></i></span><span class="nav-label">Clasificación por planta</span>
            </a>
            {{-- MÓDULO DE CONFIGURACIÓN: Admin/Superadmin únicamente (el bloque
                  "Gestión" también deja entrar a Aprobadores, por eso el if interno). --}}
            @if(Auth::user()->isSuperadmin() || Auth::user()->isAdmin())
            <a href="{{ route('configuracion.index') }}" class="nav-link {{ request()->routeIs('configuracion.*') ? 'active' : '' }}" title="Configuración del sistema">
                <span class="nav-icon"><i class="bi bi-gear-fill" aria-hidden="true"></i></span><span class="nav-label">Configuración</span>
            </a>
            @endif
            @endif

            @if(Auth::user()->isSuperadmin())
            <div class="nav-section-title"><span>Sistema</span></div>
            <a href="{{ route('logs.index') }}" class="nav-link {{ request()->routeIs('logs.*') ? 'active' : '' }}" title="Logs del sistema">
                <span class="nav-icon"><i class="bi bi-shield-lock-fill text-danger" aria-hidden="true"></i></span><span class="nav-label">Logs del sistema</span>
            </a>
            @endif
        </nav>

        <div class="sidebar-bottom">
            @if(file_exists(public_path('logo-empresa.png')))
                <div class="sidebar-company">
                    <div class="sidebar-company-logo-wrap">
                        <img src="{{ asset('logo-empresa.png') }}" alt="Fruitex de México" class="sidebar-company-logo">
                    </div>
                </div>
            @endif

            {{-- Estado de sesión --}}
            <div class="sidebar-session">
                <span class="sidebar-status"><span class="status-dot"></span><span>Sesión activa</span></span>
            </div>

            {{-- Footer compacto --}}
            <div class="sidebar-footer"><span>SCA-QR · v1.0</span><small>&copy; {{ date('Y') }} Fruitex de México</small></div>
        </div>
    </aside>

    <div class="sca-mobile-overlay" id="scaMobileOverlay" aria-hidden="true"></div>

    <!-- ===== MAIN CONTENT ===== -->
    <div class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Abrir menú de navegación" aria-controls="sidebar" aria-expanded="false">
                    <i class="bi bi-list" aria-hidden="true"></i>
                </button>
                <div class="topbar-context d-none d-lg-flex">
                    <div class="topbar-context-icon"><i class="bi bi-shield-check" aria-hidden="true"></i></div>
                    <div><strong>SCA-QR</strong><span>Control de accesos</span></div>
                </div>
            </div>
            <div class="topbar-tools">
                @include('partials._campana')
                <div class="topbar-clock d-none d-lg-flex">@include('partials._reloj', ['id' => 'topbar'])</div>
                <button type="button" class="theme-toggle" id="themeToggle" title="Cambiar tema" aria-label="Cambiar tema" aria-pressed="false">
                    <i class="bi bi-sun-fill" aria-hidden="true"></i><i class="bi bi-moon-stars-fill" aria-hidden="true"></i>
                </button>
                <div class="user-menu" aria-label="Usuario actual">
                    <div class="user-avatar" aria-hidden="true">{{ strtoupper(substr(Auth::user()->nombre_completo ?? 'U', 0, 1)) }}</div>
                    <div class="user-copy d-none d-md-block">
                        <strong>{{ Auth::user()->nombre_completo }}</strong>
                        <span><span class="user-role">{{ Auth::user()->rol->nombre }}</span>@if(Auth::user()->planta_nombre && Auth::user()->planta_nombre !== 'Sin planta')<span class="user-divider">·</span>{{ Auth::user()->planta_nombre }}@endif</span>
                    </div>
                    @if(!Auth::user()->isGuardia())
                    <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form-global').submit();" class="logout-button" title="Cerrar sesión" aria-label="Cerrar sesión"><i class="bi bi-box-arrow-right" aria-hidden="true"></i></a>
                    @endif
                </div>
            </div>
        </header>

        <main class="content-wrapper">
            {{-- ============================================================
                M1 [ScaModal] — Lector del flash estructurado 'sca_modal'.
                (MOVIDO FUERA de @auth para que funcione también en /login)

                Dispara el modal automáticamente cuando un controller hace:
                ->with('sca_modal', ['tipo'=>'success', 'titulo'=>'...', 'mensaje'=>'...'])

                Antes este bloque estaba dentro de @auth, así que no se disparaba
                en la pantalla de login (que es @guest). Ahora se dispara siempre.

                M2 va a reemplazar todos los ->with('success'/'error') por ->with('sca_modal', ...).
                M7 eliminará los lectores viejos (session('success'/'error')) de abajo.
                ============================================================ --}}
            {{-- SCA-MODAL — ÚNICO PUNTO DE ENTRADA
                 AJAX → sessionStorage
                 Normal → Laravel flash
                 El 'return' evita que se procesen ambos. --}}
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                // 1. Modal transportado por AJAX/sessionStorage
                var pending = sessionStorage.getItem('sca_pending_modal');
                if (pending) {
                    sessionStorage.removeItem('sca_pending_modal');
                    try {
                        var cfg = JSON.parse(pending);
                        if (typeof window.ScaModal !== 'undefined' && typeof window.ScaModal[cfg.tipo] === 'function') {
                            window.ScaModal[cfg.tipo](cfg.titulo, cfg.mensaje, { detalles: cfg.detalles || null });
                        } else {
                            console.error('[ScaModal] Tipo AJAX no reconocido:', cfg.tipo);
                        }
                    } catch (error) {
                        console.error('[ScaModal] Error leyendo sessionStorage:', error);
                    }
                    return; // Si hubo modal por AJAX, no procesamos el flash
                }

                // 2. Modal transportado por Laravel flash
                @if (session('sca_modal'))
                    var cfg = {{ \Illuminate\Support\Js::from(session('sca_modal')) }};
                    if (typeof window.ScaModal !== 'undefined' && typeof window.ScaModal[cfg.tipo] === 'function') {
                        window.ScaModal[cfg.tipo](cfg.titulo, cfg.mensaje, { detalles: cfg.detalles || null });
                    } else {
                        console.error('[ScaModal] Tipo flash no reconocido:', cfg.tipo);
                    }
                @endif
            });
            </script>

            @auth
                @php
                    $puestosTotales = \App\Models\Sitec\Puesto::count();
                    $puestosClasificados = \App\Models\PuestoTipoEmpleado::count();
                    $puestosSinClasificar = $puestosTotales - $puestosClasificados;
                @endphp
                @if((Auth::user()->isSuperadmin() || Auth::user()->isAdmin() || Auth::user()->isAprobador()) && $puestosSinClasificar > 0)
                    <div class="alert d-flex align-items-center mb-4 animate-fade-in-up sca-warning-alert">
                        <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
                        <div class="flex-grow-1">
                            <strong>{{ $puestosSinClasificar }} puestos sin clasificar.</strong> 
                            Los empleados con esos puestos pasarán como "No clasificados".
                        </div>
                        <a href="{{ route('puestos.index') }}" class="btn btn-sm btn-warning ms-3">Clasificar</a>
                    </div>
                @endif
            @endauth

            {{-- M7: Los lectores de session('success'/'error') y $errors->any() se eliminaron
                porque ahora todo se maneja con el flash 'sca_modal' de arriba. --}}

            {{-- M1 [ScaModal] — Lector sca_modal movido FUERA de @auth (ver arriba).
                Los lectores viejos de session('success'/'error') siguen acá abajo
                como fallback para los controllers que no se han migrado todavía
                (se eliminarán en M7). --}}

            @yield('content')
        </main>
    </div>
    @endauth

    @guest
        @yield('content')
    @endguest

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

    {{-- ============================================================
        M1 [ScaModal] — Carga del wrapper JS del sistema de modales.
        Se carga DESPUÉS de bootstrap.bundle.min.js porque depende de
        bootstrap.Modal. Se carga ANTES del @stack('scripts') para
        que cualquier script de vista pueda usar window.ScaModal.

        EXCLUSIÓN: El kiosko (rutas kiosko.*) NO carga ScaModal porque
        tiene su propio feedback visual (pantalla verde/roja grande).
        ============================================================ --}}
    @unless(request()->routeIs('kiosko.*'))
        {{-- defer: el JS descarga en paralelo pero espera a que el DOM se parseé
            completo antes de ejecutarse. Necesario porque #scaModal se incluye
            más abajo (en partials._sca_modal) y el JS lo busca al inicializar. --}}
        <script src="{{ asset('js/sca-modal.js') }}" defer></script>
    @endunless

    <script>
        (() => {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const mobileOverlay = document.getElementById('scaMobileOverlay');
            const themeToggle = document.getElementById('themeToggle');

            const closeSidebar = () => {
                sidebar?.classList.remove('show');
                mobileOverlay?.classList.remove('show');
                document.body.classList.remove('sca-sidebar-open');
                sidebarToggle?.setAttribute('aria-expanded', 'false');
                mobileOverlay?.setAttribute('aria-hidden', 'true');
            };

            const openSidebar = () => {
                sidebar?.classList.add('show');
                mobileOverlay?.classList.add('show');
                document.body.classList.add('sca-sidebar-open');
                sidebarToggle?.setAttribute('aria-expanded', 'true');
                mobileOverlay?.setAttribute('aria-hidden', 'false');
            };

            sidebarToggle?.addEventListener('click', () => {
                sidebar?.classList.contains('show') ? closeSidebar() : openSidebar();
            });

            mobileOverlay?.addEventListener('click', closeSidebar);
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape') closeSidebar();
            });

            sidebar?.querySelectorAll('a:not([data-bs-toggle="collapse"])').forEach(link => {
                link.addEventListener('click', () => {
                    if (window.matchMedia('(max-width: 992px)').matches) closeSidebar();
                });
            });

            themeToggle?.addEventListener('click', () => {
                const current = document.documentElement.getAttribute('data-bs-theme') || 'light';
                const next = current === 'dark' ? 'light' : 'dark';

                document.documentElement.setAttribute('data-bs-theme', next);
                localStorage.setItem('sca-theme', next);
                themeToggle.setAttribute('aria-pressed', next === 'dark' ? 'true' : 'false');
            });

            const currentTheme = document.documentElement.getAttribute('data-bs-theme') || 'light';
            themeToggle?.setAttribute('aria-pressed', currentTheme === 'dark' ? 'true' : 'false');

            const solicitudes = document.getElementById('submenuSolicitudes');
            const solicitudesToggle = document.querySelector('[data-bs-target="#submenuSolicitudes"]');

            if (solicitudes?.querySelector('.nav-link.active') && window.bootstrap) {
                const collapse = bootstrap.Collapse.getOrCreateInstance(solicitudes, { toggle: false });
                collapse.show();
                solicitudesToggle?.setAttribute('aria-expanded', 'true');
            }
        })();
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const defaults = {
                solicitudes: @json(route('solicitudes.hub')),
                bandeja: @json(route('solicitudes.hub')),
                usuarios: @json(route('usuarios.index')),
                empleados: @json(route('empleados.index')),
                puestos: @json(route('puestos.index')),
                clasificaciones: @json(route('clasificaciones.index')),
                inicio: @json(url('/'))
            };
            const path = window.location.pathname;
            document.querySelectorAll('a[href="javascript:history.back()"], a[data-sca-history-back]').forEach(function(link){
                link.setAttribute('href','#');
                link.addEventListener('click', function(e){
                    e.preventDefault();
                    let fallback = defaults.inicio;
                    if(path.includes('/solicitudes/bandeja')) fallback = defaults.bandeja;
                    else if(path.includes('/solicitudes')) fallback = defaults.solicitudes;
                    else if(path.includes('/usuarios')) fallback = defaults.usuarios;
                    else if(path.includes('/empleados')) fallback = defaults.empleados;
                    else if(path.includes('/puestos')) fallback = defaults.puestos;
                    else if(path.includes('/clasificaciones')) fallback = defaults.clasificaciones;
                    try {
                        const ref = document.referrer ? new URL(document.referrer) : null;
                        if(ref && ref.origin === window.location.origin) window.location.assign(ref.href);
                        else window.location.assign(fallback);
                    } catch(_) { window.location.assign(fallback); }
                });
            });
        });
    </script>
    @stack('scripts')

    {{-- FIX: Form de logout global, fuera de cualquier otro form para evitar anidamiento --}}
    <form id="logout-form-global" method="POST" action="{{ route('logout') }}" style="display:none;">
        @csrf
    </form>

    {{-- ============================================================
        M1 [ScaModal] — Container HTML + CSS del sistema de modales.
        Se incluye UNA sola vez por página, al final del body.

        EXCLUSIÓN: El kiosko (rutas kiosko.*) NO incluye el container
        porque no carga sca-modal.js (ver arriba) y tiene su propio
        feedback visual.
        ============================================================ --}}
    @unless(request()->routeIs('kiosko.*'))
        @include('partials._sca_modal')
    @endunless

</body>
</html>