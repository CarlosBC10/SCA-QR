{{-- resources/views/kiosko/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Kiosco de Acceso')

@section('content')
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

<style>
    * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
    body, html { height: 100%; overflow: hidden; }
    body { font-family: 'Poppins', sans-serif; }

    :root {
        --k-green-1: #0d3311; --k-green-2: #1b5e20; --k-green-3: #2e7d32;
        --k-red-1: #5c0000; --k-red-2: #b71c1c; --k-red-3: #d32f2f;
        --k-orange-1: #7a3900; --k-orange-2: #e65100; --k-orange-3: #f57c00;
        --k-blue-1: #012740; --k-blue-2: #01579b; --k-blue-3: #0277bd;
        --k-glass: rgba(255,255,255,0.10); --k-glass-border: rgba(255,255,255,0.22);
    }

    .kiosko-wrapper {
        height: 100vh; width: 100vw; display: flex; align-items: center; justify-content: center;
        position: relative; overflow: hidden; padding: 1rem;
        background: radial-gradient(circle at 30% 20%, var(--k-green-3) 0%, var(--k-green-2) 45%, var(--k-green-1) 100%);
    }
    .kiosko-wrapper::before, .kiosko-wrapper::after {
        content: ''; position: absolute; z-index: 0; border-radius: 50%; filter: blur(70px); pointer-events: none;
    }
    .kiosko-wrapper::before { width: 60vw; height: 60vw; top: -20vw; right: -20vw; background: rgba(255,255,255,0.07); }
    .kiosko-wrapper::after { width: 50vw; height: 50vw; bottom: -20vw; left: -20vw; background: rgba(0,0,0,0.18); }

    .kiosko-screen { display: none; flex-direction: column; align-items: center; justify-content: center; text-align: center; width: 100%; height: 100%; padding: 1rem; animation: fadeIn 0.4s ease; position: relative; z-index: 1; }
    .kiosko-screen.active { display: flex; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

    .screen-full { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 100; }
    .bg-success-full { background: radial-gradient(circle at 30% 20%, var(--k-green-3) 0%, var(--k-green-2) 45%, var(--k-green-1) 100%); color: #fff; }
    .bg-danger-full { background: radial-gradient(circle at 30% 20%, var(--k-red-3) 0%, var(--k-red-2) 45%, var(--k-red-1) 100%); color: #fff; }
    .bg-warning-full { background: radial-gradient(circle at 30% 20%, var(--k-orange-3) 0%, var(--k-orange-2) 45%, var(--k-orange-1) 100%); color: #fff; }
    .bg-info-full { background: radial-gradient(circle at 30% 20%, var(--k-blue-3) 0%, var(--k-blue-2) 45%, var(--k-blue-1) 100%); color: #fff; }

    .icon-badge { width: clamp(80px, 20vw, 140px); height: clamp(80px, 20vw, 140px); border-radius: 50%; background: rgba(255,255,255,0.15); display: flex; align-items: center; justify-content: center; margin-bottom: 1.5rem; }
    .kiosko-icon { font-size: clamp(2.5rem, 8vw, 5rem); }
    .kiosko-title { font-weight: 800; font-size: clamp(1.5rem, 5vw, 2.8rem); color: #fff; letter-spacing: -0.5px; margin-bottom: 0.5rem; }
    .kiosko-subtitle { font-weight: 500; font-size: clamp(1rem, 2.6vw, 1.4rem); color: #fff; opacity: 0.9; }
    .kiosko-message { font-weight: 500; font-size: clamp(1rem, 2.6vw, 1.3rem); color: #fff; opacity: 0.95; max-width: 600px; }

    .scan-frame { position: relative; width: clamp(180px, 40vw, 280px); height: clamp(180px, 40vw, 280px); border: 3px dashed rgba(255,255,255,0.3); border-radius: 24px; display: flex; align-items: center; justify-content: center; margin-bottom: 1.5rem; }
    .scan-corner { position: absolute; width: 32px; height: 32px; border: 4px solid rgba(255,255,255,0.9); }
    .scan-corner.tl { top: 8px; left: 8px; border-right: none; border-bottom: none; border-radius: 10px 0 0 0; }
    .scan-corner.tr { top: 8px; right: 8px; border-left: none; border-bottom: none; border-radius: 0 10px 0 0; }
    .scan-corner.bl { bottom: 8px; left: 8px; border-right: none; border-top: none; border-radius: 0 0 0 10px; }
    .scan-corner.br { bottom: 8px; right: 8px; border-left: none; border-top: none; border-radius: 0 0 10px 0; }
    .scan-frame .kiosko-icon { color: rgba(255,255,255,0.9); }
    .scan-frame .scan-line { position: absolute; left: 10px; right: 10px; height: 3px; border-radius: 3px; background: linear-gradient(90deg, transparent, #ffffff, transparent); box-shadow: 0 0 14px 2px rgba(255,255,255,0.55); animation: scanFrameLine 2.6s ease-in-out infinite; }
    @keyframes scanFrameLine { 0% { top: 16%; opacity: 0; } 12% { opacity: 1; } 50% { top: 80%; opacity: 1; } 62% { opacity: 0; } 100% { top: 16%; opacity: 0; } }

    .kiosko-brand-logo { height: 38px; padding: 6px 12px; background: #fff; border-radius: 10px; box-shadow: 0 8px 20px rgba(0,0,0,0.25); margin-bottom: 0.7rem; }
    .kiosko-eyebrow { font-size: clamp(0.65rem, 1.4vw, 0.8rem); font-weight: 700; letter-spacing: 3px; text-transform: uppercase; opacity: 0.65; margin-bottom: 0.4rem; }

    .photo-wrapper { position: relative; margin-bottom: 1rem; }
    .photo-circle { width: clamp(84px, 15vw, 144px); height: clamp(84px, 15vw, 144px); border-radius: 50%; object-fit: cover; border: 4px solid rgba(255,255,255,0.5); box-shadow: 0 0 0 8px rgba(255,255,255,0.08), 0 10px 34px rgba(0,0,0,0.4); }
    .photo-check { position: absolute; bottom: 2px; right: 2px; background: #fff; border-radius: 50%; width: clamp(26px, 5vw, 42px); height: clamp(26px, 5vw, 42px); display: flex; align-items: center; justify-content: center; font-size: clamp(0.85rem, 2.5vw, 1.5rem); box-shadow: 0 4px 14px rgba(0,0,0,0.35); border: 2px solid rgba(0,0,0,0.05); }

    #scanner-input { position: absolute; left: -9999px; opacity: 0; }

    .kiosko-btn { font-size: clamp(1rem, 2vw, 1.15rem); font-weight: 700; padding: 0.95rem 1.5rem; border-radius: 16px; border: none; cursor: pointer; transition: transform 0.15s, box-shadow 0.2s, background 0.2s; display: flex; align-items: center; justify-content: center; gap: 0.6rem; text-decoration: none; min-height: 58px; width: 100%; letter-spacing: 0.2px; }
    .kiosko-btn:active { transform: scale(0.97); }
    .kiosko-btn-primary { background: #fff; color: var(--k-green-2); box-shadow: 0 10px 26px rgba(0,0,0,0.28); }
    .kiosko-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 14px 34px rgba(0,0,0,0.34); }
    .kiosko-btn-secondary { background: rgba(255,255,255,0.12); color: #fff; border: 2px solid rgba(255,255,255,0.4); backdrop-filter: blur(6px); }
    .kiosko-btn-secondary:hover { background: rgba(255,255,255,0.2); border-color: #fff; transform: translateY(-2px); }

    .btn-group-container { display: flex; flex-direction: column; gap: 0.8rem; width: 100%; max-width: 320px; margin-top: 1.2rem; }

    .btn-exit { position: absolute; top: 14px; right: 14px; z-index: 9999; display: flex; align-items: center; gap: 0.4rem; background: rgba(0,0,0,0.28); border: 1px solid rgba(255,255,255,0.18); backdrop-filter: blur(6px); border-radius: 12px; padding: 8px 16px; font-size: 0.85rem; font-weight: 600; color: rgba(255,255,255,0.65); cursor: pointer; opacity: 0.65; transition: all 0.25s; }
    .btn-exit:hover { opacity: 1; color: #fff; background: rgba(0,0,0,0.4); }

    .kiosko-topinfo { position: absolute; top: 14px; left: 14px; z-index: 9999; background: rgba(0,0,0,0.28); border: 1px solid rgba(255,255,255,0.18); backdrop-filter: blur(6px); border-radius: 14px; padding: 10px 16px; color: #fff; min-width: 190px; }
    #kiosko-reloj { font-size: 1.5rem; font-weight: 800; line-height: 1; font-variant-numeric: tabular-nums; letter-spacing: 0.5px; }
    #kiosko-fecha { font-size: 0.75rem; opacity: 0.75; margin-top: 2px; text-transform: capitalize; }
    .kiosko-topinfo-meta { font-size: 0.72rem; opacity: 0.8; margin-top: 6px; border-top: 1px solid rgba(255,255,255,0.18); padding-top: 6px; display: flex; align-items: center; gap: 6px; font-weight: 600; }
    .modo-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
    .modo-dot.entrada { background: #66bb6a; box-shadow: 0 0 8px #66bb6a; }
    .modo-dot.salida { background: #ffb74d; box-shadow: 0 0 8px #ffb74d; }
    .modo-dot.mixto { background: #4fc3f7; box-shadow: 0 0 8px #4fc3f7; }

    .mode-badge { display: inline-flex; align-items: center; gap: 0.55rem; padding: 0.45rem 1.3rem 0.45rem 0.5rem; border-radius: 50px; font-size: clamp(0.8rem, 1.8vw, 1rem); font-weight: 700; margin-bottom: 1.3rem; background: var(--k-glass); color: #fff; border: 1px solid var(--k-glass-border); }
    .mode-badge i { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 50%; background: rgba(255,255,255,0.2); font-size: 0.85em; flex-shrink: 0; }

    /* V10.3.8-fix: ventana de cámara más grande para apuntar mejor el QR
       (antes 210px: el QR lejano no se distinguía en la previsualización) */
    .camera-floating { position: fixed; bottom: 18px; right: 18px; width: 300px; z-index: 9998; border-radius: 16px; overflow: hidden; box-shadow: 0 14px 34px rgba(0,0,0,0.5); display: none; border: 1px solid rgba(255,255,255,0.25); background: #000; }
    .camera-floating-header { display: flex; align-items: center; gap: 0.4rem; padding: 6px 10px; background: rgba(0,0,0,0.65); color: #fff; font-size: 0.68rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }
    .camera-rec-dot { width: 7px; height: 7px; border-radius: 50%; background: #ff5252; animation: recBlink 1.2s ease-in-out infinite; }
    @keyframes recBlink { 0%, 100% { opacity: 1; } 50% { opacity: 0.25; } }
    .camera-floating video { width: 100%; height: 200px; object-fit: cover; display: block; background: #000; }
    .camera-scan-line { position: absolute; top: 30px; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, #fff, transparent); animation: scanLine 2s linear infinite; }
    @keyframes scanLine { 0% { top: 30px; } 50% { top: 220px; } 100% { top: 30px; } }
    .camera-close { position: absolute; top: 5px; right: 6px; background: rgba(255,255,255,0.15); color: #fff; border: none; border-radius: 50%; width: 22px; height: 22px; font-size: 0.8rem; cursor: pointer; display: flex; align-items: center; justify-content: center; }

    /* V10.3.8-fix: barra de controles — elegir webcam externa/integrada,
       ver la resolución real de captura y acercar el QR con zoom */
    .camera-controls { position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.72); padding: 6px 8px; display: flex; flex-direction: column; gap: 5px; }
    .camera-controls-row { display: flex; align-items: center; gap: 6px; }
    .camera-controls select { flex: 1 1 auto; min-width: 0; font-size: 0.72rem; padding: 2px 4px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.25); background: rgba(255,255,255,0.12); color: #fff; outline: none; }
    .camera-controls select option { color: #333; background: #fff; }
    .camera-res { font-size: 0.65rem; color: #8fe3b4; white-space: nowrap; }
    .camera-controls input[type="range"] { flex: 1 1 auto; min-width: 0; height: 4px; accent-color: #6fae4a; cursor: pointer; }
    .camera-zoom-label { color: #fff; font-size: 0.65rem; width: 30px; text-align: right; }

    .kiosko-input { width: 100%; max-width: 300px; padding: 1rem; font-size: clamp(1.2rem, 4vw, 2rem); text-align: center; border: 2px solid rgba(255,255,255,0.4); border-radius: 14px; outline: none; background: rgba(255,255,255,0.08); color: #fff; font-weight: 700; letter-spacing: 2px; transition: all 0.2s; }
    .kiosko-input::placeholder { color: rgba(255,255,255,0.35); letter-spacing: normal; font-weight: 500; }
    .kiosko-input:focus { border-color: #fff; background: rgba(255,255,255,0.14); box-shadow: 0 0 0 5px rgba(255,255,255,0.1); }

    .kiosko-panel { background: var(--k-glass); border: 1px solid var(--k-glass-border); backdrop-filter: blur(10px); border-radius: 24px; padding: clamp(1.5rem, 4vw, 2.5rem); max-width: 480px; width: 100%; box-shadow: 0 20px 50px rgba(0,0,0,0.2); display: flex; flex-direction: column; align-items: center; }

    .kiosko-spinner { width: clamp(50px, 8vw, 80px); height: clamp(50px, 8vw, 80px); border-radius: 50%; animation: spin 0.9s linear infinite; border: 5px solid rgba(255,255,255,0.15); border-top-color: #fff; border-right-color: rgba(255,255,255,0.6); }
    @keyframes spin { to { transform: rotate(360deg); } }

    .kiosko-modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.75); backdrop-filter: blur(10px); z-index: 10000; display: none; align-items: center; justify-content: center; padding: 1rem; }
    .kiosko-modal.show { display: flex; }
    .kiosko-modal-content { background: #fff; border-radius: 24px; padding: 2rem; max-width: 360px; width: 100%; box-shadow: 0 24px 60px rgba(0,0,0,0.5); text-align: center; animation: modalIn 0.25s cubic-bezier(0.16,1,0.3,1); }
    @keyframes modalIn { from { opacity: 0; transform: scale(0.94) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
    .modal-icon-circle { width: 60px; height: 60px; border-radius: 50%; background: rgba(220,53,69,0.1); color: #dc3545; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; margin: 0 auto 0.9rem; }

    .kiosko-fruit-art { position: absolute; opacity: 0.08; pointer-events: none; z-index: 0; }
    .kiosko-fruit-art svg { width: 100%; height: 100%; }
    .kiosko-mango-br { bottom: -50px; right: -50px; width: 300px; height: 300px; }
    .kiosko-pina-bl { bottom: -30px; left: -30px; width: 250px; height: 320px; }

    #screen-loading .kiosko-subtitle { animation: pulse 1.5s ease-in-out infinite; margin-top: 1.2rem; }
    @keyframes pulse { 0%, 100% { opacity: 0.55; } 50% { opacity: 1; } }

    .kiosko-brand-footer { position: absolute; bottom: 14px; left: 0; right: 0; text-align: center; z-index: 1; font-size: 0.68rem; letter-spacing: 2px; text-transform: uppercase; color: rgba(255,255,255,0.4); font-weight: 600; }

    @media (orientation: landscape) and (max-height: 420px) {
        .icon-badge { width: 64px; height: 64px; margin-bottom: 0.5rem; }
        .kiosko-icon { font-size: 1.8rem; }
        .kiosko-title { font-size: 1.3rem; }
        .kiosko-subtitle { font-size: 0.9rem; }
        .photo-circle { width: 66px; height: 66px; }
        .mode-badge { margin-bottom: 0.5rem; padding: 0.3rem 0.9rem; }
        .camera-floating { width: 150px; }
        .camera-floating video { height: 100px; }
        .kiosko-fruit-art { display: none; }
    }
</style>

<svg style="display:none;">
    <defs>
        <symbol id="k-mango-branch" viewBox="0 0 300 340">
            <linearGradient id="kLeafA" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#6fae4a"/><stop offset="100%" stop-color="#356b2c"/></linearGradient>
            <linearGradient id="kLeafB" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#8bc34a"/><stop offset="100%" stop-color="#4a7d2e"/></linearGradient>
            <linearGradient id="kMangoA" x1="0" y1="0" x2="0.3" y2="1"><stop offset="0%" stop-color="#e2572c"/><stop offset="45%" stop-color="#f2a53c"/><stop offset="100%" stop-color="#e8d24a"/></linearGradient>
            <linearGradient id="kMangoB" x1="0" y1="0" x2="0.3" y2="1"><stop offset="0%" stop-color="#f2a53c"/><stop offset="55%" stop-color="#f5c93f"/><stop offset="100%" stop-color="#c7d445"/></linearGradient>
            <g transform="translate(0,60)">
                <path d="M150,0 C146,26 140,44 150,64 C160,84 168,96 158,118" fill="none" stroke="#5b3a22" stroke-width="5" stroke-linecap="round"/>
                <g stroke="#2f5c26" stroke-width="1" stroke-opacity="0.5">
                    <path d="M150,10 C170,-30 210,-46 238,-40 C220,-8 190,14 152,22 Z" fill="url(#kLeafA)" transform="rotate(6 150 10)"/>
                    <path d="M150,14 C130,-28 92,-46 62,-38 C82,-4 114,16 150,22 Z" fill="url(#kLeafB)" transform="rotate(-4 150 14)"/>
                    <path d="M156,30 C182,4 220,-2 244,14 C222,42 190,54 156,46 Z" fill="url(#kLeafA)" transform="rotate(10 156 30)"/>
                    <path d="M150,34 C120,10 82,8 56,26 C80,52 116,60 150,46 Z" fill="url(#kLeafB)" transform="rotate(-8 150 34)"/>
                    <path d="M158,54 C186,40 218,44 236,66 C210,86 178,86 156,70 Z" fill="url(#kLeafA)" transform="rotate(4 158 54)"/>
                </g>
                <g transform="translate(196,150) rotate(10)">
                    <path d="M0,-58 C24,-56 36,-24 34,10 C32,48 12,74 -3,75 C-18,74 -36,48 -36,12 C-36,-22 -22,-56 0,-58 Z" fill="url(#kMangoB)"/>
                    <ellipse cx="-10" cy="-14" rx="10" ry="20" fill="#ffffff" opacity="0.22" transform="rotate(-16 -10 -14)"/>
                </g>
                <g transform="translate(140,168) rotate(-6)">
                    <path d="M0,-72 C30,-70 46,-30 44,12 C42,58 16,90 -3,92 C-22,90 -44,58 -44,14 C-46,-28 -28,-70 0,-72 Z" fill="url(#kMangoA)"/>
                    <ellipse cx="-14" cy="-20" rx="13" ry="26" fill="#ffffff" opacity="0.26" transform="rotate(-16 -14 -20)"/>
                    <path d="M22,20 C36,42 32,68 6,86 C22,62 26,36 22,20 Z" fill="#8a2c14" opacity="0.16"/>
                </g>
            </g>
        </symbol>
        <symbol id="k-pineapple-fruit" viewBox="0 0 200 260">
            <linearGradient id="kPineLeaf" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#7cb342"/><stop offset="100%" stop-color="#33691e"/></linearGradient>
            <linearGradient id="kPineBody" x1="0" y1="0" x2="0.25" y2="1"><stop offset="0%" stop-color="#f6d365"/><stop offset="55%" stop-color="#e8b33d"/><stop offset="100%" stop-color="#a9701f"/></linearGradient>
            <clipPath id="kPineClip"><path d="M58,78 C55,60 145,60 142,78 C158,110 160,160 150,200 C148,215 150,230 140,240 C120,252 80,252 60,240 C50,230 52,215 50,200 C40,160 42,110 58,78 Z"/></clipPath>
            <g transform="translate(100,72)">
                <path d="M-6,0 C-11,-42 -5,-84 0,-112 C5,-84 11,-42 6,0 Z" fill="url(#kPineLeaf)" transform="rotate(-32)"/>
                <path d="M-6,0 C-11,-46 -5,-92 0,-124 C5,-92 11,-46 6,0 Z" fill="url(#kPineLeaf)" transform="rotate(-14)"/>
                <path d="M-6,0 C-11,-50 -5,-98 0,-132 C5,-98 11,-50 6,0 Z" fill="url(#kPineLeaf)" transform="rotate(4)"/>
                <path d="M-6,0 C-11,-46 -5,-92 0,-124 C5,-92 11,-46 6,0 Z" fill="url(#kPineLeaf)" transform="rotate(20)"/>
                <path d="M-6,0 C-11,-42 -5,-84 0,-112 C5,-84 11,-42 6,0 Z" fill="url(#kPineLeaf)" transform="rotate(36)"/>
            </g>
            <path d="M58,78 C55,60 145,60 142,78 C158,110 160,160 150,200 C148,215 150,230 140,240 C120,252 80,252 60,240 C50,230 52,215 50,200 C40,160 42,110 58,78 Z" fill="url(#kPineBody)"/>
            <g clip-path="url(#kPineClip)" stroke="#8a5a14" stroke-width="2.5" stroke-opacity="0.45" fill="none">
                <path d="M20,90 L100,180 M40,80 L130,200 M60,74 L155,210 M85,72 L172,215 M105,74 L185,205"/>
                <path d="M180,90 L100,180 M160,80 L70,200 M140,74 L45,210 M115,72 L28,215 M95,74 L15,205"/>
            </g>
        </symbol>
    </defs>
</svg>

<div class="kiosko-wrapper">

    <div class="kiosko-fruit-art kiosko-mango-br"><svg><use href="#k-mango-branch"/></svg></div>
    <div class="kiosko-fruit-art kiosko-pina-bl"><svg><use href="#k-pineapple-fruit"/></svg></div>

    <button class="btn-exit" onclick="showExitModal()">
        <i class="bi bi-x-circle"></i> Salir
    </button>

    <div class="kiosko-topinfo">
        <div id="kiosko-reloj">--:--:--</div>
        <div id="kiosko-fecha">Cargando...</div>
        <div class="kiosko-topinfo-meta">
            @php $modo = $turno->modo; @endphp
            <span class="modo-dot {{ $modo === 'entrada' ? 'entrada' : ($modo === 'salida' ? 'salida' : 'mixto') }}"></span>
            <i class="bi bi-geo-alt"></i> {{ $turno->planta?->nombre ?? 'N/A' }} ·
            @if($modo === 'entrada') ENTRADAS
            @elseif($modo === 'salida') SALIDAS
            @else MIXTO
            @endif
        </div>
    </div>

    <input type="text" id="scanner-input" autocomplete="off">

    <div id="screen-welcome" class="kiosko-screen active">
        <div class="scan-frame">
            <span class="scan-corner tl"></span>
            <span class="scan-corner tr"></span>
            <span class="scan-corner bl"></span>
            <span class="scan-corner br"></span>
            <i class="bi bi-qr-code-scan kiosko-icon"></i>
            <div class="scan-line"></div>
        </div>

        @if(file_exists(public_path('logo-empresa.png')))
            <img src="{{ asset('logo-empresa.png') }}" alt="Fruitex de México" class="kiosko-brand-logo">
        @endif
        <div class="kiosko-eyebrow" style="color:#fff;">Fruitex de México</div>
        <h1 class="kiosko-title" style="color:#fff;">SCA-QR</h1>
        <p class="kiosko-subtitle" style="color:#fff; margin-bottom: 0.5rem;">Acerque su credencial al escáner</p>

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

    <div id="screen-loading" class="kiosko-screen">
        <div class="kiosko-spinner"></div>
        <h3 class="kiosko-subtitle" style="color:#fff;">Procesando...</h3>
    </div>

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

    <div id="screen-denied" class="kiosko-screen screen-full bg-danger-full">
        <div class="icon-badge" style="background: rgba(220,53,69,0.25);">
            <i class="bi bi-x-lg kiosko-icon" style="color:#fff;"></i>
        </div>
        <h2 class="kiosko-title">ACCESO DENEGADO</h2>
        <p id="denied-msg" class="kiosko-message"></p>
    </div>

    <div id="screen-waiting" class="kiosko-screen screen-full bg-warning-full">
        <div class="icon-badge" style="background: rgba(255,193,7,0.25);">
            <i class="bi bi-hourglass-split kiosko-icon" style="color:#fff;"></i>
        </div>
        <h2 class="kiosko-title">ESPERE AQUÍ</h2>
        <p id="waiting-msg" class="kiosko-message">Espere al guardia para autorizar su acceso.</p>
        <div class="kiosko-spinner" style="margin-top: 1.5rem; border-color: rgba(255,255,255,0.3); border-top-color: #fff;"></div>
    </div>

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

    <div id="screen-closed" class="kiosko-screen screen-full bg-info-full">
        <div class="icon-badge" style="background: rgba(33,150,243,0.25);">
            <i class="bi bi-lock-fill kiosko-icon" style="color:#fff;"></i>
        </div>
        <h2 class="kiosko-title">KIOSKO DESACTIVADO</h2>
        <p class="kiosko-message">El guardia ha cerrado sesión. Inicie sesión de nuevo.</p>
        <p class="kiosko-subtitle" style="margin-top: 1rem; opacity: 0.7; font-size: clamp(0.8rem, 2vw, 1rem);">Redirigiendo al login...</p>
    </div>

    <div class="kiosko-brand-footer">Fruitex de México &middot; Sistema SCA-QR</div>
</div>

<div class="camera-floating" id="camera-view">
    <div class="camera-floating-header">
        <span class="camera-rec-dot"></span> Escaneando QR
    </div>
    <video id="video" playsinline></video>
    <div class="camera-scan-line"></div>
    <button class="camera-close" onclick="toggleCamera()"><i class="bi bi-x"></i></button>
    {{-- V10.3.9-fix: tip de distancia — las webcams de foco fijo (Acteck WM20,
         Logitech 720p, EMEET...) no enfocan a menos de ~30-40 cm: el QR se
         sostiene a ~40 cm y se acerca con el ZOOM, no pegándolo al lente --}}
    <div id="camera-hint" style="position:absolute; top:34px; left:50%; transform:translateX(-50%); background:rgba(0,0,0,0.75); color:#ffd166; padding:3px 10px; border-radius:8px; font-size:0.68rem; white-space:nowrap; pointer-events:none; opacity:1; transition:opacity 0.8s; z-index:2;">
        <i class="bi bi-info-circle-fill"></i> QR a ~40 cm · acerque con el zoom
    </div>
    {{-- V10.3.8-fix: selector de cámara + zoom para leer QR con webcam externa.
         V10.3.10-fix: constraints refinadas (30 fps máx., 16:9, resizeMode 'none'
         = modo nativo sin reescalado por software), sin facingMode (no
         determinista en PC con varias webcams USB), autoenfoque solo si la
         cámara lo declara (badge "Foco fijo" vs "AF"), detector QR nativo
         BarcodeDetector opcional con jsQR de respaldo, lectura por etapas
         (media resolución → región central 78% → nativa → rescate) y
         preprocesado de contraste para imagen lavada/oscura --}}
    <div class="camera-controls">
        <div class="camera-controls-row">
            <select id="camera-select" title="Cámara activa">
                <option value="">Cámaras…</option>
            </select>
            <span class="camera-res" id="camera-res" title="Resolución real de captura"></span>
        </div>
        <div class="camera-controls-row">
            <i class="bi bi-zoom-in" style="color:#fff; font-size:0.8rem;"></i>
            <input type="range" id="camera-zoom" min="1" max="5" step="0.1" value="1" title="Acercar el QR (zoom 1×-5×)">
            <span class="camera-zoom-label" id="camera-zoom-label">1.0×</span>
        </div>
    </div>
</div>

<div class="kiosko-modal" id="exitModal">
    <div class="kiosko-modal-content">
        <div class="modal-icon-circle">
            <i class="bi bi-lock-fill"></i>
        </div>
        <h4 style="font-weight: 800; font-size: 1.3rem; margin-bottom: 0.5rem; color: #333;">
            Salir del Kiosko
        </h4>
        <p style="color: #666; font-size: 1rem; margin-bottom: 1.2rem;">Ingrese sus credenciales de guardia:</p>
        <form action="{{ route('kiosko.salir', $turno->id_turno_guardia) }}" method="POST">
            @csrf
            <div class="mb-2 text-start">
                <label class="form-label small fw-bold text-muted">Usuario</label>
                <input type="text" name="username" class="form-control" placeholder="G-0001" required autofocus>
            </div>
            <div class="mb-3 text-start">
                <label class="form-label small fw-bold text-muted">Contraseña</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            @if($errors->any())
                <div class="alert alert-danger py-2 small mb-3">
                    @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif
            <div class="btn-group-container" style="max-width: 100%;">
                <button type="submit" class="kiosko-btn" style="background: #dc3545; color: #fff; box-shadow: 0 8px 20px rgba(220,53,69,0.3);">
                    <i class="bi bi-power"></i> Cerrar Kiosko
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
    const TURNO_ID = '{{ $turno->id_turno_guardia }}';
    const CSRF_TOKEN = '{{ csrf_token() }}';
    let pollingInterval = null;
    let turnoStatusInterval = null;
    let cameraStream = null;
    let currentBitacoraId = null;
    let isProcessing = false;
    let lastCodeProcessed = null;
    let lastCodeTime = 0;
    const COOLDOWN_MS = 3000;
    // V10.3.8-fix: estado del módulo de cámara en HD
    let cameraZoom = 1;      // zoom digital aplicado al decodificar (recorte central)
    let cameraHwZoom = null; // {min,max} cuando la cámara tiene zoom por hardware
    let cameraLoop = 0;      // token del bucle de escaneo vigente (evita bucles duplicados)
    let hintTimer = null;    // V10.3.9-fix: timer del tip de distancia (se auto-oculta)
    let cameraFocus = 'fijo';   // V10.3.10-fix: 'af' | 'single' | 'fijo' según lo que declara la cámara
    let detectorNativo = null;  // V10.3.10-fix: BarcodeDetector del navegador (opcional; jsQR respalda)
    const CAM_PREFS = 'scaqr_cam_kiosko'; // preferencias: {deviceId, zoom}

    // === BUGFIX: KIOSKO DESACTIVADO en Búsqueda Manual ===
    // Contador de fallos consecutivos del polling de turno. Solo cerramos
    // el kiosko si recibimos N respuestas confirmadas con activo=false.
    // Así un fallo transitorio (network blip, 401 por cookie, etc.) no
    // secuestra la pantalla mientras el guardia está trabajando.
    let turnoFailCount = 0;
    const TURNO_FAIL_THRESHOLD = 3;

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

        // ===== Polling de turno activo (cada 5s) =====
    // BUGFIX: NO cerramos el kiosko mientras se procesa un código (escaneo o
    // búsqueda manual), porque la respuesta del /api/scan puede tardar y un
    // fallo transitorio del polling no debe secuestrar la pantalla.
    // Tampoco cerramos con un único activo=false: exigimos N fallos
    // consecutivos confirmados para evitar cierres por network blips.
    function extraerActivo(data) {
        // Tolerante a ambos formatos de respuesta del backend:
        //   {activo: true}                          (formato directo)
        //   {success: true, data: {activo: true}}   (wrapper Laravel)
        if (!data) return null;
        if (typeof data.activo === 'boolean') return data.activo;
        if (data.data && typeof data.data.activo === 'boolean') return data.data.activo;
        if (data.success === false) return false; // error explícito del backend
        return null; // no se puede determinar — no cuenta como fallo
    }

    async function checkTurnoStatus() {
        // Si estamos procesando un código (escaneo o manual), saltar esta
        // vuelta: el polling se reanudará cuando volvamos a welcome.
        if (isProcessing) return;

        try {
            const res = await fetch(`/api/kiosko/${TURNO_ID}/status`, { cache: 'no-store' });
            if (!res.ok) {
                // HTTP error (401, 403, 500, etc.) — contar como fallo leve.
                console.warn('checkTurnoStatus HTTP', res.status);
                turnoFailCount++;
                return;
            }
            const data = await res.json();
            const activo = extraerActivo(data);

            if (activo === true) {
                turnoFailCount = 0;
                return;
            }
            if (activo === false) {
                turnoFailCount++;
                console.warn('Turno inactivo consecutivo:', turnoFailCount, '/', TURNO_FAIL_THRESHOLD);
                if (turnoFailCount >= TURNO_FAIL_THRESHOLD) {
                    clearInterval(turnoStatusInterval);
                    clearInterval(pollingInterval);
                    switchScreen('screen-closed');
                    setTimeout(() => { window.location.href = '{{ route("kiosko.activar.form") }}'; }, 5000);
                }
                return;
            }
            // activo === null: respuesta con formato desconocido, no contar.
            console.warn('Respuesta de status con formato no reconocido:', data);
        } catch(e) {
            console.error('Error verificando turno:', e);
            // No cerramos por un error de red: solo incrementamos el contador.
            turnoFailCount++;
        }
    }
    turnoStatusInterval = setInterval(checkTurnoStatus, 5000);

    document.addEventListener('click', (e) => {
        if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA' && e.target.tagName !== 'BUTTON' && !e.target.closest('.camera-close')) {
            const modalVisible = document.querySelector('.kiosko-modal.show');
            if (!modalVisible && !isProcessing && document.getElementById('screen-welcome').classList.contains('active')) {
                scannerInput.focus();
            }
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

    scannerInput.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            const codigo = this.value.trim();
            this.value = '';
            if (codigo) procesarCodigo(codigo, 'kiosco');
        }
    });

    // ============================================================
    // CÁMARA DEL KIOSKO
    // V10.3.8-fix: captura en HD (ideal 1920×1080 — antes el navegador
    // entregaba 640×480 y el QR se veía borroso/ilegible con webcam
    // externa), selector de cámara (webcam externa vs integrada),
    // autoenfoque continuo cuando la cámara lo soporta, zoom 1×-4×
    // (óptico si existe, digital por recorte central si no) y bucle
    // limitado a ~10 fps para no saturar el CPU con fotogramas HD.
    // V10.3.9-fix: además unsharp de rescate para cámaras de foco fijo
    // (Acteck WM20, Logitech 720p, EMEET 1080p...), tip de distancia al
    // abrir la cámara y zoom extendido a 1×-5×.
    // V10.3.10-fix: selección determinista (fuera facingMode), constraints
    // refinadas (30 fps máx., 16:9, resizeMode 'none' = modo nativo sin
    // reescalado blando), autoenfoque solo si la cámara lo DECLARA en
    // getCapabilities (el badge indica "Foco fijo" vs "AF"), detector QR
    // nativo BarcodeDetector opcional (jsQR de respaldo), 2ª pasada de
    // región central 78%, preprocesado de contraste en el rescate,
    // inversionAttempts 'attemptBoth' y 450 ms de estabilización inicial.
    // ============================================================
    function leerPrefsCamara() {
        try { return JSON.parse(localStorage.getItem(CAM_PREFS)) || {}; }
        catch (e) { return {}; }
    }

    function guardarPrefsCamara(parcial) {
        try {
            const prefs = Object.assign(leerPrefsCamara(), parcial);
            localStorage.setItem(CAM_PREFS, JSON.stringify(prefs));
        } catch (e) { /* modo privado: sin persistencia, no es error */ }
    }

    function marcarErrorCamara(mensaje) {
        // El kiosko no tiene modales de error propios: el estado se muestra
        // en el propio selector de cámara y la ventana se puede cerrar con la X.
        const select = document.getElementById('camera-select');
        if (select) select.innerHTML = '<option value="">' + mensaje + '</option>';
        const res = document.getElementById('camera-res');
        if (res) res.textContent = '';
        console.error('Kiosko cámara:', mensaje);
    }

    // V10.3.10-fix: detector QR nativo del navegador (Chrome/Edge lo traen,
    // acelerado por hardware). Opcional: si el navegador no lo soporta o no
    // declara qr_code devuelve null y jsQR sigue siendo el lector de respaldo.
    async function prepararDetectorNativo() {
        try {
            if (!('BarcodeDetector' in window)) return null;
            const formatos = await window.BarcodeDetector.getSupportedFormats();
            if (!formatos || formatos.indexOf('qr_code') === -1) return null;
            return new window.BarcodeDetector({ formats: ['qr_code'] });
        } catch (e) {
            return null;
        }
    }

    // V10.3.10-fix: badge con la resolución real + estado de enfoque declarado
    // por la cámara. Ej.: "1920×1080 · AF" o "1280×720 · Foco fijo" — se
    // comprueba de inmediato qué entrega realmente cada webcam conectada.
    function actualizarBadgeResolucion(w, h) {
        const resEl = document.getElementById('camera-res');
        if (!resEl) return;
        if (!w || !h) { resEl.textContent = ''; return; }
        const foco = cameraFocus === 'af' ? 'AF'
            : (cameraFocus === 'single' ? 'AF (1 disparo)' : 'Foco fijo');
        resEl.textContent = w + '×' + h + ' · ' + foco;
        resEl.title = cameraFocus === 'fijo'
            ? 'Cámara de foco fijo: sostener el QR a ~40 cm y acercarlo con el zoom'
            : 'Cámara con autoenfoque';
    }

    // V10.3.10-fix: preprocesado de rescate — escala de grises + contraste
    // automático. Algunas webcams entregan imagen "lavada" (reflejo de luz
    // tras el QR) u oscura (vestíbulo de noche): se muestrea la luminancia
    // mínima/máxima del fotograma y se estira a 0-255 para separar los
    // módulos del QR. Devuelve false si no hay nada que estirar.
    function mejorarContraste(img) {
        const d = img.data;
        const n = d.length >> 2;
        let mn = 255, mx = 0;
        for (let i = 0; i < n; i += 17) { // muestreo: 1 de cada 17 píxeles
            const j = i << 2;
            const g = (d[j] * 77 + d[j + 1] * 150 + d[j + 2] * 29) >> 8;
            if (g < mn) mn = g;
            if (g > mx) mx = g;
        }
        let base = mn, rango = mx - mn;
        if (rango < 60) { // imagen muy plana: estiramiento más agresivo
            base = Math.max(0, mn - 25);
            rango = mx - base;
        }
        if (rango < 1) return false;
        const esc = 255 / rango;
        for (let i = 0; i < n; i++) {
            const j = i << 2;
            let g = (d[j] * 77 + d[j + 1] * 150 + d[j + 2] * 29) >> 8;
            g = (g - base) * esc;
            if (g < 0) g = 0; else if (g > 255) g = 255;
            d[j] = d[j + 1] = d[j + 2] = g;
        }
        return true;
    }

    async function abrirCamara(deviceId) {
        // Detener stream previo (cambio de cámara) sin cerrar la ventana
        if (cameraStream) {
            cameraStream.getTracks().forEach(t => t.stop());
            cameraStream = null;
        }
        const video = document.getElementById('video');
        // V10.3.10-fix: constraints refinadas. HD ideal (una cámara 720p sigue
        // funcionando), 30 fps máx. (menos CPU y exposición más larga en luz
        // baja), 16:9 ideal y resizeMode 'none': el navegador entrega un modo
        // NATIVO de la cámara en lugar de un modo reescalado por software
        // (ese reescalado emborrona el QR). Se eliminó facingMode
        // 'environment': en una PC con varias webcams USB no es determinista
        // y podía abrir una cámara distinta de la deseada.
        const hd = {
            width: { ideal: 1920 },
            height: { ideal: 1080 },
            frameRate: { max: 30 },
            aspectRatio: { ideal: 16 / 9 },
            resizeMode: 'none'
        };
        const pedir = (videoConstraints) =>
            navigator.mediaDevices.getUserMedia({ audio: false, video: videoConstraints });
        try {
            cameraStream = deviceId
                ? await pedir(Object.assign({ deviceId: { exact: deviceId } }, hd))
                : await pedir(hd); // V10.3.10-fix: cámara predeterminada, sin facingMode
        } catch (e) {
            // Cámara guardada ya desconectada: reintentar con la predeterminada
            if (deviceId) cameraStream = await pedir(hd); // V10.3.10-fix: sin facingMode
            else throw e;
        }
        video.srcObject = cameraStream;
        try { await video.play(); } catch (e) { /* autoplay bloqueado: el usuario ya dio el clic */ }

        const track = cameraStream.getVideoTracks()[0];

        // V10.3.10-fix: capacidades REALES declaradas por la cámara (una sola
        // consulta): zoom por hardware y modo de enfoque. Una webcam de foco
        // fijo no declara focusMode y ningún software la convierte en
        // autofocus: solo se pide lo que existe y el badge lo indica, para
        // saber qué esperar de cada unidad (Acteck WM20 / Logitech 720p /
        // EMEET 1080p = foco fijo; C920/C922/Brio = AF).
        cameraHwZoom = null;
        cameraFocus = 'fijo';
        try {
            const caps = track.getCapabilities ? track.getCapabilities() : {};
            if (caps.zoom && typeof caps.zoom.min === 'number' && typeof caps.zoom.max === 'number') {
                cameraHwZoom = { min: caps.zoom.min, max: caps.zoom.max };
            }
            if (caps.focusMode && caps.focusMode.indexOf('continuous') !== -1) {
                await track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] });
                cameraFocus = 'af';
            } else if (caps.focusMode && caps.focusMode.indexOf('single-shot') !== -1) {
                await track.applyConstraints({ advanced: [{ focusMode: 'single-shot' }] });
                cameraFocus = 'single';
            }
        } catch (e) {}

        // V10.3.10-fix: detector QR nativo del navegador (si está disponible)
        detectorNativo = await prepararDetectorNativo();

        // Badge con la resolución real entregada + estado de enfoque
        const settings = track.getSettings ? track.getSettings() : {};
        actualizarBadgeResolucion(settings.width, settings.height);

        // Restaurar el último zoom usado (óptico o digital según la cámara)
        const zoomGuardado = Number(leerPrefsCamara().zoom) || 1;
        aplicarZoom(zoomGuardado, false);

        // V10.3.9-fix: tip de distancia visible 8 s al abrir la cámara:
        // con foco fijo el QR se acerca con el ZOOM, no pegándolo al lente
        const hint = document.getElementById('camera-hint');
        if (hint) {
            hint.style.opacity = '1';
            clearTimeout(hintTimer);
            hintTimer = setTimeout(() => { hint.style.opacity = '0'; }, 8000);
        }

        await cargarCamaras(settings.deviceId || deviceId);
        iniciarBucle();
    }

    async function cargarCamaras(deviceIdActiva) {
        const select = document.getElementById('camera-select');
        if (!select) return;
        try {
            const cams = (await navigator.mediaDevices.enumerateDevices()).filter(d => d.kind === 'videoinput');
            select.innerHTML = '';
            cams.forEach((c, i) => {
                const opt = document.createElement('option');
                opt.value = c.deviceId;
                opt.textContent = c.label && c.label.trim() ? c.label : ('Cámara ' + (i + 1));
                if (opt.textContent.length > 30) opt.textContent = opt.textContent.slice(0, 29) + '…';
                select.appendChild(opt);
            });
            const activa = deviceIdActiva || (cams[0] ? cams[0].deviceId : '');
            select.value = activa;
            if (activa) guardarPrefsCamara({ deviceId: activa });
        } catch (e) {
            select.innerHTML = '<option value="">Cámaras no disponibles</option>';
        }
    }

    function aplicarZoom(zoom, guardar = true) {
        zoom = Math.min(5, Math.max(1, Number(zoom) || 1)); // V10.3.9-fix: zoom hasta 5×
        const video = document.getElementById('video');
        const slider = document.getElementById('camera-zoom');
        const label = document.getElementById('camera-zoom-label');
        if (slider) slider.value = zoom;
        if (label) label.textContent = zoom.toFixed(1) + '×';

        const track = cameraStream ? cameraStream.getVideoTracks()[0] : null;
        if (cameraHwZoom && track) {
            // Zoom óptico: el propio sensor acerca → se decodifica el cuadro completo
            const min = cameraHwZoom.min, max = cameraHwZoom.max;
            const hw = min + ((zoom - 1) / 4) * (max - min); // V10.3.9-fix: slider 1×-5× → rango de la cámara
            track.applyConstraints({ advanced: [{ zoom: hw }] }).catch(() => {});
            cameraZoom = 1;
            video.style.transform = '';
        } else {
            // Zoom digital: previsualización escalada + recorte central al decodificar
            cameraZoom = zoom;
            video.style.transform = zoom > 1 ? 'scale(' + zoom + ')' : '';
        }
        if (guardar) guardarPrefsCamara({ zoom: zoom });
    }

    // V10.3.9-fix: máscara de enfoque (unsharp) en tiempo real.
    // Las webcams de foco fijo (Acteck WM20, Logitech 720p tipo C270,
    // EMEET 1080p, etc.) no pueden enfocar a menos de ~30-40 cm: a esa
    // distancia el QR sale ligeramente desenfocado AUNQUE la captura sea
    // HD. Este kernel 3×3 de realce recupera parte de los bordes de los
    // módulos del QR y le da a jsQR una segunda oportunidad sobre el
    // mismo fotograma. Se muta img.data in place leyendo de una copia
    // para no contaminar los vecinos ya filtrados.
    function enfocarImageData(img, w, h) {
        const s = img.data;
        const orig = new Uint8ClampedArray(s);
        const fila = w * 4;
        for (let y = 1; y < h - 1; y++) {
            for (let x = 1; x < w - 1; x++) {
                const i = y * fila + x * 4;
                for (let c = 0; c < 3; c++) {
                    const v = 5 * orig[i + c]
                        - orig[i - 4 + c] - orig[i + 4 + c]
                        - orig[i - fila + c] - orig[i + fila + c];
                    s[i + c] = v < 0 ? 0 : (v > 255 ? 255 : v);
                }
            }
        }
    }

    function iniciarBucle() {
        const video = document.getElementById('video');
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d', { willReadFrequently: true });
        const miLoop = ++cameraLoop;

        // V10.3.9-fix: parámetro de rescate. V10.3.10-fix: ahora preprocesa
        // contraste automático (imagen lavada/oscura) + unsharp (foco fijo
        // levemente desenfocado), en ese orden, antes de jsQR.
        const decodificar = (w, h, sx, sy, sw, sh, rescatar) => {
            canvas.width = w;
            canvas.height = h;
            context.drawImage(video, sx, sy, sw, sh, 0, 0, w, h);
            const img = context.getImageData(0, 0, w, h);
            if (rescatar) {
                mejorarContraste(img);
                enfocarImageData(img, w, h);
            }
            // V10.3.10-fix: 'attemptBoth' (default de jsQR, ahora explícito):
            // tolera QR con contraste invertido (p. ej. pantalla en modo oscuro)
            return jsQR(img.data, w, h, { inversionAttempts: 'attemptBoth' });
        };

        let intento = 0; // V10.3.9-fix: contador de ticks (el rescate corre 1 de cada 2)
        // V10.3.10-fix: tick async: BarcodeDetector.detect es una promesa. El
        // siguiente tick se programa AL TERMINAR el trabajo para no encimar
        // detecciones sobre fotogramas a medias.
        const tick = async () => {
            if (!cameraStream || miLoop !== cameraLoop) return; // bucle reemplazado o cámara cerrada
            if (!isProcessing && video.readyState === video.HAVE_ENOUGH_DATA) {
                intento++;
                try {
                    let code = null;
                    // V10.3.10-fix: 1) detector QR NATIVO del navegador cuando
                    // existe (más rápido y robusto; Chrome/Edge en Windows lo
                    // traen acelerado). Solo sin zoom: el <video> muestra el
                    // encuadre completo y es exactamente lo que debe leerse.
                    if (detectorNativo && cameraZoom <= 1) {
                        try {
                            const hallados = await detectorNativo.detect(video);
                            if (hallados && hallados.length) code = { data: hallados[0].rawValue };
                        } catch (e) { /* detector nativo falló en este fotograma: sigue jsQR */ }
                    }
                    // 2) jsQR por etapas (respaldo universal: Firefox/Safari/Linux)
                    if (!code) {
                        if (cameraZoom > 1) {
                            // Con zoom digital se decodifica SOLO la región ampliada
                            // (el mismo encuadre que se ve en la previsualización).
                            const z = cameraZoom;
                            const cw = Math.round(video.videoWidth / z), ch = Math.round(video.videoHeight / z);
                            const sx = (video.videoWidth - cw) / 2, sy = (video.videoHeight - ch) / 2;
                            code = decodificar(cw, ch, sx, sy, cw, ch);
                            // Rescate con preprocesado (QR leve fuera de foco o lavado)
                            if (!code && intento % 2 === 0) code = decodificar(cw, ch, sx, sy, cw, ch, true);
                        } else {
                            // 1ª pasada a media resolución: QR cercano (grande) sin cargar el CPU
                            const w2 = video.videoWidth >> 1, h2 = video.videoHeight >> 1;
                            if (w2 && h2) code = decodificar(w2, h2, 0, 0, video.videoWidth, video.videoHeight);
                            // 2ª pasada: región central 78% a resolución nativa — QR a media
                            // distancia que no llena el cuadro (V10.3.10-fix)
                            if (!code) {
                                const rw = Math.round(video.videoWidth * 0.78), rh = Math.round(video.videoHeight * 0.78);
                                const rx = (video.videoWidth - rw) / 2, ry = (video.videoHeight - rh) / 2;
                                code = decodificar(rw, rh, rx, ry, rw, rh);
                            }
                            // 3ª pasada a resolución nativa completa: QR lejano o pequeño
                            if (!code) code = decodificar(video.videoWidth, video.videoHeight, 0, 0, video.videoWidth, video.videoHeight);
                            // 4ª pasada de rescate con preprocesado (contraste + unsharp,
                            // 1 de cada 2 ticks para no saturar el CPU): foco fijo
                            // levemente desenfocado o imagen lavada/oscura
                            if (!code && intento % 2 === 0) code = decodificar(video.videoWidth, video.videoHeight, 0, 0, video.videoWidth, video.videoHeight, true);
                        }
                    }
                    if (code) procesarCodigo(code.data, 'kiosco');
                } catch (e) { /* fotograma corrupto: se reintenta en el siguiente tick */ }
            }
            if (cameraStream && miLoop === cameraLoop) setTimeout(tick, 80); // ~10 fps
        };
        // V10.3.10-fix: 450 ms de estabilización inicial (antes 300): margen para
        // que autofocus/exposición/balance de blancos se asienten antes de leer
        setTimeout(tick, 450);
    }

    async function toggleCamera() {
        const view = document.getElementById('camera-view');
        if (cameraStream) {
            cameraStream.getTracks().forEach(track => track.stop());
            cameraStream = null;
            view.style.display = 'none';
            return;
        }
        view.style.display = 'block';
        try {
            await abrirCamara(leerPrefsCamara().deviceId);
        } catch (e) {
            marcarErrorCamara('Sin acceso a la cámara');
            cameraStream = null;
        }
    }

    // V10.3.8-fix: controles de la ventana de cámara (selector + zoom)
    document.getElementById('camera-select').addEventListener('change', async function() {
        guardarPrefsCamara({ deviceId: this.value });
        if (!cameraStream) return; // cámara cerrada: solo se guarda la preferencia
        try {
            await abrirCamara(this.value); // reemplaza stream y bucle sin cerrar la ventana
        } catch (e) {
            marcarErrorCamara('No se pudo abrir la cámara');
            cameraStream = null;
        }
    });
    document.getElementById('camera-zoom').addEventListener('input', function() {
        aplicarZoom(this.value);
    });

    function showManualEntry() {
        switchScreen('screen-manual');
        document.getElementById('manual-input').focus();
    }
    
    function submitManual() {
        const input = document.getElementById('manual-input');
        const val = input.value.trim();
        if (!val) return;
        input.value = ''; // Limpiar inmediatamente para evitar re-envíos
        procesarCodigo(val, 'manual');
    }

    // ===== Procesar código (con lock anti-doble) =====
    async function procesarCodigo(codigo, origen = 'kiosco') {
        codigo = String(codigo).trim();
        if (!codigo || isProcessing) return;

        const ahora = Date.now();
        if (codigo === lastCodeProcessed && (ahora - lastCodeTime) < COOLDOWN_MS) return;

        isProcessing = true;
        lastCodeProcessed = codigo;
        lastCodeTime = ahora;

        switchScreen('screen-loading');
        clearInterval(pollingInterval);
        // BUGFIX: Reiniciamos el contador de fallos de turno. Mientras
        // procesamos un código, checkTurnoStatus() está pausado (retorna
        // temprano si isProcessing === true). Al terminar, arrancamos de 0
        // para no arrastrar un fallo transitorio.
        turnoFailCount = 0;

        try {
            const res = await fetch('/api/scan', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: JSON.stringify({ codigo, origen: origen, turno_id: TURNO_ID })
            });

            // BUGFIX: Si el backend responde no-2xx (401/403/422/500),
            // mostramos el mensaje de error real en vez de "Error." genérico.
            if (!res.ok) {
                let errMsg = 'Error del servidor (' + res.status + ').';
                try {
                    const errData = await res.json();
                    errMsg = errData.error || errData.message || errMsg;
                } catch (_) { /* respuesta no-JSON */ }
                mostrarDenegado(errMsg);
                return;
            }

            const data = await res.json();

            // BUGFIX: Validar respuesta exitosa del backend.
            if (data && data.success === false) {
                mostrarDenegado(data.error || data.message || 'Error.');
                return;
            }

            // BUGFIX: Algunos backends envuelven el payload en {data: {...}}.
            // Desenvolver si viene el wrapper Laravel estándar.
            const payload = (data && data.data && typeof data.data === 'object' && !Array.isArray(data.data))
                ? Object.assign({}, data, data.data)
                : data;

            currentBitacoraId = payload.bitacora_id;



            
            mostrarResultado(payload);
        } catch (error) {
            console.error('Error en /api/scan:', error);
            mostrarDenegado('Error de conexión. Intente de nuevo.');
        } finally {
            setTimeout(() => { isProcessing = false; }, 1500);
        }
    }

    // ===== Mostrar resultado del escaneo =====
    function mostrarResultado(data) {
        if (!data) {
            mostrarDenegado('Respuesta vacía del servidor.');
            return;
        }
        // BUGFIX: Acepta data.autorizado con success !== false (algunos
        // backends no devuelven success:true explícito).
        if ((data.success !== false) && data.autorizado) {
            mostrarExito(data);
        } else if ((data.success !== false) && data.pendiente) {
            // FIX interplantas: el mensaje identifica si es entrada o salida,
            // en vez del genérico "Acceso fuera de horario".
            let msg;
            if (data.tipo_pendiente === 'justificacion') {
                const tipo = (data.tipo_evento === 'salida') ? 'salida' : 'entrada';
                msg = (tipo === 'salida' ? 'Salida' : 'Entrada') + ' fuera de horario. El guardia debe autorizar su ' + tipo + '.';
            } else {
                msg = 'Espere al guardia para revisión.';
            }
            document.getElementById('waiting-msg').textContent = msg;
            switchScreen('screen-waiting');
            iniciarPollingBitacora(data.bitacora_id);
        } else {
            // BUGFIX: Denegado — priorizar message, luego motivo_denegacion,
            // luego error. Así se ve "Requiere Permiso Interplantas…",
            // "Empleado no encontrado", "Credencial expirada", etc.
            const msg = data.message || data.motivo_denegacion || data.error || 'Acceso denegado.';
            mostrarDenegado(msg);
        }
    }

    function mostrarExito(data) {
        document.getElementById('success-title').textContent = data.tipo_evento === 'entrada' ? 'ENTRADA AUTORIZADA' : 'SALIDA REGISTRADA';
        document.getElementById('success-photo').src = data.persona?.foto || 'https://ui-avatars.com/api/?name=OK&background=28a745&color=fff';
        document.getElementById('success-name').textContent = data.persona?.nombre || 'Registrado';
        document.getElementById('success-info').textContent = `${data.persona?.puesto || ''} - ${data.persona?.tipo_empleado || ''}${data.persona?.planta ? ' · ' + data.persona.planta : ''}`;
        document.getElementById('success-msg').textContent = data.message || '';
        if (resetScreenTimer) clearTimeout(resetScreenTimer);
        switchScreen('screen-success');
        resetScreenTimer = setTimeout(resetToWelcome, 5000);
    }

    function mostrarDenegado(msg) {
        document.getElementById('denied-msg').textContent = msg;
        if (resetScreenTimer) clearTimeout(resetScreenTimer);
        switchScreen('screen-denied');
        resetScreenTimer = setTimeout(resetToWelcome, 5000);
    }

    // ===== Polling de bitácora (cada 2s) =====
    // Variable para recordar el último tipo de pendiente mostrado al empleado.
    // Así detectamos cuando cambia de 'justificacion' a 'revision' (o viceversa)
    // y actualizamos el mensaje en la pantalla de espera.
    let ultimoTipoPendienteMostrado = null;

    // Protección contra peticiones de polling simultáneas y respuestas duplicadas
    let pollingRequestInFlight = false;
    let pollingBitacoraResuelto = false;
    let resetScreenTimer = null;

    async function iniciarPollingBitacora(bitacoraId) {
        clearInterval(pollingInterval);
        pollingRequestInFlight = false;
        pollingBitacoraResuelto = false;
        ultimoTipoPendienteMostrado = null;

        pollingInterval = setInterval(async () => {
            // Si ya se resolvió, ignorar
            if (pollingBitacoraResuelto) return;
            // No permitir peticiones simultáneas
            if (pollingRequestInFlight) return;
            pollingRequestInFlight = true;

            try {
                const res = await fetch(`/api/scan/status/${bitacoraId}`, { cache: 'no-store' });
                if (!res.ok) return;
                const data = await res.json();
                const payload = (data && data.data && typeof data.data === 'object' && !Array.isArray(data.data))
                    ? data.data
                    : data;

                // Otra petición pudo resolver mientras esta estaba en vuelo
                if (pollingBitacoraResuelto) return;

                // Bitácora inválida
                if (payload.estado === 'invalido' || (!payload.autorizado && !payload.denegado && !payload.pendiente)) {
                    pollingBitacoraResuelto = true;
                    clearInterval(pollingInterval);
                    mostrarDenegado('Ocurrió un error al validar su acceso. Vuelva a escanear.');
                    return;
                }

                // AUTORIZADO
                if (payload.autorizado) {
                    pollingBitacoraResuelto = true;
                    clearInterval(pollingInterval);
                    mostrarExito({
                        tipo_evento: payload.tipo_evento || 'autorizado',
                        persona: payload.persona || { 
                            nombre: 'Autorizado', puesto: '', tipo_empleado: '', planta: '', 
                            foto: 'https://ui-avatars.com/api/?name=OK&background=28a745&color=fff' 
                        },
                        message: payload.message || 'Acceso autorizado por el guardia.'
                    });
                    return;
                }

                // DENEGADO
                if (payload.denegado) {
                    pollingBitacoraResuelto = true;
                    clearInterval(pollingInterval);
                    let msg = 'El guardia denegó el acceso.';
                    if (payload.motivo === 'cerrado_sin_resolver') msg = 'El guardia cerró su turno. Vuelva a escanear.';
                    else if (payload.motivo === 'rechazado_guardia') msg = 'El guardia rechazó su acceso.';
                    else if (payload.motivo === 'pendiente_sin_resolver') msg = 'Su acceso anterior quedó pendiente. Vuelva a escanear.';
                    mostrarDenegado(msg);
                    return;
                }

                // PENDIENTE
                if (payload.pendiente) {
                    const tipoActual = payload.tipo_pendiente;
                    if (ultimoTipoPendienteMostrado !== tipoActual) {
                        let msg;
                        if (tipoActual === 'justificacion') {
                            const tipo = (payload.tipo_evento === 'salida') ? 'salida' : 'entrada';
                            msg = (tipo === 'salida' ? 'Salida' : 'Entrada') + ' fuera de horario. El guardia debe autorizar su ' + tipo + '.';
                        } else {
                            msg = 'Justificación completada. Espere aquí, el guardia debe realizar revisión.';
                        }
                        document.getElementById('waiting-msg').textContent = msg;
                        ultimoTipoPendienteMostrado = tipoActual;
                    }
                }
            } catch(e) {
                console.error('Error polling:', e);
            } finally {
                pollingRequestInFlight = false;
            }
        }, 2000);
    }

    function switchScreen(id) {
        document.querySelectorAll('.kiosko-screen').forEach(s => s.classList.remove('active'));
        document.getElementById(id).classList.add('active');
    }

    function resetToWelcome() {
        clearInterval(pollingInterval);
        pollingRequestInFlight = false;
        pollingBitacoraResuelto = false;
        ultimoTipoPendienteMostrado = null;
        if (resetScreenTimer) {
            clearTimeout(resetScreenTimer);
            resetScreenTimer = null;
        }
        switchScreen('screen-welcome');
        document.getElementById('manual-input').value = '';
        isProcessing = false;
        turnoFailCount = 0;
        scannerInput.focus();
    }

    function showExitModal() {
        document.getElementById('exitModal').classList.add('show');
        setTimeout(() => document.querySelector('#exitModal input')?.focus(), 100);
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