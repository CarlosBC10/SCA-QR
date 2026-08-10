{{-- resources/views/usuarios/create.blade.php --}}
@extends('layouts.app')

@section('title', 'Nuevo Usuario')

@section('content')

@if ($errors->any())
<div class="alert alert-danger" style="border-radius:12px;">
    <h6 class="fw-bold mb-2"><i class="bi bi-exclamation-triangle"></i> Corrija los siguientes errores:</h6>
    <ul class="mb-0 ps-3">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="mb-4">
    <h1 class="h3 mb-0">Nuevo Usuario</h1>
    <p class="text-muted mb-0">Seleccione el tipo de usuario a crear</p>
</div>

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

                    <div class="mb-3">
                        <label class="form-label">Número de Empleado (de sitec)</label>
                        <div class="input-group">
                            <input type="text" name="id_empleado" id="id_empleado" class="form-control" required value="{{ old('id_empleado') }}" placeholder="ID o Nombre del empleado">                            
                            <button type="button" class="btn btn-outline-success" id="btnBuscar">
                                <i class="bi bi-search"></i> Buscar
                            </button>
                        </div>
                        <div class="form-text">Ingrese el ID del empleado y haga clic en Buscar.</div>
                    </div>

                    <div id="datosEmpleado" class="alert alert-info d-none">
                        <div class="row">
                            <div class="col-md-6"><strong>Nombre:</strong> <span id="empNombre"></span></div>
                            <div class="col-md-6"><strong>Puesto:</strong> <span id="empPuesto"></span></div>
                            <div class="col-md-6"><strong>Departamento:</strong> <span id="empDepto"></span></div>
                            <div class="col-md-6"><strong>Turno:</strong> <span id="empTurno"></span></div>
                            <div class="col-md-6 mt-2"><strong>Planta (derivada):</strong> <span id="empPlanta"></span></div>
                        </div>
                    </div>

                    <div id="empError" class="alert alert-danger d-none"></div>

                    <hr>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Rol</label>
                            <select name="id_rol" class="form-select" required>
                                <option value="">Seleccionar...</option>
                                @foreach($rolesPermitidos as $r)
                                    <option value="{{ $r->id_rol }}" @if(old('id_rol')==$r->id_rol) selected @endif>{{ $r->nombre }}</option>
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
                            <input type="text" class="form-control bg-light" readonly value="Se asigna automáticamente desde sitec">
                            <div class="form-text">
                                <i class="bi bi-info-circle"></i>
                                La planta se deriva de la clasificación del empleado en sitec.
                            </div>
                        </div>

                        {{-- FIX I-10: Input para correo institucional obligatorio --}}
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Correo Institucional <span class="text-danger">*</span></label>
                            <input type="email" name="email_emp" class="form-control @error('email_emp') is-invalid @enderror" required value="{{ old('email_emp') }}" placeholder="correo@empresa.com">
                            @error('email_emp')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Se enviarán las credenciales a este correo.</div>
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
                            <label class="form-label">Usuario del guardia</label>
                            <input type="text" name="id_guardia" class="form-control" required pattern="G-\d{4,5}" maxlength="10" value="{{ old('id_guardia') }}" placeholder="Ej: G-0001">
                            <div class="form-text">Formato: G- seguido de 4 o 5 dígitos. Este será su usuario para iniciar sesión.</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre completo del guardia</label>
                            <input type="text" name="nombre_completo" class="form-control" required maxlength="200" value="{{ old('nombre_completo') }}">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">CURP</label>
                            <input type="text" name="curp" class="form-control" maxlength="18" value="{{ old('curp') }}" oninput="this.value = this.value.toUpperCase()" placeholder="18 caracteres (opcional)">
                            <div class="form-text">Opcional pero recomendado. Debe ser única.</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Planta asignada</label>
                            <select name="id_planta" class="form-select" required>
                                <option value="">Seleccionar...</option>
                                @foreach($plantas as $p)
                                    <option value="{{ $p->id_planta }}" @if(old('id_planta')==$p->id_planta) selected @endif>{{ $p->nombre }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- FIX I-10: Input para correo institucional obligatorio --}}
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Correo Institucional <span class="text-danger">*</span></label>
                            <input type="email" name="email_emp" class="form-control @error('email_emp') is-invalid @enderror" required value="{{ old('email_emp') }}" placeholder="correo@empresa.com">
                            @error('email_emp')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Se enviarán las credenciales a este correo.</div>
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
// FIX H: Búsqueda de empleado por ID o nombre (palabras no consecutivas)
document.getElementById('btnBuscar').addEventListener('click', async function() {
    const q = document.getElementById('id_empleado').value.trim();
    const datosDiv = document.getElementById('datosEmpleado');
    const errorDiv = document.getElementById('empError');
    const resultadosDiv = document.getElementById('resultadosBusqueda') || document.createElement('div'); // Por si no existe
    
    // Ocultar todo antes de buscar
    datosDiv.classList.add('d-none');
    errorDiv.classList.add('d-none');
    resultadosDiv.innerHTML = '';

    if (!q) return;

    // Insertar div de resultados si no existe
    if (!document.getElementById('resultadosBusqueda')) {
        resultadosDiv.id = 'resultadosBusqueda';
        resultadosDiv.className = 'mt-2';
        document.getElementById('btnBuscar').closest('.mb-3').appendChild(resultadosDiv);
    }
    resultadosDiv.innerHTML = '<div class="alert alert-info py-2 small"><span class="spinner-border spinner-border-sm"></span> Buscando...</div>';

    try {
        // Enviar siempre como 'q' (texto)
        const res = await fetch(`{{ route('usuarios.buscar') }}?q=${encodeURIComponent(q)}`);
        if (!res.ok) throw new Error('Error del servidor');
        
        const data = await res.json();
        const empleados = Array.isArray(data) ? data : (data.encontrado ? [data] : []);

        if (empleados.length === 0) {
            resultadosDiv.innerHTML = '<div class="alert alert-secondary py-2 small">No se encontraron empleados.</div>';
            return;
        }

        // Si solo hay 1 resultado, mostrarlo directamente en la tarjeta
        if (empleados.length === 1) {
            const e = empleados[0];
            if (!e.encontrado || !e.activo || e.tiene_cuenta) {
                let msg = !e.encontrado ? 'No se encontró en sitec.' : (!e.activo ? 'No tiene estatus "Alta" en sitec.' : 'Ya tiene una cuenta de usuario.');
                resultadosDiv.innerHTML = `<div class="alert alert-warning py-2 small">${msg}</div>`;
                return;
            }
            mostrarEmpleadoSeleccionado(e);
            resultadosDiv.innerHTML = '';
        } else {
            // Si hay varios, mostrar lista para seleccionar
            resultadosDiv.innerHTML = empleados.map(e => `
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div>
                        <strong>${e.nombre}</strong>
                        <small class="text-muted d-block">ID: ${e.id} · ${e.puesto || 'Sin puesto'} · ${e.planta || 'Sin planta'}</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-success" onclick='seleccionarEmpleado(${JSON.stringify(e)})'>
                        <i class="bi bi-check"></i> Seleccionar
                    </button>
                </div>
            `).join('');
        }
    } catch (err) {
        resultadosDiv.innerHTML = `<div class="alert alert-danger py-2 small">Error al buscar: ${err.message}</div>`;
    }
});

