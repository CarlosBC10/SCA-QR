{{-- resources/views/kiosko/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Kiosco de Acceso')

@section('content')
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

<style>
    * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
    body, html { height: 100%; overflow: hidden; }
    body { background: #133d16; font-family: 'Poppins', sans-serif; }

    /* ============================================================
       PALETA DEL KIOSCO (independiente del panel administrativo)
       ============================================================ */
    :root {
        --k-green-1: #0d3311;
        --k-green-2: #1b5e20;
        --k-green-3: #2e7d32;
        --k-red-1: #5c0000;
        --k-red-2: #b71c1c;
        --k-red-3: #d32f2f;
        --k-orange-1: #7a3900;
        --k-orange-2: #e65100;
        --k-orange-3: #f57c00;
        --k-blue-1: #012740;
        --k-blue-2: #01579b;
        --k-blue-3: #0277bd;
        --k-glass: rgba(255,255,255,0.10);
        --k-glass-border: rgba(255,255,255,0.22);
    }

    .kiosko-wrapper {
        height: 100vh; width: 100vw; display: flex; align-items: center; justify-content: center;
        position: relative; overflow: hidden; padding: 1rem;
        background: radial-gradient(circle at 30% 20%, var(--k-green-3) 0%, var(--k-green-2) 45%, var(--k-green-1) 100%);
    }
    .kiosko-wrapper::before, .kiosko-wrapper::after {
        content: ''; position: absolute; z-index: 0; border-radius: 50%; filter: blur(70px); pointer-events: none;
    }
    .kiosko-wrapper::before { width: 420px; height: 420px; top: -140px; right: -120px; background: rgba(255,255,255,0.10); }
    .kiosko-wrapper::after { width: 480px; height: 480px; bottom: -180px; left: -140px; background: rgba(0,0,0,0.28); }

    .kiosko-screen {
        display: none; flex-direction: column; align-items: center; justify-content: center; text-align: center;
        width: 100%; height: 100%; padding: 1rem; position: relative; z-index: 1;
        animation: screenIn 0.45s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .kiosko-screen.active { display: flex; }
    @keyframes screenIn { from { opacity: 0; transform: translateY(10px) scale(0.99); } to { opacity: 1; transform: translateY(0) scale(1); } }

    .screen-full { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 100; overflow: hidden; }
    .screen-full::before, .screen-full::after {
        content: ''; position: absolute; z-index: -1; border-radius: 50%; filter: blur(80px); pointer-events: none;
    }
    .screen-full::before { width: 460px; height: 460px; top: -160px; right: -100px; background: rgba(255,255,255,0.10); }
    .screen-full::after { width: 500px; height: 500px; bottom: -200px; left: -160px; background: rgba(0,0,0,0.25); }

    .bg-success-full { background: radial-gradient(circle at 30% 15%, var(--k-green-3), var(--k-green-2) 45%, var(--k-green-1) 100%); color: #fff; }
    .bg-danger-full  { background: radial-gradient(circle at 30% 15%, var(--k-red-3), var(--k-red-2) 45%, var(--k-red-1) 100%); color: #fff; }
    .bg-warning-full { background: radial-gradient(circle at 30% 15%, var(--k-orange-3), var(--k-orange-2) 45%, var(--k-orange-1) 100%); color: #fff; }
    .bg-info-full    { background: radial-gradient(circle at 30% 15%, var(--k-blue-3), var(--k-blue-2) 45%, var(--k-blue-1) 100%); color: #fff; }

    /* ===== Insignia de icono principal ===== */
    .icon-badge {
        position: relative; display: flex; align-items: center; justify-content: center;
        width: clamp(90px, 16vw, 130px); height: clamp(90px, 16vw, 130px);
        border-radius: 50%; margin-bottom: 1.1rem;
        background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.25);
        box-shadow: 0 0 0 10px rgba(255,255,255,0.05), 0 12px 30px rgba(0,0,0,0.25);
    }
    .icon-badge::before {
        content: ''; position: absolute; inset: -14px; border-radius: 50%;
        border: 2px solid rgba(255,255,255,0.18); animation: ringPulse 2.4s ease-out infinite;
    }
    @keyframes ringPulse {
        0% { transform: scale(0.92); opacity: 0.9; }
        70% { transform: scale(1.18); opacity: 0; }
        100% { opacity: 0; }
    }
    .kiosko-icon { font-size: clamp(2.4rem, 7vw, 4rem); line-height: 1; filter: drop-shadow(0 4px 10px rgba(0,0,0,0.25)); }
    .icon-badge .kiosko-icon { margin: 0; }

    .kiosko-eyebrow {
        font-size: clamp(0.65rem, 1.4vw, 0.8rem); font-weight: 700; letter-spacing: 3px; text-transform: uppercase;
        opacity: 0.65; margin-bottom: 0.4rem;
    }
    .kiosko-title { font-size: clamp(1.6rem, 5vw, 3.1rem); font-weight: 800; margin-bottom: 0.25rem; line-height: 1.1; letter-spacing: -0.5px; text-shadow: 0 4px 18px rgba(0,0,0,0.25); }
    .kiosko-subtitle { font-size: clamp(1rem, 3vw, 1.7rem); font-weight: 600; opacity: 0.92; margin-bottom: 0; }
    .kiosko-message { font-size: clamp(0.9rem, 2.5vw, 1.25rem); font-weight: 500; opacity: 0.88; max-width: 600px; margin-top: 0.6rem; line-height: 1.45; }

    .photo-wrapper { position: relative; margin-bottom: 1rem; }
    .photo-circle {
        width: clamp(84px, 15vw, 144px); height: clamp(84px, 15vw, 144px); border-radius: 50%; object-fit: cover;
        border: 4px solid rgba(255,255,255,0.5); box-shadow: 0 0 0 8px rgba(255,255,255,0.08), 0 10px 34px rgba(0,0,0,0.4);
    }
    .photo-check {
        position: absolute; bottom: 2px; right: 2px; background: #fff; border-radius: 50%;
        width: clamp(26px, 5vw, 42px); height: clamp(26px, 5vw, 42px); display: flex; align-items: center; justify-content: center;
        font-size: clamp(0.85rem, 2.5vw, 1.5rem); box-shadow: 0 4px 14px rgba(0,0,0,0.35); border: 2px solid rgba(0,0,0,0.05);
    }

    #scanner-input { position: absolute; top: -100px; left: -100px; opacity: 0; width: 1px; height: 1px; }

    /* ===== Botones ===== */
    .kiosko-btn {
        font-size: clamp(1rem, 2vw, 1.15rem); font-weight: 700; padding: 0.95rem 1.5rem; border-radius: 16px;
        border: none; cursor: pointer; transition: transform 0.15s ease, box-shadow 0.2s ease, background 0.2s ease;
        display: flex; align-items: center; justify-content: center; gap: 0.6rem; text-decoration: none;
        min-height: 58px; width: 100%; letter-spacing: 0.2px;
    }
    .kiosko-btn i { font-size: 1.15em; }
    .kiosko-btn:active { transform: scale(0.97); }
    .kiosko-btn-primary { background: #fff; color: var(--k-green-2); box-shadow: 0 10px 26px rgba(0,0,0,0.28); }
    .kiosko-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 14px 34px rgba(0,0,0,0.34); }
    .kiosko-btn-secondary { background: rgba(255,255,255,0.12); color: #fff; border: 2px solid rgba(255,255,255,0.4); backdrop-filter: blur(6px); }
    .kiosko-btn-secondary:hover { background: rgba(255,255,255,0.2); border-color: #fff; transform: translateY(-2px); }

    .btn-group-container { display: flex; flex-direction: column; gap: 0.8rem; width: 100%; max-width: 320px; margin-top: 1.2rem; }

    .btn-exit {
        position: absolute; top: 14px; right: 14px; z-index: 9999; display: flex; align-items: center; gap: 0.4rem;
        background: rgba(0,0,0,0.28); border: 1px solid rgba(255,255,255,0.18); backdrop-filter: blur(6px);
        border-radius: 12px; padding: 8px 16px; font-size: 0.85rem; font-weight: 600; color: rgba(255,255,255,0.65);
        cursor: pointer; opacity: 0.65; transition: all 0.25s;
    }
    .btn-exit:hover { opacity: 1; color: #fff; background: rgba(0,0,0,0.4); }

    /* ===== Barra superior de info ===== */
    .kiosko-topinfo {
        position: absolute; top: 14px; left: 14px; z-index: 9999;
        background: rgba(0,0,0,0.28); border: 1px solid rgba(255,255,255,0.18); backdrop-filter: blur(6px);
        border-radius: 14px; padding: 10px 16px; color: #fff; min-width: 190px;
    }
    #kiosko-reloj { font-size: 1.5rem; font-weight: 800; line-height: 1; font-variant-numeric: tabular-nums; letter-spacing: 0.5px; }
    #kiosko-fecha { font-size: 0.75rem; opacity: 0.75; margin-top: 2px; text-transform: capitalize; }
    .kiosko-topinfo-meta {
        font-size: 0.72rem; opacity: 0.8; margin-top: 6px; border-top: 1px solid rgba(255,255,255,0.18);
        padding-top: 6px; display: flex; align-items: center; gap: 6px; font-weight: 600;
    }
    .modo-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
    .modo-dot.entrada { background: #66bb6a; box-shadow: 0 0 8px #66bb6a; }
    .modo-dot.salida { background: #ffb74d; box-shadow: 0 0 8px #ffb74d; }
    .modo-dot.mixto { background: #4fc3f7; box-shadow: 0 0 8px #4fc3f7; }

    .mode-badge {
        display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1.3rem; border-radius: 50px;
        font-size: clamp(0.8rem, 1.8vw, 1rem); font-weight: 700; margin-bottom: 1.2rem;
        background: var(--k-glass); color: #fff; border: 1px solid var(--k-glass-border);
    }

    .camera-floating {
        position: fixed; bottom: 18px; right: 18px; width: 210px; z-index: 9998;
        border-radius: 16px; overflow: hidden; box-shadow: 0 14px 34px rgba(0,0,0,0.5); display: none;
        border: 1px solid rgba(255,255,255,0.25); background: #000;
    }
    .camera-floating-header {
        display: flex; align-items: center; gap: 0.4rem; padding: 6px 10px; background: rgba(0,0,0,0.65);
        color: #fff; font-size: 0.68rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase;
    }
    .camera-rec-dot { width: 7px; height: 7px; border-radius: 50%; background: #ff5252; animation: recBlink 1.2s ease-in-out infinite; }
    @keyframes recBlink { 0%, 100% { opacity: 1; } 50% { opacity: 0.25; } }
    .camera-floating video { width: 100%; height: 150px; object-fit: cover; display: block; background: #000; }
    .camera-scan-line { position: absolute; top: 30px; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, #fff, transparent); animation: scanLine 2s linear infinite; }
    @keyframes scanLine { 0% { top: 30px; } 50% { top: 174px; } 100% { top: 30px; } }
    .camera-close { position: absolute; top: 5px; right: 6px; background: rgba(255,255,255,0.15); color: #fff; border: none; border-radius: 50%; width: 22px; height: 22px; font-size: 0.8rem; cursor: pointer; display: flex; align-items: center; justify-content: center; }

    .kiosko-input {
        width: 100%; max-width: 300px; padding: 1rem; font-size: clamp(1.2rem, 4vw, 2rem); text-align: center;
        border: 2px solid rgba(255,255,255,0.4); border-radius: 14px; outline: none; background: rgba(255,255,255,0.08);
        color: #fff; font-weight: 700; letter-spacing: 2px; transition: all 0.2s;
    }
    .kiosko-input::placeholder { color: rgba(255,255,255,0.35); letter-spacing: normal; font-weight: 500; }
    .kiosko-input:focus { border-color: #fff; background: rgba(255,255,255,0.14); box-shadow: 0 0 0 5px rgba(255,255,255,0.1); }
    .kiosko-textarea {
        width: 100%; max-width: 460px; padding: 1rem; font-size: clamp(0.9rem, 2vw, 1.15rem);
        border: 2px solid rgba(255,255,255,0.4); border-radius: 14px; outline: none; background: rgba(255,255,255,0.08);
        color: #fff; resize: none; line-height: 1.5; transition: all 0.2s; font-family: inherit;
    }
    .kiosko-textarea::placeholder { color: rgba(255,255,255,0.35); }
    .kiosko-textarea:focus { border-color: #fff; background: rgba(255,255,255,0.14); box-shadow: 0 0 0 5px rgba(255,255,255,0.1); }

    /* ===== Panel tipo "glass" para formularios ===== */
    .kiosko-panel {
        background: var(--k-glass); border: 1px solid var(--k-glass-border); backdrop-filter: blur(10px);
        border-radius: 24px; padding: clamp(1.5rem, 4vw, 2.5rem); max-width: 480px; width: 100%;
        box-shadow: 0 20px 50px rgba(0,0,0,0.2); display: flex; flex-direction: column; align-items: center;
    }

    .kiosko-spinner { width: clamp(50px, 8vw, 80px); height: clamp(50px, 8vw, 80px); border-radius: 50%; animation: spin 0.9s linear infinite;
        border: 5px solid rgba(255,255,255,0.15); border-top-color: #fff; border-right-color: rgba(255,255,255,0.6); }
    @keyframes spin { to { transform: rotate(360deg); } }

    .kiosko-modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.75); backdrop-filter: blur(10px); z-index: 10000; display: none; align-items: center; justify-content: center; padding: 1rem; }
    .kiosko-modal.show { display: flex; }
    .kiosko-modal-content {
        background: #fff; border-radius: 24px; padding: 2rem; max-width: 360px; width: 100%;
        box-shadow: 0 24px 60px rgba(0,0,0,0.5); text-align: center; animation: modalIn 0.25s cubic-bezier(0.16,1,0.3,1);
    }
    @keyframes modalIn { from { opacity: 0; transform: scale(0.94) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
    .modal-icon-circle {
        width: 60px; height: 60px; border-radius: 50%; background: rgba(220,53,69,0.1); color: #dc3545;
        display: flex; align-items: center; justify-content: center; font-size: 1.6rem; margin: 0 auto 0.9rem;
    }

    @media (orientation: landscape) and (max-height: 420px) {
        .icon-badge { width: 64px; height: 64px; margin-bottom: 0.5rem; }
        .kiosko-icon { font-size: 1.8rem; }
        .kiosko-title { font-size: 1.3rem; }
        .kiosko-subtitle { font-size: 0.9rem; }
        .photo-circle { width: 66px; height: 66px; }
        .mode-badge { margin-bottom: 0.5rem; padding: 0.3rem 0.9rem; }
        .camera-floating { width: 150px; }
        .camera-floating video { height: 100px; }
    }

    #screen-loading .kiosko-subtitle { animation: pulse 1.5s ease-in-out infinite; margin-top: 1.2rem; }
    @keyframes pulse { 0%, 100% { opacity: 0.55; } 50% { opacity: 1; } }

    .kiosko-brand-footer {
        position: absolute; bottom: 14px; left: 0; right: 0; text-align: center; z-index: 1;
        font-size: 0.68rem; letter-spacing: 2px; text-transform: uppercase; color: rgba(255,255,255,0.4); font-weight: 600;
    }
</style>

<div class="kiosko-wrapper">

    <button class="btn-exit" onclick="showExitModal()">
        <i class="bi bi-x-circle"></i> Salir
    </button>

    {{-- Info superior con reloj, fecha, planta y modo --}}
    <div class="kiosko-topinfo">
        <div id="kiosko-reloj">--:--:--</div>
        <div id="kiosko-fecha">Cargando...</div>
        <div class="kiosko-topinfo-meta">
            @php $modo = $turno->modo; @endphp
            <span class="modo-dot {{ $modo === 'entrada' ? 'entrada' : ($modo === 'salida' ? 'salida' : 'mixto') }}"></span>
            <i class="bi bi-geo-alt"></i> {{ $turno->planta->nombre ?? 'N/A' }} ·
            @if($modo === 'entrada') ENTRADAS
            @elseif($modo === 'salida') SALIDAS
            @else MIXTO
            @endif
        </div>
    </div>

    <input type="text" id="scanner-input" autocomplete="off">

    {{-- BIENVENIDA --}}
    <div id="screen-welcome" class="kiosko-screen active">
        <div class="icon-badge">
            <i class="bi bi-qr-code-scan kiosko-icon" style="color:#fff;"></i>
        </div>
        <div class="kiosko-eyebrow" style="color:#fff;">Fruitex de México</div>
        <h1 class="kiosko-title" style="color:#fff;">SCA-QR</h1>
        <p class="kiosko-subtitle" style="color:#fff; margin-bottom: 0.5rem;">Acerque su credencial al escáner</p>

        @php $modo = $turno->modo; @endphp
        <div class="mode-badge">
            @if($modo === 'entrada')
                <i class="bi bi-box-arrow-in-right"></i> MODO ENTRADAS
            @elseif($modo === 'salida')
                <i class="bi bi-box-arrow-right"></i> MODO SALIDAS
            @else
                <i class="bi bi-arrow-left-right"></i> MODO MIXTO
            @endif
        </div>

        <div class="btn-group-container">
            <button class="kiosko-btn kiosko-btn-primary" onclick="toggleCamera()">
                <i class="bi bi-camera"></i> Usar Cámara
            </button>
            <button class="kiosko-btn kiosko-btn-secondary" onclick="showManualEntry()">
                <i class="bi bi-keyboard"></i> Búsqueda Manual
            </button>
        </div>
    </div>

    {{-- PROCESANDO --}}
    <div id="screen-loading" class="kiosko-screen">
        <div class="kiosko-spinner"></div>
        <h3 class="kiosko-subtitle" style="color:#fff;">Procesando...</h3>
    </div>

    {{-- ÉXITO --}}
    <div id="screen-success" class="kiosko-screen screen-full bg-success-full">
        <div class="icon-badge">
            <i class="bi bi-check-lg kiosko-icon" style="color:#fff;"></i>
        </div>
        <h2 id="success-title" class="kiosko-title"></h2>
        <div class="photo-wrapper">
            <img id="success-photo" src="" class="photo-circle" alt="Foto">
            <div class="photo-check">
                <i class="bi bi-check-lg text-success"></i>
            </div>
        </div>
        <h3 id="success-name" class="kiosko-title" style="font-size: clamp(1.2rem, 4vw, 2rem);"></h3>
        <p id="success-info" class="kiosko-subtitle" style="opacity: 0.85; font-size: clamp(0.85rem, 2.2vw, 1.15rem);"></p>
        <p id="success-msg" class="kiosko-message"></p>
    </div>

    {{-- DENEGADO --}}
    <div id="screen-denied" class="kiosko-screen screen-full bg-danger-full">
        <div class="icon-badge">
            <i class="bi bi-x-lg kiosko-icon" style="color:#fff;"></i>
        </div>
        <h2 class="kiosko-title">ACCESO DENEGADO</h2>
        <p id="denied-msg" class="kiosko-message"></p>
    </div>

    {{-- REVISIÓN --}}
    <div id="screen-review" class="kiosko-screen screen-full bg-warning-full">
        <div class="icon-badge">
            <i class="bi bi-hourglass-split kiosko-icon" style="color:#fff;"></i>
        </div>
        <h2 class="kiosko-title">ESPERE UN MOMENTO</h2>
        <p class="kiosko-message">Espere al guardia para revisación.</p>
        <div class="kiosko-spinner" style="margin-top: 1.3rem;"></div>
    </div>

    {{-- JUSTIFICACIÓN --}}
    <div id="screen-justify" class="kiosko-screen screen-full bg-info-full">
        <div class="kiosko-panel">
            <div class="icon-badge" style="margin-bottom: 0.8rem;">
                <i class="bi bi-exclamation-triangle-fill kiosko-icon" style="color:#fff; font-size: clamp(1.8rem, 5vw, 2.6rem);"></i>
            </div>
            <h2 class="kiosko-title" style="font-size: clamp(1.3rem, 4vw, 2rem);">JUSTIFIQUE SU ACCESO</h2>
            <p class="kiosko-message" style="margin-bottom: 1.1rem;">Se detectó acceso fuera de horario. Escriba el motivo:</p>
            <textarea id="justify-text" class="kiosko-textarea" rows="3" placeholder="Escriba aquí..."></textarea>
            <div class="btn-group-container" style="max-width: 100%;">
                <button class="kiosko-btn" style="background: #fff; color: var(--k-blue-2);" onclick="submitJustification()">
                    <i class="bi bi-send"></i> ENVIAR Y REGISTRAR
                </button>
            </div>
        </div>
    </div>

    {{-- BÚSQUEDA MANUAL --}}
    <div id="screen-manual" class="kiosko-screen">
        <div class="kiosko-panel">
            <div class="icon-badge" style="margin-bottom: 0.8rem;">
                <i class="bi bi-keyboard kiosko-icon" style="color:#fff; font-size: clamp(1.8rem, 5vw, 2.6rem);"></i>
            </div>
            <h2 class="kiosko-title" style="color:#fff; font-size: clamp(1.3rem, 4vw, 2rem);">BÚSQUEDA MANUAL</h2>
            <p class="kiosko-subtitle" style="color:#fff; margin-bottom: 1.1rem; font-size: clamp(0.9rem, 2.4vw, 1.2rem);">Ingrese su número de empleado</p>
            <input type="text" inputmode="numeric" id="manual-input" class="kiosko-input" placeholder="Ej: 1001">
            <div class="btn-group-container" style="max-width: 100%;">
                <button class="kiosko-btn kiosko-btn-primary" onclick="submitManual()">
                    <i class="bi bi-check-lg"></i> Registrar
                </button>
                <button class="kiosko-btn kiosko-btn-secondary" onclick="resetToWelcome()">
                    <i class="bi bi-arrow-left"></i> Cancelar
                </button>
            </div>
        </div>
    </div>

    <div class="kiosko-brand-footer">Fruitex de México &middot; Sistema SCA-QR</div>
</div>

{{-- CÁMARA FLOTANTE --}}
<div class="camera-floating" id="camera-view">
    <div class="camera-floating-header">
        <span class="camera-rec-dot"></span> Escaneando QR
    </div>
    <video id="video" playsinline></video>
    <div class="camera-scan-line"></div>
    <button class="camera-close" onclick="toggleCamera()"><i class="bi bi-x"></i></button>
</div>

{{-- MODAL SALIR --}}
<div class="kiosko-modal" id="exitModal">
    <div class="kiosko-modal-content">
        <div class="modal-icon-circle">
            <i class="bi bi-lock-fill"></i>
        </div>
        <h4 style="font-weight: 800; font-size: 1.3rem; margin-bottom: 0.5rem; color: #333;">
            Salir del Kiosko
        </h4>
        <p style="color: #666; font-size: 1rem; margin-bottom: 1.2rem;">Ingrese el PIN de seguridad:</p>
        {{-- Apuntamos a la ruta que incluye el ID del turno --}}
        <form action="{{ route('kiosko.salir', $turno->id) }}" method="POST">
            @csrf
            <input type="password" name="pin" class="kiosko-input" style="color: #333; border-color: #ddd; background: #f8f9fa; letter-spacing: 8px; margin-bottom: 1.2rem;" required autofocus>
            <div class="btn-group-container" style="max-width: 100%;">
                <button type="submit" class="kiosko-btn" style="background: #dc3545; color: #fff; box-shadow: 0 8px 20px rgba(220,53,69,0.3);">
                    Desactivar
                </button>
                <button type="button" class="kiosko-btn kiosko-btn-secondary" style="color: #333; border-color: #ddd; background: #f1f1f1;" onclick="hideExitModal()">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
<script>
    const scannerInput = document.getElementById('scanner-input');

    // ===== Reloj en tiempo real del kiosko =====
    const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    const dias = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
    function actualizarRelojKiosko() {
        const ahora = new Date();
        const h = String(ahora.getHours()).padStart(2, '0');
        const m = String(ahora.getMinutes()).padStart(2, '0');
        const s = String(ahora.getSeconds()).padStart(2, '0');
        document.getElementById('kiosko-reloj').textContent = `${h}:${m}:${s}`;
        const dia = dias[ahora.getDay()];
        const d = ahora.getDate();
        const mes = meses[ahora.getMonth()];
        const a = ahora.getFullYear();
        document.getElementById('kiosko-fecha').textContent = `${dia}, ${d} ${mes} ${a}`;
    }
    actualizarRelojKiosko();
    setInterval(actualizarRelojKiosko, 1000);

    let pollingInterval = null;
    let cameraStream = null;
    let currentBitacoraId = null;

    // ===== Banderas de control para evitar dobles procesamientos =====
    let isProcessing = false;        // true mientras hay un escaneo en curso
    let lastCodeProcessed = null;    // último código procesado (para evitar duplicados)
    let lastCodeTime = 0;            // timestamp del último código (cooldown 3s)
    const COOLDOWN_MS = 3000;        // no procesar el mismo código por 3 segundos

    // ===== Enfoque automático del input =====
    document.addEventListener('click', (e) => {
        if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA' && e.target.tagName !== 'BUTTON' && !e.target.closest('.camera-close')) {
            const modalVisible = document.querySelector('.kiosko-modal.show');
            if (!modalVisible && !isProcessing) scannerInput.focus();
        }
    });
    setInterval(() => {
        const modalVisible = document.querySelector('.kiosko-modal.show');
        const activeElement = document.activeElement;
        const isWritingInput = activeElement && (activeElement.tagName === 'INPUT' || activeElement.tagName === 'TEXTAREA');
        if (document.getElementById('screen-welcome').classList.contains('active') && !modalVisible && !isWritingInput && !isProcessing) {
            scannerInput.focus();
        }
    }, 1000);

    // ===== Láser / keyboard wedge: solo procesa si NO está procesando =====
    scannerInput.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const codigo = this.value.trim();
            this.value = '';
            if (codigo) procesarCodigo(codigo, 'kiosco');
        }
    });

    // ===== Cámara =====
    async function toggleCamera() {
        const view = document.getElementById('camera-view');
        if (cameraStream) {
            cameraStream.getTracks().forEach(track => track.stop());
            cameraStream = null;
            view.style.display = 'none';
            return;
        }
        view.style.display = 'block';
        const video = document.getElementById('video');
        cameraStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        video.srcObject = cameraStream;
        video.play();
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');
        const tick = () => {
            if (!cameraStream) return;
            // No detectar si estamos procesando
            if (!isProcessing && video.readyState === video.HAVE_ENOUGH_DATA) {
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                context.drawImage(video, 0, 0, canvas.width, canvas.height);
                const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
                const code = jsQR(imageData.data, imageData.width, imageData.height);
                if (code) {
                    procesarCodigo(code.data, 'kiosco');
                }
            }
            requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    }

    // ===== Entrada manual =====
    function showManualEntry() {
        switchScreen('screen-manual');
        document.getElementById('manual-input').focus();
    }
    function submitManual() {
        const val = document.getElementById('manual-input').value.trim();
        if(val) procesarCodigo(val, 'manual');
    }

    // ===== Procesar código (con lock anti-doble) =====
    async function procesarCodigo(codigo, origen = 'kiosco') {
        codigo = String(codigo).trim();
        if (!codigo) return;

        // BLOQUEO 1: si ya hay un escaneo en curso, ignorar
        if (isProcessing) return;

        // BLOQUEO 2: cooldown de 3s para el mismo código (evita dobles del láser)
        const ahora = Date.now();
        if (codigo === lastCodeProcessed && (ahora - lastCodeTime) < COOLDOWN_MS) return;

        // Activar lock y registrar
        isProcessing = true;
        lastCodeProcessed = codigo;
        lastCodeTime = ahora;

        // Mostrar pantalla de PROCESANDO inmediatamente
        switchScreen('screen-loading');
        clearInterval(pollingInterval);

        try {
            const res = await fetch('/api/scan', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ codigo, origen: origen, turno_id: '{{ $turno->id }}' })
            });
            const data = await res.json();
            currentBitacoraId = data.bitacora_id;
            mostrarResultado(data);
        } catch (error) {
            mostrarDenegado('Error de conexión.');
        } finally {
            // Liberar el lock después de un breve delay (para no re-procesar el mismo QR)
            setTimeout(() => { isProcessing = false; }, 1500);
        }
    }

    function mostrarResultado(data) {
        if (data.success && data.autorizado) {
            if (data.revision_pendiente) {
                switchScreen('screen-review');
                iniciarPolling(data.bitacora_id);
            } else if (data.requiere_justificacion) {
                switchScreen('screen-justify');
            } else {
                mostrarExito(data);
            }
        } else {
            mostrarDenegado(data.message || 'Error.');
        }
    }

    function mostrarExito(data) {
        document.getElementById('success-title').textContent = data.tipo_evento === 'entrada' ? 'ENTRADA AUTORIZADA' : 'SALIDA REGISTRADA';
        document.getElementById('success-photo').src = data.persona.foto;
        document.getElementById('success-name').textContent = data.persona.nombre;
        document.getElementById('success-info').textContent = `${data.persona.puesto || ''} - ${data.persona.tipo_empleado}${data.persona.planta ? ' · ' + data.persona.planta : ''}`;
        document.getElementById('success-msg').textContent = data.message;
        switchScreen('screen-success');
        setTimeout(resetToWelcome, 5000);
    }

    function mostrarDenegado(msg) {
        document.getElementById('denied-msg').textContent = msg;
        switchScreen('screen-denied');
        setTimeout(resetToWelcome, 5000);
    }

    async function submitJustification() {
        const text = document.getElementById('justify-text').value.trim();
        if (!text) return alert('Debe escribir una justificación.');
        // Deshabilitar botón mientras se envía
        const btn = event.target;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enviando...';
        try {
            await fetch('/api/scan/justificar', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ bitacora_id: currentBitacoraId, justificacion: text })
            });
            mostrarExito({
                tipo_evento: 'autorizado',
                persona: { nombre: 'Registrado', puesto: '', tipo_empleado: '', planta: '', foto: 'https://ui-avatars.com/api/?name=OK&background=28a745&color=fff' },
                message: 'Acceso justificado y registrado.'
            });
        } catch(e) {
            alert('Error de conexión');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send"></i> ENVIAR Y REGISTRAR';
        }
    }

    function iniciarPolling(bitacoraId) {
        pollingInterval = setInterval(async () => {
            try {
                const res = await fetch(`/api/scan/status/${bitacoraId}`);
                const data = await res.json();
                if (data.autorizado || data.estado === 'revision_completada') {
                    clearInterval(pollingInterval);
                    mostrarExito({
                        tipo_evento: 'autorizado',
                        persona: { nombre: 'Revisión Completada', puesto: '', tipo_empleado: '', planta: '', foto: 'https://ui-avatars.com/api/?name=OK&background=28a745&color=fff' },
                        message: 'Revisión completada. Acceso autorizado.'
                    });
                }
            } catch(e) {
                console.error('Error en polling:', e);
            }
        }, 3000);
    }

    function switchScreen(id) {
        document.querySelectorAll('.kiosko-screen').forEach(s => s.classList.remove('active'));
        document.getElementById(id).classList.add('active');
    }

    function resetToWelcome() {
        switchScreen('screen-welcome');
        document.getElementById('justify-text').value = '';
        document.getElementById('manual-input').value = '';
        // Resetear el lock al volver al welcome
        isProcessing = false;
        scannerInput.focus();
    }

    function showExitModal() {
        document.getElementById('exitModal').classList.add('show');
        setTimeout(() => document.querySelector('#exitModal input').focus(), 100);
    }
    function hideExitModal() {
        document.getElementById('exitModal').classList.remove('show');
        if (!isProcessing) scannerInput.focus();
    }
    document.getElementById('exitModal').addEventListener('click', function(e) {
        if (e.target === this) hideExitModal();
    });
</script>
@endsection
