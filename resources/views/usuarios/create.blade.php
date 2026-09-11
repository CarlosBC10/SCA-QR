{{-- resources/views/usuarios/create.blade.php --}}
@extends('layouts.app')

@section('title', 'Nuevo usuario')

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

<div class="sca-page-head legacy-page-head">
    <div>
    <div class="sca-page-eyebrow"><i class="bi bi-person-plus" aria-hidden="true"></i> Gestión</div>
    <h1 class="sca-page-title">Nuevo usuario</h1>
    <p class="text-muted mb-0">Seleccione el tipo de usuario a crear</p>
    </div>
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
                {{-- M2 [ScaModal] — Form de crear usuario interno. Agregamos id para que el JS lo intercepte. --}}
                <form method="POST" action="{{ route('usuarios.store.empleado') }}" id="form-create-empleado">
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
                                    {{-- M2: Un empleado interno NO puede tener rol de Guardia
                                        (los guardias son externos, con registro en tabla guardias). --}}
                                    @if($r->nombre !== 'Guardia')
                                        <option value="{{ $r->id_rol }}" @if(old('id_rol')==$r->id_rol) selected @endif>{{ $r->nombre }}</option>
                                    @endif
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
                        <button type="submit" class="btn btn-success" id="btn-create-empleado">
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
                {{-- M2 [ScaModal] — Form de crear guardia. Agregamos id para que el JS lo intercepte.
                    Los guardias NO reciben correo. El admin define la contraseña directamente. --}}
                <form method="POST" action="{{ route('usuarios.store.guardia') }}" id="form-create-guardia">
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
                            <label class="form-label">CURP <span class="text-danger">*</span></label>
                            <input type="text" name="curp" class="form-control @error('curp') is-invalid @enderror" required maxlength="18" value="{{ old('curp') }}" oninput="this.value = this.value.toUpperCase()" placeholder="18 caracteres">
                            @error('curp')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Debe ser única. 18 caracteres exactos.</div>
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

                        {{-- M2: Rol fijo en Guardia (el controller lo fuerza así).
                            Un guardia NO puede tener otro rol porque no tiene registro en sitec.empleados. --}}
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Rol</label>
                            <div class="form-control bg-light d-flex align-items-center">
                                <span class="badge bg-info text-dark">Guardia</span>
                                <i class="bi bi-lock-fill text-muted ms-2"></i>
                                <small class="text-muted ms-2">Fijo (los guardias siempre tienen este rol)</small>
                            </div>
                        </div>

                        {{-- M2 [ScaModal] — Contraseña definida por el admin (no temporal, no correo) --}}
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contraseña <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" name="password" id="pass-guardia-create" class="form-control @error('password') is-invalid @enderror" required minlength="8" value="{{ old('password') }}">
                                <button class="btn btn-outline-secondary sca-toggle-pass" type="button" data-target="pass-guardia-create" title="Mostrar/ocultar">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            @error('password')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Mínimo 8 caracteres, 1 mayúscula, 1 minúscula, 1 número y 1 símbolo. Entréguela personalmente al guardia.</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Confirmar contraseña <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" name="password_confirmation" id="pass-guardia-create-conf" class="form-control" required minlength="8" value="{{ old('password_confirmation') }}">
                                <button class="btn btn-outline-secondary sca-toggle-pass" type="button" data-target="pass-guardia-create-conf" title="Mostrar/ocultar">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success" id="btn-create-guardia">
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
        // M2 [ScaModal] — Reemplazamos alert(msg) por ScaModal.warning()
        // con título y mensaje diferenciados según el caso.
        if (!e.encontrado) {
            ScaModal.warning('Empleado no encontrado', 'No se encontró el empleado en la base de datos de sitec. Verificá el ID o buscá por nombre.');
        } else if (!e.activo) {
            ScaModal.warning('Empleado inactivo', `El empleado <strong>${e.nombre}</strong> no tiene estatus "Alta" en sitec. Solo los empleados activos pueden tener cuenta de usuario.`);
        } else if (e.tiene_cuenta) {
            ScaModal.warning('Ya tiene cuenta', `El empleado <strong>${e.nombre}</strong> ya tiene una cuenta de usuario asociada.`);
        }
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

// ============================================================
// M2 [ScaModal] — Intercept forms para crear usuario y guardia
//
// Maneja 2 flujos:
//   1. Form de crear empleado (#form-create-empleado):
//      → ScaModal.loading "Creando usuario..." → AJAX POST → flash en destino
//   2. Form de crear guardia (#form-create-guardia):
//      → ScaModal.loading "Creando guardia..." → AJAX POST → flash en destino
// ============================================================
document.addEventListener('DOMContentLoaded', function() {

    // ===== 1. Crear empleado interno =====
    const formEmpleado = document.getElementById('form-create-empleado');
    if (formEmpleado) {
        formEmpleado.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-create-empleado');
            const originalBtnHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Procesando...';

            // Mostrar loading (envía correo, puede tardar)
            ScaModal.loading('Creando usuario', 'Enviando contraseña temporal por correo...');

            try {
                const res = await fetch(formEmpleado.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: new FormData(formEmpleado),
                });
                const data = await res.json();

                if (data.success) {
                    // Option E: NO cerrar loading. Redirect directo.
                    window.location.href = data.redirect;
                } else {
                    ScaModal.close();
                    if (data.errors) {
                        // Errores de validación (422)
                        const errores = Object.values(data.errors).flat();
                        ScaModal.error('Error de validación', errores.join('<br>'));
                    } else {
                        ScaModal.error(data.titulo || 'Error', data.mensaje || 'No se pudo crear el usuario.');
                    }
                    btn.disabled = false;
                    btn.innerHTML = originalBtnHtml;
                }
            } catch(err) {
                ScaModal.close();
                ScaModal.error('Error de conexión', 'No se pudo contactar con el servidor.');
                btn.disabled = false;
                btn.innerHTML = originalBtnHtml;
            }
        });
    }

    // ===== 2. Crear guardia =====
    const formGuardia = document.getElementById('form-create-guardia');
    if (formGuardia) {
        formGuardia.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-create-guardia');
            const originalBtnHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Procesando...';

            // Guardar la contraseña ANTES de enviar para mostrarla en el success
            const passIngresada = document.getElementById('pass-guardia-create').value;

            // Mostrar loading
            ScaModal.loading('Creando guardia', 'Creando registro de guardia y usuario...');

            try {
                const res = await fetch(formGuardia.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: new FormData(formGuardia),
                });
                const data = await res.json();

                if (data.success) {
                    // Option E: NO cerrar loading. Redirect directo.
                    // Guardamos la contraseña en sessionStorage para mostrarla en el success
                    // del destino (usuarios.index) vía flash modificado.
                    if (passIngresada) {
                        sessionStorage.setItem('sca_pass_guardia_creado', passIngresada);
                    }
                    window.location.href = data.redirect;
                } else {
                    ScaModal.close();
                    if (data.errors) {
                        const errores = Object.values(data.errors).flat();
                        ScaModal.error('Error de validación', errores.join('<br>'));
                    } else {
                        ScaModal.error(data.titulo || 'Error', data.mensaje || 'No se pudo crear el guardia.');
                    }
                    btn.disabled = false;
                    btn.innerHTML = originalBtnHtml;
                }
            } catch(err) {
                ScaModal.close();
                ScaModal.error('Error de conexión', 'No se pudo contactar con el servidor.');
                btn.disabled = false;
                btn.innerHTML = originalBtnHtml;
            }
        });
    }

    // ===== 3. Toggles "ver contraseña" en el form de crear guardia =====
    document.querySelectorAll('.sca-toggle-pass').forEach(btn => {
        btn.addEventListener('click', function() {
            const target = document.getElementById(this.dataset.target);
            if (!target) return;
            const icon = this.querySelector('i');
            if (target.type === 'password') {
                target.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                target.type = 'password';
                icon.className = 'bi bi-eye';
            }
        });
    });

});
</script>
@endpush
@endsection