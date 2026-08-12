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

    .camera-floating { position: fixed; bottom: 18px; right: 18px; width: 210px; z-index: 9998; border-radius: 16px; overflow: hidden; box-shadow: 0 14px 34px rgba(0,0,0,0.5); display: none; border: 1px solid rgba(255,255,255,0.25); background: #000; }
    .camera-floating-header { display: flex; align-items: center; gap: 0.4rem; padding: 6px 10px; background: rgba(0,0,0,0.65); color: #fff; font-size: 0.68rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }
    .camera-rec-dot { width: 7px; height: 7px; border-radius: 50%; background: #ff5252; animation: recBlink 1.2s ease-in-out infinite; }
    @keyframes recBlink { 0%, 100% { opacity: 1; } 50% { opacity: 0.25; } }
    .camera-floating video { width: 100%; height: 150px; object-fit: cover; display: block; background: #000; }
    .camera-scan-line { position: absolute; top: 30px; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, #fff, transparent); animation: scanLine 2s linear infinite; }
    @keyframes scanLine { 0% { top: 30px; } 50% { top: 174px; } 100% { top: 30px; } }
    .camera-close { position: absolute; top: 5px; right: 6px; background: rgba(255,255,255,0.15); color: #fff; border: none; border-radius: 50%; width: 22px; height: 22px; font-size: 0.8rem; cursor: pointer; display: flex; align-items: center; justify-content: center; }

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

        @if(file_exists(public_path('logo-fruitex.png')))
            <img src="{{ asset('logo-fruitex.png') }}" alt="Fruitex de México" class="kiosko-brand-logo">
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
            if (!isProcessing && video.readyState === video.HAVE_ENOUGH_DATA) {
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                context.drawImage(video, 0, 0, canvas.width, canvas.height);
                const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
                const code = jsQR(imageData.data, imageData.width, imageData.height);
                if (code) procesarCodigo(code.data, 'kiosco');
            }
            requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    }

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
            const msg = data.tipo_pendiente === 'justificacion'
                ? 'Acceso fuera de horario. El guardia debe autorizar su acceso.'
                : 'Espere al guardia para revisión.';
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
        switchScreen('screen-success');
        setTimeout(resetToWelcome, 5000);
    }

    function mostrarDenegado(msg) {
        document.getElementById('denied-msg').textContent = msg;
        switchScreen('screen-denied');
        setTimeout(resetToWelcome, 5000);
    }

    // ===== Polling de bitácora (cada 2s) =====
    async function iniciarPollingBitacora(bitacoraId) {
        pollingInterval = setInterval(async () => {
            try {
                const res = await fetch(`/api/scan/status/${bitacoraId}`, { cache: 'no-store' });
                if (!res.ok) return; // esperar siguiente vuelta
                const data = await res.json();
                // BUGFIX: Tolerante al wrapper {data: {...}} de Laravel.
                const payload = (data && data.data && typeof data.data === 'object' && !Array.isArray(data.data))
                    ? data.data
                    : data;
                if (payload.autorizado) {
                    clearInterval(pollingInterval);
                    mostrarExito({
                        tipo_evento: 'autorizado',
                        persona: { nombre: 'Autorizado', puesto: '', tipo_empleado: '', planta: '', foto: 'https://ui-avatars.com/api/?name=OK&background=28a745&color=fff' },
                        message: 'Acceso autorizado por el guardia.'
                    });
                    setTimeout(resetToWelcome, 5000);
                } else if (payload.denegado) {
                    clearInterval(pollingInterval);
                    mostrarDenegado('El guardia denegó el acceso.');
                }
            } catch(e) { console.error('Error polling:', e); }
        }, 2000);
    }

    function switchScreen(id) {
        document.querySelectorAll('.kiosko-screen').forEach(s => s.classList.remove('active'));
        document.getElementById(id).classList.add('active');
    }

    function resetToWelcome() {
        switchScreen('screen-welcome');
        document.getElementById('manual-input').value = '';
        isProcessing = false;
        clearInterval(pollingInterval);
        // BUGFIX: Al volver a welcome, reiniciamos el contador de fallos de
        // turno para que el kiosko continúe operativo tras una operación
        // manual.
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