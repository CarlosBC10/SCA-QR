{{-- resources/views/solicitudes/show_ext.blade.php --}}
@extends('layouts.app')

@section('title', 'Solicitud #' . $solicitud->id_solicitud_ext)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Solicitud #{{ $solicitud->id_solicitud_ext }}</h1>
        <p class="text-muted mb-0">
            Creada: {{ $solicitud->created_at->format('d/m/Y H:i') }}
            @if($creador) · por <strong>{{ $creador->nombre_completo }}</strong> @endif
        </p>
    </div>
    <a href="{{ route('solicitudes.hub') }}" class="btn btn-outline-secondary">
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
                                $estadoClass = match($solicitud->estado) {
                                    'pendiente' => 'bg-warning text-dark',
                                    'aprobado'  => 'bg-success',
                                    'rechazado' => 'bg-danger',
                                    default     => 'bg-secondary',
                                };
                                $estadoLabel = match($solicitud->estado) {
                                    'pendiente' => 'Pendiente',
                                    'aprobado'  => 'Aprobado',
                                    'rechazado' => 'Rechazado',
                                    default     => $solicitud->estado,
                                };
                            @endphp
                            <span class="badge {{ $estadoClass }} px-3 py-2">{{ $estadoLabel }}</span>
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
                @if($solicitud->externos->isNotEmpty())
                <hr>
                <h6 class="fw-bold mb-3">
                    <i class="bi bi-people text-success"></i> Personas ({{ $solicitud->externos->count() }})
                </h6>
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
                                $qrClass = match($detalle->estado_qr) {
                                    'autorizado' => 'bg-success',
                                    'cancelado'  => 'bg-danger',
                                    default      => 'bg-secondary',
                                };
                                $puedeCancelarExt = (
                                    in_array($solicitud->estado, ['pendiente', 'aprobado', 'aprobado_parcial'])
                                    && $detalle->estado_qr !== 'cancelado'
                                    && (auth()->user()->isAdmin() || auth()->user()->isSuperadmin()
                                        || $solicitud->creador_empleado_id === (int) auth()->user()->username)
                                );
                            @endphp
                            <tr>
                                <td>{{ $ext?->nombre_completo ?? '—' }}</td>
                                <td>
                                    @if($ext?->curp)
                                        <span class="badge bg-success mb-1">CURP</span><br>
                                        <span class="font-monospace small">{{ $ext->curp }}</span>
                                    @elseif($ext?->pasaporte)
                                        <span class="badge bg-info text-dark mb-1">Pasaporte</span><br>
                                        <span class="font-monospace small">{{ $ext->pasaporte }}</span>
                                    @else
                                        <span class="text-muted">Sin identificación</span>
                                    @endif
                                </td>
                                <td>{{ $ext?->telefono ?? '—' }}</td>
                                <td><span class="badge {{ $qrClass }}">{{ ucfirst($detalle->estado_qr) }}</span></td>
                                <td>
                                    <div class="d-flex gap-2 align-items-center">
                                        {{-- Botones de QR (solo si está autorizado) --}}
                                        @if($detalle->codigo_qr && $detalle->estado_qr === 'autorizado')
                                            {{-- Contenedor oculto con el SVG del QR --}}
                                            <div id="credencial-{{ $detalle->id_solicitud_externo_detalle }}" style="display:none; background: white; padding: 60px;">
                                                {!! QrCode::size(300)->generate($detalle->codigo_qr) !!}
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-success" title="Copiar PNG"
                                                    onclick="copiarQrPng('credencial-{{ $detalle->id_solicitud_externo_detalle }}', '{{ addslashes($ext?->nombre_completo ?? 'qr') }}')">
                                                <i class="bi bi-clipboard"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" title="Descargar PNG"
                                                    onclick="descargarQrPng('credencial-{{ $detalle->id_solicitud_externo_detalle }}', '{{ addslashes($ext?->nombre_completo ?? 'qr') }}')">
                                                <i class="bi bi-download"></i>
                                            </button>
                                        @endif

                                        {{-- Botón de cancelar --}}
                                        @if($puedeCancelarExt)
                                            <form method="POST"
                                                  action="{{ route('solicitudes.cancel.externo.individual', $solicitud->id_solicitud_ext) }}"
                                                  onsubmit="return confirm('¿Cancelar a {{ $ext?->nombre_completo ?? 'este externo' }}? Su QR será anulado.')"
                                                  style="display:inline;">
                                                @csrf
                                                <input type="hidden" name="id_solicitud_externo_detalle" value="{{ $detalle->id_solicitud_externo_detalle }}">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancelar este externo">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
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
                {{-- Timeline (una sola aprobación) --}}
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

                {{-- Botones de acción si la puede aprobar --}}
                @php
                    $user = Auth::user();
                    $esSuperior = $user->isAdmin() || $user->isSuperadmin();

                    if ($esSuperior) {
                        $puedeAprobar = ($solicitud->estado === 'pendiente');
                    } else {
                        $puedeAprobar = ($solicitud->id_planta_destino === $user->planta_id && $solicitud->estado === 'pendiente' && $user->isAprobador());
                    }
                @endphp

                @if($puedeAprobar)
                    <hr>
                    <h6 class="fw-bold mb-2">Revisar Personas:</h6>
                    <div class="alert alert-info py-2 small">
                        <i class="bi bi-info-circle"></i> Si cancela alguno, no recibirá QR y no podrá entrar. La solicitud queda <strong>Aprobada Parcial</strong>.
                    </div>
                    <form method="POST" action="{{ route('solicitudes.aprobar.ext', $solicitud->id_solicitud_ext) }}">
                        @csrf
                        @foreach($solicitud->externos as $detalle)
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small>{{ $detalle->externo?->nombre_completo ?? '—' }}</small>
                            <select name="estados[{{ $detalle->id_solicitud_externo_detalle }}]" class="form-select form-select-sm w-auto">
                                <option value="autorizado">Autorizar</option>
                                <option value="cancelado">Cancelar</option>
                            </select>
                        </div>
                        @endforeach
                        <button type="submit" class="btn btn-success w-100 mt-3">
                            <i class="bi bi-check-lg"></i> Confirmar y Generar QRs
                        </button>
                    </form>
                    <button type="button" class="btn btn-outline-danger w-100 mt-2" data-bs-toggle="modal" data-bs-target="#modalRechazarExt{{ $solicitud->id_solicitud_ext }}">
                        <i class="bi bi-x-lg"></i> Rechazar Toda
                    </button>

                    {{-- Modal de rechazo --}}
                    <div class="modal fade" id="modalRechazarExt{{ $solicitud->id_solicitud_ext }}" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content sca-modal-content" style="border-radius: 16px;">
                                <div class="modal-header border-0 pb-0">
                                    <h5 class="modal-title fw-bold">Rechazar Solicitud #{{ $solicitud->id_solicitud_ext }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST" action="{{ route('solicitudes.rechazar.ext', $solicitud->id_solicitud_ext) }}">
                                    @csrf
                                    <div class="modal-body pt-2">
                                        <label class="form-label">Motivo de rechazo <span class="text-danger">*</span></label>
                                        <textarea name="motivo_rechazo" class="form-control" rows="3" required placeholder="Explique por qué se rechaza..."></textarea>
                                    </div>
                                    <div class="modal-footer border-0 pt-0">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-danger"><i class="bi bi-x-lg"></i> Confirmar Rechazo</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                {{-- V5: Mostrar botón de cancelar también cuando está aprobada --}}
                @elseif(in_array($solicitud->estado, ['pendiente', 'aprobado']) && $solicitud->creador_empleado_id === (int) $user->username)
                    <hr>
                    <form method="POST" action="{{ route('solicitudes.cancel.ext', $solicitud->id_solicitud_ext) }}" onsubmit="return confirm('¿Cancelar esta solicitud?')">
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
async function copiarQrPng(credencialId, nombre) {
    const credencial = document.getElementById(credencialId);
    if (!credencial) return;
    const svg = credencial.querySelector('svg');
    if (!svg) { alert('No se encontró el QR.'); return; }

    try {
        const pngBlob = await svgToPngBlob(svg, 240);
        if (!pngBlob) throw new Error('No se pudo generar el PNG');

        if (navigator.clipboard && window.ClipboardItem && ClipboardItem.supports('image/png')) {
            await navigator.clipboard.write([new ClipboardItem({ 'image/png': pngBlob })]);
            alert('QR copiado al portapapeles.');
        } else {
            descargarBlob(pngBlob, nombre + '.png');
            alert('Su navegador no soporta copiar imágenes. Se descargó el PNG.');
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
}

async function descargarQrPng(credencialId, nombre) {
    const credencial = document.getElementById(credencialId);
    if (!credencial) return;
    const svg = credencial.querySelector('svg');
    if (!svg) { alert('No se encontró el QR.'); return; }

    try {
        const pngBlob = await svgToPngBlob(svg, 240);
        if (!pngBlob) throw new Error('No se pudo generar el PNG');
        descargarBlob(pngBlob, nombre + '.png');
    } catch (e) {
        alert('Error: ' + e.message);
    }
}

function svgToPngBlob(svg, size) {
    return new Promise((resolve, reject) => {
        try {
            const svgClone = svg.cloneNode(true);
            svgClone.setAttribute('width', size);
            svgClone.setAttribute('height', size);
            svgClone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');

            const svgString = new XMLSerializer().serializeToString(svgClone);
            const svgBlob = new Blob([svgString], { type: 'image/svg+xml;charset=utf-8' });
            const url = URL.createObjectURL(svgBlob);

            const img = new Image();
            
            // FIX: Agregar margen blanco (Quiet Zone) para que la cámara lea mejor
            const margin = 40; // 40px de margen
            const canvasSize = size + (margin * 2);
            
            img.onload = function() {
                const canvas = document.createElement('canvas');
                canvas.width = canvasSize;
                canvas.height = canvasSize;
                const ctx = canvas.getContext('2d');
                
                // 1. Rellenar todo el canvas con fondo blanco
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, canvasSize, canvasSize);
                
                // 2. Dibujar el QR en el centro (con el margen aplicado)
                ctx.drawImage(img, margin, margin, size, size);
                
                URL.revokeObjectURL(url);
                canvas.toBlob(function(blob) {
                    if (blob) resolve(blob);
                    else reject(new Error('Canvas toBlob devolvió null'));
                }, 'image/png');
            };
            img.onerror = function() {
                URL.revokeObjectURL(url);
                reject(new Error('No se pudo cargar el SVG como imagen'));
            };
            img.src = url;
        } catch (e) {
            reject(e);
        }
    });
}

function descargarBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}
</script>
@endpush

@endsection