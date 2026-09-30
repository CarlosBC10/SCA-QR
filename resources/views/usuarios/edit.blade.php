{{-- resources/views/usuarios/edit.blade.php --}}
{{--
    CAMBIOS V4:
      - Roles: id → id_rol.
      - Plantas: id → id_planta.
      - Estado: activo via accessor (fecha_baja null).
      - $usuario->guardia_id → $usuario->esGuardia() / $usuario->id_guardias.
      - created_at no existe → usar fecha_alta.
--}}
@extends('layouts.app')

@section('title', 'Editar usuario')

@section('content')
<div class="sca-page-head legacy-page-head">
    <div>
    <div class="sca-page-eyebrow"><i class="bi bi-person-gear" aria-hidden="true"></i> Gestión</div>
    <h1 class="sca-page-title">Editar usuario</h1>
    <p class="sca-page-subtitle">
        {{ $usuario->nombre_completo }} ·
        <span class="font-monospace">{{ $usuario->username }}</span>
        @if($usuario->fecha_baja)
            <span class="badge bg-secondary ms-2">Inactivo desde {{ $usuario->fecha_baja->format('d/m/Y') }}</span>
        @endif
    </p>
    </div>
</div>

{{-- M2 [ScaModal] — Agregamos id="form-edit-usuario" para que el JS lo intercepte --}}
<form method="POST" action="{{ route('usuarios.update', $usuario) }}" id="form-edit-usuario">
    @csrf
    @method('PUT')

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Rol</label>
                    <select name="id_rol" class="form-select" required>
                        @foreach($rolesPermitidos as $r)
                            {{-- M2: Filtrar roles según tipo de usuario:
                                - Si es guardia → solo puede ser Guardia (no tiene registro en sitec)
                                - Si es interno → no puede ser Guardia (no tiene registro en guardias) --}}
                            @if($usuario->esGuardia())
                                @if($r->nombre === 'Guardia')
                                    <option value="{{ $r->id_rol }}" @if(old('id_rol', $usuario->id_rol)==$r->id_rol) selected @endif>{{ $r->nombre }}</option>
                                @endif
                            @else
                                @if($r->nombre !== 'Guardia')
                                    <option value="{{ $r->id_rol }}" @if(old('id_rol', $usuario->id_rol)==$r->id_rol) selected @endif>{{ $r->nombre }}</option>
                                @endif
                            @endif
                        @endforeach
                    </select>
                    @if($usuario->esGuardia())
                        <div class="form-text text-muted">
                            <i class="bi bi-info-circle"></i> Los guardias solo pueden tener rol de Guardia.
                        </div>
                    @else
                        <div class="form-text text-muted">
                            <i class="bi bi-info-circle"></i> Los empleados internos no pueden tener rol de Guardia.
                        </div>
                    @endif
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Estado</label>
                    <select name="activo" class="form-select" required>
                        <option value="1" @if(old('activo', $usuario->activo)) selected @endif>Activo</option>
                        <option value="0" @if(!old('activo', $usuario->activo)) selected @endif>Inactivo</option>
                    </select>
                    @if($usuario->fecha_baja)
                        <div class="form-text text-warning">
                            <i class="bi bi-info-circle"></i> Al activar, se limpiará la fecha de baja.
                        </div>
                    @endif
                </div>

                @if(!$usuario->esGuardia())
                {{-- Empleado interno --}}
                <div class="col-md-6 mb-3">
                    <label class="form-label">Tipo de Empleado</label>
                    <select name="tipo_empleado" class="form-select">
                        <option value="">—</option>
                        <option value="operativo" @if(old('tipo_empleado', $tipoEmpleado)=='operativo') selected @endif>Operativo</option>
                        <option value="administrativo" @if(old('tipo_empleado', $tipoEmpleado)=='administrativo') selected @endif>Administrativo</option>
                        <option value="corporativo" @if(old('tipo_empleado', $tipoEmpleado)=='corporativo') selected @endif>Corporativo</option>
                    </select>
                </div>

                {{-- FIX I-10 (edición): correo institucional — solo empleados internos (los guardias
                     reciben sus credenciales al correo del operador, no manejan correo propio) --}}
                <div class="col-md-6 mb-3">
                    <label class="form-label">Correo Institucional</label>
                    <input type="email" name="email_emp" class="form-control @error('email_emp') is-invalid @enderror" maxlength="100" value="{{ old('email_emp', $usuario->email_emp) }}" placeholder="correo@empresa.com" required>
                    @error('email_emp')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text text-muted">
                        <i class="bi bi-info-circle"></i> Se usa para el reseteo de contraseña y notificaciones.
                    </div>
                </div>

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
                            <option value="{{ $p->id_planta }}" @if(old('planta_id', $plantaActual)==$p->id_planta) selected @endif>{{ $p->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- M2 [ScaModal] — Eliminado el campo "Restablecer contraseña" de la edición.
        Era redundante con el botón "Resetear contraseña" (🔑) de la lista de usuarios,
        que además envía correo de notificación al usuario. Si el admin necesita cambiar
        la contraseña, debe usar ese botón. --}}
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-success" id="btn-guardar-usuario">
            <i class="bi bi-check-circle"></i> Guardar Cambios
        </button>
        <a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

