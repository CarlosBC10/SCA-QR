{{-- resources/views/puestos/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Clasificar Puestos')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-0 fw-bold">Clasificación de Puestos</h1>
    <p class="text-muted mb-0">Asigne el tipo de empleado a cada puesto. Al clasificar, se actualizan todos los empleados con ese puesto.</p>
</div>

@php
    $totalPuestos = $puestos->count();
    $clasificados = $clasificaciones->count();
    $sinClasificar = $totalPuestos - $clasificados;
    $operativos = $clasificaciones->filter(fn($t) => $t === 'operativo')->count();
    $administrativos = $clasificaciones->filter(fn($t) => $t === 'administrativo')->count();
    $corporativos = $clasificaciones->filter(fn($t) => $t === 'corporativo')->count();
@endphp

{{-- Stats --}}
<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm h-100 sca-card" style="border-radius: 12px;">
            <div class="card-body d-flex align-items-center">
                <div class="rounded-3 p-3 me-3" style="background: rgba(108,117,125,0.15);">
                    <i class="bi bi-list-ul fs-4 text-secondary"></i>
                </div>
                <div>
                    <div class="h3 fw-bold mb-0" id="stat-total">{{ $totalPuestos }}</div>
                    <div class="small text-muted">Total puestos</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm h-100 sca-card" style="border-radius: 12px;">
            <div class="card-body d-flex align-items-center">
                <div class="rounded-3 p-3 me-3" style="background: rgba(245,124,0,0.15);">
                    <i class="bi bi-hard-hat fs-4" style="color:#f57c00;"></i>
                </div>
                <div>
                    <div class="h3 fw-bold mb-0" style="color:#f57c00;" id="stat-operativo">{{ $operativos }}</div>
                    <div class="small text-muted">Operativos</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm h-100 sca-card" style="border-radius: 12px;">
            <div class="card-body d-flex align-items-center">
                <div class="rounded-3 p-3 me-3" style="background: rgba(23,162,184,0.15);">
                    <i class="bi bi-briefcase fs-4" style="color:#17a2b8;"></i>
                </div>
                <div>
                    <div class="h3 fw-bold mb-0" style="color:#17a2b8;" id="stat-administrativo">{{ $administrativos }}</div>
                    <div class="small text-muted">Administrativos</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm h-100 sca-card" style="border-radius: 12px;">
            <div class="card-body d-flex align-items-center">
                <div class="rounded-3 p-3 me-3" style="background: rgba(40,167,69,0.15);">
                    <i class="bi bi-shield-check fs-4 text-success"></i>
                </div>
                <div>
                    <div class="h3 fw-bold mb-0 text-success" id="stat-corporativo">{{ $corporativos }}</div>
                    <div class="small text-muted">Corporativos</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Barra de progreso dinámica --}}
@if($totalPuestos > 0)
<div class="card border-0 shadow-sm mb-4 sca-card" style="border-radius: 12px;">
    <div class="card-body py-3">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="small fw-semibold">Progreso de clasificación</span>
            <span class="small text-muted" id="progress-text">{{ $clasificados }}/{{ $totalPuestos }} ({{ round($clasificados/$totalPuestos*100) }}%)</span>
        </div>
        <div class="progress" style="height:10px; border-radius:5px; background: var(--sca-hover);">
            <div class="progress-bar bg-success" role="progressbar" id="progress-bar"
                 style="width: {{ $clasificados/$totalPuestos*100 }}%; border-radius:5px; transition: width 0.6s ease;"></div>
        </div>
    </div>
</div>
@endif

@if($sinClasificar > 0)
<div class="alert d-flex align-items-center mb-4 sca-alert-warning" style="border-radius:12px;">
    <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
    <div class="flex-grow-1">
        <strong>{{ $sinClasificar }} puestos sin clasificar.</strong>
        Los empleados con esos puestos pasarán como "No clasificados" y no podrán escanear.
    </div>
</div>
@endif

