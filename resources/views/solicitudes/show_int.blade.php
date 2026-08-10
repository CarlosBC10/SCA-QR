{{-- resources/views/solicitudes/show_int.blade.php --}}
@extends('layouts.app')

@section('title', 'Solicitud #' . $solicitud->id_solicitud_int)

@section('content')
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
                                $estadoClass = match($solicitud->estado) {
                                    'pendiente_origen'  => 'bg-warning text-dark',
                                    'pendiente_destino' => 'bg-info text-dark',
                                    'aprobado'          => 'bg-success',
                                    'rechazado'         => 'bg-danger',
                                    'aprobado_parcial'  => 'bg-info text-dark',
                                    default             => 'bg-secondary',
                                };
                                $estadoLabel = match($solicitud->estado) {
                                    'pendiente_origen'  => 'Pendiente Origen',
                                    'pendiente_destino' => 'Pendiente Destino',
                                    'aprobado'          => 'Aprobado',
                                    'rechazado'         => 'Rechazado',
                                    'aprobado_parcial'  => 'Aprobado Parcial',
                                    default             => $solicitud->estado,
                                };
                            @endphp
                            <span class="badge {{ $estadoClass }} px-3 py-2">{{ $estadoLabel }}</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Planta Origen</label>
                        <div class="fw-semibold">{{ $solicitud->plantaOrigen?->nombre ?? '—' }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Planta Destino</label>
                        <div class="fw-semibold">
                            {{ $solicitud->plantaDestino?->nombre ?? '—' }}
                            @if($solicitud->tipo === 'pase_salida')
                                <small class="text-muted">(misma que origen)</small>
                            @endif
                        </div>
                    </div>
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
                                    'pendiente' => 'bg-warning text-dark',
                                    'aprobado'  => 'bg-success',
                                    'rechazado' => 'bg-danger',
                                    default     => 'bg-secondary',
                                };
                                // FIX A-8: ¿Puede cancelar a este empleado?
                                $puedeCancelarEmp = (
                                    in_array($solicitud->estado, ['pendiente_origen', 'pendiente_destino', 'aprobado', 'aprobado_parcial'])
                                    && $se->estado !== 'rechazado'
                                    && $se->empleado_id !== $solicitud->creador_empleado_id
                                    && (auth()->user()->isAdmin() || auth()->user()->isSuperadmin()
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
                                    @if($puedeCancelarEmp)
                                        <form method="POST"
                                              action="{{ route('solicitudes.cancel.empleado.int', $solicitud->id_solicitud_int) }}"
                                              onsubmit="return confirm('¿Cancelar a {{ $emp?->nombre_completo ?? 'este empleado' }} de esta solicitud?')"
                                              style="display:inline;">
                                            @csrf
                                            <input type="hidden" name="empleado_id" value="{{ $se->empleado_id }}">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancelar este empleado">
                                                <i class="bi bi-x-circle"></i> Cancelar
                                            </button>
                                        </form>
                                    @elseif($se->estado === 'rechazado')
                                        <span class="text-muted small">—</span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
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
                            @elseif($solicitud->estado === 'pendiente_origen')
                                <div class="spinner-border text-warning" role="status" style="width:24px; height:24px;"></div>
                            @elseif($solicitud->estado === 'rechazado')
                                <i class="bi bi-x-circle-fill text-danger fs-4"></i>
                            @else
                                <i class="bi bi-circle text-muted fs-4"></i>
                            @endif
                        </div>
                        <div>
                            <div class="fw-semibold">Aprobación Origen</div>
                            @if($solicitud->fecha_aprobacion_origen)
                                <small class="text-muted">{{ $solicitud->fecha_aprobacion_origen->format('d/m/Y H:i') }}</small>
                            @elseif($solicitud->estado === 'pendiente_origen')
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
                            @elseif($solicitud->estado === 'pendiente_destino')
                                <div class="spinner-border text-warning" role="status" style="width:24px; height:24px;"></div>
                            @elseif($solicitud->estado === 'rechazado')
                                <i class="bi bi-x-circle-fill text-danger fs-4"></i>
                            @else
                                <i class="bi bi-circle text-muted fs-4"></i>
                            @endif
                        </div>
                        <div>
                            <div class="fw-semibold">Aprobación Destino</div>
                            @if($solicitud->fecha_aprobacion_destino)
                                <small class="text-muted">{{ $solicitud->fecha_aprobacion_destino->format('d/m/Y H:i') }}</small>
                            @elseif($solicitud->estado === 'pendiente_destino')
                                <small class="text-warning">Pendiente</small>
                            @else
                                <small class="text-muted">—</small>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>

                {{-- Botones de acción si la puede aprobar --}}
                @php
                    $user = Auth::user();
                    $esSuperior = $user->isAdmin() || $user->isSuperadmin();

                    // ¿Puede aprobar origen?
                    if ($esSuperior) {
                        $puedeAprobarOrigen = ($solicitud->estado === 'pendiente_origen');
                    } else {
                        $puedeAprobarOrigen = ($solicitud->id_planta_origen === $user->planta_id && $solicitud->estado === 'pendiente_origen');
                    }

                    // ¿Puede aprobar destino?
                    if ($esSuperior) {
                        $puedeAprobarDestino = ($solicitud->estado === 'pendiente_destino');
                    } else {
                        $puedeAprobarDestino = ($solicitud->id_planta_destino === $user->planta_id && $solicitud->estado === 'pendiente_destino');
                    }

                    $puedeAprobar = ($puedeAprobarOrigen || $puedeAprobarDestino) && ($user->isAprobador() || $esSuperior);
                @endphp

                @if($puedeAprobar)
                    <hr>
                    @if($solicitud->empleados->isNotEmpty())
                        {{-- Aprobación Grupal --}}
                        <form method="POST" action="{{ route('solicitudes.aprobar.grupal', $solicitud->id_solicitud_int) }}">
                            @csrf
                            <h6 class="fw-bold mb-2">Revisar Empleados:</h6>
                            <div class="alert alert-info py-2 small">
                                <i class="bi bi-info-circle"></i> Si rechaza alguno, la solicitud queda <strong>Aprobada Parcial</strong>: los aprobados pueden entrar, los rechazados no.
                            </div>
                            @foreach($solicitud->empleados as $se)
                                @php $emp = $empleadosDetalle->get($se->empleado_id); @endphp
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <small>{{ $emp?->nombre_completo ?? 'Desconocido' }}</small>
                                    <select name="estados[{{ $se->empleado_id }}]" class="form-select form-select-sm w-auto">
                                        <option value="aprobado" @if($se->estado=='aprobado') selected @endif>Aprobar</option>
                                        <option value="rechazado" @if($se->estado=='rechazado') selected @endif>Rechazar</option>
                                    </select>
                                </div>
                            @endforeach
                            <button type="submit" class="btn btn-success w-100 mt-3">
                                <i class="bi bi-check-lg"></i> Confirmar Revisión
                            </button>
                        </form>
                        <button type="button" class="btn btn-outline-danger w-100 mt-2" data-bs-toggle="modal" data-bs-target="#modalRechazarInt{{ $solicitud->id_solicitud_int }}">
                            <i class="bi bi-x-lg"></i> Rechazar Toda
                        </button>
                    @else
                        {{-- Aprobación Individual --}}
                        <div class="d-flex gap-2">
                            <form method="POST" action="{{ route('solicitudes.aprobar.int', $solicitud->id_solicitud_int) }}">
                                @csrf
                                <button type="submit" class="btn btn-success w-100"><i class="bi bi-check-lg"></i> Aprobar</button>
                            </form>
                            <button type="button" class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#modalRechazarInt{{ $solicitud->id_solicitud_int }}">
                                <i class="bi bi-x-lg"></i> Rechazar
                            </button>
                        </div>
                    @endif

                    {{-- Modal de rechazo --}}
                    <div class="modal fade" id="modalRechazarInt{{ $solicitud->id_solicitud_int }}" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content sca-modal-content" style="border-radius: 16px;">
                                <div class="modal-header border-0 pb-0">
                                    <h5 class="modal-title fw-bold">Rechazar Solicitud #{{ $solicitud->id_solicitud_int }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST" action="{{ route('solicitudes.rechazar.int', $solicitud->id_solicitud_int) }}">
                                    @csrf
                                    <div class="modal-body pt-2">
                                        <label class="form-label">Motivo de rechazo <span class="text-danger">*</span></label>
                                        <textarea name="motivo_rechazo" class="form-control" rows="3" required placeholder="Explique por qué se rechaza..."></textarea>
                                    </div>
                                    <div class="modal-footer border-0 pt-0">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-danger"><i class="bi bi-x-lg"></i> Confirmar Rechazo</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    {{-- V5: Mostrar botón de cancelar también cuando está aprobada o aprobada parcialmente --}}
                    @elseif(in_array($solicitud->estado, ['pendiente_origen', 'pendiente_destino', 'aprobado', 'aprobado_parcial']) && $solicitud->creador_empleado_id === (int) $user->username)                    <hr>
                    <form method="POST" action="{{ route('solicitudes.cancel.int', $solicitud->id_solicitud_int) }}" onsubmit="return confirm('¿Cancelar esta solicitud?')">
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
@endsection