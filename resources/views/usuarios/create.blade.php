{{-- resources/views/usuarios/create.blade.php --}}
@extends('layouts.app')

@section('title', 'Nuevo Usuario')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-0">Nuevo Usuario</h1>
    <p class="text-muted mb-0">Seleccione el tipo de usuario a crear</p>
</div>

{{-- Tabs --}}
<ul class="nav nav-tabs mb-3" id="tipoTab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="empleado-tab" data-bs-toggle="tab" data-bs-target="#empleado" type="button">
            <i class="bi bi-person-badge"></i> Empleado Interno
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="guardia-tab" data-bs-toggle="tab" data-bs-target="#guardia" type="button">
            <i class="bi bi-shield-lock"></i> Guardia Externo
        </button>
    </li>
</ul>

<div class="tab-content">
    {{-- =================== TAB: EMPLEADO INTERNO =================== --}}
    <div class="tab-pane fade show active" id="empleado" role="tabpanel">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ route('usuarios.store.empleado') }}">
                    @csrf

                    {{-- Paso 1: buscar empleado en sitec --}}
                    <div class="mb-3">
                        <label class="form-label">Número de Empleado (de sitec)</label>
                        <div class="input-group">
                            <input type="number" name="id_empleado" id="id_empleado" class="form-control" required value="{{ old('id_empleado') }}">
                            <button type="button" class="btn btn-outline-success" id="btnBuscar">
                                <i class="bi bi-search"></i> Buscar
                            </button>
                        </div>
                        <div class="form-text">Ingrese el ID del empleado y haga clic en Buscar.</div>
                    </div>

                    {{-- Datos del empleado encontrados (AJAX) --}}
                    <div id="datosEmpleado" class="alert alert-info d-none">
                        <div class="row">
                            <div class="col-md-6"><strong>Nombre:</strong> <span id="empNombre"></span></div>
                            <div class="col-md-6"><strong>Puesto:</strong> <span id="empPuesto"></span></div>
                            <div class="col-md-6"><strong>Departamento:</strong> <span id="empDepto"></span></div>
                            <div class="col-md-6"><strong>Turno:</strong> <span id="empTurno"></span></div>
                        </div>
                    </div>

                    <div id="empError" class="alert alert-danger d-none"></div>

                    {{-- Paso 2: datos de la cuenta --}}
                    <hr>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Rol</label>
                            <select name="id_rol" class="form-select" required>
                                <option value="">Seleccionar...</option>
                                @foreach($rolesPermitidos as $r)
                                    <option value="{{ $r->id }}" @if(old('id_rol')==$r->id) selected @endif>{{ $r->nombre }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipo de Empleado</label>
                            <select name="tipo_empleado" id="tipo_empleado" class="form-select" required>
                                <option value="">Seleccionar...</option>
                                <option value="operativo" @if(old('tipo_empleado')=='operativo') selected @endif>Operativo</option>
                                <option value="administrativo" @if(old('tipo_empleado')=='administrativo') selected @endif>Administrativo</option>
                                <option value="corporativo" @if(old('tipo_empleado')=='corporativo') selected @endif>Corporativo</option>
                            </select>
                            <div class="form-text" id="tipoHint"></div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Planta base</label>
                            <select name="planta_id" id="planta_id" class="form-select" required>
                                <option value="">Seleccionar...</option>
                                @foreach($plantas as $p)
                                    <option value="{{ $p->id }}" @if(old('planta_id')==$p->id) selected @endif>{{ $p->nombre }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Planta donde tiene su base. Los corporativos tienen acceso a todas las plantas según RN-01.</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contraseña</label>
                            <input type="password" name="password" class="form-control" required minlength="8" value="{{ old('password') }}">
                            <div class="form-text">Mínimo 8 caracteres, con mayúsculas, minúsculas, números y símbolos.</div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle"></i> Crear Usuario
                        </button>
                        <a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- =================== TAB: GUARDIA EXTERNO =================== --}}
    <div class="tab-pane fade" id="guardia" role="tabpanel">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ route('usuarios.store.guardia') }}">
                    @csrf

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre completo del guardia</label>
                            <input type="text" name="nombre_completo" class="form-control" required maxlength="200" value="{{ old('nombre_completo') }}">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Número de guardia (5 dígitos)</label>
                            <input type="text" name="numero_guardia" class="form-control" required pattern="[0-9]{5}" maxlength="5" value="{{ old('numero_guardia') }}">
                            <div class="form-text">El usuario final será: <strong>G-<span id="previewNum">XXXXX</span></strong></div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Planta asignada</label>
                            <select name="planta_id" class="form-select" required>
                                <option value="">Seleccionar...</option>
                                @foreach($plantas as $p)
                                    <option value="{{ $p->id }}" @if(old('planta_id')==$p->id) selected @endif>{{ $p->nombre }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contraseña</label>
                            <input type="password" name="password" class="form-control" required minlength="8" value="{{ old('password') }}">
                            <div class="form-text">Mínimo 8 caracteres, con mayúsculas, minúsculas, números y símbolos.</div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle"></i> Crear Guardia
                        </button>
                        <a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// ===== AJAX: buscar empleado en sitec =====
document.getElementById('btnBuscar').addEventListener('click', async function() {
    const id = document.getElementById('id_empleado').value;
    const datos = document.getElementById('datosEmpleado');
    const error = document.getElementById('empError');
    const tipoSelect = document.getElementById('tipo_empleado');
    const tipoHint = document.getElementById('tipoHint');

    datos.classList.add('d-none');
    error.classList.add('d-none');

    if (!id) return;

    try {
        const res = await fetch(`{{ route('usuarios.buscar') }}?id=${id}`);
        const data = await res.json();

        if (!data.encontrado) {
            error.textContent = 'Empleado no encontrado en sitec.';
            error.classList.remove('d-none');
            return;
        }

        if (!data.activo) {
            error.textContent = 'El empleado no tiene estatus "Alta" en sitec.';
            error.classList.remove('d-none');
            return;
        }

        if (data.tiene_cuenta) {
            error.textContent = 'Este empleado ya tiene una cuenta de usuario.';
            error.classList.remove('d-none');
            return;
        }

        document.getElementById('empNombre').textContent = data.nombre;
        document.getElementById('empPuesto').textContent = data.puesto || '—';
        document.getElementById('empDepto').textContent = data.departamento || '—';
        document.getElementById('empTurno').textContent = data.turno || '—';
        datos.classList.remove('d-none');

        // Sugerir tipo_empleado según el puesto clasificado
        if (data.tipo_sugerido) {
            tipoSelect.value = data.tipo_sugerido;
            tipoHint.textContent = `Sugerido según el puesto "${data.puesto}".`;
            tipoHint.className = 'form-text text-success';
        } else {
            tipoHint.textContent = `El puesto "${data.puesto}" no está clasificado aún. Se clasificará al guardar.`;
            tipoHint.className = 'form-text text-warning';
        }
    } catch (e) {
        error.textContent = 'Error al buscar el empleado.';
        error.classList.remove('d-none');
    }
});

// ===== Preview del username de guardia =====
document.querySelector('input[name="numero_guardia"]').addEventListener('input', function(e) {
    document.getElementById('previewNum').textContent = e.target.value || 'XXXXX';
});
</script>
@endpush
@endsection