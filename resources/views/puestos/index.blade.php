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
                    <div class="flex-grow-1">
                        <h6 class="mb-0 fw-bold">{{ $puesto->nombre }}</h6>
                        <small class="text-muted">ID: <span class="font-monospace">{{ $puesto->id_puesto }}</span></small>
                    </div>
                    <span class="badge {{ $badgeClass }} px-2 py-1 tipo-badge">
                        <i class="bi {{ $icon }}"></i>
                        <span class="tipo-label">@if($tipoActual){{ ucfirst($tipoActual) }}@else Sin clasificar @endif</span>
                    </span>
                </div>

                <div class="mb-3">
                    <small class="text-muted d-block mb-1">Empleados con este puesto:</small>
                    @php
                        $conteo = $conteoEmpleados->get($puesto->id_puesto, 0);
                    @endphp
                    <span class="badge bg-secondary count-badge" id="count-{{ $puesto->id_puesto }}" data-count="{{ $conteo }}">
                        <i class="bi bi-people"></i> {{ $conteo }} empleado{{ $conteo == 1 ? '' : 's' }}
                    </span>
                </div>

                <div class="btn-group w-100" role="group">
                    <button type="button" class="btn btn-sm btn-outline-warning tipo-btn {{ $tipoActual === 'operativo' ? 'active' : '' }}"
                            data-puesto="{{ $puesto->id_puesto }}" data-tipo="operativo">
                        <i class="bi bi-hard-hat"></i> Operativo
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-info tipo-btn {{ $tipoActual === 'administrativo' ? 'active' : '' }}"
                            data-puesto="{{ $puesto->id_puesto }}" data-tipo="administrativo">
                        <i class="bi bi-briefcase"></i> Admin
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success tipo-btn {{ $tipoActual === 'corporativo' ? 'active' : '' }}"
                            data-puesto="{{ $puesto->id_puesto }}" data-tipo="corporativo">
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
    <p class="text-muted mt-2">No hay puestos en sitec.</p>
</div>
@endif

{{-- Modal de confirmación --}}
<div class="modal fade" id="confirmModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content sca-modal-content" style="border-radius:16px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-shield-exclamation text-warning"></i>
                    <span id="modal-title-text">Confirmar clasificación</span>
                </h5>
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
                <div class="mt-3 small text-muted">
                    <i class="bi bi-info-circle"></i> Esto puede tardar unos segundos. No cierre la ventana.
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="modal-confirm-btn">
                    <span class="btn-text"><i class="bi bi-check-lg"></i> Sí, clasificar</span>
                    <span class="btn-spinner d-none">
                        <span class="spinner-border spinner-border-sm" role="status"></span> Procesando...
                    </span>
                </button>
            </div>
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

