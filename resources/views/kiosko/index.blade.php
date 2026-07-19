{{-- resources/views/kiosko/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Kiosco de Acceso')

@section('content')
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

<style>
    * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
    body, html { height: 100%; overflow: hidden; }
    body { background: #1b5e20; font-family: 'Poppins', sans-serif; }
    
    .kiosko-wrapper { height: 100vh; width: 100vw; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; padding: 1rem; }
    
    .kiosko-screen { display: none; flex-direction: column; align-items: center; justify-content: center; text-align: center; width: 100%; height: 100%; padding: 1rem; animation: fadeIn 0.4s ease; }
    .kiosko-screen.active { display: flex; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    
    .screen-full { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 100; }
    .bg-success-full { background: #1b5e20; color: #fff; }
    .bg-danger-full { background: #b71c1c; color: #fff; }
    .bg-warning-full { background: #e65100; color: #fff; }
    .bg-info-full { background: #01579b; color: #fff; }
    
    .kiosko-icon { font-size: clamp(3rem, 10vw, 6rem); margin-bottom: 0.5rem; }
    .kiosko-title { font-size: clamp(1.5rem, 5vw, 3rem); font-weight: 800; margin-bottom: 0.25rem; line-height: 1.1; }
    .kiosko-subtitle { font-size: clamp(1rem, 3vw, 2.5rem); font-weight: 600; opacity: 0.9; margin-bottom: 0; }
    .kiosko-message { font-size: clamp(0.9rem, 2.5vw, 1.3rem); font-weight: 500; opacity: 0.85; max-width: 600px; margin-top: 0.5rem; line-height: 1.3; }
    
    .photo-wrapper { position: relative; margin-bottom: 1rem; }
    .photo-circle { width: clamp(80px, 15vw, 140px); height: clamp(80px, 15vw, 140px); border-radius: 50%; object-fit: cover; border: 4px solid rgba(255,255,255,0.4); box-shadow: 0 8px 30px rgba(0,0,0,0.4); }
    .photo-check { position: absolute; bottom: 2px; right: 2px; background: white; border-radius: 50%; width: clamp(25px, 5vw, 40px); height: clamp(25px, 5vw, 40px); display: flex; align-items: center; justify-content: center; font-size: clamp(0.8rem, 2.5vw, 1.5rem); box-shadow: 0 4px 12px rgba(0,0,0,0.3); }
    
    #scanner-input { position: absolute; top: -100px; left: -100px; opacity: 0; width: 1px; height: 1px; }
    
    .kiosko-btn { font-size: clamp(1rem, 2vw, 1.2rem); font-weight: 700; padding: 0.9rem 1.5rem; border-radius: 14px; border: none; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 0.5rem; text-decoration: none; min-height: 55px; width: 100%; }
    .kiosko-btn-primary { background: #fff; color: #1b5e20; box-shadow: 0 4px 16px rgba(0,0,0,0.3); }
    .kiosko-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.4); }
    .kiosko-btn-secondary { background: rgba(255,255,255,0.15); color: #fff; border: 2px solid rgba(255,255,255,0.5); }
    .kiosko-btn-secondary:hover { background: rgba(255,255,255,0.25); border-color: #fff; }
    
    .btn-group-container { display: flex; flex-direction: column; gap: 0.75rem; width: 100%; max-width: 300px; margin-top: 1rem; }
    
    .btn-exit { position: absolute; top: 10px; right: 10px; z-index: 9999; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.2); border-radius: 10px; padding: 8px 14px; font-size: 0.85rem; color: rgba(255,255,255,0.6); cursor: pointer; opacity: 0.5; transition: all 0.3s; }
    .btn-exit:hover { opacity: 1; color: #fff; }
    
    .mode-badge { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 1.2rem; border-radius: 50px; font-size: clamp(0.8rem, 1.8vw, 1rem); font-weight: 700; margin-bottom: 1rem; background: rgba(255,255,255,0.15); color: #fff; border: 2px solid rgba(255,255,255,0.3); }
    
    .camera-floating { position: fixed; bottom: 15px; right: 15px; width: 200px; height: 150px; z-index: 9998; border-radius: 12px; overflow: hidden; box-shadow: 0 8px 24px rgba(0,0,0,0.5); display: none; border: 2px solid rgba(255,255,255,0.3); }
    .camera-floating video { width: 100%; height: 100%; object-fit: cover; display: block; background: #000; }
    .camera-scan-line { position: absolute; top: 0; left: 0; right: 0; height: 3px; background: #fff; animation: scanLine 2s linear infinite; }
    @keyframes scanLine { 0% { top: 0; } 50% { top: 100%; } 100% { top: 0; } }
    .camera-close { position: absolute; top: 4px; right: 4px; background: rgba(0,0,0,0.6); color: #fff; border: none; border-radius: 50%; width: 22px; height: 22px; font-size: 0.8rem; cursor: pointer; display: flex; align-items: center; justify-content: center; }
    
    .kiosko-input { width: 100%; max-width: 300px; padding: 1rem; font-size: clamp(1.2rem, 4vw, 2rem); text-align: center; border: 3px solid rgba(255,255,255,0.5); border-radius: 14px; outline: none; background: rgba(255,255,255,0.1); color: #fff; font-weight: 700; }
    .kiosko-input::placeholder { color: rgba(255,255,255,0.4); }
    .kiosko-input:focus { border-color: #fff; background: rgba(255,255,255,0.15); }
    .kiosko-textarea { width: 100%; max-width: 450px; padding: 1rem; font-size: clamp(0.9rem, 2vw, 1.2rem); border: 3px solid rgba(255,255,255,0.5); border-radius: 14px; outline: none; background: rgba(255,255,255,0.1); color: #fff; resize: none; line-height: 1.4; }
    .kiosko-textarea::placeholder { color: rgba(255,255,255,0.4); }
    .kiosko-textarea:focus { border-color: #fff; background: rgba(255,255,255,0.15); }
    
    .kiosko-spinner { width: clamp(50px, 8vw, 80px); height: clamp(50px, 8vw, 80px); border: 5px solid rgba(255,255,255,0.2); border-top-color: #fff; border-radius: 50%; animation: spin 1s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
    
    .kiosko-modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); backdrop-filter: blur(8px); z-index: 10000; display: none; align-items: center; justify-content: center; padding: 1rem; }
    .kiosko-modal.show { display: flex; }
    .kiosko-modal-content { background: #fff; border-radius: 20px; padding: 2rem; max-width: 350px; width: 100%; box-shadow: 0 20px 50px rgba(0,0,0,0.5); text-align: center; }
    
    @media (orientation: landscape) and (max-height: 400px) {
        .kiosko-icon { font-size: 2.5rem; }
        .kiosko-title { font-size: 1.3rem; }
        .kiosko-subtitle { font-size: 0.9rem; }
        .photo-circle { width: 70px; height: 70px; }
        .mode-badge { margin-bottom: 0.5rem; padding: 0.2rem 0.8rem; }
        .camera-floating { width: 140px; height: 105px; }
    }
</style>

<div class="kiosko-wrapper">
    
    <button class="btn-exit" onclick="showExitModal()">
        <i class="bi bi-x-circle"></i> Salir
    </button>

    <input type="text" id="scanner-input" autocomplete="off">

    {{-- BIENVENIDA --}}
    <div id="screen-welcome" class="kiosko-screen active">
        <div style="color: #fff;">
            <i class="bi bi-qr-code-scan kiosko-icon"></i>
        </div>
        <h1 class="kiosko-title">SCA-QR</h1>
        <p class="kiosko-subtitle" style="margin-bottom: 0.5rem;">Acerque su credencial al escáner</p>
        
        {{-- Obtenemos el modo directamente del objeto $turno inyectado por el controlador --}}
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
        <h3 class="kiosko-subtitle" style="margin-top: 1rem;">Procesando...</h3>
    </div>

    {{-- ÉXITO --}}
    <div id="screen-success" class="kiosko-screen screen-full bg-success-full">
        <i class="bi bi-check-circle-fill kiosko-icon"></i>
        <h2 id="success-title" class="kiosko-title"></h2>
        <div class="photo-wrapper">
            <img id="success-photo" src="" class="photo-circle" alt="Foto">
            <div class="photo-check">
                <i class="bi bi-check-lg text-success"></i>
            </div>
        </div>
        <h3 id="success-name" class="kiosko-title" style="font-size: clamp(1.2rem, 4vw, 2rem);"></h3>
        <p id="success-info" class="kiosko-subtitle" style="opacity: 0.8;"></p>
        <p id="success-msg" class="kiosko-message"></p>
    </div>

    {{-- DENEGADO --}}
    <div id="screen-denied" class="kiosko-screen screen-full bg-danger-full">
        <i class="bi bi-x-circle-fill kiosko-icon"></i>
        <h2 class="kiosko-title">ACCESO DENEGADO</h2>
        <p id="denied-msg" class="kiosko-message"></p>
    </div>

    {{-- REVISIÓN --}}
    <div id="screen-review" class="kiosko-screen screen-full bg-warning-full">
        <i class="bi bi-hourglass-split kiosko-icon"></i>
        <h2 class="kiosko-title">ESPERE UN MOMENTO</h2>
        <p class="kiosko-message">Espere al guardia para revisación.</p>
        <div class="kiosko-spinner" style="margin-top: 1rem; border-color: rgba(255,255,255,0.3); border-top-color: #fff;"></div>
    </div>

    {{-- JUSTIFICACIÓN --}}
    <div id="screen-justify" class="kiosko-screen screen-full bg-info-full">
        <i class="bi bi-exclamation-triangle-fill kiosko-icon"></i>
        <h2 class="kiosko-title">JUSTIFIQUE SU ACCESO</h2>
        <p class="kiosko-message" style="margin-bottom: 1rem;">Se detectó acceso fuera de horario. Escriba el motivo:</p>
        <textarea id="justify-text" class="kiosko-textarea" rows="3" placeholder="Escriba aquí..."></textarea>
        <div class="btn-group-container" style="max-width: 300px;">
            <button class="kiosko-btn" style="background: #fff; color: #01579b;" onclick="submitJustification()">
                <i class="bi bi-send"></i> ENVIAR Y REGISTRAR
            </button>
        </div>
    </div>

    {{-- BÚSQUEDA MANUAL --}}
    <div id="screen-manual" class="kiosko-screen">
        <div style="color: #fff;">
            <i class="bi bi-keyboard kiosko-icon"></i>
        </div>
        <h2 class="kiosko-title">BÚSQUEDA MANUAL</h2>
        <p class="kiosko-subtitle" style="margin-bottom: 1rem;">Ingrese su número de empleado</p>
        <input type="text" inputmode="numeric" id="manual-input" class="kiosko-input" placeholder="Ej: 1001">
        <div class="btn-group-container" style="max-width: 300px;">
            <button class="kiosko-btn kiosko-btn-primary" onclick="submitManual()">
                <i class="bi bi-check-lg"></i> Registrar
            </button>
            <button class="kiosko-btn kiosko-btn-secondary" onclick="resetToWelcome()">
                <i class="bi bi-arrow-left"></i> Cancelar
            </button>
        </div>
    </div>
</div>

{{-- CÁMARA FLOTANTE --}}
<div class="camera-floating" id="camera-view">
    <video id="video" playsinline></video>
    <div class="camera-scan-line"></div>
    <button class="camera-close" onclick="toggleCamera()"><i class="bi bi-x"></i></button>
</div>

{{-- MODAL SALIR --}}
<div class="kiosko-modal" id="exitModal">
    <div class="kiosko-modal-content">
        <h4 style="font-weight: 800; font-size: 1.3rem; margin-bottom: 0.5rem; color: #333;">
            <i class="bi bi-lock text-danger"></i> Salir del Kiosko
        </h4>
        <p style="color: #666; font-size: 1rem; margin-bottom: 1rem;">Ingrese el PIN de seguridad:</p>
        {{-- Apuntamos a la ruta que incluye el ID del turno --}}
        <form action="{{ route('kiosko.salir', $turno->id) }}" method="POST">
            @csrf
            <input type="password" name="pin" class="kiosko-input" style="color: #333; border-color: #ccc; background: #f8f9fa; letter-spacing: 8px; margin-bottom: 1rem;" required autofocus>
            <div class="btn-group-container" style="max-width: 100%;">
                <button type="submit" class="kiosko-btn" style="background: #dc3545; color: #fff;">
                    Desactivar
                </button>
                <button type="button" class="kiosko-btn kiosko-btn-secondary" style="color: #333; border-color: #ccc;" onclick="hideExitModal()">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
<script>
    const scannerInput = document.getElementById('scanner-input');
    let pollingInterval = null;
    let cameraStream = null;
    let currentBitacoraId = null;

    document.addEventListener('click', (e) => {
        if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA' && e.target.tagName !== 'BUTTON' && !e.target.closest('.camera-close')) {
            const modalVisible = document.querySelector('.kiosko-modal.show');
            if (!modalVisible) scannerInput.focus();
        }
    });
    setInterval(() => { 
        const modalVisible = document.querySelector('.kiosko-modal.show');
        const activeElement = document.activeElement;
        const isWritingInput = activeElement && (activeElement.tagName === 'INPUT' || activeElement.tagName === 'TEXTAREA');
        if (document.getElementById('screen-welcome').classList.contains('active') && !modalVisible && !isWritingInput) {
            scannerInput.focus();
        }
    }, 1000);

    scannerInput.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            procesarCodigo(this.value);
            this.value = '';
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
            if (video.readyState === video.HAVE_ENOUGH_DATA) {
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                context.drawImage(video, 0, 0, canvas.width, canvas.height);
                const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
                const code = jsQR(imageData.data, imageData.width, imageData.height);
                if (code) {
                    procesarCodigo(code.data);
                }
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
        const val = document.getElementById('manual-input').value;
        if(val) procesarCodigo(val, 'manual');
    }

    async function procesarCodigo(codigo, origen = 'kiosco') {
        if (!codigo) return;
        switchScreen('screen-loading');
        clearInterval(pollingInterval);
        try {
            const res = await fetch('/api/scan', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                // Enviamos el turno_id desde Blade para que el servidor sepa qué kiosco es
                body: JSON.stringify({ codigo, origen: origen, turno_id: '{{ $turno->id }}' })
            });
            const data = await res.json();
            currentBitacoraId = data.bitacora_id;
            mostrarResultado(data);
        } catch (error) {
            mostrarDenegado('Error de conexión.');
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
        document.getElementById('success-info').textContent = `${data.persona.puesto || ''} - ${data.persona.tipo_empleado}`;
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
        const text = document.getElementById('justify-text').value;
        if (!text) return alert('Debe escribir una justificación.');
        await fetch('/api/scan/justificar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ bitacora_id: currentBitacoraId, justificacion: text })
        });
        mostrarExito({
            tipo_evento: 'autorizado',
            persona: { nombre: 'Registrado', puesto: '', tipo_empleado: '', foto: 'https://ui-avatars.com/api/?name=OK&background=28a745&color=fff' },
            message: 'Acceso justificado y registrado.'
        });
    }

    function iniciarPolling(bitacoraId) {
        pollingInterval = setInterval(async () => {
            const res = await fetch(`/api/scan/status/${bitacoraId}`);
            const data = await res.json();
            if (data.autorizado || data.estado === 'revision_completada') {
                clearInterval(pollingInterval);
                mostrarExito({
                    tipo_evento: 'autorizado',
                    persona: { nombre: 'Revisión Completada', puesto: '', tipo_empleado: '', foto: 'https://ui-avatars.com/api/?name=OK&background=28a745&color=fff' },
                    message: 'Revisión completada. Acceso autorizado.'
                });
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
        scannerInput.focus();
    }
    function showExitModal() {
        document.getElementById('exitModal').classList.add('show');
        setTimeout(() => document.querySelector('#exitModal input').focus(), 100);
    }
    function hideExitModal() {
        document.getElementById('exitModal').classList.remove('show');
        scannerInput.focus();
    }
    document.getElementById('exitModal').addEventListener('click', function(e) {
        if (e.target === this) hideExitModal();
    });
</script>
@endsection