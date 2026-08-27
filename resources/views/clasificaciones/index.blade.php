{{-- resources/views/clasificaciones/index.blade.php --}}
{{--
    Mapeo de clasificaciones de sitec → plantas.
    Permite configurar a qué planta pertenece cada clasificación de sitec.
    Una vez asignado, todos los empleados con esa clasificación quedan
    automáticamente mapeados a esa planta (la planta se deriva en tiempo real).

    CAMBIOS V4:
      - name="planta_id" → name="id_planta" (FK renombrada en schema V4).
      - $p->id → $p->id_planta (PK renombrada).
      - $mapeo?->planta_id → $mapeo?->id_planta (FK en clasificacion_planta).
      - El controlador (ClasificacionPlantaController) ya fue actualizado en
        el Bloque 9 para recibir id_planta en lugar de planta_id.
--}}
@extends('layouts.app')

@section('title', 'Clasificaciones → Plantas')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-0">Clasificaciones → Plantas</h1>
    <p class="text-muted mb-0">
        Configure a qué planta pertenece cada clasificación de sitec.
        Una vez asignado, todos los empleados con esa clasificación quedan
        automáticamente mapeados a esa planta.
    </p>
</div>

<div class="alert alert-info d-flex align-items-center">
    <i class="bi bi-info-circle-fill me-3 fs-4"></i>
    <div>
        <strong>Importante:</strong> La planta del empleado se deriva de esta
        configuración en tiempo real. Si cambian la clasificación de un empleado
        en sitec, el próximo escaneo ya usa la nueva planta.
        <strong>CORPORATIVO no se mapea</strong> — los corporativos acceden por
        su tipo de empleado (RN-01).
    </div>
</div>

{{-- ============================================================
    M2 [ScaModal + UI] — Tabla rediseñada con cards visuales
    ============================================================ --}}
<div class="clasificaciones-grid">
    @forelse($clasificaciones as $c)
        @php
            $mapeo = $mapeos->get($c->id_clasificacion);
            $conteo = $conteoEmpleados->get($c->id_clasificacion, 0);
        @endphp
        <div class="clasificacion-card {{ $mapeo ? 'has-mapping' : 'no-mapping' }}">
            {{-- Header de la card: ID + Nombre + Badge de empleados --}}
            <div class="clasificacion-card-header">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="clasificacion-id">#{{ $c->id_clasificacion }}</span>
                        <h6 class="clasificacion-name">{{ $c->nombre }}</h6>
                    </div>
                    <div class="clasificacion-empleados" title="Empleados con esta clasificación">
                        <i class="bi bi-people-fill"></i>
                        <span>{{ $conteo }}</span>
                    </div>
                </div>
            </div>

            {{-- Body de la card: Planta asignada --}}
            <div class="clasificacion-card-body">
                <div class="planta-label">PLANTA ASIGNADA</div>
                @if($mapeo)
                    <div class="planta-badge planta-assigned">
                        <i class="bi bi-building-fill-check"></i>
                        <span>{{ $mapeo->planta->nombre }}</span>
                    </div>
                @else
                    <div class="planta-badge planta-unassigned">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span>Sin mapear</span>
                    </div>
                @endif

                {{-- M2 [ScaModal] — Botón para ver excepciones (empleados con planta_forzada).
                    Solo se muestra si hay excepciones (count > 0).
                    Click abre el modal con la lista completa de esos empleados. --}}
                @php
                    $numExcepciones = $excepcionesPorClas->get($c->id_clasificacion, 0);
                @endphp
                @if($numExcepciones > 0)
                    <button type="button"
                            class="btn-excepciones sca-btn-ver-excepciones"
                            data-url="{{ route('clasificaciones.excepciones', $c->id_clasificacion) }}"
                            data-nombre="{{ $c->nombre }}"
                            title="Ver empleados con planta manual (no se actualizan al cambiar mapeo)">
                        <i class="bi bi-person-gear"></i>
                        <span>{{ $numExcepciones }} con planta manual</span>
                        <i class="bi bi-chevron-right"></i>
                    </button>
                @endif
            </div>

            {{-- Footer de la card: Acciones --}}
            <div class="clasificacion-card-footer">
                {{-- Form de update (NO anidar el form de destroy aquí) --}}
                <form method="POST"
                      action="{{ route('clasificaciones.update', $c->id_clasificacion) }}"
                      class="sca-form-update">
                    @csrf
                    @method('PUT')
                    <div class="d-flex gap-2 align-items-center mb-2">
                        <select name="id_planta" class="form-select form-select-sm" required>
                            <option value="">— Seleccionar planta —</option>
                            @foreach($plantas as $p)
                                <option value="{{ $p->id_planta }}"
                                    @if($mapeo?->id_planta == $p->id_planta) selected @endif>
                                    {{ $p->nombre }}
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-sm btn-success btn-guardar" title="Guardar">
                            <i class="bi bi-check-lg"></i>
                        </button>
                    </div>
                    <div class="form-check form-check-sm">
                        <input class="form-check-input" type="checkbox" name="forzar_todos" value="1" id="forzarTodos_{{ $c->id_clasificacion }}">
                        <label class="form-check-label small" for="forzarTodos_{{ $c->id_clasificacion }}">
                            Forzar
                        </label>
                        <button type="button" class="btn btn-link btn-sm p-0 ms-1 sca-btn-info-forzar" title="¿Qué hace Forzar?">
                            <i class="bi bi-info-circle text-muted"></i>
                        </button>
                    </div>
                </form>

                {{-- Form de destroy SEPARADO (hermano, no hijo del form de update) --}}
                @if($mapeo)
                    <form method="POST"
                          action="{{ route('clasificaciones.destroy', $c->id_clasificacion) }}"
                          class="sca-form-destroy mt-2"
                          data-nombre="{{ $c->nombre }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger btn-quitar w-100" title="Eliminar mapeo">
                            <i class="bi bi-trash"></i> Quitar mapeo
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @empty
        <div class="empty-state">
            <i class="bi bi-inbox"></i>
            <p>No hay clasificaciones en sitec.</p>
        </div>
    @endforelse
