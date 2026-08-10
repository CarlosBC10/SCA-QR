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
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-0">Panel de Guardia</h3>
            <p class="text-muted mb-0">
                <i class="bi bi-person-badge"></i> {{ Auth::user()->nombre_completo }} ·
                <i class="bi bi-geo-alt"></i> {{ Auth::user()->planta_nombre ?? 'Sin asignar' }}
            </p>
        </div>

        <form method="POST" action="{{ route('guardia.terminar.turno') }}" id="form-terminar-turno"
              onsubmit="return confirm('¿Está seguro de terminar su turno?\n\nSe cerrarán TODOS sus kioskos activos y su sesión.\n\nEsta acción no se puede deshacer.')">
            @csrf
            <button type="submit" class="btn btn-danger">
                <i class="bi bi-box-arrow-right"></i> Terminar Turno
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
                    <h5 class="mb-0 fw-bold"><i class="bi bi-shield-exclamation text-warning"></i> Revisiones F10</h5>
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
<div class="camera-floating-guardia" id="camera-view-guardia" style="display:none; position:fixed; bottom:20px; right:20px; width:280px; height:210px; z-index:9998; border-radius:12px; overflow:hidden; box-shadow:0 8px 24px rgba(0,0,0,0.4); border:2px solid var(--sca-border);">
    <video id="video-guardia" playsinline style="width:100%; height:100%; object-fit:cover; display:block; background:#000;"></video>
    <div style="position:absolute; top:0; left:0; right:0; height:3px; background:var(--sca-primary); animation:scanLine 2s linear infinite;"></div>
    <button type="button" onclick="toggleCameraGuardia()" style="position:absolute; top:6px; right:6px; background:rgba(0,0,0,0.6); color:#fff; border:none; border-radius:50%; width:26px; height:26px; font-size:0.9rem; cursor:pointer; display:flex; align-items:center; justify-content:center;">
        <i class="bi bi-x"></i>
    </button>
    <div style="position:absolute; bottom:6px; left:6px; background:rgba(0,0,0,0.6); color:#fff; padding:2px 8px; border-radius:6px; font-size:0.7rem;">
        <i class="bi bi-camera-video"></i> Apunte al QR
    </div>
</div>

