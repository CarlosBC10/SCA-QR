{{-- resources/views/logs/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Logs del Sistema')

@section('content')
<div class="container-fluid py-4">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-shield-lock-fill text-danger"></i> Logs del Sistema</h1>
            <p class="text-muted mb-0">Auditoría de acciones críticas. Solo Superadmin.</p>
        </div>
        <a href="{{ route('logs.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-clockwise"></i> Limpiar filtros
        </a>
    </div>

    {{-- Cards de resumen --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-3">
                    <div class="text-muted small">Logs hoy</div>
                    <div class="fs-3 fw-bold text-primary">{{ $resumen['total_hoy'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-3">
                    <div class="text-muted small">Últimos 7 días</div>
                    <div class="fs-3 fw-bold text-info">{{ $resumen['total_7dias'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-3">
                    <div class="text-muted small">Errores hoy</div>
                    <div class="fs-3 fw-bold text-danger">{{ $resumen['errores_hoy'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-3">
                    <div class="text-muted small">Logins hoy</div>
                    <div class="fs-3 fw-bold text-success">{{ $resumen['logins_hoy'] }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent py-3">
            <strong><i class="bi bi-funnel-fill"></i> Filtros</strong>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('logs.index') }}" id="formFiltros">
                <div class="row g-3">
                    {{-- Categoría --}}
                    <div class="col-md-3">
                        <label class="form-label small">Categoría</label>
                        <select name="categoria" class="form-select form-select-sm" onchange="actualizarAcciones()">
                            @foreach ($categorias as $key => $label)
                                <option value="{{ $key }}" @selected(old('categoria', $filtros['categoria']) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Acción específica --}}
                    <div class="col-md-3">
                        <label class="form-label small">Acción específica</label>
                        <select name="accion" class="form-select form-select-sm" id="selectAccion">
                            @foreach ($accionesTodas as $key => $label)
                                <option value="{{ $key }}" @selected(old('accion', $filtros['accion']) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Usuario --}}
                    <div class="col-md-3">
                        <label class="form-label small">Usuario</label>
                        <select name="usuario_id" class="form-select form-select-sm">
                            <option value="">— Todos los usuarios —</option>
                            @foreach ($usuarios as $u)
                                <option value="{{ $u->id_usuario_sca }}" @selected(old('usuario_id', $filtros['usuario_id']) == $u->id_usuario_sca)>
                                    {{ $u->username }} · {{ $u->nombre_completo }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Buscar --}}
                    <div class="col-md-3">
                        <label class="form-label small">Buscar en descripción</label>
                        <input type="text" name="buscar" class="form-control form-control-sm"
                               value="{{ old('buscar', $filtros['buscar']) }}"
                               placeholder="Ej: Solicitud #123">
                    </div>

                    {{-- Fecha desde --}}
                    <div class="col-md-3">
                        <label class="form-label small">Desde</label>
                        <input type="date" name="fecha_desde" class="form-control form-control-sm"
                               value="{{ old('fecha_desde', $filtros['fecha_desde']) }}">
                    </div>

                    {{-- Fecha hasta --}}
                    <div class="col-md-3">
                        <label class="form-label small">Hasta</label>
                        <input type="date" name="fecha_hasta" class="form-control form-control-sm"
                               value="{{ old('fecha_hasta', $filtros['fecha_hasta']) }}">
                    </div>

                    {{-- Botones --}}
                    <div class="col-md-6 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-search"></i> Filtrar
                        </button>
                        <a href="{{ route('logs.index') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-x-lg"></i> Limpiar
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Tabla de logs --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
            <strong><i class="bi bi-list-ul"></i> Registros ({{ $logs->total() }} total)</strong>
            <span class="text-muted small">Página {{ $logs->currentPage() }} de {{ $logs->lastPage() }}</span>
        </div>
        <div class="card-body p-0">
            @if ($logs->isEmpty())
                <div class="text-center text-muted py-5">
                    <i class="bi bi-inbox fs-1"></i>
                    <p class="mt-2 mb-0">No hay registros que coincidan con los filtros.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="150">Fecha</th>
                                <th width="180">Acción</th>
                                <th width="200">Usuario</th>
                                <th>Descripción</th>
                                <th width="130">IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($logs as $log)
                                <tr>
                                    <td class="small text-muted">
                                        {{ $log->created_at?->format('d/m/Y H:i:s') }}
                                    </td>
                                    <td>
                                        @php
                                            $clase = match($log->accion) {
                                                'LOGIN', 'APROBAR_SOLICITUD', 'REVISION_ALEATORIA' => 'bg-success',
                                                'LOGOUT', 'RECHAZAR_SOLICITUD', 'RECHAZAR_ACCESO', 'CANCELAR_SOLICITUD', 'CANCELAR_EMPLEADO', 'CANCELAR_EXTERNO' => 'bg-warning',
                                                'ERROR_MAIL', 'ERROR_NOTIF', 'ERROR_PNG' => 'bg-danger',
                                                'WARN_INTERPLANTAS_DIA' => 'bg-info',
                                                default => 'bg-secondary',
                                            };
                                        @endphp
                                        <span class="badge {{ $clase }}">{{ $log->accion }}</span>
                                    </td>
                                    <td class="small">
                                        @if ($log->usuario)
                                            <strong>{{ $log->usuario->username }}</strong><br>
                                            <span class="text-muted">{{ $log->usuario->nombre_completo }}</span>
                                        @else
                                            <span class="text-muted">— usuario eliminado —</span>
                                        @endif
                                    </td>
                                    <td class="small">{{ $log->descripcion ?? '—' }}</td>
                                    <td class="small text-muted font-monospace">{{ $log->ip ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @if ($logs->hasPages())
            <div class="card-footer bg-transparent py-3">
                {{ $logs->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>

<script>
// JS: cuando se cambia la categoría, filtra las acciones del segundo dropdown
// para mostrar solo las que pertenecen a esa categoría.
const accionesPorCategoria = {
    'autenticacion': ['LOGIN', 'LOGOUT', 'CAMBIO_PASSWORD', 'CAMBIO_PASSWORD_GUARDIA', 'RESETEO_PASSWORD'],
    'solicitudes':   ['CREAR_SOLICITUD', 'CANCELAR_SOLICITUD', 'CANCELAR_EMPLEADO', 'CANCELAR_EXTERNO'],
    'aprobaciones':  ['APROBAR_SOLICITUD', 'RECHAZAR_SOLICITUD', 'REENVIAR_CREDENCIALES'],
    'acceso':        ['JUSTIFICAR_HORARIO', 'REVISION_ALEATORIA', 'RECHAZAR_ACCESO'],
    'catalogos':     ['CLASIFICAR_PUESTO', 'CLASIFICAR_PUESTOS_MASIVO'],
    'errores':       ['ERROR_MAIL', 'ERROR_NOTIF', 'ERROR_PNG'],
    'advertencias':  ['WARN_INTERPLANTAS_DIA'],
};

function actualizarAcciones() {
    const categoria = document.querySelector('select[name="categoria"]').value;
    const selectAccion = document.getElementById('selectAccion');
    const valorActual = selectAccion.value;

    // Restaurar todas las opciones
    selectAccion.innerHTML = '<option value="">— Todas las acciones —</option>';
    @foreach ($accionesTodas as $key => $label)
        selectAccion.innerHTML += `<option value="{{ $key }}">{{ $label }}</option>`;
    @endforeach

    // Si hay categoría seleccionada, filtrar
    if (categoria && accionesPorCategoria[categoria]) {
        const permitidas = accionesPorCategoria[categoria];
        Array.from(selectAccion.options).forEach(opt => {
            if (opt.value && !permitidas.includes(opt.value)) {
                opt.style.display = 'none';
            } else {
                opt.style.display = '';
            }
        });
    }

    // Restaurar valor si sigue visible
    if (valorActual) selectAccion.value = valorActual;
}

// Ejecutar al cargar la página
document.addEventListener('DOMContentLoaded', actualizarAcciones);
</script>
@endsection