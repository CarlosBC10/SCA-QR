{{-- resources/views/solicitudes/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Solicitudes')

@section('content')
<div class="sca-page-head legacy-page-head">
    <div>
        @php
            // Título dinámico según la ruta
            if (request()->routeIs('solicitudes.mis')) {
                $titulo = 'Mis Solicitudes';
            } elseif (request()->routeIs('solicitudes.mis-aprobaciones')) {
                $titulo = 'Mis Aprobaciones';
            } else {
                $titulo = 'Historial';
            }
        @endphp
        <div class="sca-page-eyebrow"><i class="bi bi-file-earmark-check" aria-hidden="true"></i> Solicitudes</div>
        <h1 class="sca-page-title">{{ $titulo }}</h1>
        <p class="sca-page-subtitle">Consulta, filtra y da seguimiento al estado de tus solicitudes.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="#" data-sca-history-back="1" class="btn btn-outline-secondary" aria-label="Volver a la vista anterior">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
        <a href="{{ route('solicitudes.create') }}" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> Nueva
        </a>
    </div>
</div>

{{-- FORMULARIO DE FILTROS --}}
<form method="GET" class="card border-0 shadow-sm mb-3 sca-card" style="border-radius:12px;">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted">Tipo</label>
                <select name="tipo" class="form-select">
                    <option value="">Todos</option>
                    <option value="pase_salida" @if(request('tipo')=='pase_salida') selected @endif>Pase de Salida</option>
                    <option value="interplantas" @if(request('tipo')=='interplantas') selected @endif>Interplantas</option>
                    {{-- V10.3 · CAMBIO #38: filtro del nuevo tipo --}}
                    <option value="permiso_entrada" @if(request('tipo')=='permiso_entrada') selected @endif>Permiso de Entrada</option>
                    <option value="visita" @if(request('tipo')=='visita') selected @endif>Visita</option>
                    <option value="contratista" @if(request('tipo')=='contratista') selected @endif>Contratista</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Estado</label>
                <select name="estado" class="form-select">
                    <option value="">Todos</option>
                    @if(request()->routeIs('solicitudes.mis'))
                        <option value="pendiente_origen" @if(request('estado')=='pendiente_origen') selected @endif>Pendiente Origen</option>
                        <option value="pendiente_destino" @if(request('estado')=='pendiente_destino') selected @endif>Pendiente Destino</option>
                        <option value="pendiente" @if(request('estado')=='pendiente') selected @endif>Pendiente (Externos)</option>
                    @endif
                    <option value="aprobado" @if(request('estado')=='aprobado') selected @endif>Aprobado</option>
                    <option value="aprobado_parcial" @if(request('estado')=='aprobado_parcial') selected @endif>Aprobado Parcial</option>
                    <option value="rechazado" @if(request('estado')=='rechazado') selected @endif>Rechazado</option>
                </select>
            </div>
            @if(request()->routeIs('solicitudes.historial'))
            <div class="col-md-3">
                <label class="form-label small text-muted">Planta</label>
                <select name="planta" class="form-select">
                    <option value="">Todas</option>
                    @foreach($plantas as $p)
                        <option value="{{ $p->id_planta }}" @if(request('planta')==$p->id_planta) selected @endif>{{ $p->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Buscar (ID / Nombre)</label>
                <input type="text" name="buscar" class="form-control" value="{{ request('buscar') }}" placeholder="Ej: 123 o Juan">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Desde</label>
                <input type="date" name="fecha_desde" class="form-control" value="{{ request('fecha_desde') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Hasta</label>
                <input type="date" name="fecha_hasta" class="form-control" value="{{ request('fecha_hasta') }}">
            </div>
            @else
            <div class="col-md-6"></div>
            @endif
            <div class="col-md-12 mt-2">
                <button type="submit" class="btn btn-outline-success w-100">
                    <i class="bi bi-search"></i> Filtrar
                </button>
            </div>
        </div>
    </div>
</form>

{{-- ===================================================== --}}
{{-- TABLA DE SOLICITUDES INTERNAS --}}
{{-- ===================================================== --}}
<div class="card border-0 shadow-sm mb-4 sca-card" style="border-radius:12px;">
    <div class="card-header bg-transparent py-3">
        <h5 class="mb-0 fw-bold"><i class="bi bi-arrow-left-right text-success"></i> Solicitudes Internas (Pase de Salida / Interplantas)</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th width="60">#</th>
                    <th>Tipo</th>
                    <th>Detalle</th>
                    <th>Ruta</th>
                    <th>Vigencia</th>
                    <th>Estado</th>
                    <th width="80">Acción</th>
                </tr>
            </thead>
            <tbody>
                @forelse($solicitudesInt as $s)
                @php
                    // V10.3 · CAMBIO #38: mapa con el nuevo tipo
                    $tipoIcon = ['pase_salida'=>'bi-box-arrow-right','interplantas'=>'bi-arrow-left-right','permiso_entrada'=>'bi-door-open'][$s->tipo] ?? 'bi-question';
                    $tipoLabel = ['pase_salida'=>'Pase de Salida','interplantas'=>'Interplantas','permiso_entrada'=>'Permiso de Entrada'][$s->tipo] ?? $s->tipo;

                    $esCancelada = $s->estado === 'rechazado' && str_contains($s->motivo_rechazo ?? '', 'Cancelada');

                    $estadoClass = match($s->estado) {
                        'pendiente_origen'  => 'bg-warning text-dark',
                        'pendiente_destino' => 'bg-info text-dark',
                        'aprobado'          => 'bg-success',
                        'rechazado'         => $esCancelada ? 'bg-secondary' : 'bg-danger',
                        'aprobado_parcial'  => 'bg-info text-dark',
                        default             => 'bg-secondary',
                    };
                    $estadoLabel = match($s->estado) {
                        'pendiente_origen'  => 'Pend. Origen',
                        'pendiente_destino' => 'Pend. Destino',
                        'aprobado'          => 'Aprobado',
                        'rechazado'         => $esCancelada ? 'Cancelada' : 'Rechazado',
                        'aprobado_parcial'  => 'Aprob. Parcial',
                        default             => $s->estado,
                    };
                @endphp
                <tr>
                    <td class="font-monospace fw-bold">{{ $s->id_solicitud_int }}</td>
                    <td>
                        <i class="bi {{ $tipoIcon }} text-success"></i>
                        <span class="small">{{ $tipoLabel }}</span>
                    </td>
                    <td>
                        @if($s->empleados->isNotEmpty())
                            <strong>{{ $s->empleados->count() }} empleado(s)</strong>
                        @else
                            <span class="text-muted">Individual</span>
                        @endif

                        {{-- Trazabilidad SOLO en Historial --}}
                        @if(request()->routeIs('solicitudes.historial'))
                            @if($s->creador)
                                <br><small class="text-muted"><i class="bi bi-pencil"></i> Creado por: {{ $s->creador->nombre_completo }}</small>
                            @endif
                            @if($s->aprobadorOrigen)
                                <br><small class="text-success"><i class="bi bi-check-circle"></i> Aprob. Origen: {{ $s->aprobadorOrigen->nombre_completo }}</small>
                            @endif
                            @if($s->aprobadorDestino)
                                <br><small class="text-success"><i class="bi bi-check-circle"></i> Aprob. Destino: {{ $s->aprobadorDestino->nombre_completo }}</small>
                            @endif
                        @endif
                    </td>
                    <td>
                        <small>{{ $s->plantaOrigen?->nombre ?? '—' }}</small>
                        @if($s->tipo === 'interplantas')
                            <i class="bi bi-arrow-right mx-1"></i>
                            <small>{{ $s->plantaDestino?->nombre ?? '—' }}</small>
                        @endif
                    </td>
                    <td>
                        <small>{{ $s->fecha_inicio->format('d/m H:i') }}</small><br>
                        <small class="text-muted">→ {{ $s->fecha_fin->format('d/m H:i') }}</small>
                    </td>
                    <td><span class="badge {{ $estadoClass }}">{{ $estadoLabel }}</span></td>
                    <td>
                        <a href="{{ route('solicitudes.show.int', $s->id_solicitud_int) }}" class="btn btn-sm btn-outline-primary" title="Ver detalle">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                        <i class="bi bi-inbox"></i>
                        <small>No hay solicitudes internas.</small>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Paginación de internas --}}
@if($solicitudesInt->hasPages())
<div class="mb-4">
    {{ $solicitudesInt->withQueryString()->links() }}
</div>
@endif

{{-- ===================================================== --}}
{{-- TABLA DE SOLICITUDES EXTERNAS --}}
{{-- ===================================================== --}}
<div class="card border-0 shadow-sm sca-card" style="border-radius:12px;">
    <div class="card-header bg-transparent py-3">
        <h5 class="mb-0 fw-bold"><i class="bi bi-people text-success"></i> Solicitudes Externas (Visitas / Contratistas)</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th width="60">#</th>
                    <th>Tipo</th>
                    <th>Personas</th>
                    <th>Planta</th>
                    <th>Vigencia</th>
                    <th>Estado</th>
                    <th width="80">Acción</th>
                </tr>
            </thead>
            <tbody>
                @forelse($solicitudesExt as $s)
                @php
                    $tipoIcon = $s->tipo === 'contratista' ? 'bi-person-badge' : 'bi-person';
                    $tipoLabel = $s->tipo === 'contratista' ? 'Contratista' : 'Visita';

                    $esCancelada = $s->estado === 'rechazado' && str_contains($s->motivo_rechazo ?? '', 'Cancelada');

                    $estadoClass = match($s->estado) {
                        'pendiente' => 'bg-warning text-dark',
                        'aprobado'  => 'bg-success',
                        'rechazado' => $esCancelada ? 'bg-secondary' : 'bg-danger',
                        default     => 'bg-secondary',
                    };
                    $estadoLabel = match($s->estado) {
                        'pendiente' => 'Pendiente',
                        'aprobado'  => 'Aprobado',
                        'rechazado' => $esCancelada ? 'Cancelada' : 'Rechazado',
                        default     => $s->estado,
                    };
                @endphp
                <tr>
                    <td class="font-monospace fw-bold">{{ $s->id_solicitud_ext }}</td>
                    <td>
                        <i class="bi {{ $tipoIcon }} text-success"></i>
                        <span class="small">{{ $tipoLabel }}</span>
                    </td>
                    <td>
                        @if($s->externos->isNotEmpty())
                            <strong>{{ $s->externos->count() }} persona(s)</strong><br>
                            <small class="text-muted">{{ $s->externos->first()->externo?->nombre_completo }}</small>
                            @if($s->externos->count() > 1)
                                <small class="text-muted"> y {{ $s->externos->count() - 1 }} más</small>
                            @endif
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td><small>{{ $s->plantaDestino?->nombre ?? '—' }}</small></td>
                    <td>
                        <small>{{ $s->fecha_inicio->format('d/m H:i') }}</small><br>
                        <small class="text-muted">→ {{ $s->fecha_fin->format('d/m H:i') }}</small>
                    </td>
                    <td><span class="badge {{ $estadoClass }}">{{ $estadoLabel }}</span></td>
                    <td>
                        <a href="{{ route('solicitudes.show.ext', $s->id_solicitud_ext) }}" class="btn btn-sm btn-outline-primary" title="Ver detalle">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                        <i class="bi bi-inbox"></i>
                        <small>No hay solicitudes externas.</small>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Paginación de externas --}}
@if($solicitudesExt->hasPages())
<div class="mt-3">
    {{ $solicitudesExt->withQueryString()->links() }}
</div>
@endif

@endsection