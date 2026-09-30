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
                            {{-- M3: contraseña temporal pendiente de cambiar (prefijo 'temp:').
                                $hidden solo afecta serialización; el atributo es accesible aquí. --}}
                            @if($u->password && str_starts_with($u->password, 'temp:'))
                                <div class="mt-1">
                                    <span class="badge bg-warning text-dark" title="Tiene una contraseña temporal sin cambiar (primer login pendiente)">
                                        <i class="bi bi-exclamation-triangle"></i> Debe cambiar contraseña
                                    </span>
                                </div>
                            @endif
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

                            {{-- M3 [ScaModal] — Resetear contraseña (UNIFICADO: empleados y guardias).
                                Solo para usuarios ACTIVOS distintos de uno mismo.
                                - Empleado → temporal a SU propio correo.
                                - Guardia  → temporal al correo de QUIEN RESETEA
                                  (data-email ya apunta al correo del operador logueado),
                                  cierra sus kioskos activos y limpia remember_token.
                                El guardia cambia la contraseña en su primer login. --}}
                            @if($u->id_usuario_sca !== Auth::id() && $u->activo)
                            <form action="{{ route('usuarios.reset-password', $u->id_usuario_sca) }}" method="POST" class="d-inline sca-form-reset"
                                  data-nombre="{{ $u->nombre_completo ?? $u->username }}"
                                  data-email="{{ $u->esGuardia() ? (auth()->user()->email_emp ?? '') : ($u->email_emp ?? '') }}"
                                  data-es-guardia="{{ $u->esGuardia() ? '1' : '0' }}"
                                  data-debe-cambiar="{{ (str_starts_with((string) $u->password, 'temp:')) ? '1' : '0' }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-warning" title="Resetear contraseña (envía temporal por correo)">
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
// M3 v2: escape de valores dinámicos antes de inyectarlos con innerHTML.
// Los datos vienen de BD (nombre, CURP, planta, puesto...) y no se debe
// asumir que jamás contendrán HTML. esc() cubre texto y atributos.
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
}[c]));

// ============================================================
// M2/M3 [ScaModal] — Handlers para los forms de la tabla de usuarios.
//
// Maneja 2 flujos:
//   1. Form de reset password (.sca-form-reset, UNIFICADO empleado/guardia):
//      → ScaModal.confirm warning (mensaje según tipo) → loading → AJAX POST → flash en destino
//   2. Form de desactivar (.sca-form-destroy):
//      → ScaModal.confirm danger con nombre → loading → AJAX DELETE → flash en destino
// ============================================================
document.addEventListener('DOMContentLoaded', function() {

    // ===== 1. Reset password (empleado: a SU correo / guardia: al correo del operador) =====
    document.querySelectorAll('.sca-form-reset').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const nombre = this.dataset.nombre;
            const email = this.dataset.email || '(sin correo)';
            const esGuardia = this.dataset.esGuardia === '1';
            const debeCambiar = this.dataset.debeCambiar === '1';

            // M3 v2: confirmación diferenciada, con variante de REENVÍO cuando
            // el guardia aún no cambia su temporal (cubre el caso "el correo
            // de credenciales falló al crear la cuenta"). Valores escapados.
            const mensaje = esGuardia
                ? (debeCambiar
                    ? `<strong>${esc(nombre)}</strong> aún no cambia su contraseña temporal.<br><br>Se generará una <strong>nueva contraseña temporal</strong> y se enviará a <strong>SU correo</strong>:<br><code>${esc(email)}</code><br><br><small class="text-muted">Se cerrarán sus kioskos activos y el guardia deberá cambiarla en su primer acceso al panel web.</small>`
                    : `Se generará una contraseña temporal para el guardia <strong>${esc(nombre)}</strong> y se enviará a <strong>SU correo</strong>:<br><br><code>${esc(email)}</code><br><br><small class="text-muted">Se cerrarán sus kioskos activos y deberá cambiar la contraseña al iniciar sesión en el panel web.</small>`)
                : `Se enviará una nueva contraseña temporal a <strong>${esc(nombre)}</strong>.<br><br>Correo: <code>${esc(email)}</code><br><br><small class="text-muted">El usuario deberá cambiarla al iniciar sesión.</small>`;

            ScaModal.confirm({
                titulo: (esGuardia && debeCambiar) ? '¿Reenviar credenciales?' : (esGuardia ? '¿Resetear contraseña del guardia?' : '¿Resetear contraseña?'),
                mensaje: mensaje,
                tipo: 'warning',
                textoConfirmar: (esGuardia && debeCambiar) ? 'Sí, reenviar' : 'Sí, resetear',
                textoCancelar: 'Cancelar',
                onConfirm: async () => {
                    ScaModal.loading(
                        esGuardia ? 'Enviando a su correo' : 'Enviando correo',
                        'Generando contraseña temporal y enviando a ' + email + '...'
                    );

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

    // ===== 2. Desactivar usuario =====
    document.querySelectorAll('.sca-form-destroy').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const nombre = this.dataset.nombre;
            const username = this.dataset.username;

            ScaModal.confirm({
                titulo: '¿Desactivar usuario?',
                mensaje: `El usuario <strong>${esc(nombre)}</strong> (${esc(username)}) será desactivado.<br>No podrá iniciar sesión hasta ser reactivado.<br><br><small class="text-muted">Podrá reactivarse cuando sea necesario.</small>`,
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