{{-- FIX J-2: Barra de acciones masivas --}}
<div class="card border-0 shadow-sm mb-4 sca-card" style="border-radius:12px; position: sticky; top: 70px; z-index: 100;">
    <div class="card-body py-2">
        <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <input type="checkbox" id="select-all" class="form-check-input" style="cursor:pointer;">
                <label for="select-all" class="form-check-label small fw-semibold" style="cursor:pointer;">Seleccionar todos</label>
                <span class="badge bg-secondary ms-2" id="selected-count">0 seleccionados</span>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-warning" id="btn-masivo-operativo" disabled>
                    <i class="bi bi-hard-hat"></i> Masivo Operativo
                </button>
                <button type="button" class="btn btn-sm btn-outline-info" id="btn-masivo-administrativo" disabled>
                    <i class="bi bi-briefcase"></i> Masivo Admin
                </button>
                <button type="button" class="btn btn-sm btn-outline-success" id="btn-masivo-corporativo" disabled>
                    <i class="bi bi-shield-check"></i> Masivo Corporativo
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Filtros --}}
<div class="card border-0 shadow-sm mb-4 sca-card" style="border-radius:12px;">
    <div class="card-body py-3">
        <div class="row g-2 align-items-center">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input type="text" id="searchPuesto" class="form-control border-start-0" placeholder="Buscar puesto...">
                </div>
            </div>
            <div class="col-md-6">
                <div class="btn-group w-100" role="group" id="filterGroup">
                    <button type="button" class="btn btn-outline-secondary active" data-filter="todos">Todos</button>
                    <button type="button" class="btn btn-outline-warning" data-filter="operativo">Operativos</button>
                    <button type="button" class="btn btn-outline-info" data-filter="administrativo">Admin</button>
                    <button type="button" class="btn btn-outline-success" data-filter="corporativo">Corporativos</button>
                    <button type="button" class="btn btn-outline-danger" data-filter="sin">Sin clasificar</button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Grid de puestos --}}
<div class="row g-3" id="gridPuestos">
    @foreach($puestos as $puesto)
    @php
        $tipoActual = $clasificaciones->get($puesto->id_puesto);
        $badgeClass = match($tipoActual) {
            'operativo' => 'bg-warning text-dark',
            'administrativo' => 'bg-info text-dark',
            'corporativo' => 'bg-success',
            default => 'bg-light text-muted border',
        };
        $icon = match($tipoActual) {
            'operativo' => 'bi-hard-hat',
            'administrativo' => 'bi-briefcase',
            'corporativo' => 'bi-shield-check',
            default => 'bi-question-circle',
        };
        $borderClass = match($tipoActual) {
            'operativo' => 'border-left-warning',
            'administrativo' => 'border-left-info',
            'corporativo' => 'border-left-success',
            default => 'border-left-muted',
        };
        $dataFilter = $tipoActual ?? 'sin';
    @endphp
    <div class="col-md-6 col-lg-4 puesto-card-wrapper" data-filter="{{ $dataFilter }}" data-nombre="{{ strtolower($puesto->nombre) }}">
        <div class="card border-0 shadow-sm h-100 {{ $borderClass }} card-puesto"
             style="border-radius:12px; border-left:4px solid !important; transition: all 0.3s ease;"
             data-puesto-id="{{ $puesto->id_puesto }}" data-tipo-actual="{{ $tipoActual ?? '' }}">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div class="d-flex align-items-start gap-2 flex-grow-1">
                        <input type="checkbox" class="form-check-input puesto-check mt-1" value="{{ $puesto->id_puesto }}" style="cursor:pointer;">
                        <div class="flex-grow-1">
                            <h6 class="mb-0 fw-bold">{{ $puesto->nombre }}</h6>
                            <small class="text-muted">ID: <span class="font-monospace">{{ $puesto->id_puesto }}</span></small>
                        </div>
                    </div>
                    <span class="badge {{ $badgeClass }} px-2 py-1 tipo-badge">
                        <i class="bi {{ $icon }}"></i>
                        <span class="tipo-label">@if($tipoActual){{ ucfirst($tipoActual) }}@else Sin clasificar @endif</span>
                    </span>
                </div>

                <div class="mb-3">
                    <small class="text-muted d-block mb-1">Empleados con este puesto:</small>
                    @php $conteo = $conteoEmpleados->get($puesto->id_puesto, 0); @endphp
                    <span class="badge bg-secondary count-badge" id="count-{{ $puesto->id_puesto }}" data-count="{{ $conteo }}">
                        <i class="bi bi-people"></i> {{ $conteo }} empleado{{ $conteo == 1 ? '' : 's' }}
                    </span>
                    <button type="button" class="btn btn-sm btn-link py-0 px-2 text-decoration-none" onclick="verEmpleadosPuesto({{ $puesto->id_puesto }}, '{{ addslashes($puesto->nombre) }}')">
                        <i class="bi bi-eye"></i> Ver
                    </button>
                </div>

                <div class="btn-group w-100" role="group">
                    <button type="button" class="btn btn-sm btn-outline-warning tipo-btn {{ $tipoActual === 'operativo' ? 'active' : '' }}" data-puesto="{{ $puesto->id_puesto }}" data-tipo="operativo">
                        <i class="bi bi-hard-hat"></i> Operativo
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-info tipo-btn {{ $tipoActual === 'administrativo' ? 'active' : '' }}" data-puesto="{{ $puesto->id_puesto }}" data-tipo="administrativo">
                        <i class="bi bi-briefcase"></i> Admin
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success tipo-btn {{ $tipoActual === 'corporativo' ? 'active' : '' }}" data-puesto="{{ $puesto->id_puesto }}" data-tipo="corporativo">
                        <i class="bi bi-shield-check"></i> Corp
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

