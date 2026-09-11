{{-- resources/views/guardia/dashboard.blade.php --}}
{{--
    PANEL DEL GUARDIA (dashboard web).

    CAMBIOS V4:
      1. Dos listas de pendientes SEPARADAS:
         - Revisiones F10 (estado='pendiente_revision'): botón todo_bien|anomalia.
         - Justificaciones de horario (estado='revision_horario'): dropdown con
           catálogo justificaciones_horario.
      2. Eliminado "marcar visto" de denegados (se eliminó visto_guardia de bitacora).
         Los denegados ahora solo se ven en el feed; no se "marcan".
      3. Eliminado el textarea de justificación del empleado (J1+J2: el guardia
         selecciona del catálogo, no escribe texto libre).
      4. Modal unificado para resolver F10 (todo_bien|anomalia + descripción).
      5. Modal nuevo para justificar horario (dropdown del catálogo).
      6. Rutas renombradas: api.guardia.dentro → api.guardia.personas-dentro.
      7. Estados: revision_completada ELIMINADO. Después de F10 o justificación,
         el estado pasa a 'autorizado'.
      8. Mostrar kioskos activos del guardia (puede tener varios: entrada, salida, mixto).
--}}
@extends('layouts.app')

@section('title', 'Panel de Guardia')

@section('content')
<div class="container-fluid">
    <div class="sca-page-head animate-fade-in-up">
        <div>
            <div class="sca-page-eyebrow"><i class="bi bi-shield-shaded" aria-hidden="true"></i> Seguridad</div>
            <h1 class="sca-page-title">Centro de control del turno</h1>
            <p class="sca-page-subtitle">
                {{ Auth::user()->nombre_completo }} · {{ Auth::user()->planta_nombre ?? 'Sin asignar' }}
            </p>
        </div>
        <form method="POST" action="{{ route('guardia.terminar.turno') }}" id="form-terminar-turno" class="sca-form-terminar-turno">
            @csrf
            <button type="submit" class="btn btn-danger">
                <i class="bi bi-box-arrow-right me-1"></i> Terminar turno
            </button>
        </form>
    </div>

    {{-- Kioskos activos del guardia (informativo) --}}
    @if($kioskosActivos && $kioskosActivos->isNotEmpty())
    <div class="alert alert-info d-flex align-items-center" style="border-radius:12px;">
        <i class="bi bi-display fs-4 me-3"></i>
        <div class="flex-grow-1">
            <strong>Tiene {{ $kioskosActivos->count() }} kiosko(s) público(s) activo(s):</strong>
            @foreach($kioskosActivos as $k)
                <span class="badge bg-light text-dark me-2">
                    {{ $k->planta?->nombre }} · {{ ucfirst($k->modo) }}
                </span>
            @endforeach
        </div>
    </div>
    @endif

    <div class="row g-3">

        {{-- ===================================================== --}}
        {{-- COL 1: Justificaciones de horario pendientes --}}
        {{-- ===================================================== --}}
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100 sca-card" style="border-radius: 12px; border-top: 4px solid #0277bd !important;">
                <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-clock-history text-primary"></i> Justificaciones de Horario</h5>
                    <span class="badge bg-primary" id="count-justificaciones">0</span>
                </div>
                <div class="card-body" id="list-justificaciones" style="max-height: 50vh; overflow-y: auto;">
                    <div class="text-center text-muted py-5">
                        <span class="spinner-border spinner-border-sm"></span> Cargando...
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================================================== --}}
        {{-- COL 2: Revisiones F10 pendientes --}}
        {{-- ===================================================== --}}
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100 sca-card" style="border-radius: 12px; border-top: 4px solid #f57c00 !important;">
                <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-shield-exclamation text-warning"></i> Revisiones</h5>
                    <span class="badge bg-warning text-dark" id="count-revisions">0</span>
                </div>
                <div class="card-body" id="list-revisions" style="max-height: 50vh; overflow-y: auto;">
                    <div class="text-center text-muted py-5">
                        <span class="spinner-border spinner-border-sm"></span> Cargando...
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================================================== --}}
        {{-- COL 3: Personas dentro --}}
        {{-- ===================================================== --}}
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100 sca-card" style="border-radius: 12px;">
                <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-people text-success"></i> Personas Dentro</h5>
                    <span class="badge bg-success" id="count-dentro">0</span>
                </div>
                <div class="card-body" id="list-dentro" style="max-height: 50vh; overflow-y: auto;">
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-people fs-1 text-muted"></i>
                        <p class="mt-2 mb-0">Sin personas dentro</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================================================== --}}
        {{-- COL 4: Escaneo rápido (cámara + manual) --}}
        {{-- ===================================================== --}}
        <div class="col-md-6">
            <div class="card border-0 shadow-sm sca-card" style="border-radius: 12px;">
                <div class="card-header bg-transparent py-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-qr-code-scan text-success"></i> Escaneo Rápido</h5>
                </div>
                <div class="card-body">
                    <button class="btn btn-outline-success w-100 btn-lg mb-2" id="btn-toggle-camera">
                        <i class="bi bi-camera"></i> Abrir Cámara
                    </button>

                    <div class="d-flex align-items-center my-3">
                        <hr class="flex-grow-1">
                        <span class="px-2 text-muted small">o búsqueda manual</span>
                        <hr class="flex-grow-1">
                    </div>

                    <div class="mb-2">
                        <label class="form-label small text-muted">Número de empleado</label>
                        <input type="number" id="manual-id" class="form-control form-control-lg" placeholder="Ej: 23951">
                    </div>
                    <button class="btn btn-success w-100 btn-lg" id="btn-manual-search">
                        <i class="bi bi-card-checklist"></i> Registrar
                    </button>
                    <div id="manual-result" class="mt-3"></div>
                </div>
            </div>
        </div>

        {{-- ===================================================== --}}
        {{-- COL 5: Feed de actividad reciente --}}
        {{-- ===================================================== --}}
        <div class="col-12">
            <div class="card border-0 shadow-sm sca-card" style="border-radius: 12px;">
                <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-activity text-info"></i> Actividad Reciente</h5>
                    <div class="btn-group btn-group-sm" role="group" id="feed-filter">
                        <button type="button" class="btn btn-outline-secondary active" data-filter="todos">Todos</button>
                        <button type="button" class="btn btn-outline-success" data-filter="entrada">Entradas</button>
                        <button type="button" class="btn btn-outline-info" data-filter="salida">Salidas</button>
                        <button type="button" class="btn btn-outline-danger" data-filter="denegado">Denegados</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="d-flex align-items-center px-3 py-2 border-bottom"
                         style="background: var(--sca-hover); font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--sca-text-muted);">
                        <div style="width: 70px;" class="text-center">Hora</div>
                        <div style="width: 50px;" class="text-center">Tipo</div>
                        <div class="flex-grow-1 px-3">Persona</div>
                        <div style="min-width: 110px;" class="text-end">Acción</div>
                    </div>
                    <div id="feed-list" style="max-height: 450px; overflow-y: auto;">
                        <div class="text-center text-muted py-5">
                            <span class="spinner-border spinner-border-sm"></span> Cargando...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Cámara flotante --}}
{{-- V10.3.8-fix: webcam externa se veía borrosa y sin poder elegirla.
     Antes getUserMedia({facingMode}) pedía la resolución por defecto del
     navegador (640×480 en muchas webcams) y el guardia no podía cambiar
     de cámara. Ahora: captura HD (ideal 1920×1080), selector de cámara,
     autoenfoque continuo (si la cámara lo soporta) y zoom 1×-4×.
     V10.3.10-fix: sin facingMode (no determinista en PC con varias webcams
     USB), constraints refinadas (30 fps máx., 16:9, resizeMode 'none' =
     modo nativo sin reescalado por software), autoenfoque solo si la cámara
     lo declara (badge "Foco fijo" vs "AF"), detector QR nativo
     BarcodeDetector opcional con jsQR de respaldo, lectura por etapas
     (media resolución → región central 78% → nativa → rescate) y
     preprocesado de contraste para imagen lavada/oscura. --}}
