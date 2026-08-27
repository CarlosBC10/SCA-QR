{{-- resources/views/solicitudes/show_int.blade.php --}}
@extends('layouts.app')

@section('title', 'Solicitud #' . $solicitud->id_solicitud_int)

@section('content')
@php
    // CAMBIO V7: Mover la lógica de permisos al inicio para que esté disponible en toda la vista
    $user = Auth::user();
    $esSuperior = $user->isAdmin() || $user->isSuperadmin();
    $estadoSol = $solicitud->estado_actual;

    // FIX 7: Permitir aprobar si está pendiente o si está aprobado_parcial y es nuestro turno
    if ($esSuperior) {
        $puedeAprobarOrigen = in_array($estadoSol, ['pendiente_origen', 'aprobado_parcial']) && $solicitud->fecha_aprobacion_origen === null;
        $puedeAprobarDestino = in_array($estadoSol, ['pendiente_destino', 'aprobado_parcial']) && $solicitud->fecha_aprobacion_destino === null;
    } else {
        $puedeAprobarOrigen = $solicitud->id_planta_origen === $user->planta_id && in_array($estadoSol, ['pendiente_origen', 'aprobado_parcial']) && $solicitud->fecha_aprobacion_origen === null;
        $puedeAprobarDestino = $solicitud->id_planta_destino === $user->planta_id && in_array($estadoSol, ['pendiente_destino', 'aprobado_parcial']) && $solicitud->fecha_aprobacion_destino === null;
    }

    $puedeAprobar = ($puedeAprobarOrigen || $puedeAprobarDestino) && ($user->isAprobador() || $esSuperior);
