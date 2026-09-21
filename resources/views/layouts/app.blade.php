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
           GUÍA PARA CAMBIAR COLORES
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

        /* ============================================================
           MODO OSCURO
           ============================================================ */
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

        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--sca-text-muted);
            border-radius: 10px;
            opacity: 0.3;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--sca-primary);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(15px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease-out forwards;
        }

        /* ============================================================
           SIDEBAR
           ============================================================ */
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

        /* ============================================================
           LEGACY / COMPATIBILIDAD
           Se conservan estas clases por seguridad aunque actualmente
           el sidebar no las utiliza directamente.
           ============================================================ */
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
           BRANDING SIDEBAR — SCA-QR
           ------------------------------------------------------------
           El sidebar utiliza únicamente la identidad del sistema.
           El logotipo de Fruitex NO se muestra en esta zona.
           ============================================================ */

        .sidebar-brand {
            padding: 1.15rem 1rem 1rem;
            border-bottom: 1px solid var(--sca-border);
            background: linear-gradient(
                180deg,
                rgba(46, 125, 50, 0.035),
                transparent 92%
            );
            text-align: center;
        }

        .sidebar-brand-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            text-decoration: none;
            color: inherit;
            border-radius: 14px;
            transition:
                transform 0.25s ease,
                background-color 0.25s ease;
        }

        /*
         * Logo principal SCA-QR.
         *
         * El PNG contiene la identidad visual y textual del sistema,
         * por eso no se coloca dentro de una segunda tarjeta.
         */
        .sidebar-sca-logo-main {
            display: block;
            width: 150px;
            height: 150px;
            max-width: 100%;
            object-fit: contain;
            filter: drop-shadow(0 4px 10px rgba(0, 0, 0, 0.10));
            transition:
                transform 0.25s ease,
                filter 0.25s ease;
        }

        .sidebar-brand-link:hover .sidebar-sca-logo-main {
            transform: translateY(-2px);
            filter: drop-shadow(0 7px 14px rgba(0, 0, 0, 0.15));
        }

        /* ============================================================
           ZONA INFERIOR DEL SIDEBAR
           ============================================================ */

        .sidebar-bottom {
            flex-shrink: 0;
            padding-top: 0.35rem;
        }

        .sidebar-session {
            padding: 0.6rem 1rem 0.7rem;
            display: flex;
            justify-content: center;
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

        .sidebar-footer {
            padding: 0.65rem 1rem 0.8rem;
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

        /* ============================================================
           DARK MODE — BRANDING SIDEBAR
           ============================================================ */

        [data-bs-theme="dark"] .sidebar-brand {
            background: linear-gradient(
                180deg,
                rgba(255, 255, 255, 0.025),
                transparent 92%
            );
        }

        [data-bs-theme="dark"] .sidebar-sca-logo-main {
            filter: drop-shadow(0 5px 12px rgba(0, 0, 0, 0.32));
        }

        [data-bs-theme="dark"] .sidebar-footer span {
            opacity: 1;
            color: rgba(232, 232, 232, 0.95);
        }

        [data-bs-theme="dark"] .sidebar-footer small {
            opacity: 0.9;
            color: rgba(180, 180, 180, 0.95);
        }

        /* ============================================================
           RESPONSIVE SIDEBAR
           ============================================================ */

        @media (max-width: 991.98px) {
            .sidebar-brand {
                padding: 1rem 0.9rem 0.9rem;
            }

            .sidebar-sca-logo-main {
                width: 128px;
                height: 128px;
            }
        }

        /* ============================================================
           NAVEGACIÓN DEL SIDEBAR
           ============================================================ */

        .sidebar-nav {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            padding: 1.1rem 0.75rem 0.85rem;
        }

        .nav-section-title {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--sca-text-muted);
            margin: 1.5rem 0.75rem 0.5rem 0.75rem;
            font-weight: 700;
        }

        .sidebar-nav .nav-link {
            color: var(--sca-text);
            padding: 0.75rem 1rem;
            border-radius: 10px;
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.85rem;
            font-weight: 500;
            transition: all 0.25s ease;
            font-size: 0.9rem;
            text-decoration: none;
        }

        .sidebar-nav .nav-link:hover {
            background-color: var(--sca-hover);
            color: var(--sca-primary);
            transform: translateX(4px);
        }

        .sidebar-nav .nav-link.active {
            background: linear-gradient(
                135deg,
                var(--sca-primary),
                var(--sca-primary-dark)
            );
            color: white;
            box-shadow: 0 4px 12px rgba(46, 125, 50, 0.25);
        }

        .sidebar-nav .nav-link i {
            font-size: 1.15rem;
        }

        /* ============================================================
           MAIN CONTENT
           ============================================================ */

        .main-content {
            margin-left: 260px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .topbar {
            background: var(--sca-topbar-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            height: 68px;
            box-shadow: 0 1px 12px var(--sca-shadow);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            position: sticky;
            top: 0;
            z-index: 999;
            border-bottom: 1px solid var(--sca-border);
        }

        .content-wrapper {
            padding: 2rem;
            flex: 1;
            animation: fadeInUp 0.4s ease-out;
        }

        /* ============================================================
           CARDS & UI
           ============================================================ */

        .card {
            border: 1px solid var(--sca-border);
            border-radius: 16px;
            box-shadow: 0 2px 8px var(--sca-shadow);
            background: var(--sca-card-bg);
            transition: all 0.3s ease;
        }

        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px var(--sca-shadow) !important;
        }

        .card-header {
            background: transparent !important;
            border-bottom: 1px solid var(--sca-border);
            font-weight: 600;
            padding: 1.25rem 1.5rem;
            color: var(--sca-text);
        }

        .btn {
            font-weight: 500;
            border-radius: 10px;
            padding: 0.6rem 1.2rem;
            transition: all 0.2s;
        }

        .btn-success {
            background: linear-gradient(135deg, var(--sca-primary), var(--sca-primary-dark));
            border: none;
            box-shadow: 0 4px 12px rgba(46, 125, 50, 0.2);
            color: white;
        }

        .btn-success:hover {
            box-shadow: 0 6px 16px rgba(46, 125, 50, 0.3);
            transform: translateY(-2px);
            color: white;
        }

        .btn-outline-primary {
            color: var(--sca-primary);
            border-color: var(--sca-primary);
        }

        .btn-outline-primary:hover {
            background-color: var(--sca-primary);
            border-color: var(--sca-primary);
            color: white;
        }

        .form-control,
        .form-select {
            border-radius: 10px;
            padding: 0.7rem 1rem;
            border: 1px solid var(--sca-input-border);
            background: var(--sca-input-bg);
            color: var(--sca-text) !important;
            transition: all 0.2s;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--sca-primary);
            box-shadow: 0 0 0 4px rgba(46, 125, 50, 0.12);
            background: var(--sca-input-bg);
            color: var(--sca-text) !important;
        }

        .form-control::placeholder {
            color: var(--sca-text-muted);
            opacity: 0.6;
        }

        /* Estilo especial para el input oculto del kiosko */
        #scanner-input {
            color: transparent !important;
            background: transparent !important;
            border: none !important;
        }

        /* ============================================================
           BADGES
           ============================================================ */

        .badge {
            font-weight: 500;
            padding: 0.4em 0.7em;
            border-radius: 6px;
        }

        /* ============================================================
           THEME TOGGLE
           ============================================================ */

        .theme-toggle {
            background: transparent;
            border: 1px solid var(--sca-border);
            border-radius: 10px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            color: var(--sca-text);
        }

        .theme-toggle:hover {
            background: var(--sca-hover);
            border-color: var(--sca-primary);
            color: var(--sca-primary);
        }

        .theme-toggle .bi-sun {
            display: none;
        }

        .theme-toggle .bi-moon {
            display: block;
        }

        [data-bs-theme="dark"] .theme-toggle .bi-sun {
            display: block;
        }

        [data-bs-theme="dark"] .theme-toggle .bi-moon {
            display: none;
        }

        /* ============================================================
           MISC
           ============================================================ */

        .text-muted {
            color: var(--sca-text-muted) !important;
        }

        .text-success {
            color: var(--sca-primary) !important;
        }

        .bg-light {
            background-color: var(--sca-hover) !important;
        }

        .modal-content {
            background: var(--sca-card-bg);
            color: var(--sca-text);
            border: 1px solid var(--sca-border);
        }

        .modal-header {
            border-bottom: 1px solid var(--sca-border);
        }

        .modal-footer {
            border-top: 1px solid var(--sca-border);
        }

        .nav-tabs {
            border-bottom: 1px solid var(--sca-border);
        }

        .nav-tabs .nav-link {
            color: var(--sca-text-muted);
            border: none;
            border-bottom: 2px solid transparent;
        }

        .nav-tabs .nav-link.active {
            color: var(--sca-primary);
            border-bottom: 2px solid var(--sca-primary);
            background: transparent;
        }

        .nav-tabs .nav-link:hover {
            border-color: transparent;
            color: var(--sca-primary);
        }

        .sidebar-toggle {
            display: none;
            background: transparent;
            border: none;
            font-size: 1.6rem;
            color: var(--sca-text);
        }

        /* ============================================================
           RESPONSIVE GENERAL
           ============================================================ */

        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .sidebar-toggle {
                display: block;
            }
        }

        /* ============================================================
           DARK MODE GLOBAL — Bootstrap overrides
           ============================================================ */

        [data-bs-theme="dark"] body {
            background-color: var(--sca-bg);
            color: var(--sca-text);
        }

        [data-bs-theme="dark"] .table {
            --bs-table-bg: transparent;
            --bs-table-color: var(--sca-text);
        }

        [data-bs-theme="dark"] .table-light {
            --bs-table-bg: rgba(255, 255, 255, 0.05);
            --bs-table-color: var(--sca-text);
            background-color: rgba(255, 255, 255, 0.05) !important;
        }

        [data-bs-theme="dark"] .table-hover > tbody > tr:hover > * {
            background-color: rgba(255, 255, 255, 0.04);
        }

        [data-bs-theme="dark"] .table td,
        [data-bs-theme="dark"] .table th {
            border-color: var(--sca-border);
        }

        [data-bs-theme="dark"] .bg-light {
            background-color: rgba(255, 255, 255, 0.05) !important;
            color: var(--sca-text) !important;
        }

        [data-bs-theme="dark"] .text-dark {
            color: var(--sca-text) !important;
        }

        [data-bs-theme="dark"] .text-muted {
            color: var(--sca-text-muted) !important;
        }

        [data-bs-theme="dark"] .form-text {
            color: var(--sca-text-muted) !important;
        }

        [data-bs-theme="dark"] .card {
            background: var(--sca-card-bg);
            color: var(--sca-text);
        }

        [data-bs-theme="dark"] .card-header {
            background: transparent !important;
            border-color: var(--sca-border);
            color: var(--sca-text);
        }

        [data-bs-theme="dark"] .card-body {
            color: var(--sca-text);
        }

        [data-bs-theme="dark"] .modal-content {
            background: var(--sca-card-bg);
            color: var(--sca-text);
            border-color: var(--sca-border);
        }

        [data-bs-theme="dark"] .modal-header {
            border-color: var(--sca-border);
        }

        [data-bs-theme="dark"] .modal-footer {
            border-color: var(--sca-border);
        }

        [data-bs-theme="dark"] .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }

        [data-bs-theme="dark"] .form-control,
        [data-bs-theme="dark"] .form-select {
            background: var(--sca-input-bg);
            color: var(--sca-text) !important;
            border-color: var(--sca-input-border);
        }

        [data-bs-theme="dark"] .form-control::placeholder {
            color: var(--sca-text-muted);
        }

        [data-bs-theme="dark"] .input-group-text {
            background: rgba(255, 255, 255, 0.05);
            border-color: var(--sca-input-border);
            color: var(--sca-text-muted);
        }

        [data-bs-theme="dark"] .dropdown-menu {
            background: var(--sca-card-bg);
            border-color: var(--sca-border);
        }

        [data-bs-theme="dark"] .dropdown-item {
            color: var(--sca-text);
        }

        [data-bs-theme="dark"] .dropdown-item:hover {
            background: var(--sca-hover);
        }

        [data-bs-theme="dark"] .nav-tabs {
            border-color: var(--sca-border);
        }

        [data-bs-theme="dark"] .nav-tabs .nav-link {
            color: var(--sca-text-muted);
        }

        [data-bs-theme="dark"] .nav-tabs .nav-link.active {
            color: var(--sca-primary);
            border-bottom-color: var(--sca-primary);
        }

        [data-bs-theme="dark"] .alert-info {
            background: rgba(23, 162, 184, 0.15);
            color: #5dcfe8;
            border-color: rgba(23, 162, 184, 0.3);
        }

        [data-bs-theme="dark"] .alert-success {
            background: rgba(40, 167, 69, 0.15);
            color: #6dd07f;
            border-color: rgba(40, 167, 69, 0.3);
        }

        [data-bs-theme="dark"] .alert-warning {
            background: rgba(255, 193, 7, 0.15);
            color: #ffdb6d;
            border-color: rgba(255, 193, 7, 0.3);
        }

        [data-bs-theme="dark"] .alert-danger {
            background: rgba(220, 53, 69, 0.15);
            color: #f08490;
            border-color: rgba(220, 53, 69, 0.3);
        }

        [data-bs-theme="dark"] .badge.bg-light {
            background: rgba(255, 255, 255, 0.1) !important;
            color: var(--sca-text) !important;
        }

        [data-bs-theme="dark"] .badge.bg-secondary {
            background: rgba(255, 255, 255, 0.15) !important;
            color: var(--sca-text) !important;
        }

        [data-bs-theme="dark"] .badge.bg-warning {
            background: #f57c00 !important;
            color: #fff !important;
        }

        [data-bs-theme="dark"] .badge.bg-info {
            background: #17a2b8 !important;
            color: #fff !important;
        }

        [data-bs-theme="dark"] .progress {
            background: rgba(255, 255, 255, 0.08);
        }

        [data-bs-theme="dark"] .list-group-item {
            background: var(--sca-card-bg);
            color: var(--sca-text);
            border-color: var(--sca-border);
        }

        [data-bs-theme="dark"] .list-group-item-action:hover {
            background: var(--sca-hover);
        }

        [data-bs-theme="dark"] .btn-outline-secondary {
            color: var(--sca-text-muted);
            border-color: var(--sca-border);
        }

        [data-bs-theme="dark"] .btn-outline-secondary:hover {
            background: var(--sca-hover);
            color: var(--sca-text);
        }

        [data-bs-theme="dark"] .btn-outline-primary {
            color: var(--sca-primary);
            border-color: var(--sca-primary);
        }

        [data-bs-theme="dark"] .btn-outline-danger {
            color: #f08490;
            border-color: rgba(220, 53, 69, 0.5);
        }

        [data-bs-theme="dark"] .btn-outline-danger:hover {
            background: #dc3545;
            color: #fff;
        }

        [data-bs-theme="dark"] .btn-outline-warning {
            color: #ffdb6d;
            border-color: rgba(255, 193, 7, 0.5);
        }

        [data-bs-theme="dark"] .btn-outline-info {
            color: #5dcfe8;
            border-color: rgba(23, 162, 184, 0.5);
        }

        [data-bs-theme="dark"] .btn-outline-success {
            color: #6dd07f;
            border-color: rgba(40, 167, 69, 0.5);
        }

        [data-bs-theme="dark"] .sca-alert-warning {
            background: rgba(255, 193, 7, 0.12);
            color: #ffdb6d;
            border: 1px solid rgba(255, 193, 7, 0.2);
        }

        [data-bs-theme="dark"] .sca-card {
            background: var(--sca-card-bg);
        }

        [data-bs-theme="dark"] .sca-modal-content {
            background: var(--sca-card-bg);
            color: var(--sca-text);
        }

        [data-bs-theme="dark"] .form-check-input {
            background-color: var(--sca-input-bg);
            border-color: var(--sca-input-border);
        }

        [data-bs-theme="dark"] .form-check-input:checked {
            background-color: var(--sca-primary);
            border-color: var(--sca-primary);
        }

        [data-bs-theme="dark"] .page-link {
            background: var(--sca-card-bg);
            border-color: var(--sca-border);
            color: var(--sca-primary);
        }

        [data-bs-theme="dark"] .page-item.disabled .page-link {
            background: var(--sca-card-bg);
            border-color: var(--sca-border);
            color: var(--sca-text-muted);
        }

        [data-bs-theme="dark"] .page-item.active .page-link {
            background: var(--sca-primary);
            border-color: var(--sca-primary);
            color: #fff;
        }

        [data-bs-theme="dark"] hr {
            border-color: var(--sca-border);
            opacity: 0.5;
        }

        [data-bs-theme="dark"] .border {
            border-color: var(--sca-border) !important;
        }

        [data-bs-theme="dark"] .border-top {
            border-top-color: var(--sca-border) !important;
        }

        [data-bs-theme="dark"] .border-bottom {
            border-bottom-color: var(--sca-border) !important;
        }

        [data-bs-theme="dark"] .border-start {
            border-left-color: var(--sca-border) !important;
        }

        [data-bs-theme="dark"] .border-end {
            border-right-color: var(--sca-border) !important;
        }

        /* ============================================================
           SUBMENÚ SIDEBAR
           ============================================================ */

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
                document.documentElement.setAttribute(
                    'data-bs-theme',
                    theme === 'dark' ? 'dark' : 'light'
                );
            } catch (e) {
                document.documentElement.setAttribute('data-bs-theme', 'light');
            }
        })();
    </script>