<div class="camera-floating-guardia" id="camera-view-guardia" style="display:none; position:fixed; bottom:20px; right:20px; width:340px; height:255px; z-index:9998; border-radius:12px; overflow:hidden; box-shadow:0 8px 24px rgba(0,0,0,0.4); border:2px solid var(--sca-border);">
    <video id="video-guardia" playsinline style="width:100%; height:100%; object-fit:cover; display:block; background:#000;"></video>
    <div style="position:absolute; top:0; left:0; right:0; height:3px; background:var(--sca-primary); animation:scanLine 2s linear infinite;"></div>
    <button type="button" onclick="toggleCameraGuardia()" style="position:absolute; top:6px; right:6px; background:rgba(0,0,0,0.6); color:#fff; border:none; border-radius:50%; width:26px; height:26px; font-size:0.9rem; cursor:pointer; display:flex; align-items:center; justify-content:center;">
        <i class="bi bi-x"></i>
    </button>
    {{-- V10.3.9-fix: tip de distancia — las webcams de foco fijo (Acteck WM20,
         Logitech 720p, EMEET...) no enfocan a menos de ~30-40 cm: el QR se
         sostiene a ~40 cm y se acerca con el ZOOM, no pegándolo al lente --}}
    <div id="camera-hint-guardia" style="position:absolute; top:10px; left:50%; transform:translateX(-50%); background:rgba(0,0,0,0.75); color:#ffd166; padding:3px 10px; border-radius:8px; font-size:0.68rem; white-space:nowrap; pointer-events:none; opacity:1; transition:opacity 0.8s; z-index:2;">
        <i class="bi bi-info-circle-fill"></i> QR a ~40 cm · acerque con el zoom
    </div>
    {{-- V10.3.8-fix: barra de controles — elegir webcam externa/integrada,
         ver la resolución real de captura y acercar el QR con zoom --}}
    <div style="position:absolute; bottom:0; left:0; right:0; background:rgba(0,0,0,0.72); padding:6px 8px; display:flex; flex-direction:column; gap:5px;">
        <div style="display:flex; align-items:center; gap:6px;">
            <select id="camera-select-guardia" title="Cámara activa" style="flex:1 1 auto; min-width:0; font-size:0.72rem; padding:2px 4px; border-radius:6px; border:1px solid rgba(255,255,255,0.25); background:rgba(255,255,255,0.12); color:#fff; outline:none;">
                <option value="">Cámaras…</option>
            </select>
            <span id="camera-res-guardia" title="Resolución real de captura" style="font-size:0.65rem; color:#8fe3b4; white-space:nowrap;"></span>
        </div>
        <div style="display:flex; align-items:center; gap:6px;">
            <i class="bi bi-zoom-in" style="color:#fff; font-size:0.8rem;"></i>
            <input type="range" id="camera-zoom-guardia" min="1" max="5" step="0.1" value="1" title="Acercar el QR (zoom 1×-5×)" style="flex:1 1 auto; min-width:0; height:4px; accent-color:var(--sca-primary); cursor:pointer;">
            <span id="camera-zoom-label-guardia" style="color:#fff; font-size:0.65rem; width:30px; text-align:right;">1.0×</span>
        </div>
    </div>
</div>

<style>
@keyframes scanLine {
    0% { top: 0; }
    50% { top: calc(100% - 3px); }
    100% { top: 0; }
}
/* V10.3.8-fix: opciones del selector legibles (el select es texto blanco
   sobre fondo oscuro, pero el dropdown nativo es blanco) */
#camera-select-guardia option { color: #222; background: #fff; }
</style>

{{-- ===================================================== --}}
{{-- MODAL: Justificar anomalía de horario (dropdown catálogo) --}}
{{-- ===================================================== --}}
<div class="modal fade" id="justifyModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content sca-modal-content" style="border-radius: 16px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-clock-history text-primary"></i> Justificar Acceso</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <p>Empleado: <strong id="justify-name"></strong></p>
                <p class="small text-muted">Tipo: <span id="justify-tipo" class="badge bg-secondary"></span> &middot; Hora: <span id="justify-hora"></span></p>

                <div class="mb-3">
                    <label class="form-label">Seleccione la justificación <span class="text-danger">*</span></label>
                    <select class="form-select" id="justify-select">
                        <option value="">Cargando catálogo...</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <input type="hidden" id="justify-bitacora-id">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-save-justify">
                    <i class="bi bi-check-lg"></i> Justificar
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ===================================================== --}}
{{-- MODAL: Resolver revisión F10 (todo_bien|anomalia) --}}
{{-- ===================================================== --}}
<div class="modal fade" id="resolveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content sca-modal-content" style="border-radius: 16px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-shield-exclamation text-warning"></i> Revisión</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <p>Empleado: <strong id="modal-name"></strong></p>
                <p class="small text-muted">Hora del acceso: <span id="modal-hora"></span></p>

                <div class="mb-3">
                    <label class="form-label">Resultado de la revisión</label>
                    <select class="form-select" id="modal-result">
                        <option value="Revición Completada Correctamente">Revisión Completada Correctamente</option>
                        <option value="Se Encontro Hallazgo">Se Encontró Hallazgo</option>
                    </select>
                </div>
                <div class="mb-3" id="modal-desc-wrapper" style="display: none;">
                    <label class="form-label">Descripción del hallazgo <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="modal-desc" rows="3" placeholder="Describa el hallazgo encontrado..."></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <input type="hidden" id="modal-bitacora-id">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="btn-save-resolution">
                    <i class="bi bi-check-lg"></i> Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>

