{{-- resources/views/guardia/dashboard.blade.php --}}
@extends('layouts.app')

@section('title', 'Panel de Guardia')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-0">Panel de Guardia</h3>
            <p class="text-muted mb-0">
                Planta: <strong>{{ $turnoActivo?->planta?->nombre ?? 'Sin asignar' }}</strong> | 
                Turno iniciado: <strong>{{ $turnoActivo?->fecha_hora_inicio->format('H:i') ?? '---' }}</strong>
            </p>
        </div>
        
        <form method="POST" action="{{ route('guardia.terminar.turno') }}" id="form-terminar-turno" 
              onsubmit="return confirm('¿Está seguro de terminar su turno?\n\nSe cerrará su sesión y el kiosko quedará desactivado.\n\nEsta acción no se puede deshacer.')">
            @csrf
            <button type="submit" class="btn btn-danger">
                <i class="bi bi-box-arrow-right"></i> Terminar Turno
            </button>
        </form>
    </div>

    <div class="row g-3">
        
        {{-- COL 1: Alertas pendientes --}}
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100 sca-card" style="border-radius: 12px;">
                <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-bell text-warning"></i> Alertas Pendientes</h5>
                    <span class="badge bg-warning text-dark" id="count-revisions">0</span>
                </div>
                <div class="card-body" id="list-revisions" style="max-height: 50vh; overflow-y: auto;">
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-check-circle fs-1 text-success"></i>
                        <p class="mt-2 mb-0">Sin alertas pendientes</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- COL 2: Personas dentro --}}
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100 sca-card" style="border-radius: 12px;">
                <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-people-fill text-success"></i> Personas Dentro</h5>
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

        {{-- COL 3: Denegados --}}
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100 sca-card" id="card-denegados" style="border-radius: 12px; display:none;">
                <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-shield-exclamation text-danger"></i> Accesos Denegados</h5>
                    <span class="badge bg-danger" id="badge-denegados">0</span>
                </div>
                <div class="card-body" id="list-denegados" style="max-height: 40vh; overflow-y: auto;">
                </div>
            </div>
        </div>

        {{-- COL 4: Escaneo (Cámara + Manual) --}}
        <div class="col-md-6">
            <div class="card border-0 shadow-sm sca-card" style="border-radius: 12px;">
                <div class="card-header bg-transparent py-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-qr-code-scan text-success"></i> Escaneo Rápido</h5>
                </div>
                <div class="card-body">
                    {{-- Botón de cámara --}}
                    <button class="btn btn-outline-success w-100 btn-lg mb-2" id="btn-toggle-camera">
                        <i class="bi bi-camera"></i> Abrir Cámara
                    </button>

                    {{-- Divider --}}
                    <div class="d-flex align-items-center my-3">
                        <hr class="flex-grow-1">
                        <span class="px-2 text-muted small">o búsqueda manual</span>
                        <hr class="flex-grow-1">
                    </div>

                    {{-- Búsqueda manual --}}
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

        {{-- COL 5: Feed de actividad reciente con filtros --}}
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

{{-- Cámara flotante para escaneo --}}
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

{{-- Modal para resolver pendiente --}}
<div class="modal fade" id="resolveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content sca-modal-content" style="border-radius: 16px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Atender Alerta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <p>Tipo: <strong id="modal-type"></strong></p>
                <p>Empleado: <strong id="modal-name"></strong></p>
                
                <div class="mb-3" id="modal-user-justification-wrapper" style="display: none;">
                    <label class="form-label">Justificación del Empleado:</label>
                    <textarea class="form-control" id="modal-user-justification" rows="2" readonly></textarea>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Resultado de la revisión</label>
                    <select class="form-select" id="modal-result">
                        <option value="todo_bien">Todo bien</option>
                        <option value="anomalia">Anomalía encontrada</option>
                    </select>
                </div>
                <div class="mb-3" id="modal-desc-wrapper" style="display: none;">
                    <label class="form-label">Descripción del hallazgo</label>
                    <textarea class="form-control" id="modal-desc" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <input type="hidden" id="modal-bitacora-id">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="btn-save-resolution">Guardar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>