@endphp

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Solicitud #{{ $solicitud->id_solicitud_int }}</h1>
        <p class="text-muted mb-0">
            Creada: {{ $solicitud->created_at->format('d/m/Y H:i') }}
            @if($creador) · por <strong>{{ $creador->nombre_completo }}</strong> @endif
        </p>
    </div>
    <a href="{{ route('solicitudes.hub') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<div class="row g-4">
    {{-- COLUMNA IZQUIERDA: Detalle completo --}}
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3 sca-card" style="border-radius:12px;">
            <div class="card-header bg-transparent py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-info-circle text-success"></i> Detalle</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Tipo</label>
                        <div class="fw-semibold">
                            @php
                                $tipoLabel = match($solicitud->tipo) {
                                    'pase_salida'  => 'Pase de Salida',
                                    'interplantas' => 'Interplantas',
                                    default        => $solicitud->tipo,
                                };
                            @endphp
                            {{ $tipoLabel }}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Estado</label>
                        <div>
                            @php
                                $estado = $solicitud->estado_actual;
                                $estadoClass = match($estado) {
                                    'pendiente_origen'  => 'bg-warning text-dark',
                                    'pendiente_destino' => 'bg-warning text-dark',
                                    'aprobado'          => 'bg-success',
                                    'aprobado_parcial'  => 'bg-info text-dark',
                                    'rechazado'         => 'bg-danger',
                                    'expirado'          => 'bg-secondary',
                                    default             => 'bg-secondary',
                                };
                                $estadoLabel = match($estado) {
                                    'pendiente_origen'  => 'Pendiente (origen)',
                                    'pendiente_destino' => 'Pendiente (destino)',
                                    'aprobado'          => 'Aprobado',
                                    'aprobado_parcial'  => 'Aprobado parcial',
                                    'rechazado'         => 'Rechazado',
                                    'expirado'          => 'Expirada',
                                    default             => $estado,
                                };
                            @endphp
                            <span class="badge {{ $estadoClass }} px-3 py-2">{{ $estadoLabel }}</span>
                            @if($estado === 'expirado')
                                <small class="d-block mt-1 text-muted">
                                    {{ $solicitud->motivo_expiracion_label }}
                                </small>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Planta Origen</label>
                        <div class="fw-semibold">{{ $solicitud->plantaOrigen?->nombre ?? '—' }}</div>
                    </div>
                    @if($solicitud->tipo === 'interplantas')
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Planta Destino</label>
                        <div class="fw-semibold">
                            {{ $solicitud->plantaDestino?->nombre ?? '—' }}
                        </div>
                    </div>
                    @endif
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Vigencia desde</label>
                        <div class="fw-semibold">{{ $solicitud->fecha_inicio->format('d/m/Y H:i') }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Vigencia hasta</label>
                        <div class="fw-semibold">{{ $solicitud->fecha_fin->format('d/m/Y H:i') }}</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-muted">Motivo</label>
                        <div class="p-3 rounded sca-card" style="background: var(--sca-hover);">
                            {{ $solicitud->motivo }}
                        </div>
                    </div>
                </div>

                {{-- Tabla de empleados (solo si es grupal) --}}
                @if($solicitud->empleados->isNotEmpty())
                <hr>
                <h6 class="fw-bold mb-3">
                    <i class="bi bi-people text-success"></i> Empleados ({{ $solicitud->empleados->count() }})
                </h6>
                @if($puedeAprobar)
                <form method="POST" action="{{ route('solicitudes.aprobar.grupal', $solicitud->id_solicitud_int) }}" id="formAprobarInt" class="sca-form-aprobar">
                    @csrf
                @endif
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Puesto</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($solicitud->empleados as $se)
                            @php
                                $emp = $empleadosDetalle->get($se->empleado_id);
                                $seClass = match($se->estado) {
                                    'pendiente'       => 'bg-warning text-dark',
                                    'aprobado'        => 'bg-success',
                                    'aprobado_origen' => 'bg-info text-dark',
                                    'rechazado'       => 'bg-danger',
                                    default           => 'bg-secondary',
                                };
                                $puedeCancelarEmp = (
                                    in_array($solicitud->estado, ['aprobado', 'aprobado_parcial'])
                                    && $se->estado !== 'rechazado'
                                    && $se->empleado_id !== $solicitud->creador_empleado_id
                                    && (auth()->user()->isAdmin() || auth()->user()->isSuperadmin() || auth()->user()->isAprobador()
                                        || $solicitud->creador_empleado_id === (int) auth()->user()->username)
                                );
                            @endphp
                            <tr>
                                <td class="font-monospace">{{ $se->empleado_id }}</td>
                                <td>
                                    {{ $emp?->nombre_completo ?? 'Desconocido' }}
                                    @if($se->empleado_id === $solicitud->creador_empleado_id)
                                        <span class="badge bg-primary ms-1">Creador</span>
                                    @endif
                                </td>
                                <td>{{ $emp?->puesto?->nombre ?? '—' }}</td>
                                <td><span class="badge {{ $seClass }}">{{ ucfirst($se->estado) }}</span></td>
                                <td>
                                    @if($puedeAprobar && $se->estado !== 'rechazado')
                                        <select name="estados[{{ $se->empleado_id }}]" class="form-select form-select-sm w-auto int-estado">
                                            <option value="aprobado" @if($se->estado=='aprobado') selected @endif>Aprobar</option>
                                            <option value="rechazado" @if($se->estado=='rechazado') selected @endif>Rechazar</option>
                                        </select>
                                    @elseif($puedeCancelarEmp)
                                        {{-- M5 [ScaModal] — Reemplazamos onclick por clase .sca-cancel-empleado --}}
                                        <button type="button" class="btn btn-sm btn-outline-danger sca-cancel-empleado" title="Cancelar este empleado"
                                                data-action="{{ route('solicitudes.cancel.empleado.int', $solicitud->id_solicitud_int) }}"
                                                data-empleado="{{ $se->empleado_id }}"
                                                data-nombre="{{ $emp?->nombre_completo ?? 'este empleado' }}">
                                            <i class="bi bi-x-circle"></i>
                                        </button>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($puedeAprobar)
                </form>
                @endif
                @else
                <hr>
                <div class="alert alert-info py-2 small mb-0">
                    <i class="bi bi-info-circle"></i> Solicitud individual (solo para el creador).
                </div>
                @endif
            </div>
        </div>

        @if($solicitud->motivo_rechazo)
        <div class="alert alert-danger" style="border-radius:12px;">
            <h6 class="fw-bold mb-1"><i class="bi bi-x-circle"></i> {{ $solicitud->estado === 'rechazado' ? 'Rechazada' : 'Cancelada' }}</h6>
            <p class="mb-0">{{ $solicitud->motivo_rechazo }}</p>
        </div>
        @endif
    </div>

    {{-- COLUMNA DERECHA: Timeline de aprobaciones + acciones --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm sca-card mb-3" style="border-radius:12px;">
            <div class="card-header bg-transparent py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-clock-history text-success"></i> Aprobaciones</h5>
            </div>
            <div class="card-body">
                {{-- Timeline --}}
                <div class="timeline">
                    {{-- Aprobación Origen --}}
                    <div class="d-flex mb-3">
                        <div class="me-3">
                            @if($solicitud->fecha_aprobacion_origen)
                                <i class="bi bi-check-circle-fill text-success fs-4"></i>
                            @elseif($solicitud->estado_actual === 'pendiente_origen' || ($solicitud->estado_actual === 'aprobado_parcial' && empty($solicitud->fecha_aprobacion_origen)))
                                <div class="spinner-border text-warning" role="status" style="width:24px; height:24px;"></div>
                            @elseif($solicitud->estado_actual === 'rechazado')
                                <i class="bi bi-x-circle-fill text-danger fs-4"></i>
                            @else
                                <i class="bi bi-circle text-muted fs-4"></i>
                            @endif
                        </div>
                        <div>
                            <div class="fw-semibold">Aprobación Origen</div>
                            @if($solicitud->fecha_aprobacion_origen)
                                <small class="text-muted">{{ $solicitud->fecha_aprobacion_origen->format('d/m/Y H:i') }}</small>
                            @elseif($solicitud->estado_actual === 'pendiente_origen' || ($solicitud->estado_actual === 'aprobado_parcial' && empty($solicitud->fecha_aprobacion_origen)))
                                <small class="text-warning">Pendiente</small>
                            @else
                                <small class="text-muted">—</small>
                            @endif
                        </div>
                    </div>

                    {{-- Aprobación Destino (solo interplantas) --}}
                    @if($solicitud->tipo === 'interplantas')
                    <div class="d-flex mb-3">
                        <div class="me-3">
                            @if($solicitud->fecha_aprobacion_destino)
                                <i class="bi bi-check-circle-fill text-success fs-4"></i>
                            @elseif($solicitud->estado_actual === 'pendiente_destino' || ($solicitud->estado_actual === 'aprobado_parcial' && empty($solicitud->fecha_aprobacion_destino)))
                                <div class="spinner-border text-warning" role="status" style="width:24px; height:24px;"></div>
                            @elseif($solicitud->estado_actual === 'rechazado')
                                <i class="bi bi-x-circle-fill text-danger fs-4"></i>
                            @else
                                <i class="bi bi-circle text-muted fs-4"></i>
                            @endif
                        </div>
                        <div>
                            <div class="fw-semibold">Aprobación Destino</div>
                            @if($solicitud->fecha_aprobacion_destino)
                                <small class="text-muted">{{ $solicitud->fecha_aprobacion_destino->format('d/m/Y H:i') }}</small>
                            @elseif($solicitud->estado_actual === 'pendiente_destino' || ($solicitud->estado_actual === 'aprobado_parcial' && empty($solicitud->fecha_aprobacion_destino)))
                                <small class="text-warning">Pendiente</small>
                            @else
                                <small class="text-muted">—</small>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>

                @if($puedeAprobar)
                    <hr>
                    @if($solicitud->empleados->isNotEmpty())
                        <div class="alert alert-info py-2 small">
                            <i class="bi bi-info-circle"></i> Si rechaza alguno, la solicitud queda <strong>Aprobada Parcial</strong>: los aprobados pueden entrar, los rechazados no.
                        </div>
                        {{-- M5 [ScaModal] -- El botón ahora envía el form #formAprobarInt que tiene la clase .sca-form-aprobar --}}
                        <button type="button" class="btn btn-success w-100 mt-3" onclick="document.getElementById('formAprobarInt').dispatchEvent(new Event('submit'))">
                            <i class="bi bi-check-lg"></i> Confirmar Revisión
                        </button>

                        {{-- M5 [ScaModal] — Reemplazamos el modal custom por clase .sca-rechazar-solicitud --}}
                        <form method="POST" action="{{ route('solicitudes.rechazar.int', $solicitud->id_solicitud_int) }}" class="sca-rechazar-solicitud mt-2">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger w-100">
                                <i class="bi bi-x-lg"></i> Rechazar Toda
                            </button>
                        </form>
                    @else
                        {{-- Aprobación Individual --}}
                        <div class="d-flex gap-2">
                            {{-- M5 [ScaModal] -- Form con clase .sca-form-aprobar --}}
                            <form method="POST" action="{{ route('solicitudes.aprobar.int', $solicitud->id_solicitud_int) }}" class="sca-form-aprobar w-100">
                                @csrf
                                <button type="submit" class="btn btn-success w-100"><i class="bi bi-check-lg"></i> Aprobar</button>
                            </form>
                            {{-- M5 [ScaModal] — Reemplazamos el modal custom por clase .sca-rechazar-solicitud --}}
                            <form method="POST" action="{{ route('solicitudes.rechazar.int', $solicitud->id_solicitud_int) }}" class="sca-rechazar-solicitud w-100">
                                @csrf
                                <button type="submit" class="btn btn-danger w-100">
                                    <i class="bi bi-x-lg"></i> Rechazar
                                </button>
                            </form>
                        </div>
                    @endif
                    {{-- V5: Mostrar botón de cancelar también cuando está aprobada o aprobada parcialmente --}}
                @elseif(in_array($estadoSol, ['pendiente_origen', 'pendiente_destino', 'aprobado', 'aprobado_parcial']) && ($solicitud->creador_empleado_id === (int) $user->username || $user->isAprobador() || $user->isAdmin() || $user->isSuperadmin()))
                    <hr>                    
                    {{-- M5 [ScaModal] — Reemplazamos el modal custom por clase .sca-cancelar-solicitud --}}
                    <form method="POST" action="{{ route('solicitudes.cancel.int', $solicitud->id_solicitud_int) }}" class="sca-cancelar-solicitud">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger w-100">
                            <i class="bi bi-x-circle"></i> Cancelar Solicitud
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// ============================================================
// M5 [ScaModal] — Handlers para las acciones de show_int
// ============================================================
document.addEventListener('DOMContentLoaded', function() {

    // Helper genérico para AJAX POST con Option E
    async function enviarFormAjax(form) {
        try {
            const res = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: new FormData(form),
            });
            const data = await res.json();
            if (data.success) {
                // Option E: NO cerrar loading. Redirect directo.
                window.location.replace(data.redirect);
            } else {
                ScaModal.close();
                if (data.errors) {
                    const errores = Object.values(data.errors).flat();
                    ScaModal.error('Error de validación', errores.join('<br>'));
                } else {
                    ScaModal.error(data.titulo || 'Error', data.mensaje || 'No se pudo procesar.');
                }
            }
        } catch(err) {
            ScaModal.close();
            ScaModal.error('Error de conexión', 'No se pudo contactar con el servidor.');
        }
    }

    // 1. Aprobar (individual o grupal)
    document.querySelectorAll('.sca-form-aprobar').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            // Revisar si hay empleados marcados como 'rechazado' en los selects
            let hayRechazados = false;
            form.querySelectorAll('.int-estado').forEach(sel => {
                if (sel.value === 'rechazado') hayRechazados = true;
            });

            if (hayRechazados) {
                // Si hay rechazados, pedir motivo general antes de enviar
                ScaModal.confirm({
                    titulo: 'Justificar Rechazos',
                    mensaje: 'Hay empleados marcados como "Rechazar". Debe ingresar un motivo general para justificar este rechazo.',
                    tipo: 'warning',
                    requiereInput: { type: 'textarea', label: 'Motivo de rechazo general', placeholder: 'Explique por qué se rechazan...', required: true, rows: 3 },
                    textoConfirmar: 'Confirmar y Enviar',
                    onConfirm: async () => {
                        // Agregar el motivo al form
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'motivo_rechazo_general';
                        input.value = document.getElementById('scaInput')?.value || '';
                        form.appendChild(input);

                        ScaModal.loading('Procesando aprobación', 'Guardando cambios...');
                        await enviarFormAjax(form);
                    }
                });
            } else {
                // Si no hay rechazados, enviar directo
                ScaModal.loading('Procesando aprobación', 'Guardando cambios...');
                await enviarFormAjax(form);
            }
        });
    });

    // 2. Rechazar (con motivo requerido)
    document.querySelectorAll('.sca-rechazar-solicitud').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            ScaModal.confirm({
                titulo: '¿Rechazar solicitud?',
                mensaje: 'Esta acción no se puede deshacer. La solicitud será rechazada por completo.',
                tipo: 'danger',
                requiereInput: { type: 'textarea', label: 'Motivo de rechazo', placeholder: 'Explique por qué se rechaza...', required: true, rows: 3 },
                textoConfirmar: 'Sí, rechazar',
                onConfirm: async () => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'motivo_rechazo';
                    input.value = document.getElementById('scaInput')?.value || '';
                    form.appendChild(input);

                    ScaModal.loading('Rechazando solicitud', 'Procesando...');
                    await enviarFormAjax(form);
                }
            });
        });
    });

    // 3. Cancelar empleado individual (con motivo)
    document.querySelectorAll('.sca-cancel-empleado').forEach(btn => {
        btn.addEventListener('click', function() {
            const empleadoId = this.dataset.empleado;
            const nombre = this.dataset.nombre || 'este empleado';
            const action = this.dataset.action;

            // Crear un form dinámico para enviar
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = action;
            // FIX: Agregar el token correctamente como un campo oculto
            const tokenInput = document.createElement('input');
            tokenInput.type = 'hidden';
            tokenInput.name = '_token';
            tokenInput.value = document.querySelector('meta[name="csrf-token"]').content;
            form.appendChild(tokenInput);

            const empInput = document.createElement('input');
            empInput.type = 'hidden';
            empInput.name = 'empleado_id';
            empInput.value = empleadoId;
            form.appendChild(empInput);
            
            document.body.appendChild(form);

            ScaModal.confirm({
                titulo: '¿Cancelar empleado?',
                mensaje: `Se cancelará el permiso de <strong>${nombre}</strong> en esta solicitud.`,
                tipo: 'danger',
                requiereInput: { type: 'textarea', label: 'Motivo de cancelación', placeholder: 'Explique el motivo...', required: true, rows: 3 },
                textoConfirmar: 'Sí, cancelar',
                onConfirm: async () => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'motivo_cancelacion';
                    input.value = document.getElementById('scaInput')?.value || '';
                    form.appendChild(input);

                    ScaModal.loading('Cancelando empleado', 'Procesando...');
                    await enviarFormAjax(form);
                }
            });
        });
    });

    // 4. Cancelar solicitud completa (con motivo)
    document.querySelectorAll('.sca-cancelar-solicitud').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            ScaModal.confirm({
                titulo: '¿Cancelar solicitud?',
                mensaje: 'La solicitud será cancelada. Los accesos asociados quedarán invalidados.',
                tipo: 'danger',
                requiereInput: { type: 'textarea', label: 'Motivo de cancelación', placeholder: 'Explique el motivo de cancelación...', required: true, rows: 3 },
                textoConfirmar: 'Sí, cancelar',
                onConfirm: async () => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'motivo_rechazo';
                    input.value = document.getElementById('scaInput')?.value || '';
                    form.appendChild(input);

                    ScaModal.loading('Cancelando solicitud', 'Procesando...');
                    await enviarFormAjax(form);
                }
            });
        });
    });

});
</script>
@endpush

@endsection