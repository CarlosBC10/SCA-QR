{{-- resources/views/empleados/index.blade.php --}}
{{--
    CAMBIOS V4:
      - Filtro planta: id → id_planta.
      - El resto no cambia (usa accesores de Sitec\Empleado que siguen funcionando).
--}}
@extends('layouts.app')

@section('title', 'Gestión de Empleados')

@section('content')
<div class="sca-page-head legacy-page-head">
    <div>
        <div class="sca-page-eyebrow"><i class="bi bi-person-vcard" aria-hidden="true"></i> Gestión</div>
        <h1 class="sca-page-title">Empleados</h1>
        <p class="sca-page-subtitle">Verifica clasificación, planta y turno de los empleados sincronizados.</p>
    </div>
</div>

<form method="GET" class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label small">Buscar (ID o Nombre)</label>
                <input type="text" name="buscar" class="form-control" value="{{ request('buscar') }}" placeholder="Ej: 1001 o Juan Pérez">
            </div>
            <div class="col-md-4">
                <label class="form-label small">Planta</label>
                <select name="planta" class="form-select">
                    <option value="">Todas / Sin asignar</option>
                    @foreach($plantas as $p)
                        <option value="{{ $p->id_planta }}" @if(request('planta')==$p->id_planta) selected @endif>{{ $p->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-success w-100">
                    <i class="bi bi-search"></i> Buscar
                </button>
            </div>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Puesto</th>
                    <th>Departamento</th>
                    <th>Turno</th>
                    <th>Planta</th>
                    <th>Tipo</th>
                    <th width="80">Acción</th>
                </tr>
            </thead>
            <tbody>
                @forelse($empleados as $e)
                <tr>
                    <td class="font-monospace">{{ $e->id_empleado }}</td>
                    <td class="fw-semibold">{{ $e->nombre_completo }}</td>
                    <td>{{ $e->puesto?->nombre ?? '—' }}</td>
                    <td>{{ $e->departamento?->nombre ?? '—' }}</td>
                    <td>
                        @if($e->turno)
                            <span class="badge bg-light text-dark border" title="{{ $e->turno->entrada }} - {{ $e->turno->salida }}">
                                <i class="bi bi-clock"></i> {{ $e->turno->nombre }}
                            </span>
                            <div class="small text-muted">{{ $e->turno->entrada }} - {{ $e->turno->salida }}</div>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @php
                            $plantaDerivada = $e->plantaDerivadaId ?? null;
                            $plantaNombre = $e->plantaDerivadaNombre ?? 'Sin planta';
                        @endphp
                        @if($plantaDerivada)
                            <span class="badge bg-success">{{ $plantaNombre }}</span>
                        @else
                            <span class="badge bg-warning text-dark">Sin planta</span>
                        @endif
                    </td>
                    <td>
                        @if($e->ext?->tipo_empleado)
                            @php
                                $tipoClass = match($e->ext->tipo_empleado) {
                                    'operativo' => 'bg-warning text-dark',
                                    'administrativo' => 'bg-info text-dark',
                                    'corporativo' => 'bg-success',
                                    default => 'bg-secondary'
                                };
                            @endphp
                            <span class="badge {{ $tipoClass }}">{{ ucfirst($e->ext->tipo_empleado) }}</span>
                        @else
                            <span class="badge bg-secondary">No clasificado</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('empleados.edit', $e->id_empleado) }}" class="btn btn-sm btn-outline-primary" title="Configurar">
                            <i class="bi bi-pencil"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">No se encontraron empleados.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">
    {{ $empleados->withQueryString()->links() }}
</div>
@endsection