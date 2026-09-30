{{-- resources/views/solicitudes/show_ext.blade.php --}}
@extends('layouts.app')

@section('title', 'Solicitud #' . $solicitud->id_solicitud_ext)

@section('content')
@php
    // M2: $puedeAprobar se define acá arriba porque se usa en la tabla de externos
    // (más arriba en el HTML) y en la columna derecha de acciones.
    $user = Auth::user();
    $esSuperior = $user->isAdmin() || $user->isSuperadmin();
    if ($esSuperior) {
        $puedeAprobar = ($solicitud->estado === 'pendiente');
    } else {
        $puedeAprobar = ($solicitud->id_planta_destino === $user->planta_id && $solicitud->estado === 'pendiente' && $user->isAprobador());
    }
@endphp

<div class="sca-page-head legacy-page-head">
    <div>
        <div class="sca-page-eyebrow"><i class="bi bi-person-badge" aria-hidden="true"></i> Solicitud externa</div>
        <h1 class="sca-page-title">Solicitud #{{ $solicitud->id_solicitud_ext }}</h1>
        <p class="sca-page-subtitle">
            Creada: {{ $solicitud->created_at->format('d/m/Y H:i') }}
            @if($creador) · por <strong>{{ $creador->nombre_completo }}</strong> @endif
        </p>
    </div>
    <a href="#" data-sca-history-back="1" class="btn btn-outline-secondary" aria-label="Volver a la vista anterior">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<div class="row g-4">
    {{-- COLUMNA IZQUIERDA: Detalle completo --}}
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3 sca-card" style="border-radius:12px;">
            <div class="card-header bg-transparent py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-info-circle text-success"></i> Detalle</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Tipo</label>
                        <div class="fw-semibold">
                            @php
                                $tipoLabel = match($solicitud->tipo) {
                                    'visitante'   => 'Visita',
                                    'contratista' => 'Contratista',
                                    default       => $solicitud->tipo,
                                };
                            @endphp
                            {{ $tipoLabel }}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Estado</label>
                        <div>
                            @php
                                $estado = $solicitud->estado_actual;
                                $estadoClass = match($estado) {
                                    'pendiente'         => 'bg-warning text-dark',
                                    'aprobado'          => 'bg-success',
                                    'aprobado_parcial'  => 'bg-info text-dark',
                                    'rechazado'         => 'bg-danger',
                                    'expirado'          => 'bg-secondary',
                                    default             => 'bg-secondary',
                                };
                                $estadoLabel = match($estado) {
                                    'pendiente'         => 'Pendiente',
                                    'aprobado'          => 'Aprobado',
                                    'aprobado_parcial'  => 'Aprobado parcial',
                                    'rechazado'         => 'Rechazado',
                                    'expirado'          => 'Expirada',
                                    default             => $estado,
                                };
                            @endphp
                            <span class="badge {{ $estadoClass }} px-3 py-2">{{ $estadoLabel }}</span>
                            @if($estado === 'expirado')
                                <small class="d-block mt-1 text-muted">
                                    {{ $solicitud->motivo_expiracion_label }}
                                </small>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Planta Destino (Visita)</label>
                        <div class="fw-semibold">{{ $solicitud->plantaDestino?->nombre ?? '—' }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Anfitrión</label>
                        <div class="fw-semibold">{{ $anfitrion?->nombre_completo ?? '—' }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Vigencia desde</label>
                        <div class="fw-semibold">{{ $solicitud->fecha_inicio->format('d/m/Y H:i') }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Vigencia hasta</label>
                        <div class="fw-semibold">{{ $solicitud->fecha_fin->format('d/m/Y H:i') }}</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-muted">Motivo</label>
                        <div class="p-3 rounded sca-card" style="background: var(--sca-hover);">
                            {{ $solicitud->motivo }}
                        </div>
                    </div>
                </div>

                {{-- Tabla de externos con sus QRs --}}
                @if($puedeAprobar)
                <form method="POST" action="{{ route('solicitudes.aprobar.ext', $solicitud->id_solicitud_ext) }}" id="formAprobarExt" class="sca-form-aprobar-ext">
                    @csrf
                @endif
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Nombre</th>
                                <th>Identificación</th>
                                <th>Teléfono</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($solicitud->externos as $detalle)
                            @php
                                $ext = $detalle->externo;
                                $qrEstado = $detalle->estado_qr_actual;
                                $qrClass = match($qrEstado) {
                                    'pendiente'  => 'bg-warning text-dark',
                                    'autorizado' => 'bg-success',
                                    'cancelado'  => 'bg-danger',
                                    'expirado'   => 'bg-secondary',
                                    default      => 'bg-secondary',
                                };
                                $estadoSol = $solicitud->estado_actual;
                                $puedeCancelarExt = (
                                    in_array($estadoSol, ['aprobado', 'aprobado_parcial'])
                                    && $qrEstado !== 'cancelado'
                                    && $qrEstado !== 'expirado'
                                    && (auth()->user()->isAdmin() || auth()->user()->isSuperadmin() || auth()->user()->isAprobador()
                                        || $solicitud->creador_empleado_id === (int) auth()->user()->username)
                                );
                            @endphp
                            <tr>
                                <td>{{ $ext?->nombre_completo ?? '—' }}</td>
                                <td>
                                    @php
                                        $tiposLabels = ['curp' => 'CURP', 'credencial_votar' => 'Credencial para votar', 'cedula_profesional' => 'Cédula profesional', 'pasaporte' => 'Pasaporte'];
                                        $badgeClass = ['curp' => 'bg-success', 'credencial_votar' => 'bg-primary', 'cedula_profesional' => 'bg-info text-dark', 'pasaporte' => 'bg-warning text-dark'];
                                        $tipoLabel = $tiposLabels[$ext?->tipo_identificacion] ?? '—';
                                        $tipoClass = $badgeClass[$ext?->tipo_identificacion] ?? 'bg-secondary';
                                    @endphp
                                    @if($ext?->identificacion)
                                        <span class="badge {{ $tipoClass }} mb-1">{{ $tipoLabel }}</span><br>
                                        <span class="font-monospace small">{{ $ext->identificacion }}</span>
                                    @else
                                        <span class="text-muted">Sin identificación</span>
                                    @endif
                                </td>
                                <td>{{ $ext?->telefono ?? '—' }}</td>
                                <td><span class="badge {{ $qrClass }}">{{ ucfirst($qrEstado) }}</span></td>
                                <td>
                                    <div class="d-flex gap-2 align-items-center">
                                        @if($puedeAprobar)
                                            <select name="estados[{{ $detalle->id_solicitud_externo_detalle }}]" class="form-select form-select-sm w-auto ext-estado">
                                                <option value="autorizado">Autorizar</option>
                                                <option value="cancelado">Cancelar</option>
                                            </select>
                                        @else
                                            @if($detalle->codigo_qr && $qrEstado === 'autorizado')
                                                <a href="{{ route('solicitudes.descargar.png', $detalle->id_solicitud_externo_detalle) }}" class="btn btn-sm btn-outline-secondary" title="Descargar PNG" target="_blank">
                                                    <i class="bi bi-download"></i>
                                                </a>
                                            @endif

                                            @if($puedeCancelarExt)
                                                {{-- M5 [ScaModal] --}}
                                                <button type="button" class="btn btn-sm btn-outline-danger sca-cancel-externo" title="Cancelar este externo" 
                                                        data-action="{{ route('solicitudes.cancel.externo.individual', $solicitud->id_solicitud_ext) }}"
                                                        data-detalle-id="{{ $detalle->id_solicitud_externo_detalle }}"
                                                        data-nombre="{{ $ext?->nombre_completo ?? 'este externo' }}">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($puedeAprobar)
                </form>
                @endif
            </div>
        </div>

        @if($solicitud->motivo_rechazo)
        <div class="alert alert-danger" style="border-radius:12px;">
            <h6 class="fw-bold mb-1"><i class="bi bi-x-circle"></i> {{ $solicitud->estado === 'rechazado' ? 'Rechazada' : 'Cancelada' }}</h6>
            <p class="mb-0">{{ $solicitud->motivo_rechazo }}</p>
        </div>
        @endif
    </div>

    {{-- COLUMNA DERECHA: Timeline + QRs descargables + acciones --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm sca-card mb-3" style="border-radius:12px;">
            <div class="card-header bg-transparent py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-clock-history text-success"></i> Aprobación</h5>
            </div>
            <div class="card-body">
                <div class="d-flex mb-3">
                    <div class="me-3">
                        @if($solicitud->fecha_aprobacion_destino)
                            <i class="bi bi-check-circle-fill text-success fs-4"></i>
                        @elseif($solicitud->estado === 'pendiente')
                            <div class="spinner-border text-warning" role="status" style="width:24px; height:24px;"></div>
                        @elseif($solicitud->estado === 'rechazado')
                            <i class="bi bi-x-circle-fill text-danger fs-4"></i>
                        @else
                            <i class="bi bi-circle text-muted fs-4"></i>
                        @endif
                    </div>
                    <div>
                        <div class="fw-semibold">Aprobación Destino</div>
                        @if($solicitud->fecha_aprobacion_destino)
                            <small class="text-muted">{{ $solicitud->fecha_aprobacion_destino->format('d/m/Y H:i') }}</small>
                        @elseif($solicitud->estado === 'pendiente')
                            <small class="text-warning">Pendiente</small>
                        @else
                            <small class="text-muted">—</small>
                        @endif
                    </div>
                </div>

                @if($puedeAprobar)
                    <hr>
                    <div class="alert alert-info py-2 small">
                        <i class="bi bi-info-circle"></i> Si cancela alguno, no recibirá QR y no podrá entrar. La solicitud queda <strong>Aprobada Parcial</strong>.
                    </div>
                    <button type="button" class="btn btn-success w-100 mt-3" onclick="document.getElementById('formAprobarExt').dispatchEvent(new Event('submit'))">
                        <i class="bi bi-check-lg"></i> Confirmar Revisión
                    </button>

                    {{-- M5 [ScaModal] --}}
                    <form method="POST" action="{{ route('solicitudes.rechazar.ext', $solicitud->id_solicitud_ext) }}" class="sca-rechazar-ext mt-2">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger w-100">
                            <i class="bi bi-x-lg"></i> Rechazar Toda
                        </button>
                    </form>
                @elseif(in_array($solicitud->estado, ['pendiente', 'aprobado', 'aprobado_parcial']) && ($solicitud->creador_empleado_id === (int) $user->username || $user->isAprobador() || $user->isAdmin() || $user->isSuperadmin()))
                    <hr>
                    @if(in_array($solicitud->estado, ['aprobado', 'aprobado_parcial']))
                    <a href="{{ route('solicitudes.descargar.credenciales', $solicitud->id_solicitud_ext) }}" class="btn btn-success w-100 mb-2" target="_blank">
                        <i class="bi bi-file-earmark-pdf-fill"></i> Descargar Todas (PDF)
                    </a>
                    {{-- M5 [ScaModal] --}}
                    <form method="POST" action="{{ route('solicitudes.reenviar.credenciales', $solicitud->id_solicitud_ext) }}" class="sca-reenviar-credenciales mb-2">
                        @csrf
                        <button type="submit" class="btn btn-outline-info w-100">
                            <i class="bi bi-envelope-paper"></i> Reenviar Credenciales
                        </button>
                    </form>
                    @endif
                    {{-- M5 [ScaModal] --}}
                    <form method="POST" action="{{ route('solicitudes.cancel.ext', $solicitud->id_solicitud_ext) }}" class="sca-cancelar-solicitud-ext">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger w-100">
                            <i class="bi bi-x-circle"></i> Cancelar Solicitud
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {

    async function enviarFormAjax(form) {
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
                window.location.replace(data.redirect);
            } else {
                ScaModal.close();
                if (data.errors) {
                    const errores = Object.values(data.errors).flat();
                    ScaModal.error('Error de validación', errores.join('<br>'));
                } else {
                    ScaModal.error(data.titulo || 'Error', data.mensaje || 'No se pudo procesar.');
                }
            }
        } catch(err) {
            ScaModal.close();
            ScaModal.error('Error de conexión', 'No se pudo contactar con el servidor.');
        }
    }

    // 1. Aprobar (con justificación si hay cancelados)
    const formAprobarExt = document.getElementById('formAprobarExt');
    if (formAprobarExt) {
        formAprobarExt.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            let hayCancelados = false;
            formAprobarExt.querySelectorAll('.ext-estado').forEach(sel => {
                if (sel.value === 'cancelado') hayCancelados = true;
            });

            if (hayCancelados) {
                ScaModal.confirm({
                    titulo: 'Justificar Rechazos',
                    mensaje: 'Hay personas marcadas como "Cancelar". Debe ingresar un motivo general para justificar este rechazo.',
                    tipo: 'warning',
                    requiereInput: { type: 'textarea', label: 'Motivo de rechazo general', placeholder: 'Explique por qué se cancelan...', required: true, rows: 3 },
                    textoConfirmar: 'Confirmar y Enviar',
                    onConfirm: async () => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'motivo_rechazo_general';
                        input.value = document.getElementById('scaInput')?.value || '';
                        formAprobarExt.appendChild(input);

                        ScaModal.loading('Procesando aprobación', 'Guardando cambios...');
                        await enviarFormAjax(formAprobarExt);
                    }
                });
            } else {
                ScaModal.loading('Procesando aprobación', 'Guardando cambios...');
                await enviarFormAjax(formAprobarExt);
            }
        });
    }

    // 2. Rechazar
    document.querySelectorAll('.sca-rechazar-ext').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            ScaModal.confirm({
                titulo: '¿Rechazar solicitud?',
                mensaje: 'Esta acción no se puede deshacer. La solicitud será rechazada por completo.',
                tipo: 'danger',
                requiereInput: { type: 'textarea', label: 'Motivo de rechazo', placeholder: 'Explique por qué se rechaza...', required: true, rows: 3 },
                textoConfirmar: 'Sí, rechazar',
                onConfirm: async () => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'motivo_rechazo';
                    input.value = document.getElementById('scaInput')?.value || '';
                    form.appendChild(input);

                    ScaModal.loading('Rechazando solicitud', 'Procesando...');
                    await enviarFormAjax(form);
                }
            });
        });
    });

    // 3. Cancelar externo individual
    document.querySelectorAll('.sca-cancel-externo').forEach(btn => {
        btn.addEventListener('click', function() {
            const detalleId = this.dataset.detalleId;
            const nombre = this.dataset.nombre || 'este externo';
            const action = this.dataset.action;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = action;
            
            const tokenInput = document.createElement('input');
            tokenInput.type = 'hidden';
            tokenInput.name = '_token';
            tokenInput.value = document.querySelector('meta[name="csrf-token"]').content;
            form.appendChild(tokenInput);

            const detInput = document.createElement('input');
            detInput.type = 'hidden';
            detInput.name = 'id_solicitud_externo_detalle';
            detInput.value = detalleId;
            form.appendChild(detInput);
            
            document.body.appendChild(form);

            ScaModal.confirm({
                titulo: '¿Cancelar externo?',
                mensaje: `Se cancelará el permiso de <strong>${nombre}</strong>. Su QR será anulado.`,
                tipo: 'danger',
                requiereInput: { type: 'textarea', label: 'Motivo de cancelación', placeholder: 'Explique el motivo...', required: true, rows: 3 },
                textoConfirmar: 'Sí, cancelar',
                onConfirm: async () => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'motivo_cancelacion';
                    input.value = document.getElementById('scaInput')?.value || '';
                    form.appendChild(input);

                    ScaModal.loading('Cancelando externo', 'Procesando...');
                    await enviarFormAjax(form);
                }
            });
        });
    });

    // 4. Cancelar solicitud completa
    document.querySelectorAll('.sca-cancelar-solicitud-ext').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            ScaModal.confirm({
                titulo: '¿Cancelar solicitud?',
                mensaje: 'La solicitud será cancelada. Los QRs de acceso serán invalidados.',
                tipo: 'danger',
                requiereInput: { type: 'textarea', label: 'Motivo de cancelación', placeholder: 'Explique el motivo...', required: true, rows: 3 },
                textoConfirmar: 'Sí, cancelar',
                onConfirm: async () => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'motivo_rechazo';
                    input.value = document.getElementById('scaInput')?.value || '';
                    form.appendChild(input);

                    ScaModal.loading('Cancelando solicitud', 'Procesando...');
                    await enviarFormAjax(form);
                }
            });
        });
    });

    // 5. Reenviar credenciales
    document.querySelectorAll('.sca-reenviar-credenciales').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            ScaModal.confirm({
                titulo: '¿Reenviar credenciales?',
                mensaje: 'Se enviarán los correos con los QRs de acceso al creador de la solicitud.',
                tipo: 'info',
                textoConfirmar: 'Sí, reenviar',
                onConfirm: async () => {
                    ScaModal.loading('Enviando credenciales', 'Procesando...');
                    await enviarFormAjax(form);
                }
            });
        });
    });

});
</script>
@endpush

@endsection