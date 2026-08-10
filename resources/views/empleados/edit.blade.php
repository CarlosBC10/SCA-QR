{{-- resources/views/empleados/edit.blade.php --}}
{{--
    CAMBIOS V4:
      - Eliminado $ext->bloqueo_manual (se infiere de usos_manuales_restantes = 0).
      - Usa el accessor $ext->bloqueado_manual.
      - El reset ya no setea bloqueo_manual=false (no existe la columna).
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
                        <div class="form-text text-warning">El puesto no está clasificado en el catálogo.</div>
                    @endif
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Planta asignada</label>
                    @php
                        // FIX E: Solo Admin/Super pueden cambiar la planta (override manual).
                        $puedeEditarPlanta = auth()->user()->isAdmin() || auth()->user()->isSuperadmin();
                        $plantaActual = $ext->id_planta ?? null;
                        $estaForzada = $ext->planta_forzada ?? false;
                    @endphp
                    @if($puedeEditarPlanta)
                        <select name="id_planta" class="form-select">
                            {{-- Opción Automática --}}
                            <option value="auto" @if(!$estaForzada) selected @endif>
                                Automática (según sitec): {{ $plantaDerivadaNombre ?? 'Sin mapear' }}
                            </option>

                            {{-- Opciones manuales (Forzar) --}}
                            @foreach($plantas as $p)
                                @php
                                    // FIX E: Ocultamos la planta derivada de sitec de la lista "Forzar" SIEMPRE.
                                    // ¿Por qué? Porque esa planta ya está representada por la opción "Automática" de arriba.
                                    // Así evitamos que se repita en la lista.
                                    $ocultar = ((string)($plantaDerivada ?? null) === (string)$p->id_planta);
                                @endphp
                                @if(!$ocultar)
                                    <option value="{{ $p->id_planta }}"
                                        @if($estaForzada && (string)$plantaActual === (string)$p->id_planta) selected @endif>
                                        Forzar: {{ $p->nombre }}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                        <div class="form-text">
                            @if($estaForzada)
                                <span class="text-warning"><i class="bi bi-exclamation-triangle"></i> Esta planta fue asignada manualmente. Se respetará ante cambios en sitec.</span>
                            @else
                                <i class="bi bi-info-circle"></i> Selecciona una planta específica para forzarla (override).
                            @endif
                        </div>
                    @else
                        {{-- Aprobador: solo lectura --}}
                        <div class="form-control bg-light" style="cursor: not-allowed;">
                            @if($plantaActual)
                                <span class="badge bg-success">{{ $plantas->firstWhere('id_planta', $plantaActual)?->nombre ?? 'Planta #' . $plantaActual }}</span>
                                @if($estaForzada)
                                    <small class="text-muted ms-2">(forzada manualmente)</small>
                                @else
                                    <small class="text-muted ms-2">(automática por sitec)</small>
                                @endif
                            @else
                                <span class="badge bg-warning text-dark">Sin planta asignada</span>
                            @endif
                        </div>
                        <div class="form-text text-muted">Solo Admin/Superadmin puede cambiar la planta.</div>
                    @endif
                </div>
            </div>

            @if($ext->exists)
            <hr>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Búsquedas manuales</label>
                    <div>
                        {{-- V4: $ext->bloqueado_manual es un accessor (usos_manuales_restantes <= 0) --}}
                        @if($ext->bloqueado_manual)
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