{{-- resources/views/partials/_campana.blade.php --}}
<div class="dropdown">
    <button class="btn position-relative" type="button" id="btnCampana" 
            data-bs-toggle="dropdown" aria-expanded="false" 
            style="background: transparent; border: 1px solid var(--sca-border); border-radius: 10px; width: 40px; height: 40px; color: var(--sca-text);">
        <i class="bi bi-bell"></i>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" 
              id="badge-notif" style="display:none; font-size: 0.65rem;">0</span>
    </button>
    
    <div class="dropdown-menu dropdown-menu-end p-0" aria-labelledby="btnCampana" 
         style="width: 380px; max-height: 500px; overflow-y: auto; border-radius: 12px; border: 1px solid var(--sca-border); background: var(--sca-card-bg);">
        
        <div class="d-flex justify-content-between align-items-center p-3 border-bottom" style="border-color: var(--sca-border) !important;">
            <h6 class="mb-0 fw-bold">Notificaciones</h6>
            <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" id="btnMarcarTodas" style="font-size: 0.75rem; color: var(--sca-primary);">Marcar todas como leídas</button>
        </div>
        
        <div id="lista-notificaciones">
            <div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span> Cargando...</div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function() {
    const badge = document.getElementById('badge-notif');
    const lista = document.getElementById('lista-notificaciones');

    async function cargarNotificaciones() {
        try {
            const res = await fetch('{{ route("api.notificaciones") }}');
            const data = await res.json();

            if (data.no_leidas > 0) {
                badge.style.display = 'inline-block';
                badge.textContent = data.no_leidas > 9 ? '9+' : data.no_leidas;
            } else {
                badge.style.display = 'none';
            }

            if (data.notificaciones.length === 0) {
                lista.innerHTML = '<div class="text-center text-muted py-5"><i class="bi bi-bell-slash fs-1 opacity-50"></i><p class="mt-2 mb-0 small">Sin notificaciones</p></div>';
                return;
            }

            let html = '';
            data.notificaciones.forEach(n => {
                let bgClass = n.leida ? '' : 'style="background: var(--sca-hover);"';
                let icono = '';
                switch(n.tipo) {
                    case 'solicitud_aprobada': case 'solicitud_aprobada_parcial': case 'solicitud_aprobada_tercero': case 'solicitud_creada_auto': icono = '<i class="bi bi-check-circle-fill text-success"></i>'; break;
                    case 'solicitud_rechazada': icono = '<i class="bi bi-x-circle-fill text-danger"></i>'; break;
                    case 'nueva_solicitud': icono = '<i class="bi bi-inbox-fill text-warning"></i>'; break;
                    default: icono = '<i class="bi bi-bell-fill text-primary"></i>';
                }

                html += `
                    <a href="${n.url || '#'}" class="d-flex align-items-start gap-2 p-3 text-decoration-none" 
                       ${bgClass} style="border-bottom: 1px solid var(--sca-border); color: var(--sca-text);"
                       onclick="marcarLeida(${n.id}, this)">
                        <div class="flex-shrink-0">${icono}</div>
                        <div class="flex-grow-1">
                            <div class="fw-semibold small">${n.titulo}</div>
                            <div class="small text-muted">${n.mensaje}</div>
                            <small class="text-muted" style="font-size: 0.7rem;">${n.tiempo}</small>
                        </div>
                        ${!n.leida ? '<div class="flex-shrink-0"><span class="badge bg-danger rounded-circle" style="width: 8px; height: 8px; padding: 0;"></span></div>' : ''}
                    </a>`;
            });
            lista.innerHTML = html;
        } catch(e) { console.error('Error notificaciones:', e); }
    }

    window.marcarLeida = async function(id, element) {
        try {
            await fetch('{{ route("api.notificaciones.marcar") }}', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body: JSON.stringify({ id }) });
            if (element) element.style.background = 'transparent';
            cargarNotificaciones();
        } catch(e) {}
    };

    document.getElementById('btnMarcarTodas')?.addEventListener('click', async function() {
        try {
            await fetch('{{ route("api.notificaciones.marcar-todas") }}', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });
            cargarNotificaciones();
        } catch(e) {}
    });

    cargarNotificaciones();
    setInterval(cargarNotificaciones, 30000);
})();
</script>
@endpush