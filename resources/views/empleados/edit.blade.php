{{-- resources/views/empleados/edit.blade.php --}}
{{--
  VISTA: Editar Empleado
  Muestra datos de sitec (read-only) y permite configurar tipo_empleado.
  La planta se deriva automáticamente de sitec (no es editable).
  Permite resetear el contador de búsquedas manuales si está bloqueado.
--}}
@extends('layouts.app')

@section('title', 'Configurar Empleado')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-0">Configurar Empleado</h1>
    <p class="text-muted mb-0">
        ID: <span class="font-monospace">{{ $empleado->id_empleado }}</span> ·
        {{ $empleado->nombre_completo }}
    </p>
</div>

{{-- Datos de sitec (solo lectura) --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white">
        <h2 class="h6 mb-0"><i class="bi bi-info-circle text-success"></i> Datos de sitec (solo lectura)</h2>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <strong>Estatus:</strong>
                @if($empleado->estaActivo())
                    <span class="badge bg-success">Alta</span>
                @else
                    <span class="badge bg-danger">{{ $empleado->estatus }}</span>
                @endif
            </div>
            <div class="col-md-3"><strong>Puesto:</strong> {{ $empleado->puesto?->nombre ?? '—' }}</div>
            <div class="col-md-3"><strong>Departamento:</strong> {{ $empleado->departamento?->nombre ?? '—' }}</div>
            <div class="col-md-3"><strong>Turno:</strong> {{ $empleado->turno?->nombre ?? '—' }}</div>
        </div>
    </div>
</div>

<form method="POST" action="{{ route('empleados.update', $empleado->id_empleado) }}">
    @csrf
    @method('PUT')

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white">
            <h2 class="h6 mb-0"><i class="bi bi-gear text-success"></i> Configuración de acceso</h2>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Tipo de Empleado</label>
                    <select name="tipo_empleado" class="form-select" required>
                        <option value="">— Seleccionar —</option>
                        <option value="operativo" @if(old('tipo_empleado', $ext->tipo_empleado)=='operativo') selected @endif>Operativo</option>
                        <option value="administrativo" @if(old('tipo_empleado', $ext->tipo_empleado)=='administrativo') selected @endif>Administrativo</option>
                        <option value="corporativo" @if(old('tipo_empleado', $ext->tipo_empleado)=='corporativo') selected @endif>Corporativo</option>
                    </select>
                    @if($tipoSugerido)
                        <div class="form-text text-success">Sugerido según el puesto: <strong>{{ ucfirst($tipoSugerido) }}</strong></div>
                    @else
                        <div class="form-text text-warning">El puesto no está clasificado en el catálogo. Clasifíquelo manualmente.</div>
                    @endif
                </div>

                {{-- NUEVO: Planta derivada de sitec (read-only) --}}
                <div class="col-md-6 mb-3">
                    <label class="form-label">Planta (derivada de sitec)</label>
                    <div class="form-control bg-light" style="cursor: not-allowed;">
                        @if($plantaDerivada)
                            <span class="badge bg-success">{{ $plantaDerivadaNombre }}</span>
                            <i class="bi bi-lock-fill text-muted ms-2" title="Se asigna automáticamente"></i>
                        @else
                            <span class="badge bg-warning text-dark">
                                Sin planta (clasificación sin mapear)
                            </span>
                            <i class="bi bi-info-circle text-muted ms-2"
                               title="Configure el mapeo en Clasificaciones → Plantas"></i>
                        @endif
                    </div>
                    <div class="form-text">
                        La planta se asigna automáticamente según la clasificación
                        del empleado en sitec. No es editable.
                    </div>
                </div>
            </div>

            {{-- Si el empleado ya tenía configuración, mostramos el estado de sus búsquedas manuales --}}
            @if($ext->exists)
            <hr>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Búsquedas manuales</label>
                    <div>
                        @if($ext->bloqueo_manual)
                            <span class="badge bg-danger">Bloqueado (agotó los 4 intentos)</span>
                        @else
                            <span class="badge bg-info text-dark">{{ $ext->usos_manuales_restantes }}/4 restantes</span>
                        @endif
                    </div>
                </div>
                <div class="col-md-6 mb-3 d-flex align-items-end">
                    <div class="form-check">
                        <input type="checkbox" name="resetear_manual" value="1" class="form-check-input" id="resetear">
                        <label class="form-check-label" for="resetear">Resetear contador a 4 y desbloquear</label>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-success">
            <i class="bi bi-check-circle"></i> Guardar Configuración
        </button>
        <a href="{{ route('empleados.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
@endsection