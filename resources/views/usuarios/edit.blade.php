{{-- resources/views/usuarios/edit.blade.php --}}
@extends('layouts.app')

@section('title', 'Editar Usuario')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-0">Editar Usuario</h1>
    <p class="text-muted mb-0">{{ $usuario->nombre_completo }} · <span class="font-monospace">{{ $usuario->username }}</span></p>
</div>

<form method="POST" action="{{ route('usuarios.update', $usuario) }}">
    @csrf
    @method('PUT')

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Rol</label>
                    <select name="id_rol" class="form-select" required>
                        @foreach($rolesPermitidos as $r)
                            <option value="{{ $r->id }}" @if(old('id_rol', $usuario->id_rol)==$r->id) selected @endif>{{ $r->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Estado</label>
                    <select name="activo" class="form-select" required>
                        <option value="1" @if(old('activo', $usuario->activo)) selected @endif>Activo</option>
                        <option value="0" @if(!old('activo', $usuario->activo)) selected @endif>Inactivo</option>
                    </select>
                </div>

                @if(!$usuario->guardia_id)
                {{-- Empleado interno: planta derivada de sitec (no editable) --}}
                <div class="col-md-6 mb-3">
                    <label class="form-label">Tipo de Empleado</label>
                    <select name="tipo_empleado" class="form-select">
                        <option value="">—</option>
                        <option value="operativo" @if(old('tipo_empleado', $tipoEmpleado)=='operativo') selected @endif>Operativo</option>
                        <option value="administrativo" @if(old('tipo_empleado', $tipoEmpleado)=='administrativo') selected @endif>Administrativo</option>
                        <option value="corporativo" @if(old('tipo_empleado', $tipoEmpleado)=='corporativo') selected @endif>Corporativo</option>
                    </select>
                </div>

                {{-- NUEVO: Planta read-only derivada de sitec --}}
                <div class="col-md-6 mb-3">
                    <label class="form-label">Planta base</label>
                    @php
                        $empleadoEdit = \App\Models\Sitec\Empleado::find($usuario->username);
                        $plantaEdit = $empleadoEdit?->plantaId();
                        $plantaEditNombre = $empleadoEdit?->plantaNombre();
                    @endphp
                    <div class="form-control bg-light" style="cursor: not-allowed;">
                        @if($plantaEdit)
                            <span class="badge bg-success">{{ $plantaEditNombre }}</span>
                            <i class="bi bi-lock-fill text-muted ms-2"></i>
                        @else
                            <span class="badge bg-warning text-dark">Sin planta</span>
                        @endif
                    </div>
                    <div class="form-text">Se asigna automáticamente desde sitec.</div>
                </div>
                @else
                {{-- Guardia externo: planta editable --}}
                <div class="col-md-6 mb-3">
                    <label class="form-label">Planta asignada</label>
                    <select name="planta_id" class="form-select" required>
                        @foreach($plantas as $p)
                            <option value="{{ $p->id }}" @if(old('planta_id', $plantaActual)==$p->id) selected @endif>{{ $p->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                <div class="col-12 mb-3">
                    <label class="form-label">Restablecer contraseña</label>
                    <input type="password" name="password" class="form-control" minlength="8" value="{{ old('password') }}">
                    <div class="form-text">Dejar en blanco para mantener la contraseña actual. Si la cambia, mínimo 8 caracteres con mayúsculas, minúsculas, números y símbolos.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-success">
            <i class="bi bi-check-circle"></i> Guardar Cambios
        </button>
        <a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
@endsection