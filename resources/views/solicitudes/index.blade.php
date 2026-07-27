{{-- resources/views/solicitudes/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Historial de Solicitudes')

@section('content')
{{-- Encabezado único con botones de navegación --}}
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Historial de Solicitudes</h1>
        <p class="text-muted mb-0">Consulte el estado de sus solicitudes</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('solicitudes.hub') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Hub
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
            <div class="col-md-4">
                <label class="form-label small text-muted">Tipo</label>
                <select name="tipo" class="form-select">
                    <option value="">Todos</option>
                    <option value="pase_salida" @if(request('tipo')=='pase_salida') selected @endif>Pase de Salida</option>
                    <option value="interplantas" @if(request('tipo')=='interplantas') selected @endif>Interplantas</option>
                    <option value="visita" @if(request('tipo')=='visita') selected @endif>Visita</option>
                    <option value="contratista" @if(request('tipo')=='contratista') selected @endif>Contratista</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted">Estado</label>
                <select name="estado" class="form-select">
                    <option value="">Todos</option>
                    <option value="pendiente_origen" @if(request('estado')=='pendiente_origen') selected @endif>Pendiente Origen</option>
                    <option value="pendiente_destino" @if(request('estado')=='pendiente_destino') selected @endif>Pendiente Destino</option>
                    <option value="aprobado" @if(request('estado')=='aprobado') selected @endif>Aprobado</option>
                    <option value="rechazado" @if(request('estado')=='rechazado') selected @endif>Rechazado</option>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-outline-success w-100">
                    <i class="bi bi-search"></i> Filtrar
                </button>
            </div>
        </div>
    </div>
</form>

{{-- TABLA DE SOLICITUDES --}}
<div class="card border-0 shadow-sm sca-card" style="border-radius:12px;">
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
                @forelse($solicitudes as $s)
                @php
                    $tipoIcon = ['pase_salida'=>'bi-box-arrow-right','interplantas'=>'bi-arrow-left-right','visita'=>'bi-person','contratista'=>'bi-person-badge'][$s->tipo] ?? 'bi-question';
                    $tipoLabel = ['pase_salida'=>'Pase de Salida','interplantas'=>'Interplantas','visita'=>'Visita','contratista'=>'Contratista'][$s->tipo] ?? $s->tipo;
                    
                    $estadoClass = match($s->estado) {
                        'pendiente_origen' => 'bg-warning text-dark',
                        'pendiente_destino' => 'bg-info text-dark',
                        'aprobado' => 'bg-success',
                        'rechazado' => 'bg-danger',
                        default => 'bg-secondary'
                    };
                    $estadoLabel = match($s->estado) {
                        'pendiente_origen' => 'Pend. Origen',
                        'pendiente_destino' => 'Pend. Destino',
                        'aprobado' => 'Aprobado',
                        'rechazado' => 'Rechazado',
                        default => $s->estado
                    };
                @endphp
                <tr>
                    <td class="font-monospace fw-bold">{{ $s->id }}</td>
                    <td>
                        <i class="bi {{ $tipoIcon }} text-success"></i>
                        <span class="small">{{ $tipoLabel }}</span>
                    </td>
                    <td>
                        @if($s->persona)
                            <strong>{{ $s->persona->nombre_completo }}</strong>
                            @if($s->persona->empresa)<br><small class="text-muted">{{ $s->persona->empresa }}</small>@endif
                        @elseif($s->tipo === 'interplantas' && $s->empleados->isNotEmpty())
                            <strong>{{ $s->empleados->count() }} empleado(s)</strong>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <small>{{ $s->plantaOrigen?->nombre ?? '—' }}</small>
                        <i class="bi bi-arrow-right mx-1"></i>
                        <small>{{ $s->plantaDestino?->nombre ?? '—' }}</small>
                    </td>
                    <td>
                        <small>{{ $s->fecha_inicio->format('d/m H:i') }}</small><br>
                        <small class="text-muted">→ {{ $s->fecha_fin->format('d/m H:i') }}</small>
                    </td>
                    <td><span class="badge {{ $estadoClass }}">{{ $estadoLabel }}</span></td>
                    <td>
                        <a href="{{ route('solicitudes.show', $s->id) }}" class="btn btn-sm btn-outline-primary" title="Ver detalle">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-5">
                        <i class="bi bi-inbox fs-1"></i>
                        <p class="mt-2 mb-0">No hay solicitudes.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">
    {{ $solicitudes->withQueryString()->links() }}
</div>
@endsection