function seleccionarEmpleado(e) {
    if (!e.encontrado || !e.activo || e.tiene_cuenta) {
        let msg = !e.encontrado ? 'No se encontró en sitec.' : (!e.activo ? 'No tiene estatus "Alta" en sitec.' : 'Ya tiene una cuenta de usuario.');
        alert(msg);
        return;
    }
    mostrarEmpleadoSeleccionado(e);
    document.getElementById('resultadosBusqueda').innerHTML = '';
    document.getElementById('id_empleado').value = e.id; // Poner el ID real en el input
}

function mostrarEmpleadoSeleccionado(e) {
    document.getElementById('id_empleado').value = e.id;
    document.getElementById('empNombre').textContent = e.nombre;
    document.getElementById('empPuesto').textContent = e.puesto || '—';
    document.getElementById('empDepto').textContent = e.departamento || '—';
    document.getElementById('empTurno').textContent = e.turno || '—';
    document.getElementById('empPlanta').textContent = e.planta || '—';
    document.getElementById('datosEmpleado').classList.remove('d-none');

    const tipoSelect = document.getElementById('tipo_empleado');
    const tipoHint = document.getElementById('tipoHint');
    if (e.tipo_sugerido) {
        tipoSelect.value = e.tipo_sugerido;
        tipoHint.textContent = `Sugerido según el puesto "${e.puesto}".`;
        tipoHint.className = 'form-text text-success';
    } else {
        tipoHint.textContent = `El puesto "${e.puesto}" no está clasificado aún. Se clasificará al guardar.`;
        tipoHint.className = 'form-text text-warning';
    }
}
</script>
@endpush
@endsection