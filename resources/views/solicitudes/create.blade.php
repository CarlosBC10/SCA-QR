{{-- resources/views/solicitudes/create.blade.php --}}
@extends('layouts.app')

@section('title', 'Nueva Solicitud')

@section('content')
<div class="sca-page-head legacy-page-head">
    <div>
    <div class="sca-page-eyebrow"><i class="bi bi-plus-circle" aria-hidden="true"></i> Solicitudes</div>
    <h1 class="sca-page-title">Nueva solicitud</h1>
    <p class="text-muted mb-0">Complete los datos según el tipo de solicitud</p>
    </div>
</div>

@if ($errors->any())
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (window.ScaModal) {
            let errores = {{ \Illuminate\Support\Js::from($errors->all()) }};
            ScaModal.error('Corrija los siguientes errores', '<ul class="mb-0 text-start"><li>' + errores.join('</li><li>') + '</li></ul>');
        }
    });
</script>
@endif

<form method="POST" action="{{ route('solicitudes.store') }}">
    @csrf

    {{-- ===== CARD 1: TIPO DE SOLICITUD ===== --}}
    <div class="card border-0 shadow-sm mb-3 sca-card" style="border-radius:12px;">
        <div class="card-header bg-transparent py-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-file-earmark-text text-success"></i> Tipo de Solicitud</h5>
        </div>
        <div class="card-body">
            <div class="row g-2 mb-3">
                @php
                    $tiposLabels = [
                        'pase_salida'  => ['Pase de Salida', 'bi-box-arrow-right', 'Permiso para ausentarse'],
                        'interplantas' => ['Interplantas', 'bi-arrow-left-right', 'Ir a otra planta'],
                        // V10.3 · CAMBIO #31: nueva tarjeta de tipo — permiso
                        // para entrar fuera de turno (domingos, temprano, sin turno).
                        'permiso_entrada' => ['Permiso de Entrada', 'bi-door-open', 'Entrar fuera de turno'],
                        'visita'       => ['Visita', 'bi-person', 'Recibir visitante(s)'],
                        'contratista'  => ['Contratista', 'bi-person-badge', 'Recibir contratista(s)'],
                    ];
                @endphp
                @foreach($tiposLabels as $tipo => $info)
                    @if(in_array($tipo, $tiposPermitidos))
                    <div class="col-md-3 col-sm-6">
                        <label class="form-check-label w-100">
                            <input type="radio" name="tipo" value="{{ $tipo }}" class="form-check-input d-none tipo-radio" @if(old('tipo')==$tipo) checked @endif required>
                            <div class="card border-0 shadow-sm h-100 tipo-card">
                                <div class="card-body text-center">
                                    <i class="bi {{ $info[1] }} fs-2"></i>
                                    <div class="fw-bold mt-2">{{ $info[0] }}</div>
                                    <small class="text-muted">{{ $info[2] }}</small>
                                </div>
                            </div>
                        </label>
                    </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>

    {{-- ===== CARD 1.5: ¿PARA QUIÉN ES? (entre Tipo y Datos Generales) ===== --}}
    <div class="card border-0 shadow-sm mb-3 sca-card" id="card-para-quien" style="border-radius:12px; display:none;">
        <div class="card-header bg-transparent py-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-question-circle text-success"></i> ¿Para quién es esta solicitud?</h5>
            <small class="text-muted">Esta decisión determina cómo se comporta el formulario.</small>
        </div>
        <div class="card-body">
            <div class="row g-2 mb-3">
                <div class="col-md-4">
                    <label class="w-100">
                        <input type="radio" name="para_quien" value="mi" class="para-quien-radio" checked>
                        <div class="para-quien-card h-100 p-3 text-center">
                            <i class="bi bi-person fs-4 text-success"></i>
                            <div class="fw-bold small">Solo para mí</div>
                            <small class="text-muted">Permiso personal</small>
                        </div>
                    </label>
                </div>
                <div class="col-md-4">
                    <label class="w-100">
                        <input type="radio" name="para_quien" value="mi_y_otros" class="para-quien-radio">
                        <div class="para-quien-card h-100 p-3 text-center">
                            <i class="bi bi-people fs-4 text-success"></i>
                            <div class="fw-bold small">Para mí y otras personas</div>
                            <small class="text-muted">Yo + compañeros</small>
                        </div>
                    </label>
                </div>
                <div class="col-md-4">
                    <label class="w-100">
                        <input type="radio" name="para_quien" value="otros" class="para-quien-radio">
                        <div class="para-quien-card h-100 p-3 text-center">
                            <i class="bi bi-person-plus fs-4 text-success"></i>
                            <div class="fw-bold small">Solo para otras personas</div>
                            <small class="text-muted">No me incluyo</small>
                        </div>
                    </label>
                </div>
            </div>

            {{-- V10.3.7-fix: aviso cuando el creador no necesita el permiso
                 seleccionado (corporativo/administrativo según el tipo). --}}
            <div id="aviso-clasificacion" class="alert alert-warning py-2 small mb-2" style="display:none;">
                <i class="bi bi-shield-exclamation"></i> <span id="aviso-clasificacion-texto"></span>
            </div>

            {{-- Nota informativa dinámica --}}
            <div id="nota-para-quien" class="alert alert-info py-2 small mb-0">
                <i class="bi bi-info-circle"></i> <span id="nota-para-quien-texto"></span>
            </div>
        </div>
    </div>

    {{-- ===== CARD 2: DATOS GENERALES ===== --}}
    <div class="card border-0 shadow-sm mb-3 sca-card" style="border-radius:12px;">
        <div class="card-header bg-transparent py-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-card-checklist text-success"></i> Datos Generales</h5>
        </div>
        <div class="card-body">
            
            <div class="row g-3 mb-3">

                {{-- PLANTA ORIGEN: visible para pase_salida e interplantas --}}
                <div class="col-md-6" id="campo-planta-origen">
                    <label class="form-label">Planta Origen <span class="text-danger">*</span></label>
                    @php
                        // FIX A-6: La planta origen es editable solo si:
                        //   - Es Admin/Super Y el selector "para_quien" es "otros"
                        //   (cuando es "mi" o "mi_y_otros", se usa la planta del usuario)
                        $puedeElegirPlantaOrigen = $puedeElegirDestinoExterno;
                    @endphp
                    <div id="planta-origen-editable" style="display:none;">
                        <select name="planta_origen_id" id="planta_origen_id" class="form-select">
                            <option value="">Seleccionar...</option>
                            @foreach($plantas as $p)
                                <option value="{{ $p->id_planta }}" @if(old('planta_origen_id')==$p->id_planta) selected @endif>{{ $p->nombre }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Planta desde donde sale el empleado.</small>
                    </div>
                    <div id="planta-origen-fija" style="display:none;">
                        <input type="text" class="form-control bg-light" readonly value="{{ $plantaOrigenNombre }}">
                        <input type="hidden" name="planta_origen_id" id="planta_origen_id_hidden" value="{{ $plantaOrigenId }}">
                        <small class="text-muted">Su planta asignada (no editable).</small>
                    </div>
                </div>

                {{-- PLANTA DESTINO: visible para interplantas (todos) y visita/contratista (solo Admin/Super) --}}
                <div class="col-md-6" id="campo-planta-destino" style="display:none;">
                    <label class="form-label">Planta Destino <span class="text-danger">*</span></label>
                    <select name="planta_destino_id" id="planta_destino_id" class="form-select">
                        <option value="">Seleccionar...</option>
                        @foreach($plantas as $p)
                            <option value="{{ $p->id_planta }}" @if(old('planta_destino_id')==$p->id_planta) selected @endif>{{ $p->nombre }}</option>
                        @endforeach
                    </select>
                    <small class="text-muted" id="planta-destino-hint"></small>
                </div>

            </div>

            {{-- V10.3.4-fix: ELIMINADA la pregunta "¿Para cuándo es el permiso?" --}}
            {{-- (radios Hoy / Otra fecha). No aportaba decisión real: el --}}
            {{-- usuario captura fecha y hora directamente en los inputs de --}}
            {{-- abajo (inicio prellenado con la hora actual, editable para --}}
            {{-- cualquier fecha). Pase de Salida ya no la mostraba, y las --}}
            {{-- solicitudes externas (visita/contratista) nunca la vieron. --}}

            {{-- FIX 3d: Inputs de fecha con lógica especial para Pase de Salida --}}
            <div class="row g-3 mb-3">
                <div class="col-md-6" id="campo-fecha-inicio">
                    <label class="form-label">
                        Fecha de inicio <span class="text-danger">*</span>
                        <span class="text-muted small" id="fecha-inicio-tipo">(fecha y hora)</span>
                    </label>
                    <input type="datetime-local" name="fecha_inicio" id="fecha_inicio" class="form-control @error('fecha_inicio') is-invalid @enderror" required value="{{ old('fecha_inicio') }}">
                    @error('fecha_inicio')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror

                    {{-- FIX 3d: Checkbox para modificar hora de inicio (solo pase_salida, día futuro) --}}
                    <div id="check-hora-manual" class="form-check mt-2" style="display:none;">
                        <input class="form-check-input" type="checkbox" name="modificar_hora" id="modificar_hora" value="1">
                        <label class="form-check-label small" for="modificar_hora">
                            Modificar hora de inicio (si llegará tarde o saldrá a hora distinta)
                        </label>
                    </div>

                    {{-- FIX 3d: Input de hora manual (visible si el check está marcado) --}}
                    <div id="campo-hora-manual" class="mt-2" style="display:none;">
                        <label class="form-label small">Hora de inicio manual</label>
                        <input type="time" name="hora_inicio_manual" id="hora_inicio_manual" class="form-control">
                    </div>
                </div>
                <div class="col-md-6" id="campo-fecha-fin">
                    <label class="form-label">
                        Fecha y hora de fin <span class="text-danger">*</span>
                        <span class="text-muted small" id="fecha-fin-tipo"></span>
                    </label>
                    <input type="datetime-local" name="fecha_fin" id="fecha_fin" class="form-control @error('fecha_fin') is-invalid @enderror" required value="{{ old('fecha_fin') }}">
                    @error('fecha_fin')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            {{-- FIX 3d: Aviso dinámico para Pase de Salida --}}
            <div id="aviso-pase-salida" class="alert alert-info py-2 small mb-3" style="display:none;">
                <i class="bi bi-info-circle"></i> <span id="aviso-pase-salida-texto"></span>
            </div>

            {{-- V5: Empresa a nivel de solicitud (solo visita/contratista) --}}
            <div class="mb-3" id="campo-empresa" style="display:none;">
                <label class="form-label">Empresa <span class="text-danger" id="empresa-req" style="display:none;">*</span></label>
                <input type="text" name="empresa" id="empresa" class="form-control" value="{{ old('empresa') }}" maxlength="150">
                <small class="text-muted" id="empresa-hint"></small>
            </div>

            <div class="mb-0">
                <label class="form-label">Motivo <span class="text-danger">*</span></label>
                <textarea name="motivo" class="form-control" rows="3" required maxlength="1000" placeholder="Explique el motivo...">{{ old('motivo') }}</textarea>
            </div>
        </div>
    </div>

    {{-- ===== CARD 3: ANFITRIÓN (solo visita/contratista) ===== --}}
    <div class="card border-0 shadow-sm mb-3 sca-card" id="card-anfitrion" style="border-radius:12px; display:none;">
        <div class="card-header bg-transparent py-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-person-check text-success"></i> Anfitrión</h5>
            <small class="text-muted">Empleado que recibirá a los visitantes/contratistas</small>
        </div>
        <div class="card-body">
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="soy_anfitrion" id="soy_anfitrion" value="1" checked>
                <label class="form-check-label" for="soy_anfitrion">
                    Yo soy el anfitrión (recibiré a los visitantes)
                </label>
            </div>
            <div id="busqueda-anfitrion" style="display:none;">
                <label class="form-label">Buscar anfitrión por ID o nombre</label>
                <div class="input-group mb-2">
                    <input type="text" id="buscar-anfitrion-input" class="form-control" placeholder="ID o nombre del empleado...">
                    <button type="button" class="btn btn-outline-success" id="btn-buscar-anfitrion">
                        <i class="bi bi-search"></i> Buscar
                    </button>
                </div>
                <div id="resultados-anfitrion" class="mb-2" style="max-height: 250px; overflow-y: auto;"></div>
                <input type="hidden" name="anfitrion_empleado_id" id="anfitrion_empleado_id" value="">
                <div id="anfitrion-seleccionado" class="alert alert-success py-2 small" style="display:none;">
                    <i class="bi bi-person-check"></i> <span id="anfitrion-nombre"></span>
                </div>
            </div>
        </div>

        {{-- FIX A-5: Alerta informativa sobre restricción de planta para Aprobador/Empleado --}}
        @if(auth()->user()->isAprobador() || auth()->user()->isEmpleado())
            <div class="alert alert-info py-2 small mb-3 d-flex align-items-center gap-2" role="alert">
                <i class="bi bi-info-circle-fill text-info fs-5"></i>
                <span>
                    <strong>Restricción de planta:</strong>
                    Solo puede seleccionar anfitriones de su planta asignada
                    (<strong>{{ auth()->user()->planta_nombre }}</strong>).
                    @if(auth()->user()->isEmpleado())
                        Si necesita un anfitrión de otra planta, solicítelo a un Admin o Aprobador.
                    @endif
                </span>
            </div>
        @endif
    </div>

    {{-- ===== CARD 4: PERSONAS EXTERNAS (solo visita/contratista) ===== --}}
    <div class="card border-0 shadow-sm mb-3 sca-card" id="card-externos" style="border-radius:12px; display:none;">
        <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0 fw-bold"><i class="bi bi-people text-success"></i> Personas (Visitantes/Contratistas)</h5>
                <small class="text-muted">Agregue una o más personas. Si ingresa CURP, se precargarán sus datos.</small>
            </div>
            <button type="button" class="btn btn-success btn-sm" onclick="agregarExterno()">
                <i class="bi bi-plus-lg"></i> Agregar Persona
            </button>
        </div>
        <div class="card-body">
            <div id="externos-lista"></div>
            <div id="externos-vacio" class="text-center text-muted py-4">
                <i class="bi bi-people fs-1"></i>
                <p class="mt-2 mb-0">No hay personas agregadas. Haga clic en "Agregar Persona".</p>
            </div>
        </div>
    </div>

    {{-- ===== CARD 5: BÚSQUEDA DE EMPLEADOS GRUPALES (solo pase_salida/interplantas) ===== --}}
    <div class="card border-0 shadow-sm mb-3 sca-card" id="card-empleados" style="border-radius:12px; display:none;">
        <div class="card-header bg-transparent py-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-people text-success"></i> Agregar Compañeros</h5>
            <small class="text-muted">Busque y agregue los empleados que acompañan al solicitante.</small>
        </div>
        <div class="card-body">
            {{-- Checkbox incluirme oculto (lo controla el JS según para_quien) --}}
            <input type="hidden" name="incluirme" id="incluirme_hidden" value="0">

            <div id="busqueda-empleados">
                <div class="input-group mb-3">
                    <input type="text" id="buscar-emp" class="form-control" placeholder="Buscar por ID o nombre...">
                    <button type="button" class="btn btn-outline-success" id="btn-buscar-emp">
                        <i class="bi bi-search"></i> Buscar
                    </button>
                </div>
                <div id="resultados-busqueda" class="mb-3" style="max-height: 250px; overflow-y: auto;"></div>
                <div id="empleados-seleccionados" class="list-group"></div>
            </div>
        </div>
    </div>

    {{-- ===== BOTONES ===== --}}
    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-success">
            <i class="bi bi-check-circle"></i> Crear Solicitud
        </button>
        <a href="{{ route('solicitudes.hub') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<style>
.tipo-card { border: 2px solid transparent !important; border-radius: 12px !important; cursor: pointer; transition: all 0.2s ease; color: #6c757d; }
.tipo-card:hover { border-color: rgba(46,125,50,0.3) !important; color: #2e7d32; }
.tipo-radio:checked + .tipo-card { border-color: #2e7d32 !important; background-color: rgba(46,125,50,0.05) !important; color: #2e7d32; transform: translateY(-3px); box-shadow: 0 4px 12px rgba(46,125,50,0.15) !important; }
.tipo-radio:checked + .tipo-card i { color: #2e7d32 !important; }
.externo-fila { border: 1px solid var(--bs-border-color); border-radius: 10px; padding: 1rem; margin-bottom: 1rem; background: var(--bs-tertiary-bg); }
/* FIX A-6: Estilo para el selector "¿Para quién es?" (mismo patrón que tipo-card) */
.para-quien-radio { position: absolute; opacity: 0; pointer-events: none; }
.para-quien-card { border: 2px solid #dee2e6 !important; border-radius: 12px !important; cursor: pointer; transition: all 0.2s ease; color: #6c757d; }
.para-quien-card:hover { border-color: rgba(46,125,50,0.3) !important; color: #2e7d32; }
.para-quien-radio:checked + .para-quien-card { border-color: #2e7d32 !important; background-color: rgba(46,125,50,0.05) !important; color: #2e7d32; transform: translateY(-3px); box-shadow: 0 4px 12px rgba(46,125,50,0.15) !important; }
.para-quien-radio:checked + .para-quien-card i { color: #2e7d32 !important; }
</style>

@push('scripts')
<script>
let empleadosAgregados = [];
let externosCounter = 0;
const puedeElegirDestinoExterno = {{ $puedeElegirDestinoExterno ? 'true' : 'false' }};
const miEmpleadoId = {{ (int) auth()->user()->username }};

@php
    $esAdminOSuperVar = auth()->user()->isAdmin() || auth()->user()->isSuperadmin();
    $miPlantaIdVar = (int) auth()->user()->planta_id;
    $miPlantaNombreVar = auth()->user()->planta_nombre;
@endphp

@php
    $creadorEmp = \App\Models\Sitec\Empleado::with('turno')->find((int) auth()->user()->username);
    $creadorEntradaVar = $creadorEmp?->turno?->entrada;
    $creadorSalidaVar = $creadorEmp?->turno?->salida;
    // V10.3.7-fix: clasificación del creador (corporativo/administrativo/
    // operativo; null si no tiene registro o clasificación). Define si
    // puede incluirse a sí mismo como beneficiario del permiso.
    $creadorClasificacionVar = $creadorEmp?->tipoEmpleado();
@endphp
// FIX A-2: Turno del creador para validar solapamiento con otros empleados
const creadorTurnoEntrada = '{{ $creadorEntradaVar }}';
const creadorTurnoSalida = '{{ $creadorSalidaVar }}';
// V10.3.7-fix: 'corporativo' | 'administrativo' | 'operativo' | '' (sin clasificar)
const creadorClasificacion = '{{ $creadorClasificacionVar }}';

// ===== FIX A-6: Selector "¿Para quién es?" =====
const esAdminOSuperParaInt = {{ $esAdminOSuperVar ? 'true' : 'false' }};
const esAdminOSuper = esAdminOSuperParaInt; // FIX: alias para compatibilidad con el JS de búsqueda
const miPlantaId = {{ $miPlantaIdVar }};
const miPlantaNombre = '{{ addslashes($miPlantaNombreVar) }}';

function actualizarParaQuien() {
    const tipo = document.querySelector('.tipo-radio:checked')?.value;
    const paraQuien = document.querySelector('.para-quien-radio:checked')?.value;

    if (!['pase_salida', 'interplantas', 'permiso_entrada'].includes(tipo)) return; // V10.3 · CAMBIO #32

    const busquedaDiv = document.getElementById('busqueda-empleados');
    const notaTexto = document.getElementById('nota-para-quien-texto');
    const incluirmeHidden = document.getElementById('incluirme_hidden');
    const plantaOrigenEditable = document.getElementById('planta-origen-editable');
    const plantaOrigenFija = document.getElementById('planta-origen-fija');

    // Mostrar/ocultar búsqueda de empleados
    if (paraQuien === 'mi') {
        busquedaDiv.style.display = 'none';
        // Ocultar la CARD 5 completa cuando es "Solo para mí"
        const cardEmpleados = document.getElementById('card-empleados');
        if (cardEmpleados) cardEmpleados.style.display = 'none';
    } else {
        busquedaDiv.style.display = 'block';
        // Mostrar la CARD 5 si es "mi_y_otros" o "otros"
        const cardEmpleados = document.getElementById('card-empleados');
        if (cardEmpleados) cardEmpleados.style.display = 'block';
    }

    // Incluirme según selección
    if (paraQuien === 'mi' || paraQuien === 'mi_y_otros') {
        incluirmeHidden.value = '1';
    } else {
        incluirmeHidden.value = '0';
    }

    // Nota informativa + control de planta origen
    if (esAdminOSuperParaInt) {
        if (paraQuien === 'mi') {
            plantaOrigenEditable.style.display = 'none';
            plantaOrigenFija.style.display = 'block';
            notaTexto.innerHTML = `<strong>Su planta origen:</strong> ${miPlantaNombre}. Permiso personal.`;
        } else if (paraQuien === 'mi_y_otros') {
            plantaOrigenEditable.style.display = 'none';
            plantaOrigenFija.style.display = 'block';
            notaTexto.innerHTML = `<strong>Su planta origen:</strong> ${miPlantaNombre}. Solo puede agregar compañeros de su misma planta.`;
        } else { // "otros"
            plantaOrigenEditable.style.display = 'block';
            plantaOrigenFija.style.display = 'none';
            notaTexto.innerHTML = '<strong>Seleccione la Planta Origen</strong> en la sección superior. Solo podrá agregar empleados de esa planta. Si cambia de planta, los empleados agregados se borrarán.';
        }
    } else { // Aprobador/Empleado
        plantaOrigenEditable.style.display = 'none';
        plantaOrigenFija.style.display = 'block';
        if (paraQuien === 'mi') {
            notaTexto.innerHTML = `<strong>Su planta origen:</strong> ${miPlantaNombre}. Permiso personal.`;
        } else {
            notaTexto.innerHTML = `<strong>Su planta origen:</strong> ${miPlantaNombre}. Solo puede agregar compañeros de su misma planta.`;
        }
    }

    // ============================================================
    // V10.3.2-fix (restaurado de V10.3.1, hallazgo 3 del usuario):
    // había DOS inputs con name="planta_origen_id" (el select editable
    // Y el hidden de la planta fija) y ambos viajaban en el POST. PHP
    // se queda con el ÚLTIMO (el hidden), así que la planta que el
    // Admin elegía para "otros" se ignoraba → error "no pertenece a
    // su planta origen". Solución: deshabilitar el campo NO visible;
    // los inputs disabled NO se envían en el submit.
    // ============================================================
    const selectPlantaOrigen = document.getElementById('planta_origen_id');
    const hiddenPlantaOrigen = document.getElementById('planta_origen_id_hidden');
    const editableVisible = plantaOrigenEditable.style.display === 'block';
    if (selectPlantaOrigen) selectPlantaOrigen.disabled = !editableVisible;
    if (hiddenPlantaOrigen) hiddenPlantaOrigen.disabled = editableVisible;

    // Limpiar empleados seleccionados al cambiar
    empleadosAgregados = [];
    // FIX A-2: Resetear turno de referencia
    turnoReferencia = { entrada: null, salida: null };
    const lista = document.getElementById('empleados-seleccionados');
    if (lista) lista.innerHTML = '';
    const resultados = document.getElementById('resultados-busqueda');
    if (resultados) resultados.innerHTML = '';

    // Para interplantas: filtrar planta destino
    if (tipo === 'interplantas') {
        filtrarPlantaDestino();
    }
}

function filtrarPlantaDestino() {
    const plantaOrigenId = document.getElementById('planta_origen_id')?.value
        || document.getElementById('planta_origen_id_hidden')?.value;
    const selectDestino = document.getElementById('planta_destino_id');
    if (!selectDestino) return;

    Array.from(selectDestino.options).forEach(opt => {
        if (!opt.value) return;
        opt.disabled = (opt.value === plantaOrigenId);
        if (opt.disabled && selectDestino.value === opt.value) {
            selectDestino.value = '';
        }
    });
}

// Listeners
document.querySelectorAll('.para-quien-radio').forEach(radio => {
    radio.addEventListener('change', actualizarParaQuien);
});

// FIX 2e + 2h: Listener ÚNICO para cambio de planta origen
const plantaOrigenSelect = document.getElementById('planta_origen_id');
if (plantaOrigenSelect) {
    plantaOrigenSelect.addEventListener('change', function() {
        filtrarPlantaDestino();
        empleadosAgregados = [];
        const lista = document.getElementById('empleados-seleccionados');
        if (lista) lista.innerHTML = '';
        const resultados = document.getElementById('resultados-busqueda');
        if (resultados) resultados.innerHTML = '';
        const inputBuscar = document.getElementById('buscar-emp');
        if (inputBuscar) inputBuscar.value = '';
    });
}

// ===== FIX A-1 (V10.3.4-fix): Preparación de fechas por tipo =====
// V10.3.4: se ELIMINÓ la pregunta "¿Para cuándo es el permiso?" (radios
// hoy/otra). Esta función ya no depende de ese radio: siempre usa
// datetime-local, prellena el inicio con la hora actual (equivalente al
// antiguo "Hoy", editable para cualquier fecha = antiguo "Otra fecha")
// y muestra el aviso propio de cada tipo. Pase de Salida sigue siendo
// solo-para-hoy (max = hoy), igual que antes.
function actualizarParaCuando() {
    const tipo = document.querySelector('.tipo-radio:checked')?.value;
    if (!['pase_salida', 'interplantas', 'permiso_entrada'].includes(tipo)) return; // V10.3 · CAMBIO #32

    const inputInicio = document.getElementById('fecha_inicio');
    const checkHoraManual = document.getElementById('check-hora-manual');
    const campoHoraManual = document.getElementById('campo-hora-manual');

    // datetime-local para TODOS los tipos internos (V10.3.4: interplantas
    // antes usaba type=date en "Otra fecha" y perdía la hora).
    inputInicio.type = 'datetime-local';

    // Prellenar el inicio con la hora actual (si el input viene vacío;
    // se conserva lo que el usuario ya haya capturado).
    const ahora = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    if (!inputInicio.value) {
        inputInicio.value = `${ahora.getFullYear()}-${pad(ahora.getMonth()+1)}-${pad(ahora.getDate())}T${pad(ahora.getHours())}:${pad(ahora.getMinutes())}`;
    }

    // FIX 6 (se mantiene): limitar la fecha a HOY SOLO para Pase de Salida
    // (interplantas y permiso_entrada permiten programar a futuro).
    if (tipo === 'pase_salida') {
        inputInicio.max = `${ahora.getFullYear()}-${pad(ahora.getMonth()+1)}-${pad(ahora.getDate())}T23:59`;
    } else {
        inputInicio.max = '';
    }

    if (checkHoraManual) checkHoraManual.style.display = 'none';
    if (campoHoraManual) campoHoraManual.style.display = 'none';

    // Aviso propio de cada tipo (V10.3.4: unificado, sin ramas hoy/otra).
    const avisoPase = document.getElementById('aviso-pase-salida');
    const avisoTexto = document.getElementById('aviso-pase-salida-texto');
    if (avisoPase && avisoTexto) {
        if (tipo === 'permiso_entrada') {
            // V10.3.3-fix (bug 1): semántica del Permiso de Entrada: la
            // vigencia debe quedar FUERA del turno del beneficiario (una
            // vigencia que termina a la hora de SALIDA del turno es un
            // permiso que el escáner jamás consulta).
            avisoPase.style.display = 'block';
            avisoTexto.innerHTML = `<strong>Permiso de Entrada:</strong> el inicio se prellenó con la hora actual; capture la fecha y hora de fin.
                <br>• Solo aplica <strong>FUERA de su turno</strong>: llegada temprana (antes de su hora de entrada), entradas después de que termina su turno, o días no laborales.
                <br>• Para una llegada temprana, termine la vigencia a más tardar a la <strong>hora de entrada de su turno</strong> — a partir de esa hora el acceso ordinario ya aplica y el permiso no se consulta.
                <br>• Durante su vigencia puede utilizarse para varias entradas fuera del acceso ordinario.`;
        } else if (tipo === 'interplantas') {
            // V10.3.4-fix: antes, con "Otra fecha", interplantas mostraba por
            // error el texto de Pase de Salida. Ahora tiene su propio aviso.
            avisoPase.style.display = 'block';
            avisoTexto.innerHTML = `<strong>Interplantas:</strong> capture la fecha y hora de inicio y fin del traslado.
                <br>• Puede programarse para hoy o para cualquier fecha futura.
                <br>• Si cae en día no laboral, se permite: el guardia justificará en el escáner.`;
        }
        // pase_salida: el aviso lo controla configurarPaseSalida().
    }

    // FIX A-3: Validar al creador inmediatamente si es Pase de Salida
    validarCreadorPaseHoy();
}

// FIX A-3: Validar al creador si el pase es para HOY
// FIX 6: Soporte para turnos nocturnos (cruzan medianoche)
function validarCreadorPaseHoy() {
    const tipo = document.querySelector('.tipo-radio:checked')?.value;
    if (tipo !== 'pase_salida') return;

    // FIX 6: Si es "Solo para otras personas", el creador no se incluye.
    // No validar su turno ni deshabilitar el botón.
    const paraQuien = document.querySelector('.para-quien-radio:checked')?.value;
    if (paraQuien === 'otros') {
        const btnSubmit = document.querySelector('button[type="submit"]');
        if (btnSubmit) {
            btnSubmit.disabled = false;
            btnSubmit.classList.remove('disabled');
        }
        return;
    }

    const avisoPase = document.getElementById('aviso-pase-salida');
    const avisoTexto = document.getElementById('aviso-pase-salida-texto');
    if (!avisoPase) return;

    // Si no hay turno, avisar que debe ser manual
    if (!creadorTurnoEntrada || !creadorTurnoSalida) {
        avisoPase.style.display = 'block';
        avisoTexto.innerHTML = `<strong>Aviso:</strong> Usted no tiene turno asignado. Deberá capturar las fechas manualmente.`;
        // Habilitar inputs manuales
        const inputInicio = document.getElementById('fecha_inicio');
        const inputFin = document.getElementById('fecha_fin');
        inputInicio.type = 'datetime-local';
        inputFin.disabled = false;
        inputFin.required = true;
        return;
    }

    // Verificar si es día laboral para el creador
    const hoy = new Date();
    const diaSemana = hoy.getDay(); // 0=Dom, 6=Sab
    const esDiaLaboral = !(diaSemana === 0); // Domingo no laboral por defecto

    // Verificar si está dentro del turno
    const horaActual = hoy.getHours() * 60 + hoy.getMinutes();
    const [he, me] = creadorTurnoEntrada.split(':');
    const [hs, ms] = creadorTurnoSalida.split(':');
    const entradaMin = parseInt(he)*60 + parseInt(me);
    const salidaMin = parseInt(hs)*60 + parseInt(ms);

    // FIX 6: Detectar turno nocturno (entrada > salida, ej: 21:30 > 06:00)
    const esTurnoNocturno = entradaMin > salidaMin;
    let dentroDeTurno;
    
    if (esTurnoNocturno) {
        // Turno nocturno: está en turno si la hora actual es >= entrada (ej: 22:00)
        // O si es < salida (ej: 03:00am, que ya es del día siguiente)
        dentroDeTurno = (horaActual >= entradaMin || horaActual <= salidaMin);
    } else {
        // Turno normal
        dentroDeTurno = (horaActual >= entradaMin && horaActual <= salidaMin);
    }

    // Buscar el botón de submit del formulario
    const btnSubmit = document.querySelector('button[type="submit"]');

    if (esDiaLaboral && dentroDeTurno) {
        avisoPase.style.display = 'block';
        avisoTexto.innerHTML = `<strong>Pase de Salida:</strong> Las fechas se calculan según su turno.
            <br>• Puede ajustar la <strong>hora de inicio</strong> si necesita que el permiso inicie más tarde.
            <br>• La <strong>hora de fin</strong> se calcula automáticamente con la hora de salida de su turno.
            <br>• Solo se pueden agregar empleados <strong>con turno asignado</strong>.`;
        
        // Habilitar botón de guardar
        if (btnSubmit) {
            btnSubmit.disabled = false;
            btnSubmit.classList.remove('disabled');
        }
    } else {
        avisoPase.style.display = 'block';
        avisoTexto.innerHTML = `<div class="alert alert-danger mb-0"><i class="bi bi-x-octagon-fill"></i> <strong>No se puede crear el Pase de Salida:</strong> Está fuera de su turno o es día no laboral. Si actualmente se encuentra en planta, podrá salir justificando su salida con el guardia (no necesita crear un pase).</div>`;
        
        // Deshabilitar botón de guardar
        if (btnSubmit) {
            btnSubmit.disabled = true;
            btnSubmit.classList.add('disabled');
        }
    }
}

// V10.3.4-fix: listeners del radio "para_cuando" ELIMINADOS junto con la
// pregunta "¿Para cuándo es el permiso?" (ya no existen radios .para-cuando-radio).

// ===== MOSTRAR/OCULTAR CAMPOS SEGÚN EL TIPO =====
function actualizarCampos() {
    const tipo = document.querySelector('.tipo-radio:checked')?.value;
    if (!tipo) return;

    const divPlantaOrigen  = document.getElementById('campo-planta-origen');
    const divPlantaDestino = document.getElementById('campo-planta-destino');
    const cardAnfitrion    = document.getElementById('card-anfitrion');
    const cardExternos     = document.getElementById('card-externos');
    const cardEmpleados    = document.getElementById('card-empleados');
    const cardParaQuien    = document.getElementById('card-para-quien');
    const hintDestino      = document.getElementById('planta-destino-hint');

    // V10.3 · CAMBIO #32: permiso_entrada también es interno (muestra
    // planta origen, ¿para quién? y búsqueda de compañeros). La planta
    // destino NO se muestra (siempre = origen). (V10.3.4: "¿para cuándo?"
    // ya no existe como pregunta.)
    const esInterno = ['pase_salida', 'interplantas', 'permiso_entrada'].includes(tipo);
    const esExterno = ['visita', 'contratista'].includes(tipo);

    divPlantaOrigen.style.display = esInterno ? 'block' : 'none';

    // --- PLANTA DESTINO ---
    let mostrarDestino = false;
    const selectDestino = document.getElementById('planta_destino_id');
    if (tipo === 'interplantas') {
        mostrarDestino = true;
        hintDestino.textContent = 'Planta a la que va el empleado (debe ser distinta a la origen).';
        // FIX: Filtrar opciones para interplantas
        filtrarPlantaDestino();
    } else if (['visita', 'contratista'].includes(tipo)) {
        mostrarDestino = puedeElegirDestinoExterno;
        hintDestino.textContent = 'Planta a la que vienen los visitantes/contratistas.';
        // FIX: Limpiar cualquier restricción previa (habilitar todas las plantas)
        if (selectDestino) {
            Array.from(selectDestino.options).forEach(opt => {
                opt.disabled = false;
            });
        }
    }
    divPlantaDestino.style.display = mostrarDestino ? 'block' : 'none';

    cardAnfitrion.style.display = esExterno ? 'block' : 'none';
    cardExternos.style.display  = esExterno ? 'block' : 'none';
    cardParaQuien.style.display = esInterno ? 'block' : 'none';
    // --- FECHAS (V10.3.4-fix: la pregunta "¿Para cuándo?" fue eliminada;
    // para tipos internos solo preparamos los inputs de fecha) ---
    if (esInterno) {
        actualizarParaCuando();
    }
    cardEmpleados.style.display = esInterno ? 'block' : 'none';

    // FIX: Si NO es solicitud externa, limpiar las filas de externos que se hayan
    // agregado antes. Si no las limpiamos, sus inputs 'required' ocultos bloquean
    // el submit del formulario en otros tipos de solicitud.
    if (!esExterno) {
        document.querySelectorAll('.externo-fila').forEach(fila => fila.remove());
        externosCounter = 0;
        const vacio = document.getElementById('externos-vacio');
        if (vacio) vacio.style.display = 'block';
    } else if (externosCounter === 0) {
        // Es externo y no hay filas → agregar la primera
        agregarExterno();
    }

    // V5: Actualizar visibilidad de Empresa
    const campoEmpresa = document.getElementById('campo-empresa');
    if (campoEmpresa) {
        const esContratista = tipo === 'contratista';
        if (esExterno) {
            campoEmpresa.style.display = 'block';
            const reqMark = document.getElementById('empresa-req');
            const input = document.getElementById('empresa');
            if (esContratista) {
                reqMark.style.display = 'inline';
                input.setAttribute('required', 'required');
            } else {
                reqMark.style.display = 'none';
                input.removeAttribute('required');
            }
        } else {
            campoEmpresa.style.display = 'none';
        }
    }

    // FIX A-6: Actualizar visibilidad según "para_quien"
    actualizarParaQuien();

    // FIX 3d: Lógica especial para Pase de Salida (cambiar inputs, avisos, etc.)
    configurarPaseSalida(tipo);

    // V10.3.7-fix: al final para tener la última palabra sobre los radios
    // de "para_quien" (FIX 6 sin-turno y clasificación pueden combinarse).
    aplicarRestriccionClasificacion(tipo);
}

// ===== V10.3.7-fix: restricción por clasificación del creador =====
// Regla única confirmada con el usuario: QUIEN NO NECESITA EL PERMISO
// NO PUEDE SER BENEFICIARIO — y el creador es un beneficiario más.
//   - Pase de Salida: solo lo necesita un OPERATIVO (corporativos y
//     administrativos salen de su planta libremente durante su turno).
//   - Permiso de Entrada / Interplantas: no los necesita un CORPORATIVO
//     (entra a cualquier planta a cualquier hora), ni un empleado sin
//     clasificar (el escáner lo rechaza siempre con sin_clasificar).
function aplicarRestriccionClasificacion(tipo) {
    const aviso = document.getElementById('aviso-clasificacion');
    const avisoTexto = document.getElementById('aviso-clasificacion-texto');
    if (!aviso || !avisoTexto) return;

    const esInterno = ['pase_salida', 'interplantas', 'permiso_entrada'].includes(tipo);
    if (!esInterno) {
        // Externos: la tarjeta "¿para quién?" está oculta y los radios
        // ya fueron restaurados por el else de configurarPaseSalida.
        aviso.style.display = 'none';
        return;
    }

    let necesitaPermiso;
    if (tipo === 'pase_salida') {
        necesitaPermiso = (creadorClasificacion === 'operativo');
    } else {
        necesitaPermiso = (creadorClasificacion === 'administrativo' || creadorClasificacion === 'operativo');
    }

    const radioMi = document.querySelector('.para-quien-radio[value="mi"]');
    const radioMiYOtros = document.querySelector('.para-quien-radio[value="mi_y_otros"]');
    const radioOtros = document.querySelector('.para-quien-radio[value="otros"]');
    const cardMi = radioMi?.closest('.col-md-4');
    const cardMiYOtros = radioMiYOtros?.closest('.col-md-4');

    if (!necesitaPermiso) {
        // Forzar "Solo para otras personas" ANTES de deshabilitar: el evento
        // change dispara configurarPaseSalida (listener de para-quien), que
        // re-habilita los radios en su rama "creador con turno". Deshabilitar
        // después garantiza que este bloque SIEMPRE tenga la última palabra.
        if (radioOtros && !radioOtros.checked) {
            radioOtros.checked = true;
            radioOtros.dispatchEvent(new Event('change'));
        }

        if (radioMi) radioMi.disabled = true;
        if (radioMiYOtros) radioMiYOtros.disabled = true;
        if (cardMi) cardMi.style.opacity = '0.4';
        if (cardMiYOtros) cardMiYOtros.style.opacity = '0.4';

        let motivo;
        if (creadorClasificacion === 'corporativo') {
            motivo = 'usted es <strong>corporativo</strong>: puede entrar y salir de cualquier planta a cualquier hora, sin permiso';
        } else if (tipo === 'pase_salida') {
            motivo = 'usted es <strong>administrativo</strong>: puede salir de su planta libremente durante su turno, sin Pase de Salida';
        } else {
            motivo = 'su empleado no tiene clasificación en RR.HH.';
        }
        const nombreTipo = { pase_salida: 'Pase de Salida', interplantas: 'Interplantas', permiso_entrada: 'Permiso de Entrada' }[tipo] || 'este permiso';

        aviso.style.display = 'block';
        avisoTexto.innerHTML = `<strong>No necesita este permiso:</strong> ${motivo}.<br>• Solo puede tramitar el <strong>${nombreTipo}</strong> para <strong>otras personas</strong> que sí lo necesiten.`;
    } else {
        // El creador SÍ necesita el permiso: ocultar el aviso. No tocamos
        // los radios aquí: los restaura configurarPaseSalida (else) y FIX 6
        // (sin turno) sigue aplicando su propia restricción para pases.
        aviso.style.display = 'none';
    }
}

// ===== FIX 3d: Configurar formulario según sea Pase de Salida o no =====
function configurarPaseSalida(tipo) {
    const inputInicio = document.getElementById('fecha_inicio');
    const inputFin = document.getElementById('fecha_fin');
    const checkHoraManual = document.getElementById('check-hora-manual');
    const campoHoraManual = document.getElementById('campo-hora-manual');
    const avisoPase = document.getElementById('aviso-pase-salida');
    const avisoTexto = document.getElementById('aviso-pase-salida-texto');
    const labelTipoInicio = document.getElementById('fecha-inicio-tipo');
    const labelTipoFin = document.getElementById('fecha-fin-tipo');

    if (!inputInicio || !inputFin) return;

    if (tipo === 'pase_salida') {
        // V10.3.4-fix: se eliminó el bloque que ocultaba la pregunta
        // "¿Para cuándo?" y forzaba el radio "Hoy" — la pregunta ya no
        // existe en el formulario. El Pase de Salida sigue siendo solo
        // para HOY (max = hoy en el input de fecha).

        // FIX 6 (Opción 3): Si el creador no tiene turno, bloquear las opciones
        // donde él está incluido ("Solo para mí" y "Para mí y otras personas").
        // Solo se le permite "Solo para otras personas".
        const creadorSinTurno = !creadorTurnoEntrada || !creadorTurnoSalida;
        const radioMi = document.querySelector('.para-quien-radio[value="mi"]');
        const radioMiYOtros = document.querySelector('.para-quien-radio[value="mi_y_otros"]');
        const radioOtros = document.querySelector('.para-quien-radio[value="otros"]');
        const cardMi = radioMi?.closest('.col-md-4');
        const cardMiYOtros = radioMiYOtros?.closest('.col-md-4');

        if (creadorSinTurno) {
            // Desactivar "Solo para mí" y "Para mí y otras personas"
            if (radioMi) radioMi.disabled = true;
            if (radioMiYOtros) radioMiYOtros.disabled = true;
            if (cardMi) cardMi.style.opacity = '0.4';
            if (cardMiYOtros) cardMiYOtros.style.opacity = '0.4';

            // Forzar "Solo para otras personas" si estaba en otra opción
            if (!radioOtros?.checked) {
                radioOtros.checked = true;
                radioOtros.dispatchEvent(new Event('change'));
            }

            // Mostrar aviso especial
            avisoPase.style.display = 'block';
            avisoTexto.innerHTML = `<strong>⚠️ Sin turno asignado:</strong> Usted no tiene turno asignado en sitec.
                <br>• No puede crear un Pase de Salida para usted mismo.
                <br>• Solo puede crear para <strong>otras personas con turno</strong>.
                <br>• Contacte a RR.HH. para que le asignen un turno.`;
        } else {
            // Creador con turno: habilitar todas las opciones
            if (radioMi) radioMi.disabled = false;
            if (radioMiYOtros) radioMiYOtros.disabled = false;
            if (cardMi) cardMi.style.opacity = '1';
            if (cardMiYOtros) cardMiYOtros.style.opacity = '1';

            // Mostrar aviso normal
            avisoPase.style.display = 'block';
            avisoTexto.innerHTML = `<strong>Pase de Salida:</strong> Las fechas se calculan según su turno.
                <br>• Si es para <strong>HOY</strong>: inicia ahora y termina a la hora de fin de su turno.
                <br>• Si es para <strong>OTRO DÍA</strong>: inicia a la hora de inicio de su turno y termina a la hora de fin.
                <br>• Solo se pueden agregar empleados <strong>con turno asignado</strong>.`;
        }

        // FIX 6: Mostrar fecha_fin calculada (visible pero no editable)
        // Solo para "Para mí" y "Para mí y otros" (usa el turno del creador).
        // Para "Solo para otras personas", se habilita captura manual (Cambio 3).
        const paraQuienActual = document.querySelector('.para-quien-radio:checked')?.value;
        if (paraQuienActual === 'otros') {
            // "Para otros": habilitar captura manual (limitada a HOY)
            inputFin.disabled = false;
            inputFin.required = true;
            inputFin.type = 'datetime-local';
            inputFin.value = '';
            inputFin.placeholder = '';
            labelTipoFin.textContent = '(manual)';
            // V10.3.3-fix: en modo manual el valor ya no es del sistema.
            delete inputFin.dataset.autoPase;
            
            // FIX 6: Limitar fecha_fin a HOY también en "Para otros"
            const hoy = new Date();
            const pad = (n) => String(n).padStart(2, '0');
            const fechaHoyStr = `${hoy.getFullYear()}-${pad(hoy.getMonth()+1)}-${pad(hoy.getDate())}T23:59`;
            inputFin.max = fechaHoyStr;
            // Y también limitar fecha_inicio
            if (inputInicio) inputInicio.max = fechaHoyStr;
        } else {
            // "Para mí" o "Para mí y otros": calcular y mostrar el valor real
            inputFin.disabled = true;
            inputFin.required = false;
            labelTipoFin.textContent = '(automática)';
            // V10.3.3-fix: marcar que este valor lo AUTOCOMPLETÓ el sistema
            // (Pase de Salida = hora de salida del turno del creador). Si el
            // usuario cambia de tipo de solicitud, este valor invade el
            // nuevo formulario si no se limpia (bug 1 del usuario: en
            // permiso_entrada quedaba pegada la hr fin del turno del
            // creador, que para ese permiso es semánticamente incorrecto:
            // el permiso de entrada solo aplica FUERA del turno).
            inputFin.dataset.autoPase = '1';

            // FIX 6: Setear el valor real con la fecha de hoy + hora de salida del turno
            // FIX 6: Soporte para turnos nocturnos (si la salida es menor que la entrada,
            // la fecha_fin es MAÑANA, no hoy)
            if (creadorTurnoSalida) {
                const hoy = new Date();
                const pad = (n) => String(n).padStart(2, '0');
                
                // Detectar turno nocturno
                const [he, me] = creadorTurnoEntrada.split(':').map(Number);
                const [hs, ms] = creadorTurnoSalida.split(':').map(Number);
                const entradaMin = he * 60 + me;
                const salidaMin = hs * 60 + ms;
                const esTurnoNocturno = entradaMin > salidaMin;
                
                // Si es turno nocturno y la hora actual es después de medianoche (ej: 2am),
                // la fecha_fin es HOY (porque el turno termina hoy temprano).
                // Si es turno nocturno y la hora actual es antes de medianoche (ej: 10pm),
                // la fecha_fin es MAÑANA (porque el turno termina mañana temprano).
                let fechaFin;
                if (esTurnoNocturno) {
                    const horaActual = hoy.getHours() * 60 + hoy.getMinutes();
                    if (horaActual <= salidaMin) {
                        // Es después de medianoche, el turno termina hoy
                        fechaFin = new Date(hoy);
                    } else {
                        // Es antes de medianoche, el turno termina mañana
                        fechaFin = new Date(hoy);
                        fechaFin.setDate(fechaFin.getDate() + 1);
                    }
                } else {
                    // Turno normal, fecha_fin es hoy
                    fechaFin = new Date(hoy);
                }
                
                const fechaStr = `${fechaFin.getFullYear()}-${pad(fechaFin.getMonth()+1)}-${pad(fechaFin.getDate())}`;
                const horaStr = creadorTurnoSalida.substring(0,5); // "HH:MM"
                inputFin.value = `${fechaStr}T${horaStr}`;
                inputFin.placeholder = '';
            } else {
                inputFin.value = '';
                inputFin.placeholder = 'Se calcula automáticamente';
            }
        }

        // FIX 6: Modal informativo (solo la primera vez que se selecciona)
        if (!window.__paseSalidaInfoShown) {
            window.__paseSalidaInfoShown = true;
            ScaModal.info('📋 Pase de Salida', `
                <div class="text-start">
                    <p class="mb-2"><strong>¿Qué es?</strong></p>
                    <p class="mb-3">Permiso para que un empleado se ausente de la planta durante su turno (no se traslada a otra planta).</p>
                    <p class="mb-2"><strong>Reglas importantes:</strong></p>
                    <ul class="mb-3">
                        <li>Solo aplica a empleados <strong>con turno asignado</strong> en sitec.</li>
                        <li>La vigencia se calcula automáticamente desde el turno.</li>
                        <li>Requiere aprobación de la planta <strong>origen</strong>.</li>
                        <li>No puede agregarse a empleados <strong>sin turno</strong>.</li>
                    </ul>
                    <p class="mb-2"><strong>Para grupos (múltiples empleados):</strong></p>
                    <ul class="mb-3">
                        <li>Todos deben <strong>compartir un rango de horario común</strong> (turnos solapados).</li>
                        <li>Si un empleado tiene un turno que no se solapa con el del creador, no se puede agregar.</li>
                    </ul>
                </div>
            `);
        }

        // FIX A-1: Delegar a actualizarParaCuando() para que maneje el input
        actualizarParaCuando();

    } else {
        // FIX 6: Restaurar los radios de "para_quien" al cambiar a otro tipo
        const radioMi = document.querySelector('.para-quien-radio[value="mi"]');
        const radioMiYOtros = document.querySelector('.para-quien-radio[value="mi_y_otros"]');
        const cardMi = radioMi?.closest('.col-md-4');
        const cardMiYOtros = radioMiYOtros?.closest('.col-md-4');
        if (radioMi) radioMi.disabled = false;
        if (radioMiYOtros) radioMiYOtros.disabled = false;
        if (cardMi) cardMi.style.opacity = '1';
        if (cardMiYOtros) cardMiYOtros.style.opacity = '1';

        // V10.3.4-fix: se eliminaron los bloques que "restauraban" la
        // pregunta "¿Para cuándo?" y el radio "Otra fecha" — ya no existen
        // en el formulario.

        // Restaurar a datetime-local para otros tipos
        inputInicio.type = 'datetime-local';
        inputFin.type = 'datetime-local';
        inputFin.disabled = false;
        inputFin.required = true;
        inputFin.placeholder = '';
        inputInicio.max = '';   // FIX 6: Limpiar límite de fecha (era de Pase de Salida)
        inputFin.max = '';      // FIX 6: Limpiar límite de fecha
        // ============================================================
        // V10.3.3-fix (bug 1 del usuario, permiso_entrada): al salir de
        // Pase de Salida, el input fecha_fin conservaba pegado el valor
        // autocompletado "hoy + hora de SALIDA del turno del creador", y
        // ese valor viajaba tal cual en el POST al crear un Permiso de
        // Entrada (para "para mí" / "para mí y otros"; con "otros" no
        // pasaba porque ahí el campo era manual y ya venía vacío). El
        // Permiso de Entrada solo tiene validez FUERA del turno, así que
        // una vigencia que termina a la hora de salida del turno es un
        // permiso que el escáner jamás consulta. Solución: se limpia
        // ÚNICAMENTE el valor que autocompletó el sistema (bandera
        // data-auto-pase); lo capturado por el usuario (incluido el
        // old() tras un error de validación) se conserva intacto.
        // ============================================================
        if (inputFin.dataset.autoPase === '1') {
            inputFin.value = '';
            delete inputFin.dataset.autoPase;
        }
        labelTipoInicio.textContent = '(fecha y hora)';
        labelTipoFin.textContent = '';
        if (checkHoraManual) checkHoraManual.style.display = 'none';
        if (campoHoraManual) campoHoraManual.style.display = 'none';
        if (avisoPase) avisoPase.style.display = 'none';
    }
}

// FIX 3d: Validar si la fecha seleccionada es hoy o futura
function validarFechaPase() {
    const inputInicio = document.getElementById('fecha_inicio');
    const checkHoraManual = document.getElementById('check-hora-manual');
    const campoHoraManual = document.getElementById('campo-hora-manual');
    const checkModificar = document.getElementById('modificar_hora');

    if (!inputInicio || !inputInicio.value) {
        if (checkHoraManual) checkHoraManual.style.display = 'none';
        if (campoHoraManual) campoHoraManual.style.display = 'none';
        return;
    }

    const fechaSel = new Date(inputInicio.value + 'T00:00:00');
    const hoy = new Date();
    hoy.setHours(0,0,0,0);

    if (fechaSel > hoy) {
        // Día futuro → mostrar checkbox
        if (checkHoraManual) checkHoraManual.style.display = 'block';
    } else {
        // Hoy → ocultar checkbox y hora manual
        if (checkHoraManual) checkHoraManual.style.display = 'none';
        if (campoHoraManual) campoHoraManual.style.display = 'none';
        if (checkModificar) checkModificar.checked = false;
    }
}

// FIX 3d: Listener del checkbox "modificar hora"
document.getElementById('modificar_hora')?.addEventListener('change', function() {
    const campoHoraManual = document.getElementById('campo-hora-manual');
    if (campoHoraManual) {
        campoHoraManual.style.display = this.checked ? 'block' : 'none';
    }
});

// ===== ANFITRIÓN: CHECKBOX =====
document.getElementById('soy_anfitrion').addEventListener('change', function() {
    const busquedaDiv = document.getElementById('busqueda-anfitrion');
    if (this.checked) {
        busquedaDiv.style.display = 'none';
        document.getElementById('anfitrion_empleado_id').value = '';
    } else {
        busquedaDiv.style.display = 'block';
        document.getElementById('anfitrion_empleado_id').value = '';
        document.getElementById('anfitrion-seleccionado').style.display = 'none';
    }
});

// ===== ANFITRIÓN: BÚSQUEDA =====
document.getElementById('btn-buscar-anfitrion').addEventListener('click', async function() {
    const q = document.getElementById('buscar-anfitrion-input').value.trim();
    const div = document.getElementById('resultados-anfitrion');
    if (q.length < 2) {
        div.innerHTML = '<div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle"></i> Escriba al menos 2 caracteres.</div>';
        return;
    }

    let urlParams = `q=${encodeURIComponent(q)}`;
    if (esAdminOSuper) {
        const plantaDestino = document.getElementById('planta_destino_id')?.value;
        if (!plantaDestino) {
            div.innerHTML = `
                <div class="alert alert-warning py-2 small">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Seleccione primero la Planta Destino</strong> en la sección superior.
                </div>`;
            return;
        }
        urlParams += `&planta_id=${plantaDestino}`;
    }

    div.innerHTML = '<div class="alert alert-info py-2 small"><span class="spinner-border spinner-border-sm"></span> Buscando...</div>';

    try {
        const res = await fetch(`{{ route('solicitudes.buscar-empleado') }}?${urlParams}`);
        const data = await res.json();

        // FIX A-3: La respuesta es un objeto {empleados, restringido, motivo_restriccion}
        const empleados = Array.isArray(data) ? data : (data.empleados || []);
        const restringido = Array.isArray(data) ? false : (data.restringido || false);
        const motivoRestriccion = Array.isArray(data) ? null : (data.motivo_restriccion || null);

        if (empleados.length === 0) {
            if (restringido && motivoRestriccion) {
                div.innerHTML = `
                    <div class="alert alert-warning py-2 small">
                        <i class="bi bi-shield-lock"></i> <strong>Sin resultados.</strong><br>
                        ${motivoRestriccion}
                    </div>`;
            } else {
                // FIX V7: mensaje simple y correcto cuando no hay resultados.
                // Antes el JS mostraba el mensaje confuso del backend, ahora
                // solo dice que no encontró con ese criterio en la planta origen.
                const nombrePlanta = '';
                div.innerHTML = `
                    <div class="alert alert-secondary py-2 small">
                        <i class="bi bi-search"></i> No se encontraron empleados de esta planta con ese criterio.
                    </div>`;
            }
            return;
        }

        div.innerHTML = empleados.map(e => `
            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                <div>
                    <strong>${e.nombre}</strong>
                    <small class="text-muted d-block">ID: ${e.id} · ${e.puesto || 'Sin puesto'} · ${e.planta}</small>
                </div>
                <button type="button" class="btn btn-sm btn-outline-success" onclick="seleccionarAnfitrion(${e.id}, '${e.nombre.replace(/'/g, "\\'")}')">
                    <i class="bi bi-check"></i> Seleccionar
                </button>
            </div>
        `).join('');

        if (restringido && motivoRestriccion) {
            div.innerHTML += `
                <div class="alert alert-info py-1 px-2 small mt-2 mb-0">
                    <i class="bi bi-info-circle"></i> ${motivoRestriccion}
                </div>`;
        }
    } catch(err) {
        div.innerHTML = '<div class="alert alert-danger py-2 small"><i class="bi bi-x-circle"></i> Error al buscar. ' + err.message + '</div>';
    }
});

document.getElementById('buscar-anfitrion-input').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); document.getElementById('btn-buscar-anfitrion').click(); }
});

function seleccionarAnfitrion(id, nombre) {
    document.getElementById('anfitrion_empleado_id').value = id;
    document.getElementById('anfitrion-nombre').textContent = `${nombre} (ID: ${id})`;
    document.getElementById('anfitrion-seleccionado').style.display = 'block';
    document.getElementById('resultados-anfitrion').innerHTML = '';
    document.getElementById('buscar-anfitrion-input').value = '';
}

// ===== EXTERNOS: AGREGAR FILA =====
// CAMBIO V7: un solo select con 4 tipos de identificación + un solo input.
// Antes había dos inputs (curp/pasaporte) y un radio button para elegir.
function agregarExterno() {
    externosCounter++;
    const idx = externosCounter;
    const lista = document.getElementById('externos-lista');
    const vacio = document.getElementById('externos-vacio');
    vacio.style.display = 'none';

    const fila = document.createElement('div');
    fila.className = 'externo-fila';
    fila.id = `externo-${idx}`;
    fila.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong class="small"><i class="bi bi-person"></i> Persona #${idx}</strong>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="quitarExterno(${idx})">
                <i class="bi bi-x"></i> Quitar
            </button>
        </div>
        <div class="row g-2">
            <div class="col-md-4">
                <label class="form-label small">Tipo de identificación <span class="text-danger">*</span></label>
                <select name="externos[${idx}][tipo_identificacion]" class="form-select form-select-sm externo-tipo-id" onchange="cambiarTipoId(${idx})">
                    <option value="">Seleccione...</option>
                    <option value="curp">CURP</option>
                    <option value="credencial_votar">Credencial para votar (INE/IFE)</option>
                    <option value="cedula_profesional">Cédula profesional</option>
                    <option value="pasaporte">Pasaporte (extranjero)</option>
                </select>
            </div>
            <div class="col-md-4" id="campo-identificacion-${idx}">
                <label class="form-label small">Número de identificación <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="text" name="externos[${idx}][identificacion]" class="form-control form-control-sm externo-identificacion" maxlength="50" placeholder="Seleccione primero el tipo" oninput="normalizarIdentificacion(${idx})">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="buscarExternoPorIdentificacion(${idx})" title="Buscar en el sistema">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
                <small class="text-muted" id="hint-identificacion-${idx}">Presione la lupa para buscar.</small>
            </div>
            <div class="col-md-8">
                <label class="form-label small">Nombre completo <span class="text-danger">*</span></label>
                <input type="text" name="externos[${idx}][nombre]" class="form-control form-control-sm externo-nombre" required
                       placeholder="Nombre Apellidos">
            </div>
            <div class="col-md-6">
                <label class="form-label small">Teléfono</label>
                <input type="tel" name="externos[${idx}][telefono]" class="form-control externo-telefono" maxlength="10" pattern="\\d{10}" placeholder="1234567890" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                <small class="text-muted">10 digitos.</small>
            </div>
        </div>
        <div class="externo-precarga mt-2" style="display:none;"></div>
    `;
    lista.appendChild(fila);
}

// CAMBIO V7: en lugar de mostrar/ocultar campos, ajusta el placeholder y
// maxlength del input según el tipo elegido, y limpia el valor si cambia.
function cambiarTipoId(idx) {
    const fila = document.getElementById(`externo-${idx}`);
    const selectTipo = fila.querySelector('.externo-tipo-id');
    const inputId = fila.querySelector('.externo-identificacion');
    const hint = document.getElementById(`hint-identificacion-${idx}`);

    const tipoId = selectTipo.value;
    if (!tipoId) {
        inputId.placeholder = 'Seleccione primero el tipo';
        inputId.maxLength = 50;
        hint.textContent = 'Presione la lupa para buscar.';
        return;
    }

    // Configuración por tipo
    const config = {
        'curp': {
            placeholder: '18 caracteres (ej. BMLC850915HMCRGN09)',
            maxLength: 18,
            hint: 'La CURP se convierte a MAYÚSCULAS automáticamente.'
        },
        'credencial_votar': {
            placeholder: '13 dígitos (ej. 1234567890123)',
            maxLength: 13,
            hint: 'Clave de elector al reverso de la INE.'
        },
        'cedula_profesional': {
            placeholder: '7-12 dígitos (ej. 12345678)',
            maxLength: 20,
            hint: 'Número de cédula profesional.'
        },
        'pasaporte': {
            placeholder: 'Número de pasaporte (ej. G12345678)',
            maxLength: 50,
            hint: 'Pasaporte tal cual aparece en el documento.'
        }
    };

    const c = config[tipoId] || { placeholder: '', maxLength: 50, hint: '' };
    inputId.placeholder = c.placeholder;
    inputId.maxLength = c.maxLength;
    hint.textContent = c.hint;

    // Limpiar valor al cambiar tipo (porque el formato cambia)
    if (inputId.value.trim() !== '') {
        inputId.value = '';
    }
}

// Normaliza el valor del input según el tipo (CURP siempre mayúsculas)
function normalizarIdentificacion(idx) {
    const fila = document.getElementById(`externo-${idx}`);
    const selectTipo = fila.querySelector('.externo-tipo-id');
    const inputId = fila.querySelector('.externo-identificacion');

    if (selectTipo.value === 'curp') {
        inputId.value = inputId.value.toUpperCase();
    }
}

function quitarExterno(idx) {
    document.getElementById(`externo-${idx}`).remove();
    if (document.querySelectorAll('.externo-fila').length === 0) {
        document.getElementById('externos-vacio').style.display = 'block';
    }
}

// ===== EXTERNOS: BUSCAR POR IDENTIFICACIÓN =====
// CAMBIO V7: unifica las viejas buscarExternoPorCurp y buscarExternoPorPasaporte.
async function buscarExternoPorIdentificacion(idx) {
    const fila = document.getElementById(`externo-${idx}`);
    const selectTipo = fila.querySelector('.externo-tipo-id');
    const inputId = fila.querySelector('.externo-identificacion');
    const precargaDiv = fila.querySelector('.externo-precarga');

    const tipoId = selectTipo.value.trim();
    const valor = inputId.value.trim();

    // Validaciones previas
    if (!tipoId) {
        precargaDiv.style.display = 'block';
        precargaDiv.className = 'externo-precarga mt-2 alert alert-warning py-1 small';
        precargaDiv.textContent = 'Seleccione primero el tipo de identificación.';
        return;
    }

    if (!valor) {
        precargaDiv.style.display = 'block';
        precargaDiv.className = 'externo-precarga mt-2 alert alert-warning py-1 small';
        precargaDiv.textContent = 'Capture el número de identificación.';
        return;
    }

    // Validación específica por tipo
    if (tipoId === 'curp' && valor.length !== 18) {
        precargaDiv.style.display = 'block';
        precargaDiv.className = 'externo-precarga mt-2 alert alert-warning py-1 small';
        precargaDiv.textContent = 'La CURP debe tener exactamente 18 caracteres.';
        return;
    }

    // Verificar duplicados en otras filas del mismo formulario
    const filas = document.querySelectorAll('.externo-fila');
    for (let i = 0; i < filas.length; i++) {
        if (filas[i].id === `externo-${idx}`) continue;
        const otroTipo = filas[i].querySelector('.externo-tipo-id').value.trim();
        const otroValor = filas[i].querySelector('.externo-identificacion').value.trim();
        if (otroTipo === tipoId && otroValor.toLowerCase() === valor.toLowerCase()) {
            precargaDiv.style.display = 'block';
            precargaDiv.className = 'externo-precarga mt-2 alert alert-warning py-1 small';
            precargaDiv.textContent = 'Esta identificación ya fue agregada en otra fila. No la repita.';
            return;
        }
    }

    try {
        const url = `{{ route('solicitudes.buscar-externo-identificacion') }}?tipo_identificacion=${encodeURIComponent(tipoId)}&identificacion=${encodeURIComponent(valor)}`;
        const res = await fetch(url);
        const data = await res.json();

        if (data.encontrado === true) {
            fila.querySelector('.externo-nombre').value = data.nombre || '';
            fila.querySelector('.externo-telefono').value = data.telefono || '';

            precargaDiv.style.display = 'block';
            precargaDiv.className = 'externo-precarga mt-2 alert alert-success py-1 small';
            precargaDiv.innerHTML = '<i class="bi bi-check-check"></i> Persona encontrada en el sistema. Datos precargados. Se vinculará a esta solicitud.';
        } else {
            precargaDiv.style.display = 'block';
            precargaDiv.className = 'externo-precarga mt-2 alert alert-info py-1 small';
            precargaDiv.textContent = 'Identificación no registrada. Se creará un nuevo registro al guardar.';
        }
    } catch(err) {
        precargaDiv.style.display = 'block';
        precargaDiv.className = 'externo-precarga mt-2 alert alert-danger py-1 small';
        precargaDiv.textContent = 'Error al buscar.';
    }
}

// ===== EMPLEADOS: BÚSQUEDA (para interplantas/pase_salida grupal) =====
document.getElementById('btn-buscar-emp').addEventListener('click', async function() {
    const q = document.getElementById('buscar-emp').value.trim();
    const div = document.getElementById('resultados-busqueda');

    if (q.length < 2) {
        div.innerHTML = '<div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle"></i> Escriba al menos 2 caracteres.</div>';
        return;
    }

    // FIX 2h: Buscar la planta origen en AMBOS lugares (select editable o hidden fija)
    // V10.3.2-fix (restaurado de V10.3.1): preferir el select SOLO si está
    // habilitado/visible; si quedó deshabilitado, su valor viejo no aplica.
    const selectPlanta = document.getElementById('planta_origen_id');
    const hiddenPlanta = document.getElementById('planta_origen_id_hidden');
    const plantaOrigen = (selectPlanta && !selectPlanta.disabled && selectPlanta.value) ||
                         (hiddenPlanta && hiddenPlanta.value);

    if (!plantaOrigen) {
        div.innerHTML = `
            <div class="alert alert-warning py-2 small">
                <i class="bi bi-exclamation-triangle"></i>
                <strong>No se pudo determinar la planta origen.</strong>
                Seleccione una opción en "¿Para quién es?" o, si es "Solo para otras personas", seleccione la Planta Origen.
            </div>`;
        return;
    }

    // FIX 6: enviar el tipo para que el backend filtre empleados sin turno
    // cuando sea pase_salida.
    const tipoActualBusqueda = document.querySelector('.tipo-radio:checked')?.value;
    const urlParams = `q=${encodeURIComponent(q)}&planta_id=${plantaOrigen}` +
                      (tipoActualBusqueda ? `&tipo=${tipoActualBusqueda}` : '');
    div.innerHTML = '<div class="alert alert-info py-2 small"><span class="spinner-border spinner-border-sm"></span> Buscando...</div>';

    try {
        const res = await fetch(`{{ route('solicitudes.buscar-empleado') }}?${urlParams}`);
        const data = await res.json();

        const empleados = Array.isArray(data) ? data : (data.empleados || []);
        const restringido = Array.isArray(data) ? false : (data.restringido || false);
        const motivoRestriccion = Array.isArray(data) ? null : (data.motivo_restriccion || null);

        if (empleados.length === 0) {
            if (restringido && motivoRestriccion) {
                div.innerHTML = `
                    <div class="alert alert-warning py-2 small">
                        <i class="bi bi-shield-lock"></i> <strong>Sin resultados.</strong><br>
                        ${motivoRestriccion}
                    </div>`;
            } else {
                div.innerHTML = '<div class="alert alert-secondary py-2 small">No se encontraron empleados con ese criterio.</div>';
            }
            return;
        }

            div.innerHTML = empleados.map(e => `
            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                <div>
                    <strong>${e.nombre}</strong>
                    <small class="text-muted d-block">ID: ${e.id} · ${e.puesto || 'Sin puesto'} · ${e.planta}${e.turno_entrada ? ' · Turno: ' + e.turno_entrada.substring(0,5) + '-' + e.turno_salida.substring(0,5) : ' · Sin turno'}</small>
                </div>
                <button type="button" class="btn btn-sm btn-outline-success" onclick='agregarEmpleado(${e.id}, "${e.nombre.replace(/"/g, '&quot;')}", "${e.turno_entrada || ''}", "${e.turno_salida || ''}", "${e.tipo_emp || ''}", ${e.esta_en_planta ? 'true' : 'false'})'>
                    <i class="bi bi-plus"></i> Agregar
                </button>
            </div>
        `).join('');

        if (restringido && motivoRestriccion) {
            div.innerHTML += `
                <div class="alert alert-info py-1 px-2 small mt-2 mb-0">
                    <i class="bi bi-info-circle"></i> ${motivoRestriccion}
                </div>`;
        }
    } catch(err) {
        div.innerHTML = '<div class="alert alert-danger py-2 small"><i class="bi bi-x-circle"></i> Error al buscar. ' + err.message + '</div>';
    }
});

document.getElementById('buscar-emp').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); document.getElementById('btn-buscar-emp').click(); }
});

// FIX A-2: Variable para guardar el turno de referencia (del creador o del primer empleado)
let turnoReferencia = { entrada: null, salida: null };

async function agregarEmpleado(id, nombre, turnoEntrada, turnoSalida, tipoEmp, estaEnPlanta) {
    if (empleadosAgregados.includes(id)) { ScaModal.warning('Empleado repetido', 'Esa empleado ya fue agregado.'); return; }
    if (parseInt(id) === miEmpleadoId) {
        ScaModal.info('Aviso', 'No puede agregarse a sí mismo. El sistema lo incluye automáticamente.');
        return;
    }

    const tipo = document.querySelector('.tipo-radio:checked')?.value;
    const paraQuien = document.querySelector('.para-quien-radio:checked')?.value;
    // V10.3.4-fix: paraCuando eliminado (la pregunta "¿Para cuándo?" ya no existe).

    // ============================================================
    // V10.3.7-fix: quien no necesita el permiso no puede ser
    // beneficiario (misma regla que el creador; el servidor la
    // valida de forma autoritativa en storeSolicitudInt).
    // ============================================================
    if (['pase_salida', 'interplantas', 'permiso_entrada'].includes(tipo)) {
        if (!tipoEmp) {
            ScaModal.info('No necesita el permiso', `${nombre} no tiene clasificación asignada en RR.HH. El escaner lo rechaza siempre (sin clasificar), así que este permiso no tendría efecto. Acuda a Recursos Humanos.`);
            return;
        }
        if (tipo === 'pase_salida' && tipoEmp !== 'operativo') {
            ScaModal.info('No necesita el permiso', `${nombre} es <strong>${tipoEmp}</strong> y no necesita un Pase de Salida: los ${tipoEmp}s pueden salir de su planta libremente durante su turno. Solo los operativos requieren pase para salir.`);
            return;
        }
        if (tipo !== 'pase_salida' && tipoEmp === 'corporativo') {
            const nombrePermiso = tipo === 'interplantas' ? 'un permiso Interplantas' : 'un Permiso de Entrada';
            ScaModal.info('No necesita el permiso', `${nombre} es <strong>corporativo</strong> y no necesita ${nombrePermiso}: puede entrar a cualquier planta a cualquier hora.`);
            return;
        }
    }

    // 1. Determinar turno de referencia
    if (tipo === 'pase_salida') {
        if (paraQuien === 'mi' || paraQuien === 'mi_y_otros') {
            // "Para mí" o "Para mí y otros": usar el turno del creador
            turnoReferencia.entrada = creadorTurnoEntrada;
            turnoReferencia.salida = creadorTurnoSalida;
        } else {
            // "Solo para otras personas": usar las horas manuales capturadas
            const inputInicioManual = document.getElementById('fecha_inicio');
            const inputFinManual = document.getElementById('fecha_fin');
            if (inputInicioManual && inputFinManual && inputInicioManual.value && inputFinManual.value) {
                // Extraer solo la hora (HH:MM) de los datetime-local
                const inicioHoras = inputInicioManual.value.split('T')[1] || '';
                const finHoras = inputFinManual.value.split('T')[1] || '';
                if (inicioHoras && finHoras) {
                    turnoReferencia.entrada = inicioHoras;
                    turnoReferencia.salida = finHoras;
                }
            }
            // Si no hay horas manuales, no se puede validar (el backend rechazará)
        }
    }

    // 2. Validaciones para PASE DE SALIDA
    if (tipo === 'pase_salida') {
        // ============================================================
        // CAMBIO V7 (fix N2): Si es para HOY y está en planta, verificar
        // si está dentro o fuera de su turno.
        // ============================================================
        // - En planta + ANTES de su hora de salida → PERMITIR (caso normal
        //   del Pase de Salida: salir temprano con permiso).
        // - En planta + DESPUÉS de su hora de salida + tolerancia →
        //   BLOQUEAR (no necesita pase; el guardia justifica la salida
        //   tardía en el kiosko al escanear su salida).
        // ============================================================
        // ============================================================
        // FIX 6: Validar que el empleado esté dentro de su turno
        // cuando el Pase de Salida es para HOY.
        // No tiene sentido un Pase de Salida para alguien que aún no
        // entra a trabajar, o que ya salió.
        // FIX 6: Soporte para turnos nocturnos (cruzan medianoche).
        // ============================================================
        // V10.3.4-fix: sin la pregunta "¿Para cuándo?", el Pase de Salida es
        // SIEMPRE para hoy (el input de fecha está limitado a hoy), así que
        // esta validación de turno ya no depende de ningún radio.
        if (turnoEntrada && turnoSalida) {
            const ahora = new Date();
            const tiempoActualMin = ahora.getHours() * 60 + ahora.getMinutes();
            const [hE, mE] = turnoEntrada.split(':').map(Number);
            const [hS, mS] = turnoSalida.split(':').map(Number);
            const entradaMin = hE * 60 + mE;
            const salidaMin = hS * 60 + mS;
            const toleranciaAntes = 30;  // puede llegar hasta 30 min antes
            const toleranciaDespues = 30; // puede salir hasta 30 min después
            const entradaConTolerancia = entradaMin - toleranciaAntes;
            const salidaConTolerancia = salidaMin + toleranciaDespues;

            // Detectar turno nocturno (entrada > salida, ej: 21:30 > 06:00)
            const esTurnoNocturno = entradaMin > salidaMin;
            let dentroDeTurno = false;

            if (esTurnoNocturno) {
                // Turno nocturno: está en turno si la hora actual es >= entrada (ej: 22:00)
                // O si es <= salida (ej: 03:00am, que ya es del día siguiente)
                dentroDeTurno = (tiempoActualMin >= entradaConTolerancia || tiempoActualMin <= salidaConTolerancia);
            } else {
                // Turno normal
                dentroDeTurno = (tiempoActualMin >= entradaConTolerancia && tiempoActualMin <= salidaConTolerancia);
            }

            if (!dentroDeTurno) {
                // Verificar si ya salió (solo aplica si NO es turno nocturno, o si es nocturno pero estamos en la parte de la tarde)
                let yaSalio = false;
                if (esTurnoNocturno) {
                    // Si es nocturno y estamos entre la salida y la entrada (ej: 10am a 9pm), ya salió
                    if (tiempoActualMin > salidaConTolerancia && tiempoActualMin < entradaConTolerancia) {
                        yaSalio = true;
                    }
                } else {
                    if (tiempoActualMin > salidaConTolerancia) {
                        yaSalio = true;
                    }
                }

                if (yaSalio) {
                    ScaModal.info('Aviso de turno', `${nombre} ya pasó su hora de salida (${turnoSalida.substring(0,5)}). No necesita un Pase de Salida: el guardia puede justificar su salida tardía directamente en el kiosko al escanear su salida.`);
                } else {
                    ScaModal.error('Fuera de turno', `${nombre} no puede agregarse porque su turno inicia a las ${turnoEntrada.substring(0,5)} y aún no es hora. Un Pase de Salida solo aplica para empleados que están dentro de su turno.`);
                }
                return;
            }
        }

        // 2.2 Validar solapamiento de turnos
        if (turnoReferencia.entrada && turnoReferencia.salida && turnoEntrada && turnoSalida) {
            const solapado = verificarSolapamiento(
                turnoReferencia.entrada, turnoReferencia.salida,
                turnoEntrada, turnoSalida
            );
            if (!solapado) {
                ScaModal.error('Turno incompatible', `No se puede agregar a ${nombre} porque su turno (${turnoEntrada.substring(0,5)}-${turnoSalida.substring(0,5)}) no se solapa con el rango del permiso. Todos deben compartir un rango de horario común.`);
                return;
            }
        } else if (!turnoEntrada || !turnoSalida) {
            // FIX 6: bloquear directamente (la búsqueda ya filtra sin turno,
            // pero esto es defensa por si llega uno por API directa o bug).
            ScaModal.error('Sin turno asignado', `${nombre} no tiene turno asignado. No se puede agregar a un Pase de Salida porque las fechas se calculan desde el turno.`);
            return;
        }

        // 2.3 (V10.3.4-fix): validación de día laboral en "otra fecha"
        // ELIMINADA para Pase de Salida — nunca aplicaba (la pregunta
        // "¿Para cuándo?" estaba oculta y forzada a HOY, y el input sigue
        // limitado a hoy). El día laboral lo valida el backend con su
        // excepción "HOY + está en planta".
    } 
    // 3. Validaciones para INTERPLANTAS (Flexible)
    else if (tipo === 'interplantas') {
        if (!turnoEntrada || !turnoSalida) {
            if (!await ScaModal.confirm({ titulo: 'Sin turno asignado', mensaje: `${nombre} no tiene turno asignado. ¿Desea agregarlo de todas formas?`, tipo: 'warning' })) {
                return;
            }
        }
        // V10.3.4-fix: advertir si la FECHA CAPTURADA cae en día no laboral
        // (antes solo se avisaba con el radio "Otra fecha"; ahora se evalúa
        // siempre que haya fecha en el input — la validación es la misma).
        // Nota: el valor ahora siempre es datetime-local ("YYYY-MM-DDTHH:MM"),
        // así que se toma solo la parte de la fecha antes de parsearla.
        {
            const inputFecha = document.getElementById('fecha_inicio');
            if (inputFecha.value) {
                const soloFecha = inputFecha.value.split('T')[0]; // "YYYY-MM-DD"
                const fechaEvaluar = new Date(soloFecha + 'T00:00:00');
                const diaSemana = fechaEvaluar.getDay();
                let esDiaLaboral = true;
                if (tipoEmp === 'administrativo' && (diaSemana === 0 || diaSemana === 6)) esDiaLaboral = false;
                if (tipoEmp === 'operativo' && diaSemana === 0) esDiaLaboral = false;

                if (!esDiaLaboral) {
                    ScaModal.warning('Día no laboral', `${nombre} está en día no laboral. Se agregará, pero el guardia deberá justificar en el escáner.`);
                }
            }
        }
    }
    // ────────────────────────────────────────────────────────────
    // V10.3 · CAMBIO #33: PERMISO DE ENTRADA — el tipo más flexible
    // de todos, a propósito: su razón de ser son los empleados SIN
    // turno y los días NO laborales (domingos de operativos, fines
    // de semana de administrativos). No se valida turno ni día:
    // el permiso define su propia ventana con las fechas/horas que
    // capture el usuario en el formulario.
    // ────────────────────────────────────────────────────────────
    else if (tipo === 'permiso_entrada') {
        // Sin validaciones adicionales de turno/día: se agrega tal cual.
    }

    // Si pasó todas las validaciones, agregar
    empleadosAgregados.push(id);
    const div = document.getElementById('empleados-seleccionados');
    const item = document.createElement('div');
    item.className = 'list-group-item d-flex justify-content-between align-items-center';
    item.id = `emp-${id}`;
    const turnoTxt = (turnoEntrada && turnoSalida) ? ` · Turno: ${turnoEntrada.substring(0,5)}-${turnoSalida.substring(0,5)}` : ' · Sin turno';
    item.innerHTML = `
        <div><i class="bi bi-person-check text-success"></i> ${nombre} <small class="text-muted">(ID: ${id}${turnoTxt})</small></div>
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="quitarEmpleado(${id})"><i class="bi bi-x"></i></button>
        <input type="hidden" name="empleados[]" value="${id}">
    `;
    div.appendChild(item);
    document.getElementById('buscar-emp').value = '';
    document.getElementById('resultados-busqueda').innerHTML = '';
}

// FIX A-2: Verifica si dos turnos se solapan
function verificarSolapamiento(entrada1, salida1, entrada2, salida2) {
    const aMinutos = (h) => {
        const [hh, mm] = h.split(':');
        return parseInt(hh) * 60 + parseInt(mm);
    };
    const e1 = aMinutos(entrada1);
    const s1 = aMinutos(salida1);
    const e2 = aMinutos(entrada2);
    const s2 = aMinutos(salida2);
    return e1 < s2 && e2 < s1;
}

// FIX A-2: Verifica si dos turnos se solapan (tienen intersección)
function verificarSolapamiento(entrada1, salida1, entrada2, salida2) {
    // Convierte "HH:MM:SS" a minutos desde medianoche
    const aMinutos = (h) => {
        const [hh, mm] = h.split(':');
        return parseInt(hh) * 60 + parseInt(mm);
    };
    const e1 = aMinutos(entrada1);
    const s1 = aMinutos(salida1);
    const e2 = aMinutos(entrada2);
    const s2 = aMinutos(salida2);
    // Hay solapamiento si el inicio de uno es menor al fin del otro Y viceversa
    return e1 < s2 && e2 < s1;
}

function quitarEmpleado(id) {
    empleadosAgregados = empleadosAgregados.filter(e => e !== id);
    document.getElementById(`emp-${id}`).remove();
}

// ===== VALIDACIÓN AL ENVIAR =====
// FIX 3d: El handler debe ser async para poder usar await en la validación de turnos
document.querySelector('form').addEventListener('submit', async function(e) {
    // FIX: Forzar eliminación de 'required' en campos que el JS deshabilitó.
    // El navegador a veces re-valida campos ocultos/deshabilitados y bloquea el submit.
    const tipoSubmit = document.querySelector('.tipo-radio:checked')?.value;

    if (tipoSubmit === 'pase_salida') {
        const inputFin = document.getElementById('fecha_fin');
        if (inputFin) {
            inputFin.removeAttribute('required');
            inputFin.disabled = true; // asegurar que no se envíe
            inputFin.value = ''; // limpiar valor para evitar error de formato
        }
    }
    // Limpiar externos si no es visita/contratista (defensivo)
    if (!['visita', 'contratista'].includes(tipoSubmit)) {
        document.querySelectorAll('.externo-fila').forEach(fila => fila.remove());
    }

    const errores = [];

    if (!tipoSubmit) {
        e.preventDefault();
        ScaModal.warning('Faltan datos', 'Seleccione el tipo de solicitud.');
        return;
    }

    if (['visita', 'contratista'].includes(tipoSubmit)) {
        const filas = document.querySelectorAll('.externo-fila');
        if (filas.length === 0) {
            errores.push('Debe agregar al menos una persona.');
        } else {
            const nombresVacios = Array.from(filas).some(f => !f.querySelector('.externo-nombre').value.trim());
            if (nombresVacios) errores.push('Todas las personas deben tener un nombre.');

            // CAMBIO V7: Validar identificación oficial unificada antes de enviar.
            // Antes este bloque buscaba .externo-curp y .externo-pasaporte que ya
            // no existen (se unificaron en .externo-identificacion). Eso hacía que
            // siempre fallara con "La CURP es obligatoria" aunque el campo estuviera
            // lleno, porque ?.value devolvía undefined y !undefined === true.
            Array.from(filas).forEach((fila, index) => {
                const tipoId = fila.querySelector('.externo-tipo-id')?.value?.trim();
                const identVal = fila.querySelector('.externo-identificacion')?.value?.trim();
                const nombre = fila.querySelector('.externo-nombre')?.value.trim() || `Persona #${index + 1}`;

                if (!tipoId) {
                    errores.push(`${nombre}: Seleccione el tipo de identificación oficial.`);
                }
                if (!identVal) {
                    errores.push(`${nombre}: El número de identificación es obligatorio.`);
                }
                if (tipoId === 'curp' && identVal && identVal.length !== 18) {
                    errores.push(`${nombre}: La CURP debe tener exactamente 18 caracteres.`);
                }
            });
        }    

        if (tipoSubmit === 'contratista') {
            const empresa = document.getElementById('empresa').value.trim();
            if (!empresa) errores.push('La empresa es obligatoria para contratistas.');
        }

        if (!document.getElementById('soy_anfitrion').checked) {
            if (!document.getElementById('anfitrion_empleado_id').value) {
                errores.push('Debe seleccionar un anfitrión o marcar "Yo soy el anfitrión".');
            }
        }
    }

    if (tipoSubmit === 'interplantas') {
        if (!document.getElementById('planta_destino_id').value) {
            errores.push('Seleccione la planta destino (debe ser distinta a la origen).');
        }
    }

    // FIX A-6: Validar empleados para "otros"
    // V10.3 · CAMBIO #32: también aplica a permiso_entrada (si eligió
    // "solo para otras personas", debe agregar al menos a alguien).
    if (['pase_salida', 'interplantas', 'permiso_entrada'].includes(tipoSubmit)) {
        const paraQuien = document.querySelector('.para-quien-radio:checked')?.value;
        if (paraQuien === 'otros') {
            if (empleadosAgregados.length === 0) {
                errores.push('Debe agregar al menos una persona (seleccionó "Solo para otras personas").');
            }
        }
    }

    // ============================================================
    // V10.3.3-fix (bug 1 del usuario): Permiso de Entrada — la vigencia
    // no debe quedar COMPLETAMENTE dentro del turno del creador. El
    // escáner solo consulta este permiso FUERA del turno (llegada muy
    // temprana, entrada tras el turno, día no laboral o sin turno); una
    // vigencia dentro del turno es un permiso muerto. Aquí se valida el
    // caso del creador ("para mí" / "para mí y otros") para dar feedback
    // inmediato; para los demás beneficiarios (y como autoridad final)
    // lo valida el servidor en SolicitudController::storeSolicitudInt.
    // Solo se evalúa Lunes-Viernes (laboral para cualquier clasificación)
    // y turnos diurnos; sábados/domingos y nocturnos se dejan al servidor.
    // ============================================================
    if (tipoSubmit === 'permiso_entrada') {
        const paraQuienPE = document.querySelector('.para-quien-radio:checked')?.value;
        const inputIniPE = document.getElementById('fecha_inicio');
        const inputFinPE = document.getElementById('fecha_fin');
        if (paraQuienPE !== 'otros' && inputIniPE && inputFinPE
            && inputIniPE.value && inputFinPE.value
            && creadorTurnoEntrada && creadorTurnoSalida) {
            const iniPE = new Date(inputIniPE.value);
            const finPE = new Date(inputFinPE.value);
            const partesE = creadorTurnoEntrada.split(':');
            const partesS = creadorTurnoSalida.split(':');
            const entradaTurnoMin = parseInt(partesE[0]) * 60 + parseInt(partesE[1]);
            const salidaTurnoMin = parseInt(partesS[0]) * 60 + parseInt(partesS[1]);
            const esNocturnoJS = entradaTurnoMin > salidaTurnoMin;
            const mismoDiaPE = iniPE.toDateString() === finPE.toDateString();
            const diaPE = iniPE.getDay(); // 0=Dom ... 6=Sáb
            const iniPEMin = iniPE.getHours() * 60 + iniPE.getMinutes();
            const finPEMin = finPE.getHours() * 60 + finPE.getMinutes();
            if (!esNocturnoJS && mismoDiaPE && diaPE >= 1 && diaPE <= 5
                && iniPEMin >= entradaTurnoMin && finPEMin <= salidaTurnoMin) {
                errores.push(`La vigencia capturada queda completamente DENTRO de su turno (${creadorTurnoEntrada.substring(0,5)}-${creadorTurnoSalida.substring(0,5)}). Un Permiso de Entrada solo aplica FUERA del turno: para una llegada temprana, termine la vigencia a más tardar a la hora de entrada de su turno (${creadorTurnoEntrada.substring(0,5)}).`);
            }
        }
    }

    // FIX 3d: Para Pase de Salida, validar turnos vía AJAX
    if (tipoSubmit === 'pase_salida' && errores.length === 0) {
        let idsAValidar = [miEmpleadoId];
        const paraQuienActual = document.querySelector('.para-quien-radio:checked')?.value;
        if (paraQuienActual !== 'mi') {
            idsAValidar = idsAValidar.concat(empleadosAgregados);
        }
        if (paraQuienActual === 'otros') {
            idsAValidar = [...empleadosAgregados];
        }

        if (idsAValidar.length > 0) {
            const url = `{{ route('solicitudes.validar.turno') }}?empleados=${idsAValidar.join(',')}`;
            try {
                const res = await fetch(url);
                const data = await res.json();
                if (!data.todos_tienen_turno) {
                    const nombres = data.empleados_sin_turno.map(e => e.nombre).join(', ');
                    errores.push(`Los siguientes empleados no tienen turno asignado: ${nombres}. Debe capturar fecha y hora de inicio y fin manualmente.`);
                    // FIX A-3: Habilitar campos manuales correctamente
                    const inputInicio = document.getElementById('fecha_inicio');
                    const inputFin = document.getElementById('fecha_fin');
                    const checkHoraManual = document.getElementById('check-hora-manual');
                    const campoHoraManual = document.getElementById('campo-hora-manual');
                    const avisoPase = document.getElementById('aviso-pase-salida');
                    
                    inputInicio.type = 'datetime-local';
                    inputInicio.value = ''; // Limpiar valor de fecha anterior
                    inputFin.type = 'datetime-local';
                    inputFin.disabled = false;
                    inputFin.required = true;
                    inputFin.value = '';
                    inputFin.placeholder = '';
                    
                    if (checkHoraManual) checkHoraManual.style.display = 'none';
                    if (campoHoraManual) campoHoraManual.style.display = 'none';
                    if (avisoPase) avisoPase.style.display = 'none';
                    
                    ScaModal.warning('Atención', 'Algunos empleados no tienen turno. Capture las fechas manualmente.');
                    e.preventDefault(); // Detener el envío para que el usuario llene las fechas
                }
            } catch (err) {
                errores.push('No se pudo validar los turnos. Intente de nuevo.');
            }
        }
    }

    if (errores.length > 0) {
        e.preventDefault();
        // M5 [ScaModal] — Reemplazamos alert() por ScaModal.error()
        ScaModal.error('No se puede crear la solicitud', '<ul class="mb-0 text-start"><li>' + errores.join('</li><li>') + '</li></ul>');
        return;
    }

    // M5 [ScaModal] — Mostrar loading antes del submit
    ScaModal.loading('Creando solicitud', 'Validando datos y enviando a aprobadores...');
    
    const btn = document.querySelector('button[type="submit"]');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Creando...';
    }
});

// ===== INICIALIZACIÓN =====
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.tipo-radio').forEach(function(radio) {
        radio.addEventListener('change', actualizarCampos);
    });
    const checked = document.querySelector('.tipo-radio:checked');
    if (checked) checked.dispatchEvent(new Event('change'));
});

// FIX 6: Listener para cambio en "¿Para quién es?" que actualiza los inputs de fecha
document.querySelectorAll('.para-quien-radio').forEach(radio => {
    radio.addEventListener('change', function() {
        const tipo = document.querySelector('.tipo-radio:checked')?.value;
        if (tipo === 'pase_salida') {
            // Re-ejecutar configurarPaseSalida para actualizar inputs
            configurarPaseSalida('pase_salida');
        }
    });
});

</script>
@endpush
@endsection