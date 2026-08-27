{{--
    ============================================================
    _sca_modal.blade.php — Container HTML + CSS del sistema ScaModal
    ============================================================

    PROPOSITO:
      Markup único (un solo <div class="modal sca-modal">) que el JS
      sca-modal.js rellena dinámicamente con el contenido del tipo
      apropiado (success/error/warning/info/loading/confirm).

      Se incluye UNA sola vez por página, vía @include en layouts/app.blade.php.

    NO INCLUIR en:
      - kiosko/index.blade.php  (el kiosko tiene su propio feedback)
      - emails/*                (plantillas de correo)
      - credenciales/*          (render de PDFs)

    DEPENDENCIAS:
      - Bootstrap 5.3 (CSS + JS bundle) — cargado por el layout
      - Bootstrap Icons                — cargado por el layout
      - /js/sca-modal.js               — cargado vía @push('scripts') en el layout

    NOTA: Este partial NO carga JS por sí mismo. Solo define el HTML
    y los estilos. La carga de sca-modal.js la hace el layout con
    <script src="{{ asset('js/sca-modal.js') }}"></script> ANTES
    de este include.
    ============================================================
--}}

{{-- ============================================================
   CSS del ScaModal — variables, layout, íconos, animaciones
   Se embebe aquí para que cargue junto con el HTML del modal.
   ============================================================ --}}
<style>
    /* ============================================================
       Variables de color por tipo (con soporte dark mode)
       
       IMPORTANTE: Este partial se incluye en páginas que extienden
       layouts/app.blade.php (que ya define --sca-card-bg, --sca-text,
       etc.) Y en páginas standalone como auth/cambiar_password.blade.php
       que NO las definen.
       
       Por eso usamos la sintaxis CSS de fallback:
           var(--sca-card-bg, #ffffff)
       que significa: "usa --sca-card-bg si existe, si no, usa #ffffff".
       
       Así el modal se ve bien en cualquier página, tenga o no el layout.
       ============================================================ */
    :root {
        /* Variables del modal (propias) */
        --sca-success: #10b981;
        --sca-success-soft: rgba(16, 185, 129, 0.10);
        --sca-error: #ef4444;
        --sca-error-soft: rgba(239, 68, 68, 0.10);
        --sca-warning: #f59e0b;
        --sca-warning-soft: rgba(245, 158, 11, 0.12);
        --sca-info: #0ea5e9;
        --sca-info-soft: rgba(14, 165, 233, 0.10);
        --sca-primary-light: #4caf50;

        /* Fallbacks para variables del layout (usadas en páginas standalone) */
        --sca-card-bg: #ffffff;
        --sca-text: #1a1d23;
        --sca-text-muted: #6c757d;
        --sca-border: rgba(0, 0, 0, 0.08);
        --sca-primary: #2e7d32;
        --sca-primary-dark: #1b5e20;
    }
    [data-bs-theme="dark"] {
        --sca-success-soft: rgba(16, 185, 129, 0.16);
        --sca-error-soft:   rgba(239, 68, 68, 0.16);
        --sca-warning-soft: rgba(245, 158, 11, 0.18);
        --sca-info-soft:    rgba(14, 165, 233, 0.16);

        /* Override de fallbacks para dark mode */
        --sca-card-bg: #151a26;
        --sca-text: #e8eaed;
        --sca-text-muted: #9ca3af;
        --sca-border: rgba(255, 255, 255, 0.07);
    }

    /* ============================================================
       Backdrop con blur (glassmorphism) — reemplaza el negro plano
       ============================================================ */
    .sca-modal + .modal-backdrop.show,
    .modal-backdrop.show {
        opacity: 0.6 !important;
        backdrop-filter: blur(8px) saturate(140%);
        -webkit-backdrop-filter: blur(8px) saturate(140%);
        background: rgba(15, 23, 42, 0.45) !important;
    }
    [data-bs-theme="dark"] .modal-backdrop.show {
        background: rgba(0, 0, 0, 0.65) !important;
    }

    /* ============================================================
       Contenedor del modal — ancho máximo, centrado
       ============================================================ */
    .sca-modal .modal-dialog { max-width: 440px; margin: 1rem auto; }

    /* ============================================================
       Card del modal — bordes redondeados, sombras en capas,
       animación de entrada tipo spring
       ============================================================ */
    .sca-modal .modal-content {
        border-radius: 24px;
        border: 1px solid var(--sca-border);
        background: var(--sca-card-bg);
        color: var(--sca-text);
        overflow: hidden;
        position: relative;
        /* Sombra en capas: tight + diffuse = profundidad real */
        box-shadow:
            0 0 0 1px rgba(255,255,255,0.05) inset,
            0 4px 6px -1px rgba(0,0,0,0.05),
            0 10px 15px -3px rgba(0,0,0,0.08),
            0 20px 40px -8px rgba(0,0,0,0.15),
            0 32px 64px -12px rgba(0,0,0,0.18);
        animation: sca-pop-in 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    [data-bs-theme="dark"] .sca-modal .modal-content {
        box-shadow:
            0 0 0 1px rgba(255,255,255,0.04) inset,
            0 4px 8px rgba(0,0,0,0.3),
            0 12px 24px rgba(0,0,0,0.4),
            0 24px 48px rgba(0,0,0,0.5);
    }

    /* Animación de entrada spring (rebote sutil) */
    @keyframes sca-pop-in {
        0%   { opacity: 0; transform: scale(0.92) translateY(20px); }
        55%  { opacity: 1; transform: scale(1.04) translateY(-3px); }
        100% { opacity: 1; transform: scale(1) translateY(0); }
    }
    @keyframes sca-fade-in {
        from { opacity: 0; transform: translateY(8px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    /* ============================================================
       Glow decorativo arriba del modal (acento de color según tipo)
       Se OCULTA en loading para evitar artifact visual
       ============================================================ */
    .sca-modal .modal-content::before {
        content: ''; position: absolute; top: -50px; left: 50%; transform: translateX(-50%);
        width: 220px; height: 90px; border-radius: 50%;
        background: radial-gradient(ellipse at center, var(--sca-accent, var(--sca-primary)) 0%, transparent 70%);
        opacity: 0.14; filter: blur(24px); pointer-events: none;
        animation: sca-fade-in 0.6s ease-out 0.1s both;
    }
    .sca-modal-loading .modal-content::before { display: none; }

    .sca-modal .modal-body { padding: 2.25rem 2rem 1.25rem; text-align: center; position: relative; }
    .sca-modal .modal-footer { border: none; padding: 0 2rem 1.75rem; justify-content: center; gap: 0.6rem; }

    /* ============================================================
       Ícono circular 72px — gradiente + halo difuso animado
       ============================================================ */
    .sca-modal .sca-icon {
        width: 72px; height: 72px; border-radius: 50%; margin: 0 auto 1.4rem;
        display: flex; align-items: center; justify-content: center;
        font-size: 2.1rem; position: relative;
        background: var(--sca-icon-bg);
        color: var(--sca-icon-color);
        animation: sca-icon-pop 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) 0.15s both;
    }
    /* Anillo decorativo detrás del ícono (pulse suave) */
    .sca-modal .sca-icon::before {
        content: ''; position: absolute; inset: -6px; border-radius: 50%;
        background: var(--sca-icon-bg);
        opacity: 0.4; filter: blur(8px); z-index: -1;
        animation: sca-icon-pulse 2.4s ease-in-out infinite;
    }
    @keyframes sca-icon-pop {
        0%   { transform: scale(0.5) rotate(-20deg); opacity: 0; }
        100% { transform: scale(1) rotate(0); opacity: 1; }
    }
    @keyframes sca-icon-pulse {
        0%,100% { transform: scale(1); opacity: 0.4; }
        50%     { transform: scale(1.15); opacity: 0.2; }
    }

    /* Spinner más grueso para mejor visibilidad */
    .sca-modal .sca-icon .spinner-border {
        width: 2.1rem; height: 2.1rem; border-width: 0.18em;
    }

    .sca-modal .sca-title {
        font-size: 1.35rem; font-weight: 700; margin-bottom: 0.55rem;
        letter-spacing: -0.02em; color: var(--sca-text);
        animation: sca-fade-in 0.4s ease-out 0.2s both;
    }
    .sca-modal .sca-message {
        color: var(--sca-text-muted); font-size: 0.95rem; line-height: 1.55;
        margin-bottom: 0; max-width: 340px; margin-left: auto; margin-right: auto;
        animation: sca-fade-in 0.4s ease-out 0.28s both;
    }

    /* ============================================================
       Bloques de detalles (aprobados/rechazados)
       con acento lateral y SCROLL si la lista es larga
       ============================================================ */
    .sca-modal .sca-details {
        margin-top: 1.4rem; text-align: left;
        animation: sca-fade-in 0.4s ease-out 0.36s both;
        max-height: 240px; overflow-y: auto; padding-right: 6px;
        scrollbar-width: thin; scrollbar-color: rgba(120,120,120,0.35) transparent;
    }
    .sca-modal .sca-details::-webkit-scrollbar { width: 8px; }
    .sca-modal .sca-details::-webkit-scrollbar-track { background: rgba(120,120,120,0.08); border-radius: 8px; }
    .sca-modal .sca-details::-webkit-scrollbar-thumb { background: rgba(120,120,120,0.35); border-radius: 8px; }
    .sca-modal .sca-details::-webkit-scrollbar-thumb:hover { background: rgba(120,120,120,0.55); }

    .sca-modal .sca-details .detail-block {
        background: var(--sca-detail-bg, rgba(0,0,0,0.025));
        border-radius: 12px; padding: 0.7rem 0.9rem 0.7rem 0.85rem;
        margin-bottom: 0.5rem; border-left: 3px solid var(--sca-detail-accent, var(--sca-text-muted));
    }
    .sca-modal .sca-details .detail-title {
        font-size: 0.72rem; font-weight: 600; text-transform: uppercase;
        letter-spacing: 0.6px; margin-bottom: 0.45rem; color: var(--sca-text-muted);
        display: flex; align-items: center; gap: 0.4rem;
    }
    .sca-modal .sca-details .detail-title .bi { font-size: 0.9rem; color: var(--sca-detail-accent, var(--sca-text-muted)); }
    .sca-modal .sca-details .detail-item {
        font-size: 0.875rem; padding: 0.3rem 0;
        display: flex; align-items: start; gap: 0.5rem;
        color: var(--sca-text); line-height: 1.4;
    }
    .sca-modal .sca-details .detail-item .bi { font-size: 0.95rem; margin-top: 0.15rem; flex-shrink: 0; }

    /* Bloques success/error toman sus colores del tipo */
    .sca-modal .sca-details .detail-block.success-block { --sca-detail-bg: var(--sca-success-soft); --sca-detail-accent: var(--sca-success); }
    .sca-modal .sca-details .detail-block.error-block   { --sca-detail-bg: var(--sca-error-soft);   --sca-detail-accent: var(--sca-error); }

    /* ============================================================
       Input (textarea/input) para pedidos de motivo
       ============================================================ */
    .sca-modal .sca-input { margin-top: 1.1rem; text-align: left; animation: sca-fade-in 0.4s ease-out 0.32s both; }
    .sca-modal .sca-input label {
        font-size: 0.82rem; font-weight: 500; margin-bottom: 0.4rem; display: block;
        color: var(--sca-text); letter-spacing: -0.005em;
    }
    .sca-modal .sca-input input,
    .sca-modal .sca-input textarea {
        width: 100%; border-radius: 12px; padding: 0.7rem 0.9rem;
        border: 1.5px solid var(--sca-border); background: var(--sca-card-bg);
        color: var(--sca-text); font-size: 0.93rem; font-family: inherit;
        transition: all 0.2s; resize: none;
    }
    .sca-modal .sca-input input:focus,
    .sca-modal .sca-input textarea:focus {
        outline: none; border-color: var(--sca-primary);
        box-shadow: 0 0 0 4px rgba(46, 125, 50, 0.12);
    }
    .sca-modal .sca-input input.is-invalid,
    .sca-modal .sca-input textarea.is-invalid {
        border-color: var(--sca-error);
        box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.12);
        animation: sca-shake 0.4s ease-in-out;
    }
    .sca-modal .sca-input-counter {
        text-align: right; font-size: 0.75rem; color: var(--sca-text-muted);
        margin-top: 0.35rem; font-variant-numeric: tabular-nums;
    }
    @keyframes sca-shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-4px); }
        75% { transform: translateX(4px); }
    }

    /* ============================================================
       Botones — primario con gradiente, hover lift, active press
       ============================================================ */
    .sca-modal .btn {
        border-radius: 12px; font-weight: 500; padding: 0.65rem 1.5rem;
        min-width: 120px; font-size: 0.92rem; border: none;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer;
        font-family: inherit; letter-spacing: -0.005em;
    }
    .sca-modal .btn:disabled { opacity: 0.6; cursor: not-allowed; }

    .sca-modal .btn-success {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #fff; box-shadow: 0 2px 8px rgba(16,185,129,0.25);
    }
    .sca-modal .btn-success:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(16,185,129,0.35); }

    .sca-modal .btn-danger {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: #fff; box-shadow: 0 2px 8px rgba(239,68,68,0.25);
    }
    .sca-modal .btn-danger:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(239,68,68,0.35); }

    .sca-modal .btn-warning {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: #fff; box-shadow: 0 2px 8px rgba(245,158,11,0.25);
    }
    .sca-modal .btn-warning:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(245,158,11,0.35); }

    .sca-modal .btn-info {
        background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
        color: #fff; box-shadow: 0 2px 8px rgba(14,165,233,0.25);
    }
    .sca-modal .btn-info:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(14,165,233,0.35); }

    .sca-modal .btn-primary {
        background: linear-gradient(135deg, var(--sca-primary) 0%, var(--sca-primary-dark) 100%);
        color: #fff; box-shadow: 0 2px 8px rgba(46,125,50,0.25);
    }
    .sca-modal .btn-primary:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(46,125,50,0.35); }

    .sca-modal .btn-secondary {
        background: rgba(120, 120, 120, 0.1);
        color: var(--sca-text); border: 1px solid var(--sca-border);
    }
    .sca-modal .btn-secondary:hover:not(:disabled) { background: rgba(120, 120, 120, 0.16); transform: translateY(-1px); }

    .sca-modal .btn:active:not(:disabled) { transform: translateY(0) scale(0.98); }

    /* ============================================================
       X de cerrar (no aparece en loading)
       ============================================================ */
    .sca-modal .btn-close {
        position: absolute; top: 1rem; right: 1rem;
        width: 32px; height: 32px; border-radius: 8px;
        opacity: 0.6; cursor: pointer; z-index: 5;
        transition: all 0.2s; background: transparent; border: none;
        display: flex; align-items: center; justify-content: center;
        color: var(--sca-text-muted); font-size: 1rem;
    }
    .sca-modal .btn-close:hover { opacity: 1; background: rgba(120,120,120,0.1); transform: rotate(90deg); }
    .sca-modal .btn-close::before { content: '\F62A'; font-family: 'bootstrap-icons'; }

    /* ============================================================
       Loading: 3 dots animados + barra de progreso visible abajo
       ============================================================ */
    .sca-modal-loading .modal-footer { padding-bottom: 0; padding-top: 0.5rem; }
    .sca-modal-loading .sca-steps {
        display: flex; justify-content: center; gap: 0.4rem; margin-top: 1.1rem;
        animation: sca-fade-in 0.4s ease-out 0.3s both;
    }
    .sca-modal-loading .sca-steps .step {
        width: 7px; height: 7px; border-radius: 50%;
        background: rgba(46, 125, 50, 0.2);
        animation: sca-step-bounce 1.4s ease-in-out infinite;
    }
    .sca-modal-loading .sca-steps .step:nth-child(2) { animation-delay: 0.2s; }
    .sca-modal-loading .sca-steps .step:nth-child(3) { animation-delay: 0.4s; }
    @keyframes sca-step-bounce {
        0%, 80%, 100% { transform: scale(0.7); background: rgba(46, 125, 50, 0.2); }
        40% { transform: scale(1.2); background: var(--sca-primary); }
    }
    .sca-modal-loading .progress-bar-wrap {
        position: absolute; bottom: 0; left: 0; right: 0; height: 5px;
        background: rgba(46, 125, 50, 0.10); overflow: hidden;
    }
    .sca-modal-loading .progress-bar-wrap::before {
        content: ''; position: absolute; height: 100%; width: 35%;
        background: linear-gradient(90deg, transparent 0%, var(--sca-primary) 50%, var(--sca-primary-light) 80%, transparent 100%);
        border-radius: 0 4px 4px 0;
        animation: sca-progress-slide 1.6s cubic-bezier(0.65, 0, 0.35, 1) infinite;
    }
    @keyframes sca-progress-slide {
        0%   { left: -35%; }
        100% { left: 100%; }
    }
    .sca-modal-loading .sca-title { animation: sca-pulse-text 1.6s ease-in-out infinite; }
    @keyframes sca-pulse-text {
        0%,100% { opacity: 1; }
        50% { opacity: 0.65; }
    }
    /* Ocultar X en loading (no cancelable) */
    .sca-modal-loading .btn-close { display: none; }

    /* ============================================================
       FIX: Congelar el loading durante el redirect (beforeunload).
       Cuando el browser está por navegar, le agregamos esta clase
       al modal container. Esto:
         - Fija el modal en opacity:1 (no se desvanece)
         - Desactiva las animaciones (no hay fade-out)
         - El loading se queda visible hasta que la nueva página cargue
       Así se elimina el "hueco" entre el fade-out del loading y el
       redirect. El loading se ve continuo hasta el success.
       ============================================================ */
    .sca-modal.sca-loading-frozen {
        opacity: 1 !important;
        transition: none !important;
    }
    .sca-modal.sca-loading-frozen .modal-content {
        animation: none !important;
        transition: none !important;
        opacity: 1 !important;
    }
    .sca-modal.sca-loading-frozen .modal-backdrop {
        opacity: 0.6 !important;
        transition: none !important;
    }

    /* ============================================================
       Colores por tipo: asigna --sca-accent (glow) y --sca-icon-*
       ============================================================ */
    .sca-modal-success { --sca-accent: var(--sca-success); }
    .sca-modal-success .sca-icon { --sca-icon-bg: var(--sca-success-soft); --sca-icon-color: var(--sca-success); }

    .sca-modal-error { --sca-accent: var(--sca-error); }
    .sca-modal-error .sca-icon { --sca-icon-bg: var(--sca-error-soft); --sca-icon-color: var(--sca-error); }

    /* Variante danger (para confirm destructivas) — mismo rojo que error */
    .sca-modal-danger { --sca-accent: var(--sca-error); }
    .sca-modal-danger .sca-icon { --sca-icon-bg: var(--sca-error-soft); --sca-icon-color: var(--sca-error); }

    .sca-modal-warning { --sca-accent: var(--sca-warning); }
    .sca-modal-warning .sca-icon { --sca-icon-bg: var(--sca-warning-soft); --sca-icon-color: var(--sca-warning); }

    .sca-modal-info { --sca-accent: var(--sca-info); }
    .sca-modal-info .sca-icon { --sca-icon-bg: var(--sca-info-soft); --sca-icon-color: var(--sca-info); }

    .sca-modal-loading { --sca-accent: var(--sca-primary); }
    .sca-modal-loading .sca-icon { --sca-icon-bg: var(--sca-success-soft); --sca-icon-color: var(--sca-primary); }

    /* ============================================================
       Dark mode refinamientos
       ============================================================ */
    [data-bs-theme="dark"] .sca-modal .sca-details .detail-block { background: rgba(255,255,255,0.03); }
    [data-bs-theme="dark"] .sca-modal .sca-input input,
    [data-bs-theme="dark"] .sca-modal .sca-input textarea { background: rgba(255,255,255,0.04); }
    [data-bs-theme="dark"] .sca-modal .btn-secondary { background: rgba(255,255,255,0.06); }
    [data-bs-theme="dark"] .sca-modal .btn-secondary:hover { background: rgba(255,255,255,0.1); }

    /* ============================================================
       Mobile: ajustes de padding y tamaño de botones
       ============================================================ */
    @media (max-width: 480px) {
        .sca-modal .modal-dialog { margin: 0.5rem; max-width: none; }
        .sca-modal .modal-content { border-radius: 20px; }
        .sca-modal .modal-body { padding: 1.75rem 1.25rem 1rem; }
        .sca-modal .modal-footer { padding: 0 1.25rem 1.25rem; }
        .sca-modal .btn { min-width: 100px; padding: 0.6rem 1rem; }
    }
</style>

{{--
    ============================================================
    CONTAINER HTML del modal (un único <div> reutilizable)
    El JS sca-modal.js rellena #scaModalBody y #scaModalFooter
    según el tipo (success/error/warning/info/loading/confirm).
    ============================================================
--}}
<div class="modal fade sca-modal" id="scaModal" tabindex="-1" data-bs-backdrop="true" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" id="scaModalContent">
            {{-- Body rellenado dinámicamente por sca-modal.js --}}
            <div class="modal-body" id="scaModalBody"></div>
            {{-- Footer (botones) rellenado dinámicamente por sca-modal.js --}}
            <div class="modal-footer" id="scaModalFooter"></div>
        </div>
    </div>
</div>