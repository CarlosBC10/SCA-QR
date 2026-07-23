{{-- resources/views/partials/_personas_dentro.blade.php --}}
{{--
  Widget reutilizable: Personas dentro de la planta.
  Se incrusta en cualquier dashboard. Se actualiza cada 10s.
--}}
<div class="card border-0 shadow-sm h-100 sca-card" style="border-radius: 12px;">
    <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold"><i class="bi bi-people-fill text-success"></i> Personas Dentro</h5>
        <span class="badge bg-success" id="count-dentro-{{ $id ?? 'default' }}">0</span>
    </div>
    <div class="card-body" id="list-dentro-{{ $id ?? 'default' }}" style="max-height: 50vh; overflow-y: auto;">
        <div class="text-center text-muted py-5">
            <i class="bi bi-people fs-1 text-muted"></i>
            <p class="mt-2 mb-0">Cargando...</p>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function() {
    const widgetId = '{{ $id ?? "default" }}';
    const badge = document.getElementById('count-dentro-' + widgetId);
    const lista = document.getElementById('list-dentro-' + widgetId);

    async function cargarDentro() {
        try {
            const res = await fetch('{{ route("api.guardia.dentro") }}');
            const data = await res.json();

            badge.textContent = data.total;

            if (data.total === 0) {
                lista.innerHTML = '<div class="text-center text-muted py-5"><i class="bi bi-people fs-1 text-muted"></i><p class="mt-2 mb-0">Sin personas dentro</p></div>';
                return;
            }

            let html = `<div class="d-flex gap-2 mb-3">
                <span class="badge bg-success">Empleados: ${data.empleados}</span>
                <span class="badge bg-info text-dark">Visitantes: ${data.visitantes}</span>
            </div>`;

            html += '<div class="list-group list-group-flush">';
            data.personas.forEach(p => {
                let icono = p.tipo === 'empleado' ? 'bi-person-badge' : 'bi-person';
                let color = p.tipo === 'empleado' ? 'success' : 'info';
                html += `
                    <div class="list-group-item list-group-item-action px-0 py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi ${icono} text-${color} me-2"></i>
                                <strong>${p.nombre}</strong>
                                <small class="text-muted d-block ms-4">${p.puesto}</small>
                            </div>
                            <div class="text-end">
                                <small class="text-muted">Entró:</small>
                                <span class="badge bg-light text-dark">${p.hora_entrada}</span>
                            </div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';

            lista.innerHTML = html;
        } catch(e) {
            console.error('Error dentro:', e);
        }
    }

    cargarDentro();
    setInterval(cargarDentro, 10000);
})();
</script>
@endpush