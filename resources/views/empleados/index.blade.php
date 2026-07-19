{{-- resources/views/empleados/index.blade.php --}}
{{-- 
  VISTA: Listado de Empleados (25,000+)
  Consulta directamente sitec.empleados y hace LEFT JOIN con empleados_ext_sca (relación 'ext').
  Así vemos a TODOS los empleados, tengan o no configuración.
--}}
@extends('layouts.app')

@section('title', 'Gestión de Empleados')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Gestión de Empleados</h1>
        <p class="text-muted mb-0">Asigne planta y verifique la clasificación de los empleados</p>
    </div>
</div>

{{-- Buscador de empleados --}}
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
                        <option value="{{ $p->id }}" @if(request('planta')==$p->id) selected @endif>{{ $p->nombre }}</option>
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

{{-- Tabla de resultados --}}
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Puesto</th>
                    <th>Departamento</th>
                    <th>Planta</th>
                    <th>Tipo</th>
                    <th width="80">Acción</th>
                </tr>
            </thead>
            <tbody>
                @forelse($empleados as $e)
                <tr>
                    <td class="font-monospace">{{ $e->id_empleado }}</td>
                    <td>{{ $e->nombre_completo }}</td>
                    <td>{{ $e->puesto?->nombre ?? '—' }}</td>
                    <td>{{ $e->departamento?->nombre ?? '—' }}</td>
                    <td>
                        {{-- Si tiene planta asignada, la mostramos. Si no, avisamos que falta asignar --}}
                        @if($e->ext?->planta_id)
                            <span class="badge bg-success">{{ $e->ext->planta->nombre }}</span>
                        @else
                            <span class="badge bg-warning text-dark">Sin asignar</span>
                        @endif
                    </td>
                    <td>
                        {{-- Si tiene tipo, lo mostramos. Si no, avisamos que no está clasificado --}}
                        @if($e->ext?->tipo_empleado)
                            <span class="badge bg-info text-dark">{{ ucfirst($e->ext->tipo_empleado) }}</span>
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
                    <td colspan="7" class="text-center text-muted py-4">No se encontraron empleados.</td>
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