</div>

{{-- ============================================================
    CSS del rediseño (scoped a .clasificaciones-grid)
    ============================================================ --}}
<style>
    .clasificaciones-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }

    .clasificacion-card {
        background: var(--sca-card-bg, #ffffff);
        border-radius: 16px;
        border: 1px solid var(--sca-border, rgba(0,0,0,0.08));
        overflow: hidden;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .clasificacion-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.08);
    }

    /* Variante: con mapeo (borde sutil verde) */
    .clasificacion-card.has-mapping {
        border-left: 4px solid var(--sca-primary, #2e7d32);
    }
    /* Variante: sin mapear (borde ámbar) */
    .clasificacion-card.no-mapping {
        border-left: 4px solid #f59e0b;
    }

    .clasificacion-card-header {
        padding: 1rem 1.25rem 0.75rem;
        border-bottom: 1px solid var(--sca-border, rgba(0,0,0,0.06));
    }
    .clasificacion-id {
        font-family: monospace;
        font-size: 0.72rem;
        color: var(--sca-text-muted, #6c757d);
        background: rgba(0,0,0,0.04);
        padding: 2px 8px;
        border-radius: 6px;
        margin-right: 6px;
    }
    [data-bs-theme="dark"] .clasificacion-id { background: rgba(255,255,255,0.06); }

    .clasificacion-name {
        font-size: 1rem;
        font-weight: 600;
        margin: 0.35rem 0 0 0;
        color: var(--sca-text, #333);
        line-height: 1.2;
    }

    .clasificacion-empleados {
        display: flex;
        align-items: center;
        gap: 4px;
        background: rgba(0,0,0,0.04);
        padding: 6px 10px;
        border-radius: 10px;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--sca-text-muted, #6c757d);
    }
    [data-bs-theme="dark"] .clasificacion-empleados { background: rgba(255,255,255,0.06); }
    .clasificacion-empleados i { font-size: 0.9rem; }

    .clasificacion-card-body {
        padding: 1rem 1.25rem;
        background: rgba(0,0,0,0.02);
    }
    [data-bs-theme="dark"] .clasificacion-card-body { background: rgba(255,255,255,0.02); }

    .planta-label {
        font-size: 0.68rem;
        font-weight: 600;
        letter-spacing: 0.5px;
        color: var(--sca-text-muted, #6c757d);
        margin-bottom: 0.5rem;
        text-transform: uppercase;
    }

    .planta-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 10px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    .planta-badge.planta-assigned {
        background: rgba(46, 125, 50, 0.12);
        color: var(--sca-primary, #2e7d32);
    }
    .planta-badge.planta-unassigned {
        background: rgba(245, 158, 11, 0.15);
        color: #b45309;
    }
    .planta-badge i { font-size: 0.95rem; }

    /* M2 [ScaModal] — Botón "X con planta manual" */
    .btn-excepciones {
        display: flex;
        align-items: center;
        gap: 6px;
        width: 100%;
        margin-top: 0.75rem;
        padding: 6px 10px;
        background: rgba(245, 158, 11, 0.12);
        color: #b45309;
        border: 1px solid rgba(245, 158, 11, 0.25);
        border-radius: 10px;
        font-size: 0.78rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-excepciones:hover {
        background: rgba(245, 158, 11, 0.18);
        border-color: rgba(245, 158, 11, 0.4);
        transform: translateY(-1px);
    }
    .btn-excepciones i:first-child { font-size: 0.9rem; }
    .btn-excepciones i:last-child {
        font-size: 0.7rem;
        margin-left: auto;
        opacity: 0.6;
    }
    .btn-excepciones span { flex: 1; text-align: left; }

    .clasificacion-card-footer {
        padding: 1rem 1.25rem 1.25rem;
    }
    .clasificacion-card-footer .btn-guardar {
        min-width: 38px;
    }
    .clasificacion-card-footer .btn-quitar {
        font-size: 0.78rem;
        padding: 4px 10px;
    }

    .form-check-sm .form-check-input {
        margin-left: -1.5em;
        width: 0.9em;
        height: 0.9em;
    }
    .form-check-sm .form-check-label {
        font-size: 0.78rem;
        padding-left: 0.25rem;
    }

    .empty-state {
        grid-column: 1 / -1;
        text-align: center;
        padding: 3rem 1rem;
        color: var(--sca-text-muted, #6c757d);
    }
    .empty-state i {
        font-size: 3rem;
        opacity: 0.5;
        margin-bottom: 0.5rem;
    }
    .empty-state p {
        font-size: 0.95rem;
        margin: 0;
    }

    /* Mobile: una sola columna */
    @media (max-width: 576px) {
        .clasificaciones-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

@push('scripts')
<script>
    // ============================================================
    // M2 [ScaModal] — Intercept forms for AJAX + loading flow
    //
    // Maneja 2 flujos:
    //   1. Form de update (clase .sca-form-update): PUT al controller
    //      → loading "Guardando mapeo..." → success "Mapeo actualizado"
    //   2. Form de destroy (clase .sca-form-destroy): DELETE al controller
    //      → ScaModal.confirm danger con nombre → loading → success "Mapeo eliminado"
    // ============================================================

    document.addEventListener('DOMContentLoaded', function() {

        // ============================================================
        // M2 [ScaModal] — Botón "Ver excepciones" en las cards.
        // 
        // Los botones (.sca-btn-ver-excepciones) viven en cada card
        // y están en el DOM desde el inicio. Cada uno tiene:
        //   - data-url: ruta AJAX para obtener la lista de excepciones
        //   - data-nombre: nombre de la clasificación (para el título del modal)
        //
        // Al hacer clic: loading → fetch JSON → modal info con tabla
        // (no cancelable con clic fuera, solo con "Entendido")
        // ============================================================
        document.querySelectorAll('.sca-btn-ver-excepciones').forEach(btn => {
            btn.addEventListener('click', async function() {
                const url = this.dataset.url;
                const nombreClas = this.dataset.nombre || 'esta clasificación';

                // Mostrar loading
                ScaModal.loading('Cargando excepciones', `Obteniendo empleados con planta manual de <strong>${nombreClas}</strong>...`);

                try {
                    const resp = await fetch(url, {
                        headers: { 'Accept': 'application/json' }
                    });
                    const excData = await resp.json();
                    ScaModal.close();

                    // Si no hay excepciones (raro porque el botón solo se muestra si hay)
                    if (excData.total === 0) {
                        ScaModal.info('Sin excepciones', `No hay empleados con planta manual en <strong>${nombreClas}</strong>.`);
                        return;
                    }

                    // Construir tabla HTML de excepciones
                    let tabla = `<div class="text-start">`;
                    tabla += `<div class="mb-2 text-muted">Clasificación: <strong>${excData.clasificacion}</strong></div>`;
                    tabla += `<div class="mb-3 text-muted">Total: <strong>${excData.total}</strong> empleado(s) con planta manual</div>`;
                    tabla += `<div class="table-responsive" style="max-height: 300px; overflow-y: auto;">`;
                    tabla += `<table class="table table-sm">`;
                    tabla += `<thead><tr><th>ID</th><th>Nombre</th><th>Puesto</th><th>Planta actual</th></tr></thead><tbody>`;
                    excData.excepciones.forEach(exc => {
                        tabla += `<tr><td class="font-monospace">${exc.id_empleado}</td><td>${exc.nombre}</td><td class="small text-muted">${exc.puesto}</td><td><span class="badge bg-info">${exc.planta}</span></td></tr>`;
                    });
                    tabla += `</tbody></table></div></div>`;

                    // Modal info que NO se cierra con clic fuera (backdrop static)
                    const modalEl = document.getElementById('scaModal');
                    const modalInstance = bootstrap.Modal.getInstance(modalEl);
                    if (modalInstance) {
                        modalInstance._config.backdrop = 'static';
                        modalInstance._config.keyboard = false;
                    }

                    ScaModal.info('Empleados con planta manual', tabla, {
                        onOk: () => {
                            if (modalInstance) {
                                modalInstance._config.backdrop = true;
                                modalInstance._config.keyboard = true;
                            }
                            // No redirigir, solo cerrar el modal
                            // (el botón está en la card, el usuario ya está en la página)
                        }
                    });
                } catch (err) {
                    ScaModal.close();
                    ScaModal.error('Error', 'No se pudieron cargar las excepciones. Inténtalo de nuevo.');
                }
            });
        });

        // ===== 1. Form de UPDATE (asignar/cambiar planta) =====
        document.querySelectorAll('.sca-form-update').forEach(form => {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                // M2 [ScaModal] — Confirmar antes de actualizar
                const select = form.querySelector('select[name="id_planta"]');
                const plantaNombre = select.options[select.selectedIndex].text;
                const forzar = form.querySelector('input[name="forzar_todos"]')?.checked;
                
                // Buscar el conteo de empleados desde la card
                const card = form.closest('.clasificacion-card');
                const badgeConteo = card?.querySelector('.clasificacion-empleados span');
                const numEmpleados = badgeConteo ? parseInt(badgeConteo.textContent) || 0 : 0;
                
                // Nombre de la clasificación (del h6 en el header)
                const nombreClas = card?.querySelector('.clasificacion-name')?.textContent.trim() || 'esta clasificación';

                let mensaje = `Se asignará la planta <strong>${plantaNombre}</strong> a la clasificación <strong>${nombreClas}</strong>.`;
                if (numEmpleados > 0) {
                    mensaje += `<br><br>Esto actualizará la planta de <strong>${numEmpleados} empleado(s)</strong>.`;
                }
                if (forzar) {
                    mensaje += `<br><br><span class="text-danger"><i class="bi bi-exclamation-triangle-fill"></i> Modo "Forzar" activado: se sobreescribirán las asignaciones manuales.</span>`;
                }

                ScaModal.confirm({
                    titulo: '¿Guardar mapeo?',
                    mensaje: mensaje,
                    tipo: forzar ? 'danger' : 'warning',
                    textoConfirmar: 'Sí, guardar',
                    textoCancelar: 'Cancelar',
                    onConfirm: async () => {
                        // Mostrar loading
                        ScaModal.loading('Guardando mapeo', 'Actualizando clasificación y reasignando empleados...');

                        try {
                            const response = await fetch(form.action, {
                                method: 'POST',  // Laravel convierte POST + _method=PUT a PUT
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                    'Accept': 'application/json',
                                },
                                body: new FormData(form),
                            });

                            const data = await response.json();
                            ScaModal.close();

                            if (data.success) {
                                // Cerrar loading y mostrar success en esta misma página.
                                // El success incluye el aviso de excepciones si las hay.
                                ScaModal.close();
                                
                                let mensajeFinal = data.mensaje;
                                if (data.tiene_excepciones) {
                                    mensajeFinal += `<br><br><div class="alert alert-warning py-2 mb-0"><i class="bi bi-exclamation-triangle-fill"></i> <strong>${data.total_excepciones} empleado(s)</strong> no se actualizaron (planta manual).<br><small>Podés ver la lista desde el botón "X con planta manual" en la tarjeta.</small></div>`;
                                }
                                
                                ScaModal.success(data.titulo, mensajeFinal, {
                                    onOk: () => { window.location.href = data.redirect; },
                                });
                            } else {
                                ScaModal.close();
                                ScaModal.error(data.titulo || 'Error', data.mensaje || 'No se pudo actualizar.');
                            }
                        } catch (err) {
                            ScaModal.close();
                            ScaModal.error('Error de conexión', 'No se pudo contactar con el servidor. Inténtalo de nuevo.');
                        }
                    }
                });
            });
        });

        // ===== 1b. Botón info "Forzar" =====
        document.querySelectorAll('.sca-btn-info-forzar').forEach(btn => {
            btn.addEventListener('click', function() {
                ScaModal.info(
                    '¿Qué hace "Forzar"?',
                    'Al actualizar un mapeo, el sistema cambia la planta de todos los empleados de esa clasificación.<br><br>' +
                    '<strong>Sin "Forzar"</strong> (default): Respeta las asignaciones manuales. Si un admin asignó manualmente una planta diferente a un empleado, NO se sobreescribe.<br><br>' +
                    '<strong>Con "Forzar"</strong>: Sobreescribe TODOS los empleados, incluyendo los que tienen asignación manual. Útil si querés resetear todas las asignaciones manuales y que todos vuelvan a la planta de su clasificación.'
                );
            });
        });

        // ===== 2. Form de DESTROY (eliminar mapeo) =====
        document.querySelectorAll('.sca-form-destroy').forEach(form => {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                const nombre = form.dataset.nombre || 'esta clasificación';

                // Confirmación con ScaModal (reemplaza el confirm() nativo)
                ScaModal.confirm({
                    titulo: '¿Eliminar mapeo?',
                    mensaje: `Se eliminará el mapeo de <strong>${nombre}</strong>. Los empleados volverán a "Sin mapear".`,
                    tipo: 'danger',
                    textoConfirmar: 'Sí, eliminar',
                    textoCancelar: 'Cancelar',
                    onConfirm: async () => {
                        // Mostrar loading
                        ScaModal.loading('Eliminando mapeo', 'Procesando...');

                        try {
                            const response = await fetch(form.action, {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                    'Accept': 'application/json',
                                },
                                body: new FormData(form),
                            });

                            const data = await response.json();

                            if (data.success) {
                                // Option E: NO cerramos el loading. Redirect directo.
                                window.location.href = data.redirect;
                            } else {
                                ScaModal.close();
                                ScaModal.error(data.titulo || 'Error', data.mensaje || 'No se pudo eliminar.');
                            }
                        } catch (err) {
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