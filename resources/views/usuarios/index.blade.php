{{-- resources/views/usuarios/index.blade.php --}}
{{--
    CAMBIOS V4:
      - Filtros: id → id_rol, id → id_planta.
      - Estado: usa fecha_baja (null=activo, not-null=inactivo) via accessor $u->activo.
      - created_at: ya no existe en usuarios_sca (usa fecha_alta).
      - Desactivar: usa $u->id_usuario_sca en lugar de $u->id.
--}}
@extends('layouts.app')

@section('title', 'Gestión de Usuarios')

@section('content')
<div class="sca-page-head legacy-page-head">
    <div>
        <div class="sca-page-eyebrow"><i class="bi bi-people" aria-hidden="true"></i> Gestión</div>
        <h1 class="sca-page-title">Usuarios</h1>
        <p class="sca-page-subtitle">Administra cuentas, roles, plantas y estado de acceso al sistema.</p>
    </div>
    <a href="{{ route('usuarios.create') }}" class="btn btn-success">
        <i class="bi bi-person-plus me-1"></i> Nuevo usuario
    </a>
</div>

<form method="GET" class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small">Buscar por usuario</label>
                <input type="text" name="buscar" class="form-control" value="{{ request('buscar') }}" placeholder="ID, Nombre o Usuario">            
            </div>
            <div class="col-md-3">
                <label class="form-label small">Rol</label>
                <select name="rol" class="form-select">
                    <option value="">Todos</option>
                    @foreach($roles as $r)
                        <option value="{{ $r->id_rol }}" @if(request('rol')==$r->id_rol) selected @endif>{{ $r->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Planta</label>
                <select name="planta" class="form-select">
                    <option value="">Todas</option>
                    @foreach($plantas as $p)
                        <option value="{{ $p->id_planta }}" @if(request('planta')==$p->id_planta) selected @endif>{{ $p->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Estado</label>
                <select name="estado" class="form-select">
                    <option value="">Todos</option>
                    <option value="activo" @if(request('estado')=='activo') selected @endif>Activos</option>
                    <option value="inactivo" @if(request('estado')=='inactivo') selected @endif>Inactivos</option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-outline-success w-100">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Usuario</th>
                    <th>Nombre</th>
                    <th>Rol</th>
                    <th>Planta</th>
                    <th>Estado</th>
                    <th>Alta</th>
                    <th width="120">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($usuarios as $u)
                <tr>
                    <td class="font-monospace">{{ $u->username }}</td>
                    <td>{{ $u->nombre_completo }}</td>
                    <td>
                        <span class="badge bg-{{
                            $u->rol->nombre == 'Superadmin' ? 'danger' :
                            ($u->rol->nombre == 'Admin' ? 'success' :
                            ($u->rol->nombre == 'Aprobador' ? 'warning text-dark' :
                            ($u->rol->nombre == 'Guardia' ? 'info text-dark' : 'secondary')))
                        }}">{{ $u->rol->nombre }}</span>
                    </td>
                    <td>{{ $u->planta_nombre }}</td>
                    <td>
                        {{-- V4: $u->activo es un accessor que revisa fecha_baja --}}
                        @if($u->activo)
                            <span class="badge bg-success">Activo</span>
                        @else
                            <span class="badge bg-secondary">Inactivo</span>
                        @endif
                    </td>
                    <td class="small text-muted">{{ $u->fecha_alta?->format('d/m/Y') ?? '—' }}</td>
                    <td>
                        <div class="d-flex gap-1">
                            {{-- Editar --}}
                            <a href="{{ route('usuarios.edit', $u) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>

                            {{-- M2 [ScaModal] — Resetear contraseña (empleado interno).
                                Solo para empleados internos ACTIVOS (no guardias, no desactivados).
                                Envía correo con contraseña temporal. --}}
                            @if($u->id_usuario_sca !== Auth::id() && !$u->esGuardia() && $u->activo)
                            <form action="{{ route('usuarios.reset-password', $u->id_usuario_sca) }}" method="POST" class="d-inline sca-form-reset" data-nombre="{{ $u->nombre_completo ?? $u->username }}" data-email="{{ $u->email_emp ?? '' }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-warning" title="Resetear contraseña (envía correo)">
                                    <i class="bi bi-key"></i>
                                </button>
                            </form>
                            @endif

                            {{-- M2 [ScaModal] — Cambiar contraseña (guardia).
                                Solo para guardias ACTIVOS (no empleados internos).
                                El admin define la nueva contraseña directamente (sin correo). --}}
                            @if($u->id_usuario_sca !== Auth::id() && $u->esGuardia() && $u->activo)
                            <form action="{{ route('usuarios.cambiar-password-guardia', $u->id_usuario_sca) }}" method="POST" class="d-inline sca-form-pass-guardia" data-nombre="{{ $u->nombre_completo ?? $u->username }}" data-username="{{ $u->username }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-warning" title="Cambiar contraseña (directa)">
                                    <i class="bi bi-key"></i>
                                </button>
                            </form>
                            @endif
                            {{-- M2 [ScaModal] — Desactivar usuario.
                                Reemplazamos el JS viejo (que usaba un modal confirmModal inexistente)
                                por clase .sca-form-destroy que el JS intercepta con ScaModal.confirm(). --}}
                            @if($u->id_usuario_sca !== Auth::id() && $u->activo)
                            <form method="POST" action="{{ route('usuarios.destroy', $u) }}" class="d-inline sca-form-destroy" data-nombre="{{ $u->nombre_completo ?? $u->username }}" data-username="{{ $u->username }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Desactivar">
                                    <i class="bi bi-person-x"></i>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">No hay usuarios que coincidan con los filtros.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">
    {{ $usuarios->withQueryString()->links() }}
</div>

@push('scripts')
<script>
// ============================================================
// M2 [ScaModal] — Handlers para los forms de la tabla de usuarios.
//
// Maneja 2 flujos:
//   1. Form de reset password (.sca-form-reset):
//      → ScaModal.confirm warning con email → loading → AJAX POST → flash en destino
//   2. Form de desactivar (.sca-form-destroy):
//      → ScaModal.confirm danger con nombre → loading → AJAX DELETE → flash en destino
// ============================================================
document.addEventListener('DOMContentLoaded', function() {

    // ===== 1. Reset password =====
    document.querySelectorAll('.sca-form-reset').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const nombre = this.dataset.nombre;
            const email = this.dataset.email || '(sin correo)';

            ScaModal.confirm({
                titulo: '¿Resetear contraseña?',
                mensaje: `Se enviará una nueva contraseña temporal a <strong>${nombre}</strong>.<br><br>Correo: <code>${email}</code><br><br><small class="text-muted">El usuario deberá cambiarla al iniciar sesión.</small>`,
                tipo: 'warning',
                textoConfirmar: 'Sí, resetear',
                textoCancelar: 'Cancelar',
                onConfirm: async () => {
                    ScaModal.loading('Enviando correo', 'Generando contraseña temporal y enviando a ' + email + '...');

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
                            ScaModal.error(data.titulo || 'Error', data.mensaje || 'No se pudo resetear la contraseña.');
                        }
                    } catch(err) {
                        ScaModal.close();
                        ScaModal.error('Error de conexión', 'No se pudo contactar con el servidor.');
                    }
                }
            });
        });
    });

    // ===== 1b. Cambiar contraseña de guardia =====
    document.querySelectorAll('.sca-form-pass-guardia').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const nombre = this.dataset.nombre;
            const username = this.dataset.username;

            // Modal con input para la nueva contraseña + confirmación.
            // ScaModal.confirm no soporta 2 inputs, así que usamos un success/info
            // con HTML custom que incluye los inputs y un botón que valida y envía.
            ScaModal.confirm({
                titulo: '¿Cambiar contraseña del guardia?',
                mensaje: `<div class="text-start">
                    <div class="mb-3">Guardia: <strong>${nombre}</strong> (${username})</div>
                    <div class="mb-3">
                        <label class="form-label">Nueva contraseña <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" id="sca-pass-guardia" class="form-control" placeholder="Mínimo 8 caracteres" required>
                            <button class="btn btn-outline-secondary sca-toggle-pass" type="button" data-target="sca-pass-guardia" title="Mostrar/ocultar">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">Mínimo 8, 1 mayúscula, 1 minúscula, 1 número, 1 símbolo.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirmar contraseña <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" id="sca-pass-guardia-conf" class="form-control" placeholder="Repetir contraseña" required>
                            <button class="btn btn-outline-secondary sca-toggle-pass" type="button" data-target="sca-pass-guardia-conf" title="Mostrar/ocultar">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>`,
                tipo: 'warning',
                textoConfirmar: 'Sí, cambiar',
                textoCancelar: 'Cancelar',
                requiereInput: { type: 'text', label: '__dummy__', required: false },  // hack para que muestre el footer con confirm
                onConfirm: async () => {
                    const pass1 = document.getElementById('sca-pass-guardia').value;
                    const pass2 = document.getElementById('sca-pass-guardia-conf').value;

                    if (!pass1 || !pass2) {
                        ScaModal.warning('Campos vacíos', 'Por favor completá ambas contraseñas.');
                        return;
                    }
                    if (pass1 !== pass2) {
                        ScaModal.error('Las contraseñas no coinciden', 'Asegurate de escribir la misma contraseña en ambos campos.');
                        return;
                    }
                    if (pass1.length < 8) {
                        ScaModal.warning('Contraseña muy corta', 'La contraseña debe tener al menos 8 caracteres.');
                        return;
                    }

                    // Mostrar loading
                    ScaModal.loading('Guardando contraseña', 'Actualizando contraseña del guardia...');

                    try {
                        // Enviar con FormData (incluye los campos que el controller espera)
                        const formData = new FormData(form);
                        formData.append('password', pass1);
                        formData.append('password_confirmation', pass2);

                        const res = await fetch(form.action, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            },
                            body: formData,
                        });
                        const data = await res.json();

                        if (data.success) {
                            // Option E: NO cerrar loading. Redirect directo.
                            window.location.href = data.redirect;
                        } else if (data.errors) {
                            ScaModal.close();
                            const errores = Object.values(data.errors).flat();
                            ScaModal.error('Error de validación', errores.join('<br>'));
                        } else {
                            ScaModal.close();
                            ScaModal.error(data.titulo || 'Error', data.mensaje || 'No se pudo cambiar la contraseña.');
                        }
                    } catch(err) {
                        ScaModal.close();
                        ScaModal.error('Error de conexión', 'No se pudo contactar con el servidor.');
                    }
                }
            });

            // ScaModal.confirm con requiereInput crea un textarea oculto. Lo ocultamos.
            setTimeout(() => {
                const input = document.getElementById('scaInput');
                if (input) {
                    input.closest('.sca-input').style.display = 'none';
                }
                // M2: Activar toggles de "ver contraseña" dentro del modal
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
            }, 50);
        });
    });

    // ===== 2. Desactivar usuario =====
    document.querySelectorAll('.sca-form-destroy').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const nombre = this.dataset.nombre;
            const username = this.dataset.username;

            ScaModal.confirm({
                titulo: '¿Desactivar usuario?',
                mensaje: `El usuario <strong>${nombre}</strong> (${username}) será desactivado.<br>No podrá iniciar sesión hasta ser reactivado.<br><br><small class="text-muted">Podrá reactivarse cuando sea necesario.</small>`,
                tipo: 'danger',
                textoConfirmar: 'Sí, desactivar',
                textoCancelar: 'Cancelar',
                onConfirm: async () => {
                    ScaModal.loading('Desactivando usuario', 'Procesando...');

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
                            ScaModal.error(data.titulo || 'Error', data.mensaje || 'No se pudo desactivar.');
                        }
                    } catch(err) {
                        ScaModal.close();
                        ScaModal.error('Error de conexión', 'No se pudo contactar con el servidor.');
                    }
                }
            });
        });
    });

});
</script>
@endpush
@endsection