{{-- resources/views/partials/_personas_dentro.blade.php --}}
<section class="card border-0 shadow-sm h-100 sca-card sca-presence-card">
    <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
        <div>
            <div class="sca-presence-kicker"><i class="bi bi-activity" aria-hidden="true"></i> Estado actual</div>
            <h5 class="mb-0 fw-bold sca-presence-title">Personas dentro</h5>
            <div class="sca-presence-subtitle">Último acceso autorizado sin salida registrada.</div>
        </div>
        <span class="sca-presence-count" id="count-dentro-{{ $id ?? 'default' }}" aria-live="polite">0</span>
    </div>

    @if(isset($plantas) && $plantas->count() > 1)
    <div class="px-3 pb-2">
        <label class="visually-hidden" for="select-planta-{{ $id ?? 'default' }}">Planta</label>
        <select class="form-select form-select-sm" id="select-planta-{{ $id ?? 'default' }}"
                onchange="cambiarPlantaDentro('{{ $id ?? "default" }}')">
            @foreach($plantas as $p)
                <option value="{{ $p->id_planta }}">{{ $p->nombre }}</option>
            @endforeach
        </select>
    </div>
    @endif

    <div class="card-body sca-presence-list" id="list-dentro-{{ $id ?? 'default' }}">
        <div class="sca-presence-empty" aria-live="polite">
            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
            <strong>Consultando presencia…</strong>
        </div>
    </div>
</section>

@push('scripts')
<script>
(function() {
    const widgetId = '{{ $id ?? "default" }}';
    const badge = document.getElementById('count-dentro-' + widgetId);
    const lista = document.getElementById('list-dentro-' + widgetId);
    const selectPlanta = document.getElementById('select-planta-' + widgetId);

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
    })[char]);

    const formatDuracion = (minutos) => {
        const total = Math.max(0, Math.floor(Number(minutos) || 0));
        const dias = Math.floor(total / 1440);
        const horas = Math.floor((total % 1440) / 60);
        const mins = total % 60;
        if (dias > 0) {
            if (horas === 0 && mins === 0) return `${dias} d`;
            if (mins === 0) return `${dias} d ${horas} h`;
            return `${dias} d ${horas} h ${mins} min`;
        }
        if (horas === 0) return `${mins} min`;
        if (mins === 0) return `${horas} h`;
        return `${horas} h ${mins} min`;
    };

    const clasePermanencia = (minutos) => {
        const total = Math.max(0, Math.floor(Number(minutos) || 0));
        if (total > 720) return 'sca-permanencia-alerta';   // >12h
        if (total > 480) return 'sca-permanencia-atencion'; // 8-12h
        return 'sca-permanencia-normal';                    // <8h
    };

    const etiquetaFecha = (fecha) => {
        const hoy = @json(today()->toDateString());
        if (!fecha || fecha === hoy) return 'Hoy';
        const partes = String(fecha).split('-');
        if (partes.length !== 3) return fecha;
        return `${partes[2]}/${partes[1]}/${partes[0]}`;
    };

    const fechaHoraVigencia = (value) => {
        if (!value) return '';
        const [fecha, hora] = String(value).split(' ');
        return `${etiquetaFecha(fecha)}${hora ? ' · ' + hora.slice(0, 5) : ''}`;
    };

    async function cargarDentro() {
        try {
            let url = '{{ route("api.guardia.personas-dentro") }}';
            if (selectPlanta && selectPlanta.value) {
                url += '?planta_id=' + encodeURIComponent(selectPlanta.value);
            }

            const res = await fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                cache: 'no-store'
            });

            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();
            badge.textContent = data.total ?? 0;

            if (!data.total) {
                lista.innerHTML = '<div class="sca-presence-empty"><i class="bi bi-person-check"></i><strong>Sin personas dentro</strong><span>No hay una entrada autorizada pendiente de salida en esta planta.</span></div>';
                return;
            }

            let html = `<div class="sca-presence-summary">
                <div class="sca-presence-types">
                    <span><i class="bi bi-person-badge" aria-hidden="true"></i> ${escapeHtml(data.empleados)} empleados</span>
                    <span><i class="bi bi-person" aria-hidden="true"></i> ${escapeHtml(data.visitantes)} externos</span>
                </div>
                <small class="sca-presence-plant"><i class="bi bi-geo-alt" aria-hidden="true"></i> ${escapeHtml(data.planta_nombre || 'Planta')}</small>
            </div>`;

            html += '<div class="sca-presence-items">';
            data.personas.forEach(p => {
                const esEmpleado = p.tipo === 'empleado';
                const icono = esEmpleado ? 'bi-person-badge' : 'bi-person-vcard';
                const clase = esEmpleado ? 'is-employee' : 'is-external';
                const etiquetaTipo = esEmpleado ? 'Empleado' : escapeHtml(p.puesto || 'Externo');
                const metaPrincipal = esEmpleado
                    ? [p.puesto, p.departamento].filter(v => v && v !== '—').map(escapeHtml).join(' · ')
                    : [p.empresa].filter(v => v && v !== '—').map(escapeHtml).join(' · ');
                const metaSecundaria = esEmpleado
                    ? `ID ${escapeHtml(p.id)}${p.turno && p.turno !== '—' ? ' · Turno ' + escapeHtml(p.turno) : ''}`
                    : `${p.anfitrion && p.anfitrion !== '—' ? 'Anfitrión: ' + escapeHtml(p.anfitrion) : 'Solicitud externa'}`;

                html += `
                    <article class="sca-presence-item">
                        <div class="sca-presence-avatar ${clase}" aria-hidden="true"><i class="bi ${icono}"></i></div>
                        <div class="sca-presence-person">
                            <div class="sca-presence-name-row">
                                <strong title="${escapeHtml(p.nombre)}">${escapeHtml(p.nombre)}</strong>
                                <span class="sca-presence-type ${clase}">${etiquetaTipo}</span>
                            </div>
                            <span class="sca-presence-meta">${metaPrincipal || 'Sin información adicional'}</span>
                            <span class="sca-presence-meta-secondary">${metaSecundaria}</span>
                        </div>
                        <div class="sca-presence-time">
                            <strong>${escapeHtml(p.hora_entrada)}</strong>
                            <span>${escapeHtml(etiquetaFecha(p.fecha_entrada))}</span>
                            <small><i class="bi bi-hourglass-split" aria-hidden="true"></i> ${escapeHtml(formatDuracion(p.duracion_minutos))}</small>
                        </div>
                    </article>
                    ${!esEmpleado && p.vigencia_hasta ? `<div class="sca-presence-external-detail"><i class="bi bi-calendar-check" aria-hidden="true"></i> Vigencia: ${escapeHtml(fechaHoraVigencia(p.vigencia_hasta))}${p.solicitud_id ? ` · Solicitud #${escapeHtml(p.solicitud_id)}` : ''}</div>` : ''}
                `;
            });
            html += '</div>';
            lista.innerHTML = html;
        } catch (e) {
            console.error('Error dentro:', e);
            if (lista) {
                lista.innerHTML = '<div class="sca-presence-empty sca-presence-error"><i class="bi bi-cloud-slash"></i><strong>No se pudo actualizar</strong><span>La información anterior no se puede confirmar. Comprueba la conexión e inténtalo de nuevo.</span></div>';
            }
        }
    }

    window.cambiarPlantaDentro = function(id) {
        if (id === widgetId) cargarDentro();
    };

    cargarDentro();
    window.setInterval(cargarDentro, 10000);
})();
</script>
@endpush