@if($puestos->isEmpty())
<div class="text-center py-5">
    <i class="bi bi-inbox fs-1 text-muted"></i>
    <p class="text-muted mt-2">No hay puestos con empleados activos para clasificar.</p>
</div>
@endif

{{-- Modal de confirmación individual --}}
<div class="modal fade" id="confirmModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content sca-modal-content" style="border-radius:16px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-shield-exclamation text-warning"></i> <span id="modal-title-text">Confirmar</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <p class="mb-3" id="modal-message">¿Está seguro?</p>
                <div class="confirm-count" id="modal-count-box">
                    <div class="d-flex align-items-center justify-content-center gap-3">
                        <i class="bi bi-people-fill fs-1 text-warning"></i>
                        <div>
                            <div class="num" id="modal-count-num">0</div>
                            <div class="small text-muted">empleados serán actualizados</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="modal-confirm-btn"><span class="btn-text"><i class="bi bi-check-lg"></i> Sí, clasificar</span></button>
            </div>
        </div>
    </div>
</div>

{{-- FIX J-2: Modal de confirmación masiva --}}
<div class="modal fade" id="modalMasivo" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content sca-modal-content" style="border-radius:16px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-stack text-warning"></i> Confirmar clasificación masiva</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <p id="modal-masivo-message">¿Está seguro?</p>
                <div class="confirm-count">
                    <div class="d-flex align-items-center justify-content-center gap-3">
                        <i class="bi bi-stack fs-1 text-warning"></i>
                        <div>
                            <div class="num" id="modal-masivo-num">0</div>
                            <div class="small text-muted">puestos serán reclasificados</div>
                        </div>
                    </div>
                </div>
                <div class="mt-3 small text-muted"><i class="bi bi-info-circle"></i> Todos los empleados con los puestos seleccionados serán actualizados. Esto puede tardar varios segundos.</div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="modal-masivo-confirm-btn"><span class="btn-text"><i class="bi bi-check-lg"></i> Sí, clasificar todos</span></button>
            </div>
        </div>
    </div>
</div>

{{-- FIX J-3: Modal para ver empleados del puesto --}}
<div class="modal fade" id="modalEmpleadosPuesto" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content sca-modal-content" style="border-radius:16px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-people text-success"></i> <span id="modal-emp-title">Empleados del puesto</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <div id="modal-emp-loading" class="text-center py-4"><span class="spinner-border spinner-border-lg text-success"></span><p class="text-muted mt-2">Cargando empleados...</p></div>
                <div id="modal-emp-content" style="display:none;">
                    <div class="alert alert-info py-2 small mb-3" id="modal-emp-summary"></div>
                    <div class="input-group mb-3"><span class="input-group-text"><i class="bi bi-search"></i></span><input type="text" id="modal-emp-search" class="form-control" placeholder="Filtrar por nombre..."></div>
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-sm table-hover">
                            <thead class="sticky-top bg-light"><tr><th>ID</th><th>Nombre</th><th>Departamento</th><th>Planta</th></tr></thead>
                            <tbody id="modal-emp-tbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button></div>
        </div>
    </div>