</head>

<body>

    @auth

    <!-- ============================================================
         SIDEBAR
         ============================================================ -->
    <aside class="sidebar" id="sidebar" aria-label="Navegación principal">

        {{-- ========================================================
             BRANDING SCA-QR
             --------------------------------------------------------
             Se utiliza únicamente el logo del sistema.
             El logo de la empresa ya no se muestra aquí.
             ======================================================== --}}
        <div class="sidebar-brand">
            <a href="{{ url('/') }}"
               class="sidebar-brand-link"
               aria-label="SCA-QR - Ir al inicio">

                <img
                    src="{{ asset('logos/scaqr_logo.png') }}"
                    alt="SCA-QR · Sistema de Control de Accesos"
                    class="sidebar-sca-logo-main"
                >

            </a>
        </div>

        <!-- ========================================================
             NAVEGACIÓN
             ======================================================== -->
        <nav class="sidebar-nav" aria-label="Módulos del sistema">

            <div class="nav-section-title">
                <span>Inicio</span>
            </div>

            <a
                href="{{ url('/') }}"
                class="nav-link {{ request()->routeIs('*.dashboard') ? 'active' : '' }}"
                aria-current="{{ request()->routeIs('*.dashboard') ? 'page' : 'false' }}"
                title="Dashboard"
            >
                <span class="nav-icon">
                    <i class="bi bi-grid-1x2-fill" aria-hidden="true"></i>
                </span>
                <span class="nav-label">Dashboard</span>
            </a>

            @if(!Auth::user()->isGuardia())

                <div class="nav-section-title">
                    <span>Operación</span>
                </div>

                <div class="nav-group">
                    <a
                        href="#"
                        class="nav-link nav-parent {{ request()->routeIs('solicitudes.*') ? 'active' : '' }}"
                        data-bs-toggle="collapse"
                        data-bs-target="#submenuSolicitudes"
                        aria-expanded="{{ request()->routeIs('solicitudes.*') ? 'true' : 'false' }}"
                        title="Solicitudes"
                    >
                        <span class="nav-main">
                            <span class="nav-icon">
                                <i class="bi bi-arrow-left-right" aria-hidden="true"></i>
                            </span>
                            <span class="nav-label">Solicitudes</span>
                        </span>

                        <i class="bi bi-chevron-down nav-chevron" aria-hidden="true"></i>
                    </a>

                    <div
                        class="collapse nav-submenu {{ request()->routeIs('solicitudes.*') ? 'show' : '' }}"
                        id="submenuSolicitudes"
                    >
                        <a
                            href="{{ route('solicitudes.create') }}"
                            class="nav-link {{ request()->routeIs('solicitudes.create') ? 'active' : '' }}"
                        >
                            <span class="nav-icon">
                                <i class="bi bi-plus-lg" aria-hidden="true"></i>
                            </span>
                            <span class="nav-label">Nueva solicitud</span>
                        </a>

                        <a
                            href="{{ route('solicitudes.mis') }}"
                            class="nav-link {{ request()->routeIs('solicitudes.mis') ? 'active' : '' }}"
                        >
                            <span class="nav-icon">
                                <i class="bi bi-file-earmark-person" aria-hidden="true"></i>
                            </span>
                            <span class="nav-label">Mis solicitudes</span>
                        </a>

                        @if(Auth::user()->isAprobador() || Auth::user()->isAdmin() || Auth::user()->isSuperadmin())

                            <a
                                href="{{ route('solicitudes.bandeja') }}"
                                class="nav-link {{ request()->routeIs('solicitudes.bandeja') ? 'active' : '' }}"
                            >
                                <span class="nav-icon">
                                    <i class="bi bi-inbox-fill" aria-hidden="true"></i>
                                </span>
                                <span class="nav-label">Bandeja</span>
                            </a>

                            <a
                                href="{{ route('solicitudes.mis-aprobaciones') }}"
                                class="nav-link {{ request()->routeIs('solicitudes.mis-aprobaciones') ? 'active' : '' }}"
                            >
                                <span class="nav-icon">
                                    <i class="bi bi-check2-square" aria-hidden="true"></i>
                                </span>
                                <span class="nav-label">Mis aprobaciones</span>
                            </a>

                        @endif

                        @if(Auth::user()->isAdmin() || Auth::user()->isSuperadmin())

                            <a
                                href="{{ route('solicitudes.historial') }}"
                                class="nav-link {{ request()->routeIs('solicitudes.historial') ? 'active' : '' }}"
                            >
                                <span class="nav-icon">
                                    <i class="bi bi-clock-history" aria-hidden="true"></i>
                                </span>
                                <span class="nav-label">Historial</span>
                            </a>

                        @endif
                    </div>
                </div>

                @if(Auth::user()->isAdmin() || Auth::user()->isSuperadmin() || Auth::user()->isAprobador())

                    <a
                        href="{{ route('bitacora.index') }}"
                        class="nav-link {{ request()->routeIs('bitacora.*') ? 'active' : '' }}"
                        title="Bitácora de accesos"
                    >
                        <span class="nav-icon">
                            <i class="bi bi-journal-check" aria-hidden="true"></i>
                        </span>
                        <span class="nav-label">Bitácora de accesos</span>
                    </a>

                @endif

            @endif

            @if(Auth::user()->isSuperadmin() || Auth::user()->isAdmin() || Auth::user()->isAprobador())

                <div class="nav-section-title">
                    <span>Gestión</span>
                </div>

                <a
                    href="{{ route('usuarios.index') }}"
                    class="nav-link {{ request()->routeIs('usuarios.*') ? 'active' : '' }}"
                    title="Usuarios"
                >
                    <span class="nav-icon">
                        <i class="bi bi-people-fill" aria-hidden="true"></i>
                    </span>
                    <span class="nav-label">Usuarios</span>
                </a>

                <a
                    href="{{ route('empleados.index') }}"
                    class="nav-link {{ request()->routeIs('empleados.*') ? 'active' : '' }}"
                    title="Empleados"
                >
                    <span class="nav-icon">
                        <i class="bi bi-person-vcard-fill" aria-hidden="true"></i>
                    </span>
                    <span class="nav-label">Empleados</span>
                </a>

                <a
                    href="{{ route('puestos.index') }}"
                    class="nav-link {{ request()->routeIs('puestos.*') ? 'active' : '' }}"
                    title="Puestos"
                >
                    <span class="nav-icon">
                        <i class="bi bi-diagram-2-fill" aria-hidden="true"></i>
                    </span>
                    <span class="nav-label">Puestos</span>
                </a>

                <a
                    href="{{ route('clasificaciones.index') }}"
                    class="nav-link {{ request()->routeIs('clasificaciones.*') ? 'active' : '' }}"
                    title="Clasificación por planta"
                >
                    <span class="nav-icon">
                        <i class="bi bi-diagram-3-fill" aria-hidden="true"></i>
                    </span>
                    <span class="nav-label">Clasificación por planta</span>
                </a>

                {{-- MÓDULO DE CONFIGURACIÓN --}}
                @if(Auth::user()->isSuperadmin() || Auth::user()->isAdmin())

                    <a
                        href="{{ route('configuracion.index') }}"
                        class="nav-link {{ request()->routeIs('configuracion.*') ? 'active' : '' }}"
                        title="Configuración del sistema"
                    >
                        <span class="nav-icon">
                            <i class="bi bi-gear-fill" aria-hidden="true"></i>
                        </span>
                        <span class="nav-label">Configuración</span>
                    </a>

                @endif

            @endif

            @if(Auth::user()->isSuperadmin())

                <div class="nav-section-title">
                    <span>Sistema</span>
                </div>

                <a
                    href="{{ route('logs.index') }}"
                    class="nav-link {{ request()->routeIs('logs.*') ? 'active' : '' }}"
                    title="Logs del sistema"
                >
                    <span class="nav-icon">
                        <i class="bi bi-shield-lock-fill text-danger" aria-hidden="true"></i>
                    </span>
                    <span class="nav-label">Logs del sistema</span>
                </a>

            @endif

        </nav>

        <!-- ========================================================
             ZONA INFERIOR
             --------------------------------------------------------
             No se muestra logo de empresa.
             ======================================================== -->
        <div class="sidebar-bottom">

            {{-- Estado de sesión --}}
            <div class="sidebar-session">
                <span class="sidebar-status">
                    <span class="status-dot"></span>
                    <span>Sesión activa</span>
                </span>
            </div>

            {{-- Footer compacto --}}
            <div class="sidebar-footer">
                <span>SCA-QR · v1.0</span>
                <small>&copy; {{ date('Y') }} Fruitex de México</small>
            </div>

        </div>

    </aside>

    <div class="sca-mobile-overlay" id="scaMobileOverlay" aria-hidden="true"></div>

    <!-- ============================================================
         MAIN CONTENT
         ============================================================ -->
    <div class="main-content">

        <header class="topbar">

            <div class="topbar-left">

                <button
                    type="button"
                    class="sidebar-toggle"
                    id="sidebarToggle"
                    aria-label="Abrir menú de navegación"
                    aria-controls="sidebar"
                    aria-expanded="false"
                >
                    <i class="bi bi-list" aria-hidden="true"></i>
                </button>

                <div class="topbar-context d-none d-lg-flex">
                    <div class="topbar-context-icon">
                        <i class="bi bi-shield-check" aria-hidden="true"></i>
                    </div>

                    <div>
                        <strong>SCA-QR</strong>
                        <span>Control de accesos</span>
                    </div>
                </div>

            </div>

            <div class="topbar-tools">

                @include('partials._campana')

                <div class="topbar-clock d-none d-lg-flex">
                    @include('partials._reloj', ['id' => 'topbar'])
                </div>

                <button
                    type="button"
                    class="theme-toggle"
                    id="themeToggle"
                    title="Cambiar tema"
                    aria-label="Cambiar tema"
                    aria-pressed="false"
                >
                    <i class="bi bi-sun-fill" aria-hidden="true"></i>
                    <i class="bi bi-moon-stars-fill" aria-hidden="true"></i>
                </button>

                <div class="user-menu" aria-label="Usuario actual">

                    <div class="user-avatar" aria-hidden="true">
                        {{ strtoupper(substr(Auth::user()->nombre_completo ?? 'U', 0, 1)) }}
                    </div>

                    <div class="user-copy d-none d-md-block">

                        <strong>
                            {{ Auth::user()->nombre_completo }}
                        </strong>

                        <span>
                            <span class="user-role">
                                {{ Auth::user()->rol->nombre }}
                            </span>

                            @if(Auth::user()->planta_nombre && Auth::user()->planta_nombre !== 'Sin planta')
                                <span class="user-divider">·</span>
                                {{ Auth::user()->planta_nombre }}
                            @endif
                        </span>

                    </div>

                    @if(!Auth::user()->isGuardia())

                        <a
                            href="#"
                            onclick="event.preventDefault(); document.getElementById('logout-form-global').submit();"
                            class="logout-button"
                            title="Cerrar sesión"
                            aria-label="Cerrar sesión"
                        >
                            <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                        </a>

                    @endif

                </div>

            </div>

        </header>

        <main class="content-wrapper">

            {{-- ============================================================
                SCA-MODAL — ÚNICO PUNTO DE ENTRADA

                AJAX → sessionStorage
                Normal → Laravel flash
                ============================================================ --}}

            <script>
                document.addEventListener('DOMContentLoaded', function () {

                    // 1. Modal transportado por AJAX/sessionStorage
                    var pending = sessionStorage.getItem('sca_pending_modal');

                    if (pending) {

                        sessionStorage.removeItem('sca_pending_modal');

                        try {

                            var cfg = JSON.parse(pending);

                            if (
                                typeof window.ScaModal !== 'undefined' &&
                                typeof window.ScaModal[cfg.tipo] === 'function'
                            ) {
                                window.ScaModal[cfg.tipo](
                                    cfg.titulo,
                                    cfg.mensaje,
                                    {
                                        detalles: cfg.detalles || null
                                    }
                                );
                            } else {
                                console.error(
                                    '[ScaModal] Tipo AJAX no reconocido:',
                                    cfg.tipo
                                );
                            }

                        } catch (error) {
                            console.error(
                                '[ScaModal] Error leyendo sessionStorage:',
                                error
                            );
                        }

                        return;
                    }

                    // 2. Modal transportado por Laravel flash
                    @if (session('sca_modal'))

                        var cfg = {{ \Illuminate\Support\Js::from(session('sca_modal')) }};

                        if (
                            typeof window.ScaModal !== 'undefined' &&
                            typeof window.ScaModal[cfg.tipo] === 'function'
                        ) {
                            window.ScaModal[cfg.tipo](
                                cfg.titulo,
                                cfg.mensaje,
                                {
                                    detalles: cfg.detalles || null
                                }
                            );
                        } else {
                            console.error(
                                '[ScaModal] Tipo flash no reconocido:',
                                cfg.tipo
                            );
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

                @if(
                    (Auth::user()->isSuperadmin() ||
                     Auth::user()->isAdmin() ||
                     Auth::user()->isAprobador()) &&
                    $puestosSinClasificar > 0
                )

                    <div class="alert d-flex align-items-center mb-4 animate-fade-in-up sca-warning-alert">

                        <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>

                        <div class="flex-grow-1">

                            <strong>
                                {{ $puestosSinClasificar }} puestos sin clasificar.
                            </strong>

                            Los empleados con esos puestos pasarán como "No clasificados".

                        </div>

                        <a
                            href="{{ route('puestos.index') }}"
                            class="btn btn-sm btn-warning ms-3"
                        >
                            Clasificar
                        </a>

                    </div>

                @endif

            @endauth

            @yield('content')

        </main>

    </div>

    @endauth

    @guest

        @yield('content')

    @endguest

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

    {{-- ============================================================
        SCA-MODAL
        ============================================================ --}}
    @unless(request()->routeIs('kiosko.*'))

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

                sidebarToggle?.setAttribute(
                    'aria-expanded',
                    'false'
                );

                mobileOverlay?.setAttribute(
                    'aria-hidden',
                    'true'
                );
            };

            const openSidebar = () => {

                sidebar?.classList.add('show');
                mobileOverlay?.classList.add('show');
                document.body.classList.add('sca-sidebar-open');

                sidebarToggle?.setAttribute(
                    'aria-expanded',
                    'true'
                );

                mobileOverlay?.setAttribute(
                    'aria-hidden',
                    'false'
                );
            };

            sidebarToggle?.addEventListener('click', () => {

                sidebar?.classList.contains('show')
                    ? closeSidebar()
                    : openSidebar();

            });

            mobileOverlay?.addEventListener(
                'click',
                closeSidebar
            );

            document.addEventListener(
                'keydown',
                event => {
                    if (event.key === 'Escape') {
                        closeSidebar();
                    }
                }
            );

            sidebar?.querySelectorAll(
                'a:not([data-bs-toggle="collapse"])'
            ).forEach(link => {

                link.addEventListener('click', () => {

                    if (
                        window.matchMedia(
                            '(max-width: 992px)'
                        ).matches
                    ) {
                        closeSidebar();
                    }

                });

            });

            themeToggle?.addEventListener(
                'click',
                () => {

                    const current =
                        document.documentElement.getAttribute(
                            'data-bs-theme'
                        ) || 'light';

                    const next =
                        current === 'dark'
                            ? 'light'
                            : 'dark';

                    document.documentElement.setAttribute(
                        'data-bs-theme',
                        next
                    );

                    localStorage.setItem(
                        'sca-theme',
                        next
                    );

                    themeToggle.setAttribute(
                        'aria-pressed',
                        next === 'dark'
                            ? 'true'
                            : 'false'
                    );

                }
            );

            const currentTheme =
                document.documentElement.getAttribute(
                    'data-bs-theme'
                ) || 'light';

            themeToggle?.setAttribute(
                'aria-pressed',
                currentTheme === 'dark'
                    ? 'true'
                    : 'false'
            );

            const solicitudes =
                document.getElementById(
                    'submenuSolicitudes'
                );

            const solicitudesToggle =
                document.querySelector(
                    '[data-bs-target="#submenuSolicitudes"]'
                );

            if (
                solicitudes?.querySelector(
                    '.nav-link.active'
                ) &&
                window.bootstrap
            ) {

                const collapse =
                    bootstrap.Collapse.getOrCreateInstance(
                        solicitudes,
                        {
                            toggle: false
                        }
                    );

                collapse.show();

                solicitudesToggle?.setAttribute(
                    'aria-expanded',
                    'true'
                );
            }

        })();
    </script>

    <script>
        document.addEventListener(
            'DOMContentLoaded',
            function () {

                const defaults = {

                    solicitudes:
                        @json(route('solicitudes.hub')),

                    bandeja:
                        @json(route('solicitudes.hub')),

                    usuarios:
                        @json(route('usuarios.index')),

                    empleados:
                        @json(route('empleados.index')),

                    puestos:
                        @json(route('puestos.index')),

                    clasificaciones:
                        @json(route('clasificaciones.index')),

                    inicio:
                        @json(url('/'))

                };

                const path =
                    window.location.pathname;

                document.querySelectorAll(
                    'a[href="javascript:history.back()"], a[data-sca-history-back]'
                ).forEach(function (link) {

                    link.setAttribute(
                        'href',
                        '#'
                    );

                    link.addEventListener(
                        'click',
                        function (e) {

                            e.preventDefault();

                            let fallback =
                                defaults.inicio;

                            if (
                                path.includes(
                                    '/solicitudes/bandeja'
                                )
                            ) {
                                fallback =
                                    defaults.bandeja;

                            } else if (
                                path.includes(
                                    '/solicitudes'
                                )
                            ) {
                                fallback =
                                    defaults.solicitudes;

                            } else if (
                                path.includes(
                                    '/usuarios'
                                )
                            ) {
                                fallback =
                                    defaults.usuarios;

                            } else if (
                                path.includes(
                                    '/empleados'
                                )
                            ) {
                                fallback =
                                    defaults.empleados;

                            } else if (
                                path.includes(
                                    '/puestos'
                                )
                            ) {
                                fallback =
                                    defaults.puestos;

                            } else if (
                                path.includes(
                                    '/clasificaciones'
                                )
                            ) {
                                fallback =
                                    defaults.clasificaciones;
                            }

                            try {

                                const ref =
                                    document.referrer
                                        ? new URL(
                                            document.referrer
                                        )
                                        : null;

                                if (
                                    ref &&
                                    ref.origin ===
                                    window.location.origin
                                ) {

                                    window.location.assign(
                                        ref.href
                                    );

                                } else {

                                    window.location.assign(
                                        fallback
                                    );

                                }

                            } catch (_) {

                                window.location.assign(
                                    fallback
                                );

                            }

                        }
                    );

                });

            }
        );
    </script>

    @stack('scripts')

    {{-- ============================================================
        LOGOUT GLOBAL
        Se mantiene fuera de cualquier otro formulario.
        ============================================================ --}}
    <form
        id="logout-form-global"
        method="POST"
        action="{{ route('logout') }}"
        style="display:none;"
    >
        @csrf
    </form>

    {{-- ============================================================
        CONTAINER GLOBAL DE SCA-MODAL
        ============================================================ --}}
    @unless(request()->routeIs('kiosko.*'))

        @include('partials._sca_modal')

    @endunless

</body>
</html>