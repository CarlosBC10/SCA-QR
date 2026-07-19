{{-- resources/views/guardia/dashboard.blade.php --}}
@extends('layouts.app')

@section('title', 'Panel de Guardia')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-0">Panel de Guardia</h3>
            <p class="text-muted mb-0">
                Planta: <strong>{{ $turnoActivo?->planta?->nombre ?? 'Sin asignar' }}</strong> | 
                Turno iniciado: <strong>{{ $turnoActivo?->fecha_hora_inicio->format('H:i') ?? '---' }}</strong>
            </p>
        </div>
        
        <form method="POST" action="{{ route('guardia.terminar.turno') }}" id="form-terminar-turno">
            @csrf
            <button type="submit" class="btn btn-danger">
                <i class="bi bi-box-arrow-right"></i> Terminar Turno
            </button>
        </form>
    </div>

    <div class="row g-4">
        
        {{-- COL 1: Pendientes (Revisiones y Anomalías) --}}
        <div class="col-md-8">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
                <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-bell text-warning"></i> Alertas Pendientes</h5>
                    <span class="badge bg-warning text-dark" id="count-revisions">0</span>
                </div>
                <div class="card-body" id="list-revisions" style="max-height: 60vh; overflow-y: auto;">
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-check-circle fs-1 text-success"></i>
                        <p class="mt-2 mb-0">Sin alertas pendientes</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- COL 2: Búsqueda Manual (F11) --}}
        <div class="col-md-4">
            <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                <div class="card-header bg-transparent py-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-search text-success"></i> Búsqueda Manual</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label small text-muted">Número de empleado</label>
                        <input type="number" id="manual-id" class="form-control form-control-lg" placeholder="Ej: 23951">
                    </div>
                    <button class="btn btn-success w-100 btn-lg" id="btn-manual-search">
                        <i class="bi bi-card-checklist"></i> Registrar
                    </button>
                    <div id="manual-result" class="mt-3"></div>
                </div>
            </div>
        </div>

    </div>
</div>

{{-- Modal para resolver pendiente --}}
<div class="modal fade" id="resolveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px;">
            <div class="modal-header">
                <h5 class="modal-title">Atender Alerta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Tipo: <strong id="modal-type"></strong></p>
                <p>Empleado: <strong id="modal-name"></strong></p>
                
                <div class="mb-3" id="modal-user-justification-wrapper" style="display: none;">
                    <label class="form-label">Justificación del Empleado:</label>
                    <textarea class="form-control" id="modal-user-justification" rows="2" readonly></textarea>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Resultado de la revisión</label>
                    <select class="form-select" id="modal-result">
                        <option value="todo_bien">Todo bien</option>
                        <option value="anomalia">Anomalía encontrada</option>
                    </select>
                </div>
                <div class="mb-3" id="modal-desc-wrapper" style="display: none;">
                    <label class="form-label">Descripción del hallazgo</label>
                    <textarea class="form-control" id="modal-desc" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <input type="hidden" id="modal-bitacora-id">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="btn-save-resolution">Guardar</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    let resolveModal;

    document.addEventListener('DOMContentLoaded', function() {
        resolveModal = new bootstrap.Modal(document.getElementById('resolveModal'));

        // POLLING: Consultar pendientes cada 5 segundos
        fetchPending();
        setInterval(fetchPending, 5000);

        // BÚSQUEDA MANUAL
        document.getElementById('btn-manual-search').addEventListener('click', async () => {
            const id = document.getElementById('manual-id').value;
            const resDiv = document.getElementById('manual-result');
            if(!id) return;
            try {
                const res = await fetch('/api/scan', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ codigo: id, origen: 'manual', turno_id: '{{ $turnoActivo->id ?? null }}', planta_id: '{{ $turnoActivo->planta_id ?? null }}' })
                });
                const data = await res.json();
                if(data.success) {
                    resDiv.innerHTML = `<div class="alert alert-success d-flex align-items-center"><img src="${data.persona.foto}" class="rounded-circle me-3" width="50" height="50"><div><strong>${data.persona.nombre}</strong><br>${data.message}</div></div>`;
                } else {
                    resDiv.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
                }
            } catch(e) {
                resDiv.innerHTML = `<div class="alert alert-danger">Error de conexión</div>`;
            }
        });

        document.getElementById('modal-result').addEventListener('change', function() {
            document.getElementById('modal-desc-wrapper').style.display = this.value === 'anomalia' ? 'block' : 'none';
        });

        document.getElementById('btn-save-resolution').addEventListener('click', async () => {
            const bitacoraId = document.getElementById('modal-bitacora-id').value;
            const resultado = document.getElementById('modal-result').value;
            const descripcion = document.getElementById('modal-desc').value;

            await fetch('/api/guardia/resolve', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ bitacora_id: bitacoraId, resultado, descripcion })
            });
            resolveModal.hide();
            fetchPending();
        });
    });

    // Función para obtener pendientes del servidor
    async function fetchPending() {
        try {
            const res = await fetch('/api/guardia/pending');
            const data = await res.json();
            
            let html = '';
            let count = 0;
            
            data.forEach(item => {
                count++;
                // Color y ícono según si es Revisión (amarillo) o Anomalía (azul)
                let bgClass = item.tipo === 'revision' ? 'alert-warning' : 'alert-info';
                let icon = item.tipo === 'revision' ? 'bi-shield-exclamation' : 'bi-exclamation-triangle';
                let text = item.tipo === 'revision' ? 'Revisión aleatoria' : 'Anomalía de horario';
                
                html += `
                    <div class="alert ${bgClass} d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <strong><i class="bi ${icon} me-1"></i> ${item.nombre}</strong> 
                            <small class="text-muted d-block">${text} - ${item.hora}</small>
                        </div>
                        <button class="btn btn-sm btn-success" onclick="openResolveModal(${item.bitacora_id}, '${item.nombre}', '${item.tipo}', '${item.justificacion}')">Atender</button>
                    </div>
                `;
            });
            
            document.getElementById('count-revisions').innerText = count;
            document.getElementById('list-revisions').innerHTML = count > 0 ? html : '<div class="text-center text-muted py-5"><i class="bi bi-check-circle fs-1 text-success"></i><p class="mt-2 mb-0">Sin alertas pendientes</p></div>';
        } catch(e) {
            console.error('Error:', e);
        }
    }

    function openResolveModal(bitacoraId, nombre, tipo, justificacion) {
        document.getElementById('modal-bitacora-id').value = bitacoraId;
        document.getElementById('modal-name').innerText = nombre;
        document.getElementById('modal-type').innerText = tipo === 'revision' ? 'Revisión Aleatoria' : 'Anomalía de Horario';
        document.getElementById('modal-result').value = 'todo_bien';
        document.getElementById('modal-desc-wrapper').style.display = 'none';
        document.getElementById('modal-desc').value = '';
        
        // Si es anomalía, mostramos la justificación que escribió el empleado en el kiosco
        if(tipo === 'anomalia' && justificacion) {
            document.getElementById('modal-user-justification').value = justificacion;
            document.getElementById('modal-user-justification-wrapper').style.display = 'block';
        } else {
            document.getElementById('modal-user-justification-wrapper').style.display = 'none';
        }
        
        resolveModal.show();
    }
</script>
@endpush
@endsection