[data-bs-theme="dark"] .confirm-count { background: linear-gradient(135deg, rgba(255,193,7,0.15), rgba(245,124,0,0.1)); border-color: #f57c00; }
[data-bs-theme="dark"] .confirm-count .num { color: #ffb74d; }

.toast-container-custom { position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 320px; max-width: 400px; }
.toast-custom { border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.15); padding: 16px; color: #fff; display: flex; align-items: flex-start; gap: 12px; margin-bottom: 8px; animation: slideInRight 0.3s ease; }
.toast-custom.success { background: linear-gradient(135deg, #28a745, #20c997); }
.toast-custom.warning { background: linear-gradient(135deg, #ffc107, #f57c00); color: #333; }
.toast-custom.danger { background: linear-gradient(135deg, #dc3545, #c82333); }
.toast-custom .toast-icon { font-size: 1.5rem; }
.toast-custom .toast-body { flex: 1; }
.toast-custom .toast-title { font-weight: 700; margin-bottom: 2px; }
.toast-custom .toast-msg { font-size: 0.875rem; opacity: 0.95; }
.toast-custom .toast-close { background: transparent; border: none; color: inherit; opacity: 0.7; cursor: pointer; padding: 0; font-size: 1.2rem; line-height: 1; }
@keyframes slideInRight { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
@keyframes slideOutRight { from { transform: translateX(0); opacity: 1; } to { transform: translateX(120%); opacity: 0; } }
.toast-custom.hiding { animation: slideOutRight 0.3s ease forwards; }
</style>

<div class="toast-container-custom" id="toastContainer"></div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
    let pendingAction = null;

    // Botones de clasificación → abren modal
    document.querySelectorAll('.tipo-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const idPuesto = this.dataset.puesto;
            const tipo = this.dataset.tipo;
            const card = this.closest('.card-puesto');
            const countBadge = document.getElementById('count-' + idPuesto);
            const count = countBadge.dataset.count || '?';
            const puestoNombre = card.querySelector('h6').textContent;
            const tipoActual = card.dataset.tipoActual || '';

            if (tipoActual === tipo) {
                showToast('warning', 'Sin cambios', `El puesto "${puestoNombre}" ya está clasificado como ${tipo}.`);
                return;
            }

            const accion = tipoActual ? 'recategorizar' : 'clasificar';
            document.getElementById('modal-title-text').textContent = tipoActual ? 'Recategorizar puesto' : 'Clasificar puesto';
            document.getElementById('modal-message').innerHTML =
                `¿Está seguro de <strong>${accion}</strong> el puesto "<strong>${puestoNombre}</strong>" como <strong class="text-capitalize">${tipo}</strong>?`;

            document.getElementById('modal-count-num').textContent = count;

            pendingAction = { idPuesto, tipo, card, count, puestoNombre, tipoActual };

            document.querySelector('#modal-confirm-btn .btn-text').classList.remove('d-none');
            document.querySelector('#modal-confirm-btn .btn-spinner').classList.add('d-none');
            document.getElementById('modal-confirm-btn').disabled = false;

            confirmModal.show();
        });
    });

    // Confirmar en el modal
    document.getElementById('modal-confirm-btn').addEventListener('click', async function() {
        if (!pendingAction) return;

        const { idPuesto, tipo, card, count, puestoNombre, tipoActual } = pendingAction;

        document.querySelector('#modal-confirm-btn .btn-text').classList.add('d-none');
        document.querySelector('#modal-confirm-btn .btn-spinner').classList.remove('d-none');
        this.disabled = true;

        card.classList.add('processing');
        const btns = card.querySelectorAll('.tipo-btn');
        btns.forEach(b => {
            b.classList.remove('active');
            if (b.dataset.tipo === tipo) b.classList.add('active');
        });

        try {
            const res = await fetch('{{ route("puestos.store") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ id_puesto: parseInt(idPuesto), tipo_empleado: tipo })
            });

            if (res.ok) {
                await new Promise(r => setTimeout(r, 300));

                const newIcon = { 'operativo': 'bi-hard-hat', 'administrativo': 'bi-briefcase', 'corporativo': 'bi-shield-check' }[tipo];
                const newBadgeClass = { 'operativo': 'bg-warning text-dark', 'administrativo': 'bg-info text-dark', 'corporativo': 'bg-success' }[tipo];
                const newBorderClass = { 'operativo': 'border-left-warning', 'administrativo': 'border-left-info', 'corporativo': 'border-left-success' }[tipo];

                const badge = card.querySelector('.tipo-badge');
                badge.className = `badge ${newBadgeClass} px-2 py-1 tipo-badge`;
                badge.innerHTML = `<i class="bi ${newIcon}"></i> <span class="tipo-label">${tipo.charAt(0).toUpperCase() + tipo.slice(1)}</span>`;

                card.classList.remove('border-left-warning', 'border-left-info', 'border-left-success', 'border-left-muted');
                card.classList.add(newBorderClass);
                card.dataset.tipoActual = tipo;
                card.closest('.puesto-card-wrapper').dataset.filter = tipo;

                updateStats(tipoActual, tipo);

                showToast('success', 'Clasificación actualizada',
                    `Puesto "${puestoNombre}" → ${tipo}.\n${count !== '?' ? count + ' empleado(s) actualizado(s).' : ''}`);

                confirmModal.hide();
            } else if (res.status === 422) {
                const data = await res.json();
                const errors = Object.values(data.errors || {}).flat().join('\n');
                showToast('danger', 'Error de validación', errors);
                confirmModal.hide();
                btns.forEach(b => b.classList.remove('active'));
            } else {
                showToast('danger', 'Error del servidor', `Código ${res.status}. Revisa el log.`);
                confirmModal.hide();
                btns.forEach(b => b.classList.remove('active'));
            }
        } catch(e) {
            showToast('danger', 'Error de conexión', e.message);
            confirmModal.hide();
            btns.forEach(b => b.classList.remove('active'));
        } finally {
            card.classList.remove('processing');
            pendingAction = null;
        }
    });

    // Actualizar stats y barra dinámicamente
    function updateStats(oldTipo, newTipo) {
        const statOp = document.getElementById('stat-operativo');
        const statAdm = document.getElementById('stat-administrativo');
        const statCor = document.getElementById('stat-corporativo');

        if (oldTipo) {
            if (oldTipo === 'operativo') statOp.textContent = parseInt(statOp.textContent) - 1;
            if (oldTipo === 'administrativo') statAdm.textContent = parseInt(statAdm.textContent) - 1;
            if (oldTipo === 'corporativo') statCor.textContent = parseInt(statCor.textContent) - 1;
        }
        if (newTipo === 'operativo') statOp.textContent = parseInt(statOp.textContent) + 1;
        if (newTipo === 'administrativo') statAdm.textContent = parseInt(statAdm.textContent) + 1;
        if (newTipo === 'corporativo') statCor.textContent = parseInt(statCor.textContent) + 1;

        const clasificados = parseInt(statOp.textContent) + parseInt(statAdm.textContent) + parseInt(statCor.textContent);
        const total = parseInt(document.getElementById('stat-total').textContent);
        const pct = total > 0 ? Math.round(clasificados / total * 100) : 0;
        document.getElementById('progress-bar').style.width = pct + '%';
        document.getElementById('progress-text').textContent = `${clasificados}/${total} (${pct}%)`;
    }

    // Toasts
    function showToast(type, title, msg) {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast-custom ${type}`;
        const icon = { success: 'bi-check-circle-fill', warning: 'bi-exclamation-triangle-fill', danger: 'bi-x-circle-fill' }[type];
        toast.innerHTML = `
            <i class="bi ${icon} toast-icon"></i>
            <div class="toast-body">
                <div class="toast-title">${title}</div>
                <div class="toast-msg">${msg.replace(/\n/g, '<br>')}</div>
            </div>
            <button class="toast-close"><i class="bi bi-x"></i></button>
        `;
        toast.querySelector('.toast-close').addEventListener('click', function() {
            toast.classList.add('hiding');
            setTimeout(() => toast.remove(), 300);
        });
        container.appendChild(toast);
        setTimeout(() => {
            if (toast.parentElement) {
                toast.classList.add('hiding');
                setTimeout(() => toast.remove(), 300);
            }
        }, 5000);
    }

    // Filtros
    document.querySelectorAll('#filterGroup button').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('#filterGroup button').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const filter = this.dataset.filter;
            document.querySelectorAll('.puesto-card-wrapper').forEach(function(card) {
                card.style.display = (filter === 'todos' || card.dataset.filter === filter) ? '' : 'none';
            });
        });
    });

    // Búsqueda
    document.getElementById('searchPuesto').addEventListener('input', function() {
        const q = this.value.toLowerCase();
        document.querySelectorAll('.puesto-card-wrapper').forEach(function(card) {
            card.style.display = card.dataset.nombre.includes(q) ? '' : 'none';
        });
    });
});
</script>
@endpush
@endsection