</div>

<style>
.border-left-warning { border-left-color: #f57c00 !important; }
.border-left-info { border-left-color: #17a2b8 !important; }
.border-left-success { border-left-color: #28a745 !important; }
.border-left-muted { border-left-color: #adb5bd !important; }
.card-puesto:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.1) !important; }
.card-puesto.processing { opacity: 0.6; pointer-events: none; }
.confirm-count { background: linear-gradient(135deg, #fff3cd, #ffe69c); border: 2px solid #f57c00; border-radius: 12px; padding: 16px; margin: 12px 0; text-align: center; }
.confirm-count .num { font-size: 2rem; font-weight: 800; color: #f57c00; line-height: 1; }
.toast-container-custom { position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 320px; max-width: 400px; }
.toast-custom { border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.15); padding: 16px; color: #fff; display: flex; align-items: flex-start; gap: 12px; margin-bottom: 8px; animation: slideInRight 0.3s ease; }
.toast-custom.success { background: linear-gradient(135deg, #28a745, #20c997); }
.toast-custom.warning { background: linear-gradient(135deg, #ffc107, #f57c00); color: #333; }
.toast-custom.danger { background: linear-gradient(135deg, #dc3545, #c82333); }
@keyframes slideInRight { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
</style>

<div class="toast-container-custom" id="toastContainer"></div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
    let masivoModal = new bootstrap.Modal(document.getElementById('modalMasivo'));
    let empModal = new bootstrap.Modal(document.getElementById('modalEmpleadosPuesto'));
    let pendingAction = null;
    let pendingMasivo = null;

    // --- Clasificación Individual ---
    document.querySelectorAll('.tipo-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const idPuesto = this.dataset.puesto;
            const tipo = this.dataset.tipo;
            const card = this.closest('.card-puesto');
            const count = document.getElementById('count-' + idPuesto).dataset.count || '?';
            const puestoNombre = card.querySelector('h6').textContent;
            const tipoActual = card.dataset.tipoActual || '';

            if (tipoActual === tipo) { showToast('warning', 'Sin cambios', `El puesto ya está clasificado como ${tipo}.`); return; }

            document.getElementById('modal-title-text').textContent = tipoActual ? 'Recategorizar puesto' : 'Clasificar puesto';
            document.getElementById('modal-message').innerHTML = `¿Clasificar "<strong>${puestoNombre}</strong>" como <strong class="text-capitalize">${tipo}</strong>?`;
            document.getElementById('modal-count-num').textContent = count;
            
            pendingAction = { idPuesto, tipo, card, count, puestoNombre, tipoActual };
            confirmModal.show();
        });
    });

    document.getElementById('modal-confirm-btn').addEventListener('click', async function() {
        if (!pendingAction) return;
        const { idPuesto, tipo, card, count, puestoNombre, tipoActual } = pendingAction;
        this.disabled = true; this.querySelector('.btn-text').innerHTML = '<span class="spinner-border spinner-border-sm"></span> Procesando...';
        card.classList.add('processing');

        try {
            const res = await fetch('{{ route("puestos.store") }}', {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ id_puesto: parseInt(idPuesto), tipo_empleado: tipo })
            });
            if (res.ok) {
                updateCardUI(card, tipo);
                updateStats(tipoActual, tipo);
                showToast('success', 'Clasificación actualizada', `${puestoNombre} → ${tipo}. ${count} empleado(s).`);
                confirmModal.hide();
            } else { showToast('danger', 'Error', 'No se pudo clasificar.'); confirmModal.hide(); }
        } catch(e) { showToast('danger', 'Error de conexión', e.message); confirmModal.hide(); }
        finally { this.disabled = false; this.querySelector('.btn-text').innerHTML = '<i class="bi bi-check-lg"></i> Sí, clasificar'; card.classList.remove('processing'); pendingAction = null; }
    });

    // --- Clasificación Masiva (J-2) ---
    function updateMasivoButtons() {
        const seleccionados = document.querySelectorAll('.puesto-check:checked');
        const count = seleccionados.length;
        document.getElementById('selected-count').textContent = `${count} seleccionado${count === 1 ? '' : 's'}`;
        document.getElementById('btn-masivo-operativo').disabled = count === 0;
        document.getElementById('btn-masivo-administrativo').disabled = count === 0;
        document.getElementById('btn-masivo-corporativo').disabled = count === 0;
    }

    document.querySelectorAll('.puesto-check').forEach(cb => { cb.addEventListener('change', updateMasivoButtons); cb.addEventListener('click', e => e.stopPropagation()); });

    document.getElementById('select-all').addEventListener('change', function() {
        document.querySelectorAll('.puesto-card-wrapper').forEach(w => { if(w.style.display !== 'none') w.querySelector('.puesto-check').checked = this.checked; });
        updateMasivoButtons();
    });

    function abrirMasivo(tipo) {
        const seleccionados = Array.from(document.querySelectorAll('.puesto-check:checked')).map(cb => parseInt(cb.value));
        if (seleccionados.length === 0) return;
        const labels = { 'operativo': 'Operativo', 'administrativo': 'Administrativo', 'corporativo': 'Corporativo' };
        document.getElementById('modal-masivo-message').innerHTML = `¿Clasificar <strong>${seleccionados.length}</strong> puesto(s) como <strong>${labels[tipo]}</strong>?`;
        document.getElementById('modal-masivo-num').textContent = seleccionados.length;
        pendingMasivo = { id_puestos: seleccionados, tipo };
        masivoModal.show();
    }

    document.getElementById('btn-masivo-operativo').addEventListener('click', () => abrirMasivo('operativo'));
    document.getElementById('btn-masivo-administrativo').addEventListener('click', () => abrirMasivo('administrativo'));
    document.getElementById('btn-masivo-corporativo').addEventListener('click', () => abrirMasivo('corporativo'));

    document.getElementById('modal-masivo-confirm-btn').addEventListener('click', function() {
        if (!pendingMasivo) return;
        const form = document.createElement('form');
        form.method = 'POST'; form.action = '{{ route("puestos.clasificar.masivo") }}';
        const csrf = document.createElement('input'); csrf.type = 'hidden'; csrf.name = '_token'; csrf.value = '{{ csrf_token() }}'; form.appendChild(csrf);
        pendingMasivo.id_puestos.forEach(id => { const i = document.createElement('input'); i.type = 'hidden'; i.name = 'id_puestos[]'; i.value = id; form.appendChild(i); });
        const tipoInput = document.createElement('input'); tipoInput.type = 'hidden'; tipoInput.name = 'tipo_empleado'; tipoInput.value = pendingMasivo.tipo; form.appendChild(tipoInput);
        document.body.appendChild(form); form.submit();
    });

    // --- Ver Empleados del Puesto (J-3) ---
    window.verEmpleadosPuesto = async function(idPuesto, nombrePuesto) {
        document.getElementById('modal-emp-title').textContent = `Empleados con puesto: ${nombrePuesto}`;
        document.getElementById('modal-emp-loading').style.display = 'block';
        document.getElementById('modal-emp-content').style.display = 'none';
        empModal.show();
        try {
            const res = await fetch(`{{ route("puestos.empleados", ['id_puesto' => ':ID']) }}`.replace(':ID', idPuesto));
            const data = await res.json();
            renderEmpleadosTabla(data.empleados || []);
            document.getElementById('modal-emp-loading').style.display = 'none';
            document.getElementById('modal-emp-content').style.display = 'block';
            document.getElementById('modal-emp-summary').innerHTML = `<i class="bi bi-info-circle"></i> Puesto: <strong>${data.puesto}</strong> · Total: <strong>${data.total}</strong> empleado(s)`;
        } catch(e) { document.getElementById('modal-emp-loading').innerHTML = `<div class="alert alert-danger">Error: ${e.message}</div>`; }
    };

    function renderEmpleadosTabla(lista) {
        const tbody = document.getElementById('modal-emp-tbody');
        tbody.innerHTML = lista.length === 0 ? '<tr><td colspan="4" class="text-center text-muted py-3">Sin empleados</td></tr>' : 
            lista.map(e => `<tr><td><code>${e.id}</code></td><td>${e.nombre}</td><td>${e.departamento}</td><td><span class="badge bg-light text-dark">${e.planta}</span></td></tr>`).join('');
    }

    // --- UI Helpers ---
    function updateCardUI(card, tipo) {
        const newIcon = { 'operativo': 'bi-hard-hat', 'administrativo': 'bi-briefcase', 'corporativo': 'bi-shield-check' }[tipo];
        const newBadgeClass = { 'operativo': 'bg-warning text-dark', 'administrativo': 'bg-info text-dark', 'corporativo': 'bg-success' }[tipo];
        const newBorderClass = { 'operativo': 'border-left-warning', 'administrativo': 'border-left-info', 'corporativo': 'border-left-success' }[tipo];
        card.querySelector('.tipo-badge').className = `badge ${newBadgeClass} px-2 py-1 tipo-badge`;
        card.querySelector('.tipo-badge').innerHTML = `<i class="bi ${newIcon}"></i> <span class="tipo-label">${tipo.charAt(0).toUpperCase() + tipo.slice(1)}</span>`;
        card.classList.remove('border-left-warning', 'border-left-info', 'border-left-success', 'border-left-muted');
        card.classList.add(newBorderClass);
        card.dataset.tipoActual = tipo;
        card.closest('.puesto-card-wrapper').dataset.filter = tipo;
        card.querySelectorAll('.tipo-btn').forEach(b => { b.classList.remove('active'); if(b.dataset.tipo === tipo) b.classList.add('active'); });
    }

    function updateStats(oldTipo, newTipo) {
        const statOp = document.getElementById('stat-operativo');
        const statAdm = document.getElementById('stat-administrativo');
        const statCor = document.getElementById('stat-corporativo');
        if (oldTipo === 'operativo') statOp.textContent = parseInt(statOp.textContent) - 1;
        if (oldTipo === 'administrativo') statAdm.textContent = parseInt(statAdm.textContent) - 1;
        if (oldTipo === 'corporativo') statCor.textContent = parseInt(statCor.textContent) - 1;
        if (newTipo === 'operativo') statOp.textContent = parseInt(statOp.textContent) + 1;
        if (newTipo === 'administrativo') statAdm.textContent = parseInt(statAdm.textContent) + 1;
        if (newTipo === 'corporativo') statCor.textContent = parseInt(statCor.textContent) + 1;
        const clasificados = parseInt(statOp.textContent) + parseInt(statAdm.textContent) + parseInt(statCor.textContent);
        const total = parseInt(document.getElementById('stat-total').textContent);
        document.getElementById('progress-bar').style.width = (clasificados/total*100) + '%';
        document.getElementById('progress-text').textContent = `${clasificados}/${total} (${Math.round(clasificados/total*100)}%)`;
    }

    function showToast(type, title, msg) {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast-custom ${type}`;
        const icon = { success: 'bi-check-circle-fill', warning: 'bi-exclamation-triangle-fill', danger: 'bi-x-circle-fill' }[type];
        toast.innerHTML = `<i class="bi ${icon} toast-icon"></i><div class="toast-body"><div class="toast-title">${title}</div><div class="toast-msg">${msg.replace(/\n/g, '<br>')}</div></div><button class="toast-close btn-close btn-close-white" style="opacity:0.7"></button>`;
        toast.querySelector('.toast-close').addEventListener('click', () => toast.remove());
        container.appendChild(toast);
        setTimeout(() => toast.remove(), 5000);
    }

    // --- Filtros y Búsqueda ---
    document.querySelectorAll('#filterGroup button').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('#filterGroup button').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const filter = this.dataset.filter;
            document.querySelectorAll('.puesto-card-wrapper').forEach(card => { card.style.display = (filter === 'todos' || card.dataset.filter === filter) ? '' : 'none'; });
        });
    });

    document.getElementById('searchPuesto').addEventListener('input', function() {
        const q = this.value.toLowerCase();
        document.querySelectorAll('.puesto-card-wrapper').forEach(card => { if(card.dataset.nombre.includes(q)) card.style.display = ''; else card.style.display = 'none'; });
    });
});
</script>
@endpush
@endsection