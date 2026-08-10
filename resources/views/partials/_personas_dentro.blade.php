{{-- resources/views/partials/_personas_dentro.blade.php --}}
{{--
  Widget reutilizable: Personas dentro de la planta.
  - Guardia/Aprobador: ven su planta (sin selector)
  - Admin/Superadmin: ven selector de planta (si se pasa $plantas)

  CAMBIOS V4:
    - Ruta api.guardia.dentro → api.guardia.personas-dentro (renombrada en Bloque 8).
    - FK: $p->id → $p->id_planta (PK renombrada en schema V4).
    - El parámetro de query string sigue siendo ?planta_id=X (lo recibe el
      controlador y lo usa para filtrar; no requiere cambio en el JS).
--}}
<div class="card border-0 shadow-sm h-100 sca-card" style="border-radius: 12px;">
    <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold"><i class="bi bi-people-fill text-success"></i> Personas Dentro</h5>
        <span class="badge bg-success" id="count-dentro-{{ $id ?? 'default' }}">0</span>
    </div>

    {{-- Selector de planta (solo para admin/superadmin si se pasa $plantas) --}}
    @if(isset($plantas) && $plantas->count() > 1)
    <div class="px-3 pb-2">
        <select class="form-select form-select-sm" id="select-planta-{{ $id ?? 'default' }}"
                onchange="cambiarPlantaDentro('{{ $id ?? "default" }}')">
            @foreach($plantas as $p)
                {{-- V4: id_planta en lugar de id --}}
                <option value="{{ $p->id_planta }}">{{ $p->nombre }}</option>
            @endforeach
        </select>
    </div>
    @endif

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
    const selectPlanta = document.getElementById('select-planta-' + widgetId);

    async function cargarDentro() {
        try {
            // V4: ruta renombrada a api.guardia.personas-dentro
            let url = '{{ route("api.guardia.personas-dentro") }}';
            // Si hay selector de planta, enviar la planta seleccionada como query param
            if (selectPlanta && selectPlanta.value) {
                url += '?planta_id=' + selectPlanta.value;
            }

            const res = await fetch(url);
            const data = await res.json();

            badge.textContent = data.total;

            if (data.total === 0) {
                lista.innerHTML = '<div class="text-center text-muted py-5"><i class="bi bi-people fs-1 text-muted"></i><p class="mt-2 mb-0">Sin personas dentro</p></div>';
                return;
            }

            // Resumen: cuántos empleados y cuántos visitantes
            let html = `<div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex gap-2">
                    <span class="badge bg-success">Empleados: ${data.empleados}</span>
                    <span class="badge bg-info text-dark">Visitantes: ${data.visitantes}</span>
                </div>
                <small class="text-muted">${data.planta_nombre || ''}</small>
            </div>`;

            // Lista de personas
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

    // Función global para cambiar de planta (la llama el onchange del select)
    window.cambiarPlantaDentro = function(id) {
        if (id === widgetId) {
            cargarDentro();
        }
    };

    // Cargar al inicio y luego cada 10 segundos
    cargarDentro();
    setInterval(cargarDentro, 10000);
})();
</script>
@endpush