@push('scripts')
<script>
    let resolveModal;
    let cameraStreamGuardia = null;
    let isProcessingGuardia = false;

    document.addEventListener('DOMContentLoaded', function() {
        resolveModal = new bootstrap.Modal(document.getElementById('resolveModal'));

        // Polling de alertas pendientes
        fetchPending();
        setInterval(fetchPending, 5000);

        // Polling de denegados cada 10s
        cargarDenegados();
        setInterval(cargarDenegados, 10000);

        // Polling de personas dentro cada 10s
        cargarDentro();
        setInterval(cargarDentro, 10000);

        // Polling de feed cada 5s
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

        document.getElementById('modal-result').addEventListener('change', function() {
            document.getElementById('modal-desc-wrapper').style.display = this.value === 'anomalia' ? 'block' : 'none';
        });

        document.getElementById('btn-save-resolution').addEventListener('click', async () => {
            const bitacoraId = document.getElementById('modal-bitacora-id').value;
            const resultado = document.getElementById('modal-result').value;
            const descripcion = document.getElementById('modal-desc').value;

            await fetch('/api/guardia/resolve', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ bitacora_id: bitacoraId, resultado, descripcion })
            });
            resolveModal.hide();
            fetchPending();
        });
    });

    // ===== Escaneo desde el dashboard del guardia =====
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
                        procesarDesdeGuardia(code.data, 'kiosco');
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
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ codigo, origen })
            });
            const data = await res.json();
            if (data.success) {
                resDiv.innerHTML = `<div class="alert alert-success d-flex align-items-center"><img src="${data.persona.foto}" class="rounded-circle me-3" width="50" height="50"><div><strong>${data.persona.nombre}</strong><br>${data.message}</div></div>`;
            } else {
                resDiv.innerHTML = `<div class="alert alert-danger"><i class="bi bi-x-circle"></i> ${data.message}</div>`;
            }
        } catch(e) {
            resDiv.innerHTML = `<div class="alert alert-danger">Error de conexión</div>`;
        } finally {
            setTimeout(() => { isProcessingGuardia = false; }, 1500);
        }
    }

    // ===== Alertas pendientes =====
    async function fetchPending() {
        try {
            const res = await fetch('/api/guardia/pending');
            const data = await res.json();
            
            let html = '';
            let count = 0;
            
            data.forEach(item => {
                count++;
                let bgClass = item.tipo === 'revision' ? 'alert-warning' : 'alert-info';
                let icon = item.tipo === 'revision' ? 'bi-shield-exclamation' : 'bi-exclamation-triangle';
                let text = item.tipo === 'revision' ? 'Revisión aleatoria' : 'Anomalía de horario';
                
                html += `
                    <div class="alert ${bgClass} d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <strong><i class="bi ${icon} me-1"></i> ${item.nombre}</strong> 
                            <small class="text-muted d-block">${text} - ${item.hora}</small>
                        </div>
                        <button class="btn btn-sm btn-success" onclick="openResolveModal(${item.bitacora_id}, '${item.nombre}', '${item.tipo}', '${item.justificacion}')">Atender</button>
                    </div>
                `;
            });
            
            document.getElementById('count-revisions').innerText = count;
            document.getElementById('list-revisions').innerHTML = count > 0 ? html : '<div class="text-center text-muted py-5"><i class="bi bi-check-circle fs-1 text-success"></i><p class="mt-2 mb-0">Sin alertas pendientes</p></div>';
        } catch(e) {
            console.error('Error:', e);
        }
    }

    // ===== Denegados =====
    async function cargarDenegados() {
        try {
            const res = await fetch('{{ route("api.guardia.denegados") }}');
            const data = await res.json();

            const card = document.getElementById('card-denegados');
            const badge = document.getElementById('badge-denegados');
            const lista = document.getElementById('list-denegados');

            if (!card) return;

            if (data.length === 0) {
                card.style.display = 'none';
                return;
            }

            card.style.display = 'block';
            badge.textContent = data.length;

            let html = '';
            data.forEach(d => {
                html += `
                    <div class="alert alert-danger d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <strong><i class="bi bi-x-circle me-1"></i> ${d.nombre}</strong>
                            <small class="text-muted d-block">${d.motivo} - ${d.hora}</small>
                        </div>
                        <button class="btn btn-sm btn-outline-secondary" onclick="marcarVisto(${d.bitacora_id}, this)" title="Marcar como revisado">
                            <i class="bi bi-check2"></i>
                        </button>
                    </div>
                `;
            });
            lista.innerHTML = html;
        } catch(e) {
            console.error('Error denegados:', e);
        }
    }

    async function marcarVisto(bitacoraId, btn) {
        if (!confirm('¿Marcar como revisado?')) return;
        
        const card = btn.closest('.alert');
        card.style.transition = 'opacity 0.2s';
        card.style.opacity = '0.3';
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        
        try {
            const res = await fetch('{{ route("api.guardia.marcar-visto") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ bitacora_id: bitacoraId })
            });
            const data = await res.json();
            
            if (data.success) {
                card.remove();
                const badge = document.getElementById('badge-denegados');
                const nuevoTotal = parseInt(badge.textContent) - 1;
                badge.textContent = nuevoTotal;
                if (nuevoTotal === 0) {
                    document.getElementById('card-denegados').style.display = 'none';
                }
            } else {
                card.style.opacity = '1';
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check2"></i>';
                alert(data.message || 'Error al marcar');
            }
        } catch(e) {
            card.style.opacity = '1';
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check2"></i>';
            alert('Error de conexión');
        }
    }

    // ===== Personas dentro =====
    async function cargarDentro() {
        try {
            const res = await fetch('{{ route("api.guardia.dentro") }}');
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

    // ===== Feed de actividad reciente (con filtros) =====
    let feedData = [];
    let feedFilter = 'todos';

    // Filtros (botones Todos/Entradas/Salidas/Denegados)
    document.querySelectorAll('#feed-filter button').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('#feed-filter button').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            feedFilter = this.dataset.filter;
            renderFeed();
        });
    });

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
        if (!container) {
            console.warn('Elemento #feed-list no encontrado');
            return;
        }

        if (feedData.length === 0) {
            container.innerHTML = '<div class="text-center text-muted py-5"><i class="bi bi-activity fs-1 text-muted opacity-50"></i><p class="mt-2 mb-0">Sin actividad hoy</p></div>';
            return;
        }

        // Filtrar según el botón activo
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
                ? '<span class="badge bg-success-subtle text-success border border-success-subtle ms-1">Emp</span>' 
                : '<span class="badge bg-info-subtle text-info border border-info-subtle ms-1">Vis</span>';

            let detalle = '';
            if (e.estado === 'denegado' && e.motivo) {
                detalle = `<div class="small text-danger mt-1"><i class="bi bi-exclamation-circle"></i> ${motivoTexto(e.motivo)}</div>`;
            }

            let origenBadge = e.origen === 'manual' 
                ? '<span class="badge bg-secondary ms-1">Manual</span>' 
                : '';

            html += `
                <div class="d-flex align-items-center px-3 py-2" style="border-bottom: 1px solid var(--sca-border);">
                    <div class="me-3">
                        <i class="bi ${e.icono} text-${e.color} fs-4"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center flex-wrap gap-1">
                            <strong>${e.nombre}</strong>
                            <span class="badge bg-${e.color}">${accion}</span>
                            ${tipoBadge}
                            ${origenBadge}
                        </div>
                        ${detalle}
                    </div>
                    <div class="text-end ms-2">
                        <small class="text-muted font-monospace">${e.hora}</small>
                    </div>
                </div>
            `;
        });
        container.innerHTML = html;
    }

    function matchAccion(estado, evento) {
        if (estado === 'denegado') return 'Denegado';
        if (estado === 'pendiente_revision') return 'Revisión';
        if (estado === 'revision_completada') return 'Revisado';
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
            'salida_temprana': 'Intentó salir antes de tiempo'
        };
        return map[motivo] || motivo;
    }

    function matchEstado(estado, evento) {
        if (estado === 'denegado') return 'Denegado';
        if (estado === 'pendiente_revision') return 'Revisión';
        if (estado === 'revision_completada') return 'Revisado';
        if (estado === 'autorizado') {
            return evento === 'entrada' ? 'Entrada' : 'Salida';
        }
        return estado;
    }

    function openResolveModal(bitacoraId, nombre, tipo, justificacion) {
        document.getElementById('modal-bitacora-id').value = bitacoraId;
        document.getElementById('modal-name').innerText = nombre;
        document.getElementById('modal-type').innerText = tipo === 'revision' ? 'Revisión Aleatoria' : 'Anomalía de Horario';
        document.getElementById('modal-result').value = 'todo_bien';
        document.getElementById('modal-desc-wrapper').style.display = 'none';
        document.getElementById('modal-desc').value = '';
        
        if(tipo === 'anomalia' && justificacion) {
            document.getElementById('modal-user-justification').value = justificacion;
            document.getElementById('modal-user-justification-wrapper').style.display = 'block';
        } else {
            document.getElementById('modal-user-justification-wrapper').style.display = 'none';
        }
        
        resolveModal.show();
    }
</script>
@endpush
@endsection