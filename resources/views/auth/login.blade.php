{{-- resources/views/auth/login.blade.php --}}
{{--
    CAMBIOS V4 (solo lógica, el diseño visual se conserva 100%):
      - Eliminado el MODAL MODO KIOSKO con PIN (ya no se usa; el kiosko se
        activa desde /kiosko/activar con login de guardia).
      - Eliminado el JS que abría el modal si había kiosko_error en sesión.
      - Agregado un enlace "Activar Kiosko" que redirige a /kiosko/activar,
        usando la clase .login-kiosko-btn que YA EXISTÍA en tu CSS.
      - Todo el CSS, los SVGs (mango, manzana, piña) y la estructura visual
        se conservan exactamente igual.
--}}
@extends('layouts.app')

@section('title', 'Iniciar Sesión')

@section('content')
<style>
    :root {
        --l-green-1: #0d3311;
        --l-green-2: #1b5e20;
        --l-green-3: #2e7d32;
    }

    .login-wrap {
        min-height: 100vh; width: 100%; display: flex; flex-wrap: wrap;
        font-family: 'Poppins', sans-serif;
    }

    /* ===== PANEL IZQUIERDO (marca + ilustración de mango) ===== */
    .login-brand {
        flex: 1 1 460px; min-height: 46vh; position: relative; overflow: hidden;
        display: flex; align-items: center; justify-content: center; padding: 3rem 2.5rem;
        color: #fff; background-color: var(--l-green-2);
        background-image: radial-gradient(circle at 20% 15%, var(--l-green-3) 0%, var(--l-green-2) 45%, var(--l-green-1) 100%);
    }

    /* Ilustraciones de fruta (SVG propio, sin dependencias externas) */
    .login-fruit-art { position: absolute; z-index: 0; pointer-events: none; }
    .login-fruit-art svg { width: 100%; height: 100%; display: block; }
    .login-mango-tr { top: -34px; right: -46px; width: 300px; height: 340px; opacity: 0.97; filter: drop-shadow(0 18px 30px rgba(0,0,0,0.35)); }

    /* Racimo piña + manzana: la manzana se posiciona RELATIVA al racimo (no al panel completo),
       así nunca queda "flotando" fuera de sitio si el panel se angosta. */
    .login-fruit-cluster { position: absolute; z-index: 0; bottom: -18px; left: -14px; width: 224px; height: 232px; pointer-events: none; }
    .login-fruit-cluster svg { width: 100%; height: 100%; display: block; }
    .login-fruit-cluster .cluster-pina { position: absolute; left: 0; bottom: 0; width: 176px; height: 229px; opacity: 0.92; transform: rotate(-10deg); filter: drop-shadow(0 12px 22px rgba(0,0,0,0.35)); }
    .login-fruit-cluster .cluster-manzana { position: absolute; right: 6px; bottom: 46px; width: 86px; height: 95px; opacity: 0.9; transform: rotate(-12deg); filter: drop-shadow(0 8px 16px rgba(0,0,0,0.3)); }

    /* Línea de escaneo — guiño a la cámara QR del kiosco SCA */
    .login-scanline {
        position: absolute; left: 0; right: 0; height: 2px; z-index: 1; pointer-events: none;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.5), transparent);
        animation: loginScan 4.5s linear infinite;
    }
    @keyframes loginScan { 0% { top: 6%; opacity: 0; } 8% { opacity: 1; } 50% { top: 92%; opacity: 1; } 58% { opacity: 0; } 100% { top: 92%; opacity: 0; } }

    .login-brand-inner {
        position: relative; z-index: 2; max-width: 430px; width: 100%;
        background: rgba(8,28,10,0.34); border: 1px solid rgba(255,255,255,0.14);
        backdrop-filter: blur(6px); border-radius: 22px; padding: 2.2rem 2.1rem;
        box-shadow: 0 20px 50px rgba(0,0,0,0.25);
    }

    .login-logo-img { max-height: 48px; max-width: 210px; object-fit: contain; margin-bottom: 1.5rem; filter: drop-shadow(0 6px 16px rgba(0,0,0,0.25)); background: #fff; border-radius: 12px; padding: 7px 12px; }
    .login-logo-fallback {
        display: inline-flex; align-items: center; justify-content: center; width: 56px; height: 56px;
        border-radius: 14px; background: rgba(255,255,255,0.14); border: 1px solid rgba(255,255,255,0.3);
        font-weight: 800; font-size: 1.2rem; margin-bottom: 1.5rem;
    }

    .login-eyebrow { font-size: 0.72rem; font-weight: 700; letter-spacing: 2.5px; text-transform: uppercase; opacity: 0.72; margin-bottom: 0.35rem; }
    .login-title { font-size: clamp(1.9rem, 3.6vw, 2.6rem); font-weight: 800; letter-spacing: -0.5px; margin-bottom: 0.35rem; text-shadow: 0 4px 16px rgba(0,0,0,0.3); }
    .login-subtitle { font-size: 1rem; font-weight: 500; opacity: 0.9; margin-bottom: 1.6rem; }

    .login-features { list-style: none; margin: 0 0 1.8rem 0; padding: 0; display: flex; flex-direction: column; gap: 0.85rem; }
    .login-features li { display: flex; align-items: center; gap: 0.7rem; font-size: 0.9rem; font-weight: 500; opacity: 0.96; }
    .login-features i { color: #81c784; font-size: 1.1rem; flex-shrink: 0; }

    .login-brand-footer { font-size: 0.7rem; opacity: 0.6; line-height: 1.5; }

    /* ===== PANEL DERECHO (formulario) ===== */
    .login-form-side {
        flex: 1 1 420px; display: flex; align-items: center; justify-content: center;
        padding: 3rem 2rem; background: var(--sca-bg, #f0f2f5);
        position: relative; overflow: hidden;
    }
    .login-manzana-tr { top: -22px; right: -26px; width: 150px; height: 165px; opacity: 0.94; transform: rotate(14deg); filter: drop-shadow(0 10px 20px rgba(27,94,32,0.16)); }
    .login-mango-br { bottom: -46px; right: -50px; width: 230px; height: 261px; opacity: 0.9; transform: rotate(18deg); filter: drop-shadow(0 10px 20px rgba(27,94,32,0.14)); }

    .login-form-card {
        position: relative; z-index: 1;
        max-width: 400px; width: 100%; background: var(--sca-card-bg, #fff);
        border: 1px solid var(--sca-border, rgba(0,0,0,0.06)); border-radius: 24px;
        padding: 2.6rem 2.2rem; box-shadow: 0 24px 60px rgba(27,94,32,0.09);
    }

    .login-chip {
        display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.72rem; font-weight: 700;
        letter-spacing: 0.4px; text-transform: uppercase; color: var(--l-green-3);
        background: rgba(46,125,50,0.1); padding: 0.35rem 0.8rem; border-radius: 50px; margin-bottom: 1rem;
    }
    .login-form-card h2 { font-weight: 800; font-size: 1.8rem; color: var(--sca-text, #222); margin-bottom: 0.3rem; letter-spacing: -0.5px; }
    .login-form-sub { color: var(--sca-text-muted, #6c757d); font-size: 0.92rem; margin-bottom: 1.8rem; }

    .login-field { margin-bottom: 1.2rem; }
    .login-field label {
        display: block; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px;
        color: var(--sca-text-muted, #6c757d); margin-bottom: 0.5rem;
    }
    .login-input-group {
        display: flex; align-items: center; border: 1.5px solid var(--sca-input-border, #e0e0e0);
        border-radius: 14px; background: var(--sca-input-bg, #fff); transition: all 0.2s; overflow: hidden;
    }
    .login-input-group:focus-within { border-color: var(--l-green-3); box-shadow: 0 0 0 4px rgba(46,125,50,0.12); }
    .login-input-icon {
        display: flex; align-items: center; justify-content: center; width: 34px; height: 34px;
        margin-left: 0.55rem; border-radius: 9px; background: rgba(46,125,50,0.1); color: var(--l-green-3); flex-shrink: 0;
    }
    .login-input-group input {
        flex: 1; border: none; outline: none; background: transparent; padding: 0.85rem 0.75rem;
        font-size: 1rem; color: var(--sca-text, #222); font-family: inherit; min-width: 0;
    }
    .login-pwd-toggle { padding: 0 1rem; cursor: pointer; display: flex; align-items: center; color: var(--sca-text-muted, #6c757d); }
    .login-pwd-toggle:hover i { color: var(--l-green-3); }

    .login-submit-btn {
        width: 100%; padding: 0.95rem; border: none; border-radius: 14px; font-weight: 700; font-size: 1rem;
        letter-spacing: 0.3px; color: #fff; cursor: pointer; margin-top: 0.5rem;
        background: linear-gradient(135deg, var(--l-green-3), var(--l-green-2));
        box-shadow: 0 10px 24px rgba(46,125,50,0.3); transition: all 0.2s;
        display: flex; align-items: center; justify-content: center; gap: 0.5rem;
    }
    .login-submit-btn:hover { transform: translateY(-2px); box-shadow: 0 14px 30px rgba(46,125,50,0.38); }
    .login-submit-btn:active { transform: translateY(0) scale(0.99); }

    .login-divider { display: flex; align-items: center; gap: 0.8rem; margin: 1.5rem 0; color: var(--sca-text-muted, #6c757d); font-size: 0.75rem; }
    .login-divider::before, .login-divider::after { content: ''; flex: 1; height: 1px; background: var(--sca-border, #e5e5e5); }

    .login-kiosko-btn {
        width: 100%; padding: 0.8rem; border-radius: 14px; border: 1.5px solid var(--sca-border, #e0e0e0);
        background: transparent; color: var(--sca-text-muted, #6c757d); font-weight: 600; font-size: 0.9rem;
        display: flex; align-items: center; justify-content: center; gap: 0.5rem; cursor: pointer; transition: all 0.2s;
        text-decoration: none;
    }
    .login-kiosko-btn:hover { border-color: var(--l-green-3); color: var(--l-green-3); background: rgba(46,125,50,0.06); }

    .login-error {
        display: flex; align-items: center; gap: 0.6rem; padding: 0.8rem 1rem; border-radius: 12px;
        background: rgba(220,53,69,0.1); color: #c0392b; font-size: 0.85rem; margin-bottom: 1.2rem;
    }

    .login-form-footer { text-align: center; margin-top: 1.8rem; font-size: 0.72rem; color: var(--sca-text-muted, #6c757d); }

    @media (max-width: 767px) {
        .login-brand { padding: 2.2rem 1.4rem; min-height: 52vh; }
        .login-form-side { padding: 2rem 1.25rem; }
        .login-form-card { padding: 2rem 1.5rem; border-radius: 20px; }
        .login-manzana-cluster { width: 64px; height: 70px; bottom: 16px; left: auto; right: 14px; opacity: 0.85; }
        .login-pina-bl { width: 130px; height: 169px; }
        .login-manzana-tr { display: none; }
        .login-mango-br { width: 170px; height: 193px; opacity: 0.7; }
    }
</style>

<div class="login-wrap">

    {{-- PANEL DE MARCA --}}
    <div class="login-brand">
        {{-- Ilustración de mango (SVG original, sin bancos de imágenes externos) --}}
        <svg width="0" height="0" style="position:absolute;">
            <defs>
                <symbol id="mango-branch" viewBox="0 0 300 340">
                    <linearGradient id="leafA" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#6fae4a"/>
                        <stop offset="100%" stop-color="#356b2c"/>
                    </linearGradient>
                    <linearGradient id="leafB" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#8bc34a"/>
                        <stop offset="100%" stop-color="#4a7d2e"/>
                    </linearGradient>
                    <linearGradient id="mangoA" x1="0" y1="0" x2="0.3" y2="1">
                        <stop offset="0%" stop-color="#e2572c"/>
                        <stop offset="45%" stop-color="#f2a53c"/>
                        <stop offset="100%" stop-color="#e8d24a"/>
                    </linearGradient>
                    <linearGradient id="mangoB" x1="0" y1="0" x2="0.3" y2="1">
                        <stop offset="0%" stop-color="#f2a53c"/>
                        <stop offset="55%" stop-color="#f5c93f"/>
                        <stop offset="100%" stop-color="#c7d445"/>
                    </linearGradient>

                    <g transform="translate(0,60)">
                        {{-- rama --}}
                        <path d="M150,0 C146,26 140,44 150,64 C160,84 168,96 158,118" fill="none" stroke="#5b3a22" stroke-width="5" stroke-linecap="round"/>

                        {{-- hojas --}}
                        <g stroke="#2f5c26" stroke-width="1" stroke-opacity="0.5">
                            <path d="M150,10 C170,-30 210,-46 238,-40 C220,-8 190,14 152,22 Z" fill="url(#leafA)" transform="rotate(6 150 10)"/>
                            <path d="M150,14 C130,-28 92,-46 62,-38 C82,-4 114,16 150,22 Z" fill="url(#leafB)" transform="rotate(-4 150 14)"/>
                            <path d="M156,30 C182,4 220,-2 244,14 C222,42 190,54 156,46 Z" fill="url(#leafA)" transform="rotate(10 156 30)"/>
                            <path d="M150,34 C120,10 82,8 56,26 C80,52 116,60 150,46 Z" fill="url(#leafB)" transform="rotate(-8 150 34)"/>
                            <path d="M158,54 C186,40 218,44 236,66 C210,86 178,86 156,70 Z" fill="url(#leafA)" transform="rotate(4 158 54)"/>
                        </g>

                        {{-- mango trasero --}}
                        <g transform="translate(196,150) rotate(10)">
                            <path d="M0,-58 C24,-56 36,-24 34,10 C32,48 12,74 -3,75 C-18,74 -36,48 -36,12 C-36,-22 -22,-56 0,-58 Z" fill="url(#mangoB)"/>
                            <ellipse cx="-10" cy="-14" rx="10" ry="20" fill="#ffffff" opacity="0.22" transform="rotate(-16 -10 -14)"/>
                        </g>

                        {{-- mango frente --}}
                        <g transform="translate(140,168) rotate(-6)">
                            <path d="M0,-72 C30,-70 46,-30 44,12 C42,58 16,90 -3,92 C-22,90 -44,58 -44,14 C-46,-28 -28,-70 0,-72 Z" fill="url(#mangoA)"/>
                            <ellipse cx="-14" cy="-20" rx="13" ry="26" fill="#ffffff" opacity="0.26" transform="rotate(-16 -14 -20)"/>
                            <path d="M22,20 C36,42 32,68 6,86 C22,62 26,36 22,20 Z" fill="#8a2c14" opacity="0.16"/>
                        </g>
                    </g>
                </symbol>

                <symbol id="apple-fruit" viewBox="0 0 200 220">
                    <linearGradient id="appleSkin" x1="0.1" y1="0" x2="0.35" y2="1">
                        <stop offset="0%" stop-color="#f2453c"/>
                        <stop offset="40%" stop-color="#d32f2f"/>
                        <stop offset="75%" stop-color="#a31f1f"/>
                        <stop offset="100%" stop-color="#6b1414"/>
                    </linearGradient>
                    <linearGradient id="appleLeaf" x1="0" y1="0" x2="0.2" y2="1">
                        <stop offset="0%" stop-color="#9ccc65"/>
                        <stop offset="100%" stop-color="#3f7d34"/>
                    </linearGradient>

                    {{-- tallo y hoja --}}
                    <path d="M100,40 C104,26 106,18 112,10" fill="none" stroke="#5b3a22" stroke-width="5" stroke-linecap="round"/>
                    <path d="M110,16 C122,2 140,-4 154,2 C146,16 132,24 116,22 Z" fill="url(#appleLeaf)"/>

                    {{-- cuerpo (silueta redonda, sin doble lóbulo) --}}
                    <path d="M100,38 C152,26 190,60 186,110 C182,160 150,202 100,208 C50,202 18,160 14,110 C10,60 48,26 100,38 Z" fill="url(#appleSkin)"/>

                    {{-- brillos --}}
                    <ellipse cx="58" cy="82" rx="20" ry="36" fill="#ffffff" opacity="0.22" transform="rotate(-16 58 82)"/>
                    <ellipse cx="46" cy="60" rx="7" ry="13" fill="#ffffff" opacity="0.4" transform="rotate(-12 46 60)"/>

                    {{-- sombra de volumen --}}
                    <path d="M138,92 C158,124 152,164 118,192 C142,160 146,122 138,92 Z" fill="#450a0a" opacity="0.18"/>
                </symbol>

                <symbol id="pineapple-fruit" viewBox="0 0 200 260">
                    <linearGradient id="pineLeaf" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#7cb342"/>
                        <stop offset="100%" stop-color="#33691e"/>
                    </linearGradient>
                    <linearGradient id="pineBody" x1="0" y1="0" x2="0.25" y2="1">
                        <stop offset="0%" stop-color="#f6d365"/>
                        <stop offset="55%" stop-color="#e8b33d"/>
                        <stop offset="100%" stop-color="#a9701f"/>
                    </linearGradient>
                    <clipPath id="pineClip">
                        <path d="M58,78 C55,60 145,60 142,78 C158,110 160,160 150,200 C148,215 150,230 140,240 C120,252 80,252 60,240 C50,230 52,215 50,200 C40,160 42,110 58,78 Z"/>
                    </clipPath>

                    {{-- corona --}}
                    <g transform="translate(100,72)">
                        <path d="M-6,0 C-11,-42 -5,-84 0,-112 C5,-84 11,-42 6,0 Z" fill="url(#pineLeaf)" transform="rotate(-32)"/>
                        <path d="M-6,0 C-11,-46 -5,-92 0,-124 C5,-92 11,-46 6,0 Z" fill="url(#pineLeaf)" transform="rotate(-14)"/>
                        <path d="M-6,0 C-11,-50 -5,-98 0,-132 C5,-98 11,-50 6,0 Z" fill="url(#pineLeaf)" transform="rotate(4)"/>
                        <path d="M-6,0 C-11,-46 -5,-92 0,-124 C5,-92 11,-46 6,0 Z" fill="url(#pineLeaf)" transform="rotate(20)"/>
                        <path d="M-6,0 C-11,-42 -5,-84 0,-112 C5,-84 11,-42 6,0 Z" fill="url(#pineLeaf)" transform="rotate(36)"/>
                    </g>

                    {{-- cuerpo --}}
                    <path d="M58,78 C55,60 145,60 142,78 C158,110 160,160 150,200 C148,215 150,230 140,240 C120,252 80,252 60,240 C50,230 52,215 50,200 C40,160 42,110 58,78 Z" fill="url(#pineBody)"/>

                    {{-- textura rombos --}}
                    <g clip-path="url(#pineClip)" stroke="#8a5a14" stroke-width="2.5" stroke-opacity="0.45" fill="none">
                        <path d="M20,90 L100,180 M40,80 L130,200 M60,74 L155,210 M85,72 L172,215 M105,74 L185,205"/>
                        <path d="M180,90 L100,180 M160,80 L70,200 M140,74 L45,210 M115,72 L28,215 M95,74 L15,205"/>
                    </g>
                </symbol>
            </defs>
        </svg>

        <div class="login-fruit-art login-mango-tr"><svg><use href="#mango-branch"/></svg></div>
        <div class="login-fruit-cluster">
            <div class="cluster-pina"><svg><use href="#pineapple-fruit"/></svg></div>
            <div class="cluster-manzana"><svg><use href="#apple-fruit"/></svg></div>
        </div>

        <div class="login-scanline"></div>
        <div class="login-brand-inner">
            @if(file_exists(public_path('logo-fruitex.png')))
                <img src="{{ asset('logo-fruitex.png') }}" alt="Fruitex de México" class="login-logo-img">
            @elseif(file_exists(public_path('logo.png')))
                <img src="{{ asset('logo.png') }}" alt="Fruitex de México" class="login-logo-img">
            @else
                <div class="login-logo-fallback">SCA</div>
            @endif

            <div class="login-eyebrow">Fruitex de México</div>
            <h1 class="login-title">SCA-QR</h1>
            <p class="login-subtitle">Sistema de Control de Accesos</p>

            <ul class="login-features">
                <li><i class="bi bi-check-circle-fill"></i> Registro de entradas y salidas en tiempo real</li>
                <li><i class="bi bi-check-circle-fill"></i> Escaneo QR y bitácora automática</li>
                <li><i class="bi bi-check-circle-fill"></i> Control por planta, turno y personal</li>
            </ul>

            <div class="login-brand-footer">&copy; {{ date('Y') }} Fruitex de México · Acceso restringido a personal autorizado</div>
        </div>
    </div>

    {{-- PANEL DE FORMULARIO --}}
    <div class="login-form-side">
        <div class="login-fruit-art login-manzana-tr"><svg><use href="#apple-fruit"/></svg></div>
        <div class="login-fruit-art login-mango-br"><svg><use href="#mango-branch"/></svg></div>

        <div class="login-form-card">
            <div class="login-chip"><i class="bi bi-shield-check"></i> Acceso seguro</div>
            <h2>Bienvenido</h2>
            <p class="login-form-sub">Ingresa tus credenciales para acceder al sistema</p>

            <form method="POST" action="{{ route('login') }}">
                @csrf

                @if (session('success'))
                    <div class="alert alert-success" style="border-radius:12px;">
                        <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
                    </div>
                @endif
                @if ($errors->any())
                    <div class="login-error">
                        <i class="bi bi-exclamation-triangle-fill"></i> {{ $errors->first() }}
                    </div>
                @endif

                <div class="login-field">
                    <label>Usuario</label>
                    <div class="login-input-group">
                        <span class="login-input-icon"><i class="bi bi-person"></i></span>
                        <input type="text" name="username" required autofocus placeholder="Número de empleado">
                    </div>
                </div>

                <div class="login-field">
                    <label>Contraseña</label>
                    <div class="login-input-group">
                        <span class="login-input-icon"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" id="loginPassword" required placeholder="Ingresa tu contraseña">
                        <span class="login-pwd-toggle" onclick="togglePasswordVisibility()">
                            <i class="bi bi-eye" id="loginPasswordIcon"></i>
                        </span>
                    </div>
                </div>

                <button type="submit" class="login-submit-btn">
                    <i class="bi bi-box-arrow-in-right"></i> Ingresar al sistema
                </button>
                <div class="text-center mt-3">
                    <a href="{{ route('password.request') }}" class="text-muted small text-decoration-none">
                        ¿Olvidó su contraseña?
                    </a>
                </div>
            </form>
            <p class="login-form-footer">&copy; {{ date('Y') }} SCA-QR &middot; Fruitex de México</p>
        </div>
    </div>
</div>

<script>
    // Toggle de visibilidad de contraseña (sin cambios)
    function togglePasswordVisibility() {
        const input = document.getElementById('loginPassword');
        const icon = document.getElementById('loginPasswordIcon');
        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        icon.classList.toggle('bi-eye', !isHidden);
        icon.classList.toggle('bi-eye-slash', isHidden);
    }

    {{-- V4: Eliminado el JS que abría el modal de kiosko con PIN --}}
    {{-- Ya no hay modal; el enlace <a> redirige directamente a /kiosko/activar --}}
</script>
@endsection