@push('scripts')
<script>
    const CSRF_TOKEN = '{{ csrf_token() }}';
    let resolveModal, justifyModal;
    let cameraStreamGuardia = null;
    let isProcessingGuardia = false;
    // V10.3.8-fix: estado del módulo de cámara en HD
    let cameraZoomGuardia = 1;      // zoom digital aplicado al decodificar (recorte central)
    let cameraHwZoomGuardia = null; // {min,max} cuando la cámara tiene zoom por hardware
    let cameraLoopGuardia = 0;      // token del bucle de escaneo vigente (evita bucles duplicados)
    let hintTimerGuardia = null;    // V10.3.9-fix: timer del tip de distancia (se auto-oculta)
    let cameraFocusGuardia = 'fijo';   // V10.3.10-fix: 'af' | 'single' | 'fijo' según lo que declara la cámara
    let detectorNativoGuardia = null;  // V10.3.10-fix: BarcodeDetector del navegador (opcional; jsQR respalda)
    const CAM_PREFS_GUARDIA = 'scaqr_cam_guardia'; // preferencias: {deviceId, zoom}

    document.addEventListener('DOMContentLoaded', function() {
        resolveModal = new bootstrap.Modal(document.getElementById('resolveModal'));
        justifyModal = new bootstrap.Modal(document.getElementById('justifyModal'));

        // Cargar catálogo de justificaciones (1 sola vez)
        cargarCatalogoJustificaciones();

        // Polling de pendientes (justificaciones + revisiones F10)
        fetchPending();
        setInterval(fetchPending, 5000);

        // Polling de personas dentro
        cargarDentro();
        setInterval(cargarDentro, 10000);

        // Polling de feed
        cargarFeed();
        setInterval(cargarFeed, 5000);

        // Botón cámara
        document.getElementById('btn-toggle-camera').addEventListener('click', toggleCameraGuardia);

        // V10.3.8-fix: controles de la ventana de cámara
        document.getElementById('camera-select-guardia').addEventListener('change', async function() {
            guardarPrefsCamaraGuardia({ deviceId: this.value });
            if (!cameraStreamGuardia) return; // cámara cerrada: solo se guarda la preferencia
            try {
                await abrirCamaraGuardia(this.value); // reemplaza stream y bucle sin cerrar la ventana
            } catch (e) {
                ScaModal.error('Error de cámara', 'No se pudo abrir la cámara seleccionada: ' + e.message);
                cerrarCamaraGuardia();
            }
        });
        document.getElementById('camera-zoom-guardia').addEventListener('input', function() {
            aplicarZoomGuardia(this.value);
        });

        // Búsqueda manual
        document.getElementById('btn-manual-search').addEventListener('click', async () => {
            const id = document.getElementById('manual-id').value;
            if(!id) return;
            procesarDesdeGuardia(id, 'manual');
        });

        // Modal F10: mostrar/ocultar descripción según resultado
        document.getElementById('modal-result').addEventListener('change', function() {
            document.getElementById('modal-desc-wrapper').style.display = this.value === 'Se Encontro Hallazgo' ? 'block' : 'none';
        });

        // Guardar resolución F10
        document.getElementById('btn-save-resolution').addEventListener('click', guardarResolucionF10);

        // Guardar justificación de horario
        document.getElementById('btn-save-justify').addEventListener('click', guardarJustificacion);

        // Filtros del feed
        document.querySelectorAll('#feed-filter button').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('#feed-filter button').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                feedFilter = this.dataset.filter;
                renderFeed();
            });
        });
    });

    // ============================================================
    // CARGAR CATÁLOGO DE JUSTIFICACIONES (1 vez, al cargar)
    // ============================================================
    async function cargarCatalogoJustificaciones() {
        try {
            const res = await fetch('/api/justificaciones');
            const data = await res.json();
            const select = document.getElementById('justify-select');
            
            // FIX catálogo: agrupar por categoría con <optgroup> para que el
            // guardia encuentre rápido la opción (en vez de scrollear 30 opciones planas).
            let html = '<option value="">Seleccione una opción...</option>';
            
            // Agrupar por categoría
            const porCategoria = {};
            data.forEach(j => {
                const cat = j.categoria || 'Otros';
                if (!porCategoria[cat]) porCategoria[cat] = [];
                porCategoria[cat].push(j);
            });
            
            // Generar optgroups en orden de categorías
            const ordenCategorias = ['Entradas', 'Salidas', 'Permisos', 'Otros'];
            ordenCategorias.forEach(cat => {
                if (porCategoria[cat]) {
                    html += `<optgroup label="${cat}">`;
                    porCategoria[cat].forEach(j => {
                        html += `<option value="${j.id_justificacion}" title="${j.descripcion || ''}">${j.nombre}</option>`;
                    });
                    html += '</optgroup>';
                }
            });
            
            // Categorías extras que no estén en el orden predefinido
            Object.keys(porCategoria).forEach(cat => {
                if (!ordenCategorias.includes(cat)) {
                    html += `<optgroup label="${cat}">`;
                    porCategoria[cat].forEach(j => {
                        html += `<option value="${j.id_justificacion}" title="${j.descripcion || ''}">${j.nombre}</option>`;
                    });
                    html += '</optgroup>';
                }
            });
            
            select.innerHTML = html;
        } catch(e) {
            console.error('Error cargando justificaciones:', e);
            document.getElementById('justify-select').innerHTML = '<option value="">Error al cargar</option>';
        }
    }

    // ============================================================
    // CÁMARA Y ESCANEO DESDE PANEL
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
    function leerPrefsCamaraGuardia() {
        try { return JSON.parse(localStorage.getItem(CAM_PREFS_GUARDIA)) || {}; }
        catch (e) { return {}; }
    }

    function guardarPrefsCamaraGuardia(parcial) {
        try {
            const prefs = Object.assign(leerPrefsCamaraGuardia(), parcial);
            localStorage.setItem(CAM_PREFS_GUARDIA, JSON.stringify(prefs));
        } catch (e) { /* modo privado: sin persistencia, no es error */ }
    }

    function marcarCamaraAbiertaGuardia(abierta) {
        const btn = document.getElementById('btn-toggle-camera');
        if (abierta) {
            btn.innerHTML = '<i class="bi bi-camera-video-off"></i> Cerrar Cámara';
            btn.classList.remove('btn-outline-success');
            btn.classList.add('btn-danger');
        } else {
            btn.innerHTML = '<i class="bi bi-camera"></i> Abrir Cámara';
            btn.classList.remove('btn-danger');
            btn.classList.add('btn-outline-success');
        }
    }

    function cerrarCamaraGuardia() {
        if (cameraStreamGuardia) {
            cameraStreamGuardia.getTracks().forEach(track => track.stop());
            cameraStreamGuardia = null;
        }
        document.getElementById('camera-view-guardia').style.display = 'none';
        marcarCamaraAbiertaGuardia(false);
    }

    // V10.3.10-fix: detector QR nativo del navegador (Chrome/Edge lo traen,
    // acelerado por hardware). Opcional: si el navegador no lo soporta o no
    // declara qr_code devuelve null y jsQR sigue siendo el lector de respaldo.
    async function prepararDetectorNativoGuardia() {
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
    function actualizarBadgeResolucionGuardia(w, h) {
        const resEl = document.getElementById('camera-res-guardia');
        if (!resEl) return;
        if (!w || !h) { resEl.textContent = ''; return; }
        const foco = cameraFocusGuardia === 'af' ? 'AF'
            : (cameraFocusGuardia === 'single' ? 'AF (1 disparo)' : 'Foco fijo');
        resEl.textContent = w + '×' + h + ' · ' + foco;
        resEl.title = cameraFocusGuardia === 'fijo'
            ? 'Cámara de foco fijo: sostener el QR a ~40 cm y acercarlo con el zoom'
            : 'Cámara con autoenfoque';
    }

    // V10.3.10-fix: preprocesado de rescate — escala de grises + contraste
    // automático. Algunas webcams entregan imagen "lavada" (reflejo de luz
    // tras el QR) u oscura (vestíbulo de noche): se muestrea la luminancia
    // mínima/máxima del fotograma y se estira a 0-255 para separar los
    // módulos del QR. Devuelve false si no hay nada que estirar.
    function mejorarContrasteGuardia(img) {
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

    async function abrirCamaraGuardia(deviceId) {
        // Detener stream previo (cambio de cámara) sin cerrar la ventana
        if (cameraStreamGuardia) {
            cameraStreamGuardia.getTracks().forEach(t => t.stop());
            cameraStreamGuardia = null;
        }
        const video = document.getElementById('video-guardia');
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
            cameraStreamGuardia = deviceId
                ? await pedir(Object.assign({ deviceId: { exact: deviceId } }, hd))
                : await pedir(hd); // V10.3.10-fix: cámara predeterminada, sin facingMode
        } catch (e) {
            // Cámara guardada ya desconectada: reintentar con la predeterminada
            if (deviceId) cameraStreamGuardia = await pedir(hd); // V10.3.10-fix: sin facingMode
            else throw e;
        }
        video.srcObject = cameraStreamGuardia;
        try { await video.play(); } catch (e) { /* autoplay bloqueado: el usuario ya dio el clic */ }

        const track = cameraStreamGuardia.getVideoTracks()[0];

        // V10.3.10-fix: capacidades REALES declaradas por la cámara (una sola
        // consulta): zoom por hardware y modo de enfoque. Una webcam de foco
        // fijo no declara focusMode y ningún software la convierte en
        // autofocus: solo se pide lo que existe y el badge lo indica, para
        // saber qué esperar de cada unidad (Acteck WM20 / Logitech 720p /
        // EMEET 1080p = foco fijo; C920/C922/Brio = AF).
        cameraHwZoomGuardia = null;
        cameraFocusGuardia = 'fijo';
        try {
            const caps = track.getCapabilities ? track.getCapabilities() : {};
            if (caps.zoom && typeof caps.zoom.min === 'number' && typeof caps.zoom.max === 'number') {
                cameraHwZoomGuardia = { min: caps.zoom.min, max: caps.zoom.max };
            }
            if (caps.focusMode && caps.focusMode.indexOf('continuous') !== -1) {
                await track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] });
                cameraFocusGuardia = 'af';
            } else if (caps.focusMode && caps.focusMode.indexOf('single-shot') !== -1) {
                await track.applyConstraints({ advanced: [{ focusMode: 'single-shot' }] });
                cameraFocusGuardia = 'single';
            }
        } catch (e) {}

        // V10.3.10-fix: detector QR nativo del navegador (si está disponible)
        detectorNativoGuardia = await prepararDetectorNativoGuardia();

        // Badge con la resolución real entregada + estado de enfoque
        const settings = track.getSettings ? track.getSettings() : {};
        actualizarBadgeResolucionGuardia(settings.width, settings.height);

        // Restaurar el último zoom usado (óptico o digital según la cámara)
        const zoomGuardado = Number(leerPrefsCamaraGuardia().zoom) || 1;
        aplicarZoomGuardia(zoomGuardado, false);

        // V10.3.9-fix: tip de distancia visible 8 s al abrir la cámara:
        // con foco fijo el QR se acerca con el ZOOM, no pegándolo al lente
        const hint = document.getElementById('camera-hint-guardia');
        if (hint) {
            hint.style.opacity = '1';
            clearTimeout(hintTimerGuardia);
            hintTimerGuardia = setTimeout(() => { hint.style.opacity = '0'; }, 8000);
        }

        await cargarCamarasGuardia(settings.deviceId || deviceId);
        iniciarBucleGuardia();
    }

    async function cargarCamarasGuardia(deviceIdActiva) {
        const select = document.getElementById('camera-select-guardia');
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
            if (activa) guardarPrefsCamaraGuardia({ deviceId: activa });
        } catch (e) {
            select.innerHTML = '<option value="">Cámaras no disponibles</option>';
        }
    }

    function aplicarZoomGuardia(zoom, guardar = true) {
        zoom = Math.min(5, Math.max(1, Number(zoom) || 1)); // V10.3.9-fix: zoom hasta 5×
        const video = document.getElementById('video-guardia');
        const slider = document.getElementById('camera-zoom-guardia');
        const label = document.getElementById('camera-zoom-label-guardia');
        if (slider) slider.value = zoom;
        if (label) label.textContent = zoom.toFixed(1) + '×';

        const track = cameraStreamGuardia ? cameraStreamGuardia.getVideoTracks()[0] : null;
        if (cameraHwZoomGuardia && track) {
            // Zoom óptico: el propio sensor acerca → se decodifica el cuadro completo
            const min = cameraHwZoomGuardia.min, max = cameraHwZoomGuardia.max;
            const hw = min + ((zoom - 1) / 4) * (max - min); // V10.3.9-fix: slider 1×-5× → rango de la cámara
            track.applyConstraints({ advanced: [{ zoom: hw }] }).catch(() => {});
            cameraZoomGuardia = 1;
            video.style.transform = '';
        } else {
            // Zoom digital: previsualización escalada + recorte central al decodificar
            cameraZoomGuardia = zoom;
            video.style.transform = zoom > 1 ? 'scale(' + zoom + ')' : '';
        }
        if (guardar) guardarPrefsCamaraGuardia({ zoom: zoom });
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

    function iniciarBucleGuardia() {
        const video = document.getElementById('video-guardia');
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d', { willReadFrequently: true });
        const miLoop = ++cameraLoopGuardia;

        // V10.3.9-fix: parámetro de rescate. V10.3.10-fix: ahora preprocesa
        // contraste automático (imagen lavada/oscura) + unsharp (foco fijo
        // levemente desenfocado), en ese orden, antes de jsQR.
        const decodificar = (w, h, sx, sy, sw, sh, rescatar) => {
            canvas.width = w;
            canvas.height = h;
            context.drawImage(video, sx, sy, sw, sh, 0, 0, w, h);
            const img = context.getImageData(0, 0, w, h);
            if (rescatar) {
                mejorarContrasteGuardia(img);
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
            if (!cameraStreamGuardia || miLoop !== cameraLoopGuardia) return; // bucle reemplazado o cámara cerrada
            if (!isProcessingGuardia && video.readyState === video.HAVE_ENOUGH_DATA) {
                intento++;
                try {
                    let code = null;
                    // V10.3.10-fix: 1) detector QR NATIVO del navegador cuando
                    // existe (más rápido y robusto; Chrome/Edge en Windows lo
                    // traen acelerado). Solo sin zoom: el <video> muestra el
                    // encuadre completo y es exactamente lo que debe leerse.
                    if (detectorNativoGuardia && cameraZoomGuardia <= 1) {
                        try {
                            const hallados = await detectorNativoGuardia.detect(video);
                            if (hallados && hallados.length) code = { data: hallados[0].rawValue };
                        } catch (e) { /* detector nativo falló en este fotograma: sigue jsQR */ }
                    }
                    // 2) jsQR por etapas (respaldo universal: Firefox/Safari/Linux)
                    if (!code) {
                        if (cameraZoomGuardia > 1) {
                            // Con zoom digital se decodifica SOLO la región ampliada
                            // (el mismo encuadre que se ve en la previsualización).
                            const z = cameraZoomGuardia;
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
                    if (code) procesarDesdeGuardia(code.data, 'guardia');
                } catch (e) { /* fotograma corrupto: se reintenta en el siguiente tick */ }
            }
            if (cameraStreamGuardia && miLoop === cameraLoopGuardia) setTimeout(tick, 80); // ~10 fps
        };
        // V10.3.10-fix: 450 ms de estabilización inicial (antes 300): margen para
        // que autofocus/exposición/balance de blancos se asienten antes de leer
        setTimeout(tick, 450);
    }

    async function toggleCameraGuardia() {
        const view = document.getElementById('camera-view-guardia');

        if (cameraStreamGuardia) {
            cerrarCamaraGuardia();
            return;
        }

        try {
            view.style.display = 'block';
            marcarCamaraAbiertaGuardia(true);
            await abrirCamaraGuardia(leerPrefsCamaraGuardia().deviceId);
        } catch(e) {
            cerrarCamaraGuardia();
            ScaModal.error('Error de cámara', 'No se pudo acceder a la cámara: ' + e.message);
        }
    }

    async function procesarDesdeGuardia(codigo, origen) {
        codigo = String(codigo).trim();
        if (!codigo || isProcessingGuardia) return;
        isProcessingGuardia = true;

        const resDiv = document.getElementById('manual-result');
        resDiv.innerHTML = '<div class="alert alert-info"><span class="spinner-border spinner-border-sm"></span> Procesando...</div>';

        try {
            const res = await fetch('/api/scan', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: JSON.stringify({ codigo, origen })
            });
            const data = await res.json();
            if (data.success && data.autorizado) {
                resDiv.innerHTML = `<div class="alert alert-success d-flex align-items-center"><img src="${data.persona?.foto || ''}" class="rounded-circle me-3" width="50" height="50"><div><strong>${data.persona?.nombre || ''}</strong><br>${data.message}</div></div>`;
            } else if (data.success && data.pendiente) {
                resDiv.innerHTML = `<div class="alert alert-warning"><i class="bi bi-hourglass"></i> <strong>${data.persona?.nombre || 'Empleado'}</strong> — ${data.message}</div>`;
                fetchPending(); // Refrescar pendientes
            } else {
                resDiv.innerHTML = `<div class="alert alert-danger"><i class="bi bi-x-circle"></i> ${data.message || 'Error'}</div>`;
            }
        } catch(e) {
            resDiv.innerHTML = `<div class="alert alert-danger">Error de conexión</div>`;
        } finally {
            setTimeout(() => { isProcessingGuardia = false; }, 1500);
        }
    }

    // ============================================================
    // PENDIENTES (2 listas: justificaciones + revisiones F10)
    // ============================================================
    async function fetchPending() {
        try {
            const res = await fetch('{{ route("api.guardia.pending") }}');
            const data = await res.json();

            // Justificaciones de horario (estado='revision_horario')
            const justificaciones = data.justificaciones || [];
            const revisions = data.revisiones || [];

            // Render justificaciones
            const justList = document.getElementById('list-justificaciones');
            const justCount = document.getElementById('count-justificaciones');
            justCount.textContent = justificaciones.length;

            if (justificaciones.length === 0) {
                justList.innerHTML = '<div class="text-center text-muted py-5"><i class="bi bi-check-circle fs-1 text-success opacity-50"></i><p class="mt-2 mb-0">Sin justificaciones pendientes</p></div>';
            } else {
                justList.innerHTML = justificaciones.map(item => {
                    // FIX interplantas: mostrar badge Entrada/Salida según tipo_evento
                    const tipoEvento = item.tipo_evento || 'entrada';
                    const esEntrada = tipoEvento === 'entrada';
                    const tipoBadge = esEntrada
                        ? '<span class="badge bg-success bg-opacity-10 text-success mb-1"><i class="bi bi-box-arrow-in-right"></i> Entrada</span>'
                        : '<span class="badge bg-warning bg-opacity-10 text-warning mb-1"><i class="bi bi-box-arrow-right"></i> Salida</span>';
                    const tipoLabel = esEntrada ? 'entrada' : 'salida';
                    return `
                    <div class="alert alert-primary d-flex justify-content-between align-items-center mb-2">
                        <div>
                            ${tipoBadge}
                            <strong><i class="bi bi-clock-history me-1"></i> ${item.nombre}</strong>
                            <small class="text-muted d-block">${tipoLabel.charAt(0).toUpperCase() + tipoLabel.slice(1)} fuera de horario - ${item.hora}</small>
                        </div>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-primary" onclick="openJustifyModal(${item.bitacora_id}, '${item.nombre.replace(/'/g, "\\'")}', '${item.hora}', '${tipoEvento}')">
                                <i class="bi bi-check-lg"></i> Justificar
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="rechazarPendiente(${item.bitacora_id}, '${item.nombre.replace(/'/g, "\\'")}')">
                                <i class="bi bi-x-lg"></i> Rechazar
                            </button>
                        </div>
                    </div>
                `;}).join('');
            }

            // Render revisiones F10
            const revList = document.getElementById('list-revisions');
            const revCount = document.getElementById('count-revisions');
            revCount.textContent = revisions.length;

            if (revisions.length === 0) {
                revList.innerHTML = '<div class="text-center text-muted py-5"><i class="bi bi-check-circle fs-1 text-success opacity-50"></i><p class="mt-2 mb-0">Sin revisiones pendientes</p></div>';
            } else {
                revList.innerHTML = revisions.map(item => {
                    // FIX interplantas: mostrar badge Entrada/Salida también en revisiones F10
                    const tipoEvento = item.tipo_evento || 'entrada';
                    const esEntrada = tipoEvento === 'entrada';
                    const tipoBadge = esEntrada
                        ? '<span class="badge bg-success bg-opacity-10 text-success mb-1"><i class="bi bi-box-arrow-in-right"></i> Entrada</span>'
                        : '<span class="badge bg-warning bg-opacity-10 text-warning mb-1"><i class="bi bi-box-arrow-right"></i> Salida</span>';
                    return `
                    <div class="alert alert-warning d-flex justify-content-between align-items-center mb-2">
                        <div>
                            ${tipoBadge}
                            <strong><i class="bi bi-shield-exclamation me-1"></i> ${item.nombre}</strong>
                            <small class="text-muted d-block">Revisión aleatoria - ${item.hora}</small>
                        </div>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-success" onclick="openResolveModal(${item.bitacora_id}, '${item.nombre.replace(/'/g, "\\'")}', '${item.hora}')">
                                <i class="bi bi-check-lg"></i> Atender
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="rechazarPendiente(${item.bitacora_id}, '${item.nombre.replace(/'/g, "\\'")}')">
                                <i class="bi bi-x-lg"></i> Rechazar
                            </button>
                        </div>
                    </div>
                `;}).join('');
            }
        } catch(e) {
            if (e instanceof SyntaxError) { window.location.href = '{{ route("login") }}'; }
            else { console.error('Error fetchPending:', e); }
        }
    }

    // ============================================================
    // MODAL: Justificar anomalía de horario
    // ============================================================
    function openJustifyModal(bitacoraId, nombre, hora, tipoEvento) {
        document.getElementById('justify-bitacora-id').value = bitacoraId;
        document.getElementById('justify-name').innerText = nombre;
        document.getElementById('justify-hora').innerText = hora;
        // FIX interplantas: mostrar si fue entrada o salida
        const tipoSpan = document.getElementById('justify-tipo');
        if (tipoEvento === 'salida') {
            tipoSpan.textContent = 'Salida';
            tipoSpan.className = 'badge bg-warning text-dark';
        } else {
            tipoSpan.textContent = 'Entrada';
            tipoSpan.className = 'badge bg-success';
        }
        document.getElementById('justify-select').value = '';
        justifyModal.show();
    }

    async function guardarJustificacion() {
        const bitacoraId = document.getElementById('justify-bitacora-id').value;
        const idJustificacion = document.getElementById('justify-select').value;

        if (!idJustificacion) {
            ScaModal.warning('Atención', 'Debe seleccionar una justificación.');
            return;
        }

        const btn = document.getElementById('btn-save-justify');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';

        try {
            const res = await fetch('{{ route("api.guardia.justificar") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: JSON.stringify({ bitacora_id: parseInt(bitacoraId), id_justificacion: parseInt(idJustificacion) })
            });
            const data = await res.json();

            if (data.success) {
                justifyModal.hide();
                ScaModal.success('Acceso autorizado', 'La justificación de horario fue aplicada correctamente. El empleado verá la pantalla de acceso autorizado en el kiosko.');
                fetchPending();
                cargarFeed();
            } else {
                ScaModal.error('Error', data.message || 'Error al justificar.');
            }
        } catch(e) {
            ScaModal.error('Error de conexión', 'No se pudo contactar con el servidor.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Justificar';
        }
    }

    // ============================================================
    // FIX Cambio 2: Rechazar acceso pendiente (justificación o F10)
    // ============================================================
    function rechazarPendiente(bitacoraId, nombre) {
        ScaModal.confirm({
            titulo: '¿Rechazar acceso?',
            mensaje: `Está por rechazar el acceso de <strong>${nombre}</strong>. La persona verá pantalla de denegado en el kiosko.`,
            tipo: 'danger',
            textoConfirmar: 'Sí, rechazar',
            onConfirm: async () => {
                try {
                    ScaModal.loading('Rechazando acceso', 'Procesando...');
                    const res = await fetch('{{ route("api.guardia.rechazar") }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                        body: JSON.stringify({ bitacora_id: parseInt(bitacoraId) })
                    });
                    const data = await res.json();
                    ScaModal.close();
                    if (data.success) {
                        ScaModal.success('Acceso rechazado', 'La persona verá pantalla de denegado.');
                        fetchPending();
                        cargarFeed();
                    } else {
                        ScaModal.error('Error', data.message || 'Error al rechazar.');
                    }
                } catch(e) {
                    ScaModal.close();
                    ScaModal.error('Error de conexión', 'No se pudo contactar con el servidor.');
                }
            }
        });
    }

    // ============================================================
    // MODAL: Resolver revisión F10
    // ============================================================
    function openResolveModal(bitacoraId, nombre, hora) {
        document.getElementById('modal-bitacora-id').value = bitacoraId;
        document.getElementById('modal-name').innerText = nombre;
        document.getElementById('modal-hora').innerText = hora;
        document.getElementById('modal-result').value = 'Revición Completada Correctamente';
        document.getElementById('modal-desc-wrapper').style.display = 'none';
        document.getElementById('modal-desc').value = '';
        resolveModal.show();
    }

    async function guardarResolucionF10() {
        const bitacoraId = document.getElementById('modal-bitacora-id').value;
        const resultado = document.getElementById('modal-result').value;
        const descripcion = document.getElementById('modal-desc').value;

        if (resultado === 'Se Encontro Hallazgo' && !descripcion.trim()) {
            ScaModal.warning('Atención', 'Debe describir el hallazgo encontrado.');
            return;
        }

        const btn = document.getElementById('btn-save-resolution');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';

        try {
            const res = await fetch('{{ route("api.guardia.resolve") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: JSON.stringify({
                    bitacora_id: parseInt(bitacoraId),
                    resultado: resultado,
                    descripcion: descripcion || null
                })
            });
            const data = await res.json();

            if (data.success) {
                resolveModal.hide();
                fetchPending();
                cargarFeed();
            } else {
                ScaModal.error('Error', data.message || 'Error al resolver.');
            }
        } catch(e) {
            ScaModal.error('Error de conexión', 'No se pudo contactar con el servidor.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
        }
    }

    // ============================================================
    // PERSONAS DENTRO
    // ============================================================
    async function cargarDentro() {
        try {
            const res = await fetch('{{ route("api.guardia.personas-dentro") }}', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                cache: 'no-store'
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();

            const badge = document.getElementById('count-dentro');
            const lista = document.getElementById('list-dentro');
            badge.textContent = data.total ?? 0;

            const esc = (v) => String(v ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'})[c]);
            const duracion = (m) => {
                const total = Math.max(0, Math.floor(Number(m) || 0));
                const d = Math.floor(total / 1440);
                const h = Math.floor((total % 1440) / 60);
                const min = total % 60;
                if (d > 0) {
                    if (h === 0 && min === 0) return `${d} d`;
                    if (min === 0) return `${d} d ${h} h`;
                    return `${d} d ${h} h ${min} min`;
                }
                if (h === 0) return `${min} min`;
                if (min === 0) return `${h} h`;
                return `${h} h ${min} min`;
            };

            const clasePermanencia = (m) => {
                const total = Math.max(0, Math.floor(Number(m) || 0));
                if (total > 720) return 'sca-permanencia-alerta';   // más de 12h → rojo
                if (total > 480) return 'sca-permanencia-atencion'; // entre 8-12h → ámbar
                return 'sca-permanencia-normal';                    // menos de 8h → verde
            };
            const fecha = (v) => {
                const hoy = @json(today()->toDateString());
                if (!v || v === hoy) return 'Hoy';
                const [y,m,d] = String(v).split('-');
                return `${d}/${m}/${y}`;
            };

            if (!data.total) {
                lista.innerHTML = '<div class="sca-presence-empty"><i class="bi bi-person-check"></i><strong>Sin personas dentro</strong><span>No hay una entrada autorizada pendiente de salida.</span></div>';
                return;
            }

            let html = `<div class="sca-presence-summary">
                <div class="sca-presence-types">
                    <span><i class="bi bi-person-badge"></i> ${esc(data.empleados)} empleados</span>
                    <span><i class="bi bi-person"></i> ${esc(data.visitantes)} externos</span>
                </div>
                <small class="sca-presence-plant"><i class="bi bi-geo-alt"></i> ${esc(data.planta_nombre || 'Planta')}</small>
            </div>`;

            html += '<div class="sca-presence-items">';
            data.personas.forEach(p => {
                const emp = p.tipo === 'empleado';
                const clase = emp ? 'is-employee' : 'is-external';
                const icono = emp ? 'bi-person-badge' : 'bi-person-vcard';
                const principal = emp
                    ? [p.puesto, p.departamento].filter(v => v && v !== '—').map(esc).join(' · ')
                    : (p.empresa && p.empresa !== '—' ? esc(p.empresa) : 'Externo');
                const secundaria = emp
                    ? `ID ${esc(p.id)}${p.turno && p.turno !== '—' ? ' · Turno ' + esc(p.turno) : ''}`
                    : (p.anfitrion && p.anfitrion !== '—' ? 'Anfitrión: ' + esc(p.anfitrion) : 'Solicitud externa');
                html += `
                    <article class="sca-presence-item">
                        <div class="sca-presence-avatar ${clase}"><i class="bi ${icono}"></i></div>
                        <div class="sca-presence-person">
                            <div class="sca-presence-name-row"><strong title="${esc(p.nombre)}">${esc(p.nombre)}</strong><span class="sca-presence-type ${clase}">${emp ? 'Empleado' : esc(p.puesto || 'Externo')}</span></div>
                            <span class="sca-presence-meta">${principal || 'Sin información adicional'}</span>
                            <span class="sca-presence-meta-secondary">${secundaria}</span>
                        </div>
                        <div class="sca-presence-time"><strong>${esc(p.hora_entrada)}</strong><span>${esc(fecha(p.fecha_entrada))}</span><small><i class="bi bi-hourglass-split"></i> ${esc(duracion(p.duracion_minutos))}</small></div>
                    </article>`;
            });
            html += '</div>';
            lista.innerHTML = html;
        } catch(e) {
            if (e instanceof SyntaxError) { window.location.href = '{{ route("login") }}'; }
            else {
                console.error('Error dentro:', e);
                document.getElementById('list-dentro').innerHTML = '<div class="sca-presence-empty sca-presence-error"><i class="bi bi-cloud-slash"></i><strong>No se pudo actualizar</strong><span>Comprueba la conexión e inténtalo de nuevo.</span></div>';
            }
        }
    }

    // ============================================================
    // FEED DE ACTIVIDAD
    // ============================================================
    let feedData = [];
    let feedFilter = 'todos';

    async function cargarFeed() {
        try {
            const res = await fetch('{{ route("api.guardia.feed") }}');
            feedData = await res.json();
            renderFeed();
        } catch(e) {
            if (e instanceof SyntaxError) { window.location.href = '{{ route("login") }}'; }
            else { console.error('Error feed:', e); }
        }
    }

    function renderFeed() {
        const container = document.getElementById('feed-list');
        if (!container) return;

        if (feedData.length === 0) {
            container.innerHTML = '<div class="text-center text-muted py-5"><i class="bi bi-activity fs-1 text-muted opacity-50"></i><p class="mt-2 mb-0">Sin actividad hoy</p></div>';
            return;
        }

        let datos = feedData;
        if (feedFilter !== 'todos') {
            if (feedFilter === 'denegado') {
                datos = feedData.filter(e => e.estado === 'denegado');
            } else {
                datos = feedData.filter(e => e.estado === 'autorizado' && e.evento === feedFilter);
            }
        }

        if (datos.length === 0) {
            container.innerHTML = `<div class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 text-muted opacity-50"></i><p class="mt-2 mb-0">Sin eventos de este tipo</p></div>`;
            return;
        }

        let html = '';
        datos.forEach(e => {
            let accion = matchAccion(e.estado, e.evento);

            // FIX V7 (Problema 3 y 4): badges con labels completos + colores
            // distintos según el tipo de persona. Para empleados, mostrar la
            // planta base (de dónde viene).
            let tipoBadge = '';
            if (e.tipo_persona === 'Emp') {
                // Empleado: verde + planta base si está disponible
                const plantaTxt = (e.planta_base && e.planta_base !== '—')
                    ? ` · ${e.planta_base}` : '';
                tipoBadge = `<span class="badge bg-success-subtle text-success border border-success-subtle">Emp${plantaTxt}</span>`;
            } else if (e.tipo_persona === 'Contratista') {
                // Contratista: morado (Bootstrap no tiene morado-subtle, usamos inline)
                tipoBadge = `<span class="badge border" style="background: rgba(111, 66, 193, 0.12); color: #6f42c1; border-color: rgba(111, 66, 193, 0.25) !important;">Contratista</span>`;
            } else {
                // Visita: naranja
                tipoBadge = `<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Visita</span>`;
            }

            let detalle = '';
            if (e.estado === 'denegado' && e.motivo) {
                detalle = `<div class="small text-danger mt-1"><i class="bi bi-exclamation-circle"></i> ${motivoTexto(e.motivo)}</div>`;
            }

            html += `
                <div class="d-flex align-items-center px-3 py-3 border-bottom" style="transition: background 0.2s;"
                     onmouseover="this.style.background='var(--sca-hover)'"
                     onmouseout="this.style.background='transparent'">
                    <div style="width: 70px;" class="text-center">
                        <div class="font-monospace fw-bold small">${e.hora}</div>
                    </div>
                    <div style="width: 50px;" class="text-center">
                        <div class="d-inline-flex align-items-center justify-content-center rounded-circle"
                             style="width: 36px; height: 36px; background: var(--sca-hover);">
                            <i class="bi ${e.icono} text-${e.color}"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 px-3">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <strong>${e.nombre}</strong>
                            ${tipoBadge}
                        </div>
                        ${detalle}
                    </div>
                    <div style="min-width: 110px;" class="text-end">
                        <span class="badge bg-${e.color}">${accion}</span>
                    </div>
                </div>
            `;
        });
        container.innerHTML = html;
    }

    function matchAccion(estado, evento) {
        if (estado === 'denegado') return 'Denegado';
        if (estado === 'pendiente_revision') return 'Revisión F10';
        if (estado === 'revision_horario') return 'Justificación';
        if (estado === 'autorizado') {
            return evento === 'entrada' ? 'Entrada' : 'Salida';
        }
        return estado;
    }

    function motivoTexto(motivo) {
        const map = {
            'sin_clasificar': 'Empleado sin clasificar',
            'sin_planta': 'Planta no configurada',
            'planta_no_autorizada': 'Planta no autorizada',
            'salida_temprana': 'Intentó salir antes de tiempo',
            'empleado_inactivo': 'Empleado inactivo',
            'manual_bloqueado': 'Búsqueda manual bloqueada',
            'credencial_expirada': 'Credencial expirada',
            // FIX Cambio 5: motivos nuevos de los Cambios 1, 2 y 3
            'pendiente_sin_resolver': 'Pendiente vieja sin resolver',
            'rechazado_guardia': 'Rechazado por el guardia',
            'cerrado_sin_resolver': 'Cerrado sin resolver al fin de turno',
            // FIX: motivos que faltaban del catálogo original
            'planta_inconsistente': 'Planta inconsistente (registre salida en la otra planta)',
            'sin_turno': 'Sin turno asignado',
            'dia_no_laboral': 'Día no laboral',
            'llegada_tardia': 'Llegada tardía',
            'salida_antes_turno': 'Salida antes de iniciar turno',
            'salida_tardia': 'Salida tardía'
        };
        return map[motivo] || motivo;
    }

// ============================================================
// M5 [ScaModal] — Terminar turno con confirm + loading + AJAX
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    const formTerminar = document.querySelector('.sca-form-terminar-turno');
    if (formTerminar) {
        formTerminar.addEventListener('submit', async function(e) {
            e.preventDefault();
            ScaModal.confirm({
                titulo: '¿Terminar turno?',
                mensaje: 'Se cerrarán <strong>TODOS</strong> sus kioskos activos y su sesión.<br><br><small class="text-muted">Esta acción no se puede deshacer.</small>',
                tipo: 'danger',
                textoConfirmar: 'Sí, terminar',
                textoCancelar: 'Cancelar',
                onConfirm: async () => {
                    ScaModal.loading('Terminando turno', 'Cerrando kioskos y sesión...');
                    try {
                        const res = await fetch(formTerminar.action, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            },
                            body: new FormData(formTerminar),
                        });
                        const data = await res.json();
                        if (data.success) {
                            window.location.href = data.redirect;
                        } else {
                            ScaModal.close();
                            ScaModal.error('Error', data.mensaje || 'No se pudo terminar el turno.');
                        }
                    } catch(err) {
                        ScaModal.close();
                        ScaModal.error('Error de conexión', 'No se pudo contactar con el servidor.');
                    }
                }
            });
        });
    }
});
</script>
@endpush
@endsection