<style>
@keyframes scanLine {
    0% { top: 0; }
    50% { top: calc(100% - 3px); }
    100% { top: 0; }
}
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
                <p class="small text-muted">Hora del acceso: <span id="justify-hora"></span></p>

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
                <h5 class="modal-title fw-bold"><i class="bi bi-shield-exclamation text-warning"></i> Revisión F10</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <p>Empleado: <strong id="modal-name"></strong></p>
                <p class="small text-muted">Hora del acceso: <span id="modal-hora"></span></p>

                <div class="mb-3">
                    <label class="form-label">Resultado de la revisión</label>
                    <select class="form-select" id="modal-result">
                        <option value="todo_bien">Todo bien</option>
                        <option value="anomalia">Anomalía encontrada</option>
                    </select>
                </div>
                <div class="mb-3" id="modal-desc-wrapper" style="display: none;">
                    <label class="form-label">Descripción del hallazgo <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="modal-desc" rows="3" placeholder="Describa la anomalía..."></textarea>
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

        // Búsqueda manual
        document.getElementById('btn-manual-search').addEventListener('click', async () => {
            const id = document.getElementById('manual-id').value;
            if(!id) return;
            procesarDesdeGuardia(id, 'manual');
        });

        // Modal F10: mostrar/ocultar descripción según resultado
        document.getElementById('modal-result').addEventListener('change', function() {
            document.getElementById('modal-desc-wrapper').style.display = this.value === 'anomalia' ? 'block' : 'none';
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
            // Asumimos que hay una ruta API; si no, se puede agregar en el controlador
            // Por ahora usamos una ruta directa al modelo
            const res = await fetch('/api/justificaciones');
            const data = await res.json();
            const select = document.getElementById('justify-select');
            select.innerHTML = '<option value="">Seleccione una opción...</option>';
            data.forEach(j => {
                select.innerHTML += `<option value="${j.id_justificacion}">${j.nombre}</option>`;
            });
        } catch(e) {
            console.error('Error cargando justificaciones:', e);
            document.getElementById('justify-select').innerHTML = '<option value="">Error al cargar</option>';
        }
    }

    // ============================================================
    // CÁMARA Y ESCANEO DESDE PANEL
    // ============================================================
    async function toggleCameraGuardia() {
        const view = document.getElementById('camera-view-guardia');
        const btn = document.getElementById('btn-toggle-camera');

        if (cameraStreamGuardia) {
            cameraStreamGuardia.getTracks().forEach(track => track.stop());
            cameraStreamGuardia = null;
            view.style.display = 'none';
            btn.innerHTML = '<i class="bi bi-camera"></i> Abrir Cámara';
            btn.classList.remove('btn-danger');
            btn.classList.add('btn-outline-success');
            return;
        }

        try {
            view.style.display = 'block';
            const video = document.getElementById('video-guardia');
            cameraStreamGuardia = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
            video.srcObject = cameraStreamGuardia;
            video.play();
            btn.innerHTML = '<i class="bi bi-camera-video-off"></i> Cerrar Cámara';
            btn.classList.remove('btn-outline-success');
            btn.classList.add('btn-danger');

            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d');
            const tick = () => {
                if (!cameraStreamGuardia) return;
                if (!isProcessingGuardia && video.readyState === video.HAVE_ENOUGH_DATA) {
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    context.drawImage(video, 0, 0, canvas.width, canvas.height);
                    const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
                    const code = jsQR(imageData.data, imageData.width, imageData.height);
                    if (code) {
                        procesarDesdeGuardia(code.data, 'guardia');
                    }
                }
                requestAnimationFrame(tick);
            };
            requestAnimationFrame(tick);
        } catch(e) {
            alert('No se pudo acceder a la cámara: ' + e.message);
            view.style.display = 'none';
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
                justList.innerHTML = justificaciones.map(item => `
                    <div class="alert alert-primary d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <strong><i class="bi bi-clock-history me-1"></i> ${item.nombre}</strong>
                            <small class="text-muted d-block">Acceso fuera de horario - ${item.hora}</small>
                        </div>
                        <button class="btn btn-sm btn-primary" onclick="openJustifyModal(${item.bitacora_id}, '${item.nombre.replace(/'/g, "\\'")}', '${item.hora}')">
                            <i class="bi bi-check-lg"></i> Justificar
                        </button>
                    </div>
                `).join('');
            }

            // Render revisiones F10
            const revList = document.getElementById('list-revisions');
            const revCount = document.getElementById('count-revisions');
            revCount.textContent = revisions.length;

            if (revisions.length === 0) {
                revList.innerHTML = '<div class="text-center text-muted py-5"><i class="bi bi-check-circle fs-1 text-success opacity-50"></i><p class="mt-2 mb-0">Sin revisiones pendientes</p></div>';
            } else {
                revList.innerHTML = revisions.map(item => `
                    <div class="alert alert-warning d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <strong><i class="bi bi-shield-exclamation me-1"></i> ${item.nombre}</strong>
                            <small class="text-muted d-block">Revisión aleatoria F10 - ${item.hora}</small>
                        </div>
                        <button class="btn btn-sm btn-success" onclick="openResolveModal(${item.bitacora_id}, '${item.nombre.replace(/'/g, "\\'")}', '${item.hora}')">
                            <i class="bi bi-check-lg"></i> Atender
                        </button>
                    </div>
                `).join('');
            }
        } catch(e) {
            console.error('Error fetchPending:', e);
        }
    }

    // ============================================================
    // MODAL: Justificar anomalía de horario
    // ============================================================
    function openJustifyModal(bitacoraId, nombre, hora) {
        document.getElementById('justify-bitacora-id').value = bitacoraId;
        document.getElementById('justify-name').innerText = nombre;
        document.getElementById('justify-hora').innerText = hora;
        document.getElementById('justify-select').value = '';
        justifyModal.show();
    }

    async function guardarJustificacion() {
        const bitacoraId = document.getElementById('justify-bitacora-id').value;
        const idJustificacion = document.getElementById('justify-select').value;

        if (!idJustificacion) {
            alert('Debe seleccionar una justificación.');
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
                fetchPending();
                cargarFeed();
            } else {
                alert(data.message || 'Error al justificar.');
            }
        } catch(e) {
            alert('Error de conexión');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Justificar';
        }
    }

    // ============================================================
    // MODAL: Resolver revisión F10
    // ============================================================
    function openResolveModal(bitacoraId, nombre, hora) {
        document.getElementById('modal-bitacora-id').value = bitacoraId;
        document.getElementById('modal-name').innerText = nombre;
        document.getElementById('modal-hora').innerText = hora;
        document.getElementById('modal-result').value = 'todo_bien';
        document.getElementById('modal-desc-wrapper').style.display = 'none';
        document.getElementById('modal-desc').value = '';
        resolveModal.show();
    }

    async function guardarResolucionF10() {
        const bitacoraId = document.getElementById('modal-bitacora-id').value;
        const resultado = document.getElementById('modal-result').value;
        const descripcion = document.getElementById('modal-desc').value;

        if (resultado === 'anomalia' && !descripcion.trim()) {
            alert('Debe describir la anomalía.');
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
                alert(data.message || 'Error al resolver.');
            }
        } catch(e) {
            alert('Error de conexión');
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
            const res = await fetch('{{ route("api.guardia.personas-dentro") }}');
            const data = await res.json();

            const badge = document.getElementById('count-dentro');
            const lista = document.getElementById('list-dentro');

            badge.textContent = data.total;

            if (data.total === 0) {
                lista.innerHTML = '<div class="text-center text-muted py-5"><i class="bi bi-people fs-1 text-muted"></i><p class="mt-2 mb-0">Sin personas dentro</p></div>';
                return;
            }

            let html = `<div class="d-flex gap-2 mb-3">
                <span class="badge bg-success">Empleados: ${data.empleados}</span>
                <span class="badge bg-info text-dark">Visitantes: ${data.visitantes}</span>
            </div>`;

            html += '<div class="list-group list-group-flush">';
            data.personas.forEach(p => {
                let icono = p.tipo === 'empleado' ? 'bi-person-badge' : 'bi-person';
                let color = p.tipo === 'empleado' ? 'success' : 'info';
                html += `
                    <div class="list-group-item list-group-item-action px-0 py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi ${icono} text-${color} me-2"></i>
                                <strong>${p.nombre}</strong>
                                <small class="text-muted d-block ms-4">${p.puesto}</small>
                            </div>
                            <div class="text-end">
                                <small class="text-muted">Entró:</small>
                                <span class="badge bg-light text-dark">${p.hora_entrada}</span>
                            </div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';

            lista.innerHTML = html;
        } catch(e) {
            console.error('Error dentro:', e);
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
            console.error('Error feed:', e);
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

            let tipoBadge = e.tipo_persona === 'Emp'
                ? '<span class="badge bg-success-subtle text-success border border-success-subtle">Emp</span>'
                : '<span class="badge bg-info-subtle text-info border border-info-subtle">Vis</span>';

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
            'credencial_expirada': 'Credencial expirada'
        };
        return map[motivo] || motivo;
    }
</script>
@endpush
@endsection