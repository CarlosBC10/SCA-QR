{{-- resources/views/puestos/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Clasificación de Puestos')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-0">Clasificación de Puestos</h1>
    <p class="text-muted mb-0">Clasifique cada puesto de sitec como Operativo, Administrativo o Corporativo. Al clasificar, se actualizan todos los empleados con ese puesto.</p>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Puesto</th>
                    <th>Clasificación actual</th>
                    <th width="320">Cambiar a</th>
                </tr>
            </thead>
            <tbody>
                @forelse($puestos as $p)
                <tr>
                    <td class="font-monospace">{{ $p->id_puesto }}</td>
                    <td>{{ $p->nombre }}</td>
                    <td>
                        @isset($clasificaciones[$p->id_puesto])
                            @php $tipo = $clasificaciones[$p->id_puesto]; @endphp
                            <span class="badge bg-{{
                                $tipo == 'corporativo' ? 'danger' :
                                ($tipo == 'administrativo' ? 'warning text-dark' : 'success')
                            }}">{{ ucfirst($tipo) }}</span>
                        @else
                            <span class="badge bg-secondary">Sin clasificar</span>
                        @endisset
                    </td>
                    <td>
                        <form method="POST" action="{{ route('puestos.store') }}" class="d-flex gap-1 form-clasificar">
                            @csrf
                            <input type="hidden" name="id_puesto" value="{{ $p->id_puesto }}">
                            <input type="hidden" name="nombre_puesto" value="{{ $p->nombre }}">
                            <select name="tipo_empleado" class="form-select form-select-sm">
                                <option value="">—</option>
                                <option value="operativo">Operativo</option>
                                <option value="administrativo">Administrativo</option>
                                <option value="corporativo">Corporativo</option>
                            </select>
                            <button type="button" class="btn btn-sm btn-outline-success btn-clasificar">
                                <i class="bi bi-check"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="text-center text-muted py-4">No se pudieron cargar los puestos de sitec (verifique permisos).</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
// Clasificar puesto con modal de confirmación (UI-28)
document.querySelectorAll('.btn-clasificar').forEach(btn => {
    btn.addEventListener('click', async function(e) {
        e.preventDefault();
        const form = this.closest('form');
        const select = form.querySelector('select');
        const tipo = select.value;
        if (!tipo) return;

        const idPuesto = form.querySelector('input[name="id_puesto"]').value;
        const nombrePuesto = form.querySelector('input[name="nombre_puesto"]').value;

        // Consultar cuántos empleados se verán afectados
        let cantidad = 0;
        try {
            const res = await fetch(`{{ route('puestos.contar') }}?id_puesto=${idPuesto}`);
            const data = await res.json();
            cantidad = data.cantidad;
        } catch (e) {}

        const modal = new bootstrap.Modal(document.getElementById('confirmModal'));

        let cuerpo = `¿Clasificar el puesto <strong>${nombrePuesto}</strong> como <strong>${tipo}</strong>?`;
        if (cantidad > 0) {
            cuerpo += `<br><br><div class="alert alert-warning mb-0">⚠ Esto afectará a <strong>${cantidad}</strong> empleado(s) con este puesto. Su tipo de empleado se actualizará.</div>`;
        } else {
            cuerpo += `<br><small class="text-muted">No hay empleados activos con este puesto todavía.</small>`;
        }

        document.getElementById('confirmModalBody').innerHTML = cuerpo;
        document.getElementById('confirmModalBtn').onclick = () => form.submit();
        document.getElementById('confirmModalBtn').className = 'btn btn-success';
        modal.show();
    });
});
</script>
@endpush
@endsection