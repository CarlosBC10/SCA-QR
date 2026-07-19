{{-- resources/views/usuarios/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Gestión de Usuarios')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Gestión de Usuarios</h1>
        <p class="text-muted mb-0">Usuarios del sistema con sus roles y plantas</p>
    </div>
    <a href="{{ route('usuarios.create') }}" class="btn btn-success">
        <i class="bi bi-person-plus"></i> Nuevo Usuario
    </a>
</div>

{{-- Filtros (UI-22) --}}
<form method="GET" class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small">Buscar por usuario</label>
                <input type="text" name="buscar" class="form-control" value="{{ request('buscar') }}" placeholder="Ej: 1001">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Rol</label>
                <select name="rol" class="form-select">
                    <option value="">Todos</option>
                    @foreach($roles as $r)
                        <option value="{{ $r->id }}" @if(request('rol')==$r->id) selected @endif>{{ $r->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Planta</label>
                <select name="planta" class="form-select">
                    <option value="">Todas</option>
                    @foreach($plantas as $p)
                        <option value="{{ $p->id }}" @if(request('planta')==$p->id) selected @endif>{{ $p->nombre }}</option>
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

{{-- Tabla (UI-26: table-responsive) --}}
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
                    <th>Creado</th>
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
                        @if($u->activo)
                            <span class="badge bg-success">Activo</span>
                        @else
                            <span class="badge bg-secondary">Inactivo</span>
                        @endif
                    </td>
                    <td class="small text-muted">{{ $u->created_at->format('d/m/Y') }}</td>
                    <td>
                        <a href="{{ route('usuarios.edit', $u) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                            <i class="bi bi-pencil"></i>
                        </a>
                        @if($u->id !== Auth::id() && $u->activo)
                        <form method="POST" action="{{ route('usuarios.destroy', $u) }}" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="btn btn-sm btn-outline-danger btn-desactivar" data-nombre="{{ $u->username }}" title="Desactivar">
                                <i class="bi bi-person-x"></i>
                            </button>
                        </form>
                        @endif
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
// Modal de confirmación para desactivar (UI-28)
document.querySelectorAll('.btn-desactivar').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const form = this.closest('form');
        const nombre = this.dataset.nombre;

        const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
        document.getElementById('confirmModalBody').innerHTML =
            `¿Desactivar al usuario <strong>${nombre}</strong>?<br><small class="text-muted">No podrá iniciar sesión hasta ser reactivado.</small>`;
        document.getElementById('confirmModalBtn').onclick = () => form.submit();
        modal.show();
    });
});
</script>
@endpush
@endsection