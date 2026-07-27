{{-- resources/views/solicitudes/show.blade.php --}}
@extends('layouts.app')

@section('title', 'Solicitud #' . $solicitud->id)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Solicitud #{{ $solicitud->id }}</h1>
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
                {{-- Grid de datos básicos --}}
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Tipo</label>
                        <div class="fw-semibold">
                            @php $tipoLabel = ['pase_salida'=>'Pase de Salida','interplantas'=>'Interplantas','visita'=>'Visita','contratista'=>'Contratista'][$solicitud->tipo] ?? $solicitud->tipo; @endphp
                            {{ $tipoLabel }}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Estado</label>
                        <div>
                            @php
                                // Mismo color coding que en index.blade.php
                                $estadoClass = match($solicitud->estado) {
                                    'pendiente_origen' => 'bg-warning text-dark',
                                    'pendiente_destino' => 'bg-info text-dark',
                                    'aprobado' => 'bg-success',
                                    'rechazado' => 'bg-danger',
                                    default => 'bg-secondary'
                                };
                                $estadoLabel = match($solicitud->estado) {
                                    'pendiente_origen' => 'Pendiente Origen',
                                    'pendiente_destino' => 'Pendiente Destino',
                                    'aprobado' => 'Aprobado',
                                    'rechazado' => 'Rechazado',
                                    default => $solicitud->estado
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
                        <div class="fw-semibold">{{ $solicitud->plantaDestino?->nombre ?? '—' }}</div>
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

                {{-- Datos de persona (solo si la solicitud tiene persona) --}}
                @if($solicitud->persona)
                <hr>
                <h6 class="fw-bold mb-3"><i class="bi bi-person text-success"></i> Datos de la Persona</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Nombre</label>
                        <div class="fw-semibold">{{ $solicitud->persona->nombre_completo }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Teléfono</label>
                        <div class="fw-semibold">{{ $solicitud->persona->telefono ?? '—' }}</div>
                    </div>
                    @if($solicitud->persona->empresa)
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Empresa</label>
                        <div class="fw-semibold">{{ $solicitud->persona->empresa }}</div>
                    </div>
                    @endif
                </div>
                @endif

                {{-- Tabla de empleados (solo interplantas grupal) --}}
                @if($solicitud->tipo === 'interplantas' && $solicitud->empleados->isNotEmpty())
                <hr>
                <h6 class="fw-bold mb-3"><i class="bi bi-people text-success"></i> Empleados ({{ $solicitud->empleados->count() }})</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Puesto</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($solicitud->empleados as $se)
                            @php
                                // $empleadosDetalle es una colección keyBy('id_empleado')
                                // Lo usamos como diccionario para buscar el empleado por ID
                                $emp = $empleadosDetalle->get($se->empleado_id);
                                
                                // Color del badge según el estado individual del empleado
                                $seClass = match($se->estado) {
                                    'pendiente' => 'bg-warning text-dark',
                                    'aprobado' => 'bg-success',
                                    'rechazado' => 'bg-danger',
                                    default => 'bg-secondary'
                                };
                            @endphp
                            <tr>
                                <td class="font-monospace">{{ $se->empleado_id }}</td>
                                <td>{{ $emp?->nombre_completo ?? 'Desconocido' }}</td>
                                <td>{{ $emp?->puesto?->nombre ?? '—' }}</td>
                                <td><span class="badge {{ $seClass }}">{{ ucfirst($se->estado) }}</span></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>

        {{-- Motivo de rechazo/cancelación (si aplica) --}}
        @if($solicitud->motivo_rechazo)
        <div class="alert alert-danger" style="border-radius:12px;">
            <h6 class="fw-bold mb-1"><i class="bi bi-x-circle"></i> {{ $solicitud->estado === 'rechazado' ? 'Rechazada' : 'Cancelada' }}</h6>
            <p class="mb-0">{{ $solicitud->motivo_rechazo }}</p>
        </div>
        @endif
    </div>

    {{-- COLUMNA DERECHA: Timeline de aprobaciones --}}
    {{-- Muestra visualmente el progreso: qué se aprobó, qué falta --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm sca-card" style="border-radius:12px;">
            <div class="card-header bg-transparent py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-clock-history text-success"></i> Aprobaciones</h5>
            </div>
            <div class="card-body">
                <div class="timeline">
                    {{-- APROBACIÓN ORIGEN --}}
                    {{-- Icono según estado: ✓ si aprobado, ⏳ si pendiente, ✗ si rechazado --}}
                    <div class="d-flex mb-3">
                        <div class="me-3">
                            @if($solicitud->fecha_aprobacion_origen)
                                <i class="bi bi-check-circle-fill text-success fs-4"></i>
                            @elseif(in_array($solicitud->estado, ['pendiente_origen']))
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

                    {{-- APROBACIÓN DESTINO --}}
                    {{-- Solo se muestra si el tipo requiere 2 aprobadores --}}
                    @if(in_array($solicitud->tipo, ['interplantas', 'visita', 'contratista']))
                    <div class="d-flex mb-3">
                        <div class="me-3">
                            @if($solicitud->fecha_aprobacion_destino)
                                <i class="bi bi-check-circle-fill text-success fs-4"></i>
                            @elseif(in_array($solicitud->estado, ['pendiente_destino']))
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

                    {{-- CREDENCIAL QR --}}
                    {{-- Solo para visita/contratista. Se genera en M5 al aprobar --}}
                    @if(in_array($solicitud->tipo, ['visita', 'contratista']))
                    <div class="d-flex">
                        <div class="me-3">
                            @if($solicitud->credencial)
                                <i class="bi bi-qr-code text-success fs-4"></i>
                            @else
                                <i class="bi bi-circle text-muted fs-4"></i>
                            @endif
                        </div>
                        <div>
                            <div class="fw-semibold">Credencial QR</div>
                            @if($solicitud->credencial)
                                <small class="text-success">Generada</small>
                            @else
                                <small class="text-muted">Se genera al aprobar (M5)</small>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>

                {{-- BOTÓN CANCELAR --}}
                {{-- Solo aparece si la solicitud está pendiente --}}
                {{-- Si ya está aprobada o rechazada, no se puede cancelar --}}
                @if(in_array($solicitud->estado, ['pendiente_origen', 'pendiente_destino']))
                <hr>
                <form method="POST" action="{{ route('solicitudes.cancel', $solicitud->id) }}" onsubmit="return confirm('¿Cancelar esta solicitud?')">
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