@push('scripts')
<script>
// ============================================================
// M2 [ScaModal] — Intercept form submit for AJAX + confirm + loading
//
// FLUJO:
//   1. Admin hace clic en "Guardar Cambios"
//   2. ScaModal.confirm con resumen de cambios
//   3. ScaModal.loading "Guardando..."
//   4. fetch() AJAX PUT al controller
//   5. Controller actualiza + setea flash + devuelve JSON
//   6. JS redirect (loading se queda visible)
//   7. usuarios.index carga + success aparece vía flash
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('form-edit-usuario');
    if (!form) return;

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const btn = document.getElementById('btn-guardar-usuario');
        const originalBtnHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Procesando...';

        // ===== Construir resumen para el confirm =====
        const rolSelect = form.querySelector('select[name="id_rol"]');
        const rolNombre = rolSelect.options[rolSelect.selectedIndex].text;

        const estadoSelect = form.querySelector('select[name="activo"]');
        const estadoNombre = estadoSelect.options[estadoSelect.selectedIndex].text;

        // Tomar el nombre del usuario del H1 de la página
        const nombreUsuario = document.querySelector('p.text-muted')?.textContent.trim().split('·')[0].trim() || 'este usuario';

        let mensaje = `<div class="text-start">`;
        mensaje += `<div class="mb-2"><strong>Usuario:</strong> ${nombreUsuario}</div>`;
        mensaje += `<div class="mb-2"><strong>Rol:</strong> ${rolNombre}</div>`;
        mensaje += `<div class="mb-2"><strong>Estado:</strong> ${estadoNombre}</div>`;
        mensaje += `</div>`;

        ScaModal.confirm({
            titulo: '¿Guardar cambios?',
            mensaje: mensaje,
            tipo: 'warning',
            textoConfirmar: 'Sí, guardar',
            textoCancelar: 'Cancelar',
            onConfirm: async () => {
                ScaModal.loading('Guardando cambios', 'Actualizando datos del usuario...');

                try {
                    const res = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                        body: new FormData(form),
                    });
                    const data = await res.json();

                    if (data.success) {
                        // Option E: NO cerrar loading. Redirect directo.
                        window.location.href = data.redirect;
                    } else {
                        ScaModal.close();
                        ScaModal.error(data.titulo || 'Error', data.mensaje || 'No se pudo guardar.');
                        btn.disabled = false;
                        btn.innerHTML = originalBtnHtml;
                    }
                } catch(err) {
                    ScaModal.close();
                    ScaModal.error('Error de conexión', 'No se pudo contactar con el servidor.');
                    btn.disabled = false;
                    btn.innerHTML = originalBtnHtml;
                }
            },
            onCancel: () => {
                // Si cancela, rehabilitar el botón
                btn.disabled = false;
                btn.innerHTML = originalBtnHtml;
            }
        });
    });
});
</script>
@endpush

@endsection