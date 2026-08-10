{{-- resources/views/clasificaciones/index.blade.php --}}
{{--
    Mapeo de clasificaciones de sitec → plantas.
    Permite configurar a qué planta pertenece cada clasificación de sitec.
    Una vez asignado, todos los empleados con esa clasificación quedan
    automáticamente mapeados a esa planta (la planta se deriva en tiempo real).

    CAMBIOS V4:
      - name="planta_id" → name="id_planta" (FK renombrada en schema V4).
      - $p->id → $p->id_planta (PK renombrada).
      - $mapeo?->planta_id → $mapeo?->id_planta (FK en clasificacion_planta).
      - El controlador (ClasificacionPlantaController) ya fue actualizado en
        el Bloque 9 para recibir id_planta en lugar de planta_id.
--}}
@extends('layouts.app')

@section('title', 'Clasificaciones → Plantas')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-0">Clasificaciones → Plantas</h1>
    <p class="text-muted mb-0">
        Configure a qué planta pertenece cada clasificación de sitec.
        Una vez asignado, todos los empleados con esa clasificación quedan
        automáticamente mapeados a esa planta.
    </p>
</div>

<div class="alert alert-info d-flex align-items-center">
    <i class="bi bi-info-circle-fill me-3 fs-4"></i>
    <div>
        <strong>Importante:</strong> La planta del empleado se deriva de esta
        configuración en tiempo real. Si cambian la clasificación de un empleado
        en sitec, el próximo escaneo ya usa la nueva planta.
        <strong>CORPORATIVO no se mapea</strong> — los corporativos acceden por
        su tipo de empleado (RN-01).
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th width="60">ID</th>
                    <th>Clasificación (sitec)</th>
                    <th width="120">Empleados</th>
                    <th>Planta asignada</th>
                    <th width="200">Acción</th>
                </tr>
            </thead>
            <tbody>
                @forelse($clasificaciones as $c)
                @php
                    // $mapeos es una colección keyBy('id_clasificacion')
                    $mapeo = $mapeos->get($c->id_clasificacion);
                    // $conteoEmpleados es un pluck('total', 'id_clasificacion')
                    $conteo = $conteoEmpleados->get($c->id_clasificacion, 0);
                @endphp
                <tr>
                    <td class="font-monospace">{{ $c->id_clasificacion }}</td>
                    <td>{{ $c->nombre }}</td>
                    <td>
                        <span class="badge bg-secondary">{{ $conteo }}</span>
                    </td>
                    <td>
                        @if($mapeo)
                            <span class="badge bg-success">{{ $mapeo->planta->nombre }}</span>
                        @else
                            <span class="badge bg-warning text-dark">Sin mapear</span>
                        @endif
                    </td>
                    <td>
                        {{-- Formulario para asignar/cambiar planta --}}
                        <form method="POST"
                              action="{{ route('clasificaciones.update', $c->id_clasificacion) }}"
                              class="d-flex gap-1">
                            @csrf
                            @method('PUT')
                            {{-- V4: name="id_planta" (era planta_id) --}}
                            <select name="id_planta" class="form-select form-select-sm" required>
                                <option value="">—</option>
                                @foreach($plantas as $p)
                                    {{-- V4: value=$p->id_planta y comparar con $mapeo?->id_planta --}}
                                    <option value="{{ $p->id_planta }}"
                                        @if($mapeo?->id_planta == $p->id_planta) selected @endif>
                                        {{ $p->nombre }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-sm btn-success" title="Guardar">
                                <i class="bi bi-check-lg"></i>
                            </button>

                            {{-- FIX E: Opción para forzar el remapeo a todos los empleados --}}
                            <div class="form-check ms-2 d-flex align-items-center">
                                <input class="form-check-input" type="checkbox" name="forzar_todos" value="1" id="forzarTodos_{{ $c->id_clasificacion }}">
                                <label class="form-check-label small ms-1" for="forzarTodos_{{ $c->id_clasificacion }}" title="Sobreescribir asignaciones manuales previas">
                                    Forzar
                                </label>
                            </div>
                        </form>
                        {{-- Botón para eliminar el mapeo (si existe) --}}
                        @if($mapeo)
                            <form method="POST"
                                  action="{{ route('clasificaciones.destroy', $c->id_clasificacion) }}"
                                  class="mt-1"
                                  onsubmit="return confirm('¿Eliminar mapeo de {{ $c->nombre }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                                    <i class="bi bi-trash"></i> Quitar
                                </button>
                            </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">
                        No hay clasificaciones en sitec.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection