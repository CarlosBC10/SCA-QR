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
                {{-- M3 — Verificación de guardias existentes ANTES del alta.
                    Busca por CURP (exacta o prefijo), ID G-XXXX o nombre.
                    Auto-verificación al completar los 18 caracteres de la CURP del form. --}}
                <div class="mb-4 p-3 border rounded-3 bg-light">
                    <label class="form-label fw-bold"><i class="bi bi-search"></i> Verificar si el guardia ya existe</label>
                    <div class="input-group">
                        <input type="text" id="buscar-guardia-q" class="form-control" maxlength="40" placeholder="CURP completa o parcial, ID (G-0001) o nombre">
                        <button type="button" class="btn btn-outline-success" id="btnBuscarGuardia">
                            <i class="bi bi-search"></i> Buscar
                        </button>
                    </div>
                    <div class="form-text">Si el guardia ya está registrado, aparecerá aquí con su estado y podrá editar su cuenta en lugar de duplicarlo.</div>
                    <div id="resultadoGuardia" class="mt-2"></div>
                </div>

                {{-- M3 [ScaModal] — Form de crear guardia. Agregamos id para que el JS lo intercepte.
                    Username: AUTOGENERADO (readonly, vista previa referencial).
                    Contraseña: TEMPORAL autogenerada, enviada por correo al CREADOR.
                    El guardia la cambia en su primer login. --}}
                <form method="POST" action="{{ route('usuarios.store.guardia') }}" id="form-create-guardia">
                    @csrf

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Usuario del guardia (automático)</label>
                            <div class="input-group">
                                <input type="text" id="username-guardia-preview" class="form-control bg-light font-monospace" readonly value="G-????">
                                <button type="button" class="btn btn-outline-success" id="btnGenUsername" title="Generar vista previa de nuevo">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>
                            </div>
                            <div class="form-text">Se asigna automáticamente al guardar. La vista previa es referencial: si otro administrador crea un guardia antes, el sistema asignará el siguiente disponible y lo verá en la confirmación.</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre completo del guardia</label>
                            <input type="text" name="nombre_completo" class="form-control @error('nombre_completo') is-invalid @enderror" required maxlength="200" value="{{ old('nombre_completo') }}">
                            @error('nombre_completo')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">CURP <span class="text-danger">*</span></label>
                            <input type="text" name="curp" id="curp-guardia-form" class="form-control @error('curp') is-invalid @enderror" required maxlength="18" value="{{ old('curp') }}" oninput="this.value = this.value.toUpperCase(); verificarCurpAuto()" placeholder="18 caracteres">
                            @error('curp')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Debe ser única. Al completar los 18 caracteres se verifica automáticamente si ya existe.</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Planta asignada</label>
                            <select name="id_planta" class="form-select @error('id_planta') is-invalid @enderror" required>
                                <option value="">Seleccionar...</option>
                                @foreach($plantas as $p)
                                    <option value="{{ $p->id_planta }}" @if(old('id_planta')==$p->id_planta) selected @endif>{{ $p->nombre }}</option>
                                @endforeach
                            </select>
                            @error('id_planta')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
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

                        {{-- M3: Contraseña temporal automática. Se genera en el servidor,
                            se envía al correo del CREADOR y el guardia la cambia en su
                            primer login. No se captura ni se muestra en pantalla. --}}
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contraseña</label>
                            <div class="form-control bg-light d-flex align-items-center">
                                <span class="badge bg-warning text-dark"><i class="bi bi-shield-lock"></i> Temporal automática</span>
                                <i class="bi bi-envelope-fill text-muted ms-2"></i>
                                <small class="text-muted ms-2">Se envía a su correo al crear</small>
                            </div>
                            <div class="form-text">El sistema genera una contraseña temporal y la envía a <strong>su correo</strong>. Usted la entrega al guardia, quien deberá cambiarla al iniciar sesión en el panel web.</div>
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
// M3 v2: escape de valores dinámicos antes de inyectarlos con innerHTML.
// Los datos vienen de BD (nombre, CURP, planta, puesto...) y no se debe
// asumir que jamás contendrán HTML. esc() cubre texto y atributos.
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
}[c]));

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
                        <strong>${esc(e.nombre)}</strong>
                        <small class="text-muted d-block">ID: ${esc(e.id)} · ${esc(e.puesto || 'Sin puesto')} · ${esc(e.planta || 'Sin planta')}</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-success" onclick='seleccionarEmpleado(${esc(JSON.stringify(e))})'>
                        <i class="bi bi-check"></i> Seleccionar
                    </button>
                </div>
            `).join('');
        }
    } catch (err) {
        resultadosDiv.innerHTML = `<div class="alert alert-danger py-2 small">Error al buscar: ${esc(err.message)}</div>`;
    }
});

function seleccionarEmpleado(e) {
    if (!e.encontrado || !e.activo || e.tiene_cuenta) {
        // M2 [ScaModal] — Reemplazamos alert(msg) por ScaModal.warning()
        // con título y mensaje diferenciados según el caso.
        if (!e.encontrado) {
            ScaModal.warning('Empleado no encontrado', 'No se encontró el empleado en la base de datos de sitec. Verificá el ID o buscá por nombre.');
        } else if (!e.activo) {
            ScaModal.warning('Empleado inactivo', `El empleado <strong>${esc(e.nombre)}</strong> no tiene estatus "Alta" en sitec. Solo los empleados activos pueden tener cuenta de usuario.`);
        } else if (e.tiene_cuenta) {
            ScaModal.warning('Ya tiene cuenta', `El empleado <strong>${esc(e.nombre)}</strong> ya tiene una cuenta de usuario asociada.`);
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

    // ===== 2. Crear guardia (M3: username autogenerado + contraseña temporal por correo) =====
    const formGuardia = document.getElementById('form-create-guardia');
    if (formGuardia) {
        formGuardia.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-create-guardia');
            const originalBtnHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Procesando...';

            // M3: no hay contraseña en el form — el servidor la genera y envía
            // al correo del creador. El loading cubre el envío del correo.
            ScaModal.loading('Creando guardia', 'Generando contraseña temporal y enviándola a su correo...');

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

    // ===== 3. M3: Vista previa del username autogenerado (G-XXXX) =====
    const previewUsername = document.getElementById('username-guardia-preview');
    if (previewUsername) {
        const cargarPreview = async () => {
            try {
                const res = await fetch("{{ route('usuarios.next-guardia') }}");
                if (!res.ok) return;
                const data = await res.json();
                if (data.username) previewUsername.value = data.username;
            } catch(e) { /* la vista previa es cosmética; si falla, queda G-???? */ }
        };
        cargarPreview();
        document.getElementById('btnGenUsername').addEventListener('click', cargarPreview);
    }

    // ===== 4. M3: Búsqueda de guardias existentes (CURP / G-XXXX / nombre) =====
    const btnBuscarGuardia = document.getElementById('btnBuscarGuardia');
    if (btnBuscarGuardia) {
        const buscarGuardias = async (q) => {
            const cont = document.getElementById('resultadoGuardia');
            q = (q || '').trim();
            if (!q) {
                cont.innerHTML = '<div class="alert alert-secondary py-2 small mb-0">Escriba una CURP, un ID (G-0001) o un nombre para buscar.</div>';
                return;
            }
            cont.innerHTML = '<div class="alert alert-info py-2 small mb-0"><span class="spinner-border spinner-border-sm"></span> Buscando...</div>';
            try {
                const res = await fetch(`{{ route('usuarios.buscar.guardia') }}?q=${encodeURIComponent(q)}`);
                if (!res.ok) throw new Error('Error del servidor');
                const guardias = await res.json();
                renderizarGuardias(Array.isArray(guardias) ? guardias : []);
            } catch(err) {
                cont.innerHTML = `<div class="alert alert-danger py-2 small mb-0">Error al buscar: ${esc(err.message)}</div>`;
            }
        };

        const renderizarGuardias = (guardias) => {
            const cont = document.getElementById('resultadoGuardia');

            if (guardias.length === 0) {
                cont.innerHTML = '<div class="alert alert-success py-2 small mb-0"><i class="bi bi-check-circle-fill"></i> No existe ningún guardia con ese dato. Puede continuar con el registro.</div>';
                return;
            }

            cont.innerHTML = guardias.map(g => {
                const estado = g.activo
                    ? '<span class="badge bg-success">Activo</span>'
                    : `<span class="badge bg-secondary">Baja${g.fecha_baja ? ' (' + esc(g.fecha_baja) + ')' : ''}</span>`;
                const cuenta = g.tiene_cuenta
                    ? (g.cuenta_activa
                        ? '<span class="badge bg-info text-dark">Cuenta activa</span>'
                        : '<span class="badge bg-secondary">Cuenta inactiva</span>')
                    : '<span class="badge bg-light text-dark border">Sin cuenta web</span>';
                const passPendiente = g.debe_cambiar
                    ? ' <span class="badge bg-warning text-dark">Debe cambiar contraseña</span>'
                    : '';
                const editar = g.edit_url
                    ? `<a href="${esc(g.edit_url)}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Editar cuenta</a>`
                    : '';
                const reactivarHint = !g.activo
                    ? '<div class="small text-muted mt-1">El guardia está dado de baja. Si vuelve, edite su cuenta para reactivarlo en lugar de crear uno nuevo.</div>'
                    : '';
                return `<div class="alert alert-warning py-2 small mb-2">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <strong>${esc(g.nombre)}</strong> <code>${esc(g.id)}</code> · ${esc(g.planta || 'Sin planta')}<br>
                            <span class="text-muted">CURP: ${esc(g.curp || '—')}</span><br>
                            ${estado} ${cuenta}${passPendiente}
                            ${reactivarHint}
                        </div>
                        <div>${editar}</div>
                    </div>
                </div>`;
            }).join('');
        };

        btnBuscarGuardia.addEventListener('click', () => {
            buscarGuardias(document.getElementById('buscar-guardia-q').value);
        });
        document.getElementById('buscar-guardia-q').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); buscarGuardias(e.target.value); }
        });

        // Auto-verificación al completar 18 chars en el CURP del form
        window.verificarCurpAuto = function() {
            const curp = document.getElementById('curp-guardia-form');
            if (curp && curp.value.trim().length === 18) {
                buscarGuardias(curp.value.trim());
            }
        };
    }

});
</script>
@endpush
@endsection