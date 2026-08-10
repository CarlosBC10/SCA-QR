{{-- resources/views/solicitudes/create.blade.php --}}
@extends('layouts.app')

@section('title', 'Nueva Solicitud')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-0 fw-bold">Nueva Solicitud</h1>
    <p class="text-muted mb-0">Complete los datos según el tipo de solicitud</p>
</div>

@if ($errors->any())
<div class="alert alert-danger" style="border-radius:12px;">
    <h6 class="fw-bold mb-2"><i class="bi bi-exclamation-triangle"></i> Corrija los siguientes errores:</h6>
    <ul class="mb-0 ps-3">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
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

            {{-- FIX A-1: Pregunta "¿Para cuándo es?" (solo pase_salida/interplantas) --}}
            <div id="campo-cuando" class="mb-3" style="display:none;">
                <label class="form-label fw-bold">¿Para cuándo es el permiso?</label>
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="w-100">
                            <input type="radio" name="para_cuando" value="hoy" class="para-cuando-radio" checked>
                            <div class="para-quien-card h-100 p-2 text-center">
                                <i class="bi bi-calendar-day fs-4 text-success"></i>
                                <div class="fw-bold small">Hoy</div>
                                <small class="text-muted">Saldrá inmediatamente</small>
                            </div>
                        </label>
                    </div>
                    <div class="col-md-6">
                        <label class="w-100">
                            <input type="radio" name="para_cuando" value="otra" class="para-cuando-radio">
                            <div class="para-quien-card h-100 p-2 text-center">
                                <i class="bi bi-calendar-plus fs-4 text-success"></i>
                                <div class="fw-bold small">Otra fecha</div>
                                <small class="text-muted">Programar para otro día</small>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

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
                <div id="resultados-anfitrion" class="mb-2"></div>
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
                <div id="resultados-busqueda" class="mb-3"></div>
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
@endphp
// FIX A-2: Turno del creador para validar solapamiento con otros empleados
const creadorTurnoEntrada = '{{ $creadorEntradaVar }}';
const creadorTurnoSalida = '{{ $creadorSalidaVar }}';

// ===== FIX A-6: Selector "¿Para quién es?" =====
const esAdminOSuperParaInt = {{ $esAdminOSuperVar ? 'true' : 'false' }};
const esAdminOSuper = esAdminOSuperParaInt; // FIX: alias para compatibilidad con el JS de búsqueda
const miPlantaId = {{ $miPlantaIdVar }};
const miPlantaNombre = '{{ addslashes($miPlantaNombreVar) }}';

function actualizarParaQuien() {
    const tipo = document.querySelector('.tipo-radio:checked')?.value;
    const paraQuien = document.querySelector('.para-quien-radio:checked')?.value;

    if (!['pase_salida', 'interplantas'].includes(tipo)) return;

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

// ===== FIX A-1: Lógica para "¿Para cuándo es?" =====
function actualizarParaCuando() {
    const tipo = document.querySelector('.tipo-radio:checked')?.value;
    if (!['pase_salida', 'interplantas'].includes(tipo)) return;

    const paraCuando = document.querySelector('.para-cuando-radio:checked')?.value;
    const inputInicio = document.getElementById('fecha_inicio');
    const checkHoraManual = document.getElementById('check-hora-manual');
    const campoHoraManual = document.getElementById('campo-hora-manual');

    if (paraCuando === 'hoy') {
        // Si es hoy, ocultamos el input de fecha
        inputInicio.type = 'hidden';
        // FIX: Enviar fecha y hora completa actual para que no falle la validación backend
        const ahora = new Date();
        const pad = (n) => String(n).padStart(2, '0');
        inputInicio.value = `${ahora.getFullYear()}-${pad(ahora.getMonth()+1)}-${pad(ahora.getDate())}T${pad(ahora.getHours())}:${pad(ahora.getMinutes())}`;
        
        if (checkHoraManual) checkHoraManual.style.display = 'none';
        if (campoHoraManual) campoHoraManual.style.display = 'none';

        // FIX A-3: Validar al creador inmediatamente si es Pase de Salida
        validarCreadorPaseHoy();

    } else {
        // Si es otra fecha, mostramos el input de fecha
        inputInicio.type = 'date';
        inputInicio.value = ''; // limpiar para que el usuario elija
        if (checkHoraManual) checkHoraManual.style.display = 'none';
        if (campoHoraManual) campoHoraManual.style.display = 'none';
        
        // Limpiar aviso previo
        const avisoPase = document.getElementById('aviso-pase-salida');
        if (avisoPase) {
            avisoPase.style.display = 'block';
            document.getElementById('aviso-pase-salida-texto').innerHTML = `<strong>Pase de Salida:</strong> Las fechas se calculan según su turno.
                <br>• Si es para <strong>OTRO DÍA</strong>: inicia a la hora de inicio de su turno y termina a la hora de fin.
                <br>• Si algún empleado <strong>no tiene turno</strong>, deberá capturar las fechas manualmente.`;
        }
    }
}

// FIX A-3: Validar al creador si el pase es para HOY
function validarCreadorPaseHoy() {
    const tipo = document.querySelector('.tipo-radio:checked')?.value;
    if (tipo !== 'pase_salida') return;

    const avisoPase = document.getElementById('aviso-pase-salida');
    const avisoTexto = document.getElementById('aviso-pase-salida-texto');
    if (!avisoPase) return;

    // Obtener info del creador (embebida en el blade)
    // Si no tiene turno, avisar
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

    // Verificar si es día laboral para el creador (asumimos L-V administrativo, L-S operativo por ahora)
    // Para simplificar, si es superadmin/admin, asumimos que puede crear.
    const hoy = new Date();
    const diaSemana = hoy.getDay(); // 0=Dom, 6=Sab
    
    // Verificar si está dentro del turno
    const horaActual = hoy.getHours() * 60 + hoy.getMinutes();
    const [he, me] = creadorTurnoEntrada.split(':');
    const [hs, ms] = creadorTurnoSalida.split(':');
    const entradaMin = parseInt(he)*60 + parseInt(me);
    const salidaMin = parseInt(hs)*60 + parseInt(ms);

    const esDiaLaboral = !(diaSemana === 0); // Domingo no laboral por defecto
    const dentroDeTurno = (horaActual >= entradaMin && horaActual <= salidaMin);

    if (esDiaLaboral && dentroDeTurno) {
        avisoPase.style.display = 'block';
        avisoTexto.innerHTML = `<strong>Pase de Salida:</strong> Las fechas se calculan según su turno.
            <br>• Si es para <strong>HOY</strong>: inicia ahora y termina a la hora de fin de su turno.`;
    } else {
        // Verificar si está en planta (requiere AJAX, lo omitimos para simplicidad aquí y dejamos que el backend lo valide)
        // O simplemente mostramos el mensaje de aviso
        avisoPase.style.display = 'block';
        avisoTexto.innerHTML = `<div class="alert alert-warning mb-0"><i class="bi bi-exclamation-triangle"></i> <strong>Atención:</strong> Está fuera de su turno o es día no laboral. Si actualmente se encuentra en planta, podrá salir justificando su salida con el guardia (no necesita crear un pase). Si no está en planta, no se puede crear el pase.</div>`;
    }
}

// Listeners para el radio "para_cuando"
document.querySelectorAll('.para-cuando-radio').forEach(radio => {
    radio.addEventListener('change', actualizarParaCuando);
});

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

    const esInterno = ['pase_salida', 'interplantas'].includes(tipo);
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
    // --- PREGUNTA "¿PARA CUÁNDO ES?" ---
    const campoCuando = document.getElementById('campo-cuando');
    if (campoCuando) {
        campoCuando.style.display = esInterno ? 'block' : 'none';
        // Resetear a "Hoy" si cambiamos de tipo
        if (esInterno) {
            document.querySelector('.para-cuando-radio[value="hoy"]').checked = true;
            actualizarParaCuando();
        }
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
        // Deshabilitar input de fin (se calcula solo)
        inputFin.disabled = true;
        inputFin.required = false;
        inputFin.value = '';
        inputFin.placeholder = 'Se calcula automáticamente';
        labelTipoFin.textContent = '(automática)';

        // Mostrar aviso
        avisoPase.style.display = 'block';
        avisoTexto.innerHTML = `<strong>Pase de Salida:</strong> Las fechas se calculan según su turno.
            <br>• Si es para <strong>HOY</strong>: inicia ahora y termina a la hora de fin de su turno.
            <br>• Si es para <strong>OTRO DÍA</strong>: inicia a la hora de inicio de su turno y termina a la hora de fin.
            <br>• Si algún empleado <strong>no tiene turno</strong>, deberá capturar las fechas manualmente.`;

        // FIX A-1: Delegar a actualizarParaCuando() para que maneje el input
        actualizarParaCuando();

    } else {
        // Restaurar a datetime-local para otros tipos
        inputInicio.type = 'datetime-local';
        inputFin.type = 'datetime-local';
        inputFin.disabled = false;
        inputFin.required = true;
        inputFin.placeholder = '';
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
                div.innerHTML = '<div class="alert alert-secondary py-2 small">No se encontraron empleados con ese criterio.</div>';
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
                <select name="externos[${idx}][tipo_id]" class="form-select form-select-sm externo-tipo-id" onchange="cambiarTipoId(${idx})">
                    <option value="curp">CURP (Mexicano)</option>
                    <option value="pasaporte">Pasaporte (Extranjero)</option>
                </select>
            </div>
            <div class="col-md-4" id="campo-curp-${idx}">
                <label class="form-label small">CURP <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="text" name="externos[${idx}][curp]" class="form-control form-control-sm externo-curp" maxlength="18" placeholder="18 caracteres" oninput="this.value = this.value.toUpperCase()" required>                    
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="buscarExternoPorCurp(${idx})">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
                <small class="text-muted">Presione la lupa para buscar.</small>
            </div>
            <div class="col-md-4" id="campo-pasaporte-${idx}" style="display:none;">
                <label class="form-label small">Pasaporte <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="text" name="externos[${idx}][pasaporte]" class="form-control form-control-sm externo-pasaporte" maxlength="50" placeholder="Número de pasaporte" oninput="this.value = this.value.toUpperCase()">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="buscarExternoPorPasaporte(${idx})">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
                <small class="text-muted">Presione la lupa para buscar.</small>
            </div>
            <div class="col-md-8">
                <label class="form-label small">Nombre completo <span class="text-danger">*</span></label>
                <input type="text" name="externos[${idx}][nombre]" class="form-control form-control-sm externo-nombre" required
                       placeholder="Carlos Blanco Castillo">
                <small class="text-muted">Se normaliza a Primera Mayúscula al salir del campo.</small>
            </div>
            <div class="col-md-6">
                <label class="form-label small">Teléfono</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">+52</span>
                    <input type="tel" name="externos[${idx}][telefono]" class="form-control externo-telefono" maxlength="10" pattern="\\d{10}" placeholder="1234567890" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                </div>
            </div>
        </div>
        <div class="externo-precarga mt-2" style="display:none;"></div>
    `;
    lista.appendChild(fila);
}

// FIX: Cambiar entre CURP y Pasaporte
function cambiarTipoId(idx) {
    const tipoId = document.querySelector(`#externo-${idx} .externo-tipo-id:checked`).value;
    const campoCurp = document.getElementById(`campo-curp-${idx}`);
    const campoPasaporte = document.getElementById(`campo-pasaporte-${idx}`);
    const inputCurp = document.querySelector(`#externo-${idx} .externo-curp`);
    const inputPasaporte = document.querySelector(`#externo-${idx} .externo-pasaporte`);

    if (tipoId === 'curp') {
        campoCurp.style.display = 'block';
        campoPasaporte.style.display = 'none';
        inputCurp.required = true;
        inputPasaporte.required = false;
        // No limpiamos el valor si ya tenía algo, por si se equivocó de botón
    } else {
        campoCurp.style.display = 'none';
        campoPasaporte.style.display = 'block';
        inputCurp.required = false;
        inputPasaporte.required = true;
    }
}

// FIX: Buscar externo por pasaporte
async function buscarExternoPorPasaporte(idx) {
    const fila = document.getElementById(`externo-${idx}`);
    const pasaporte = fila.querySelector('.externo-pasaporte').value.trim().toUpperCase();
    const precargaDiv = fila.querySelector('.externo-precarga');

    if (pasaporte.length < 5) {
        precargaDiv.style.display = 'block';
        precargaDiv.className = 'externo-precarga mt-2 alert alert-warning py-1 small';
        precargaDiv.textContent = 'El pasaporte debe tener al menos 5 caracteres.';
        return;
    }

    try {
        const res = await fetch(`{{ route('solicitudes.buscar-externo-pasaporte') }}?pasaporte=${encodeURIComponent(pasaporte)}`);
        const data = await res.json();
        
        if (data.encontrado === true) {
            fila.querySelector('.externo-nombre').value = data.nombre || '';
            fila.querySelector('.externo-telefono').value = data.telefono || '';
            
            precargaDiv.style.display = 'block';
            precargaDiv.className = 'externo-precarga mt-2 alert alert-success py-1 small';
            precargaDiv.innerHTML = '<i class="bi bi-check-check"></i> Persona encontrada en el sistema. Datos precargados.';
        } else {
            precargaDiv.style.display = 'block';
            precargaDiv.className = 'externo-precarga mt-2 alert alert-info py-1 small';
            precargaDiv.textContent = 'Pasaporte no registrado. Se creará un nuevo registro al guardar.';
        }
    } catch(err) {
        precargaDiv.style.display = 'block';
        precargaDiv.className = 'externo-precarga mt-2 alert alert-danger py-1 small';
        precargaDiv.textContent = 'Error al buscar.';
    }
}

function quitarExterno(idx) {
    document.getElementById(`externo-${idx}`).remove();
    if (document.querySelectorAll('.externo-fila').length === 0) {
        document.getElementById('externos-vacio').style.display = 'block';
    }
}

// ===== EXTERNOS: BUSCAR POR CURP =====
async function buscarExternoPorCurp(idx) {
    const fila = document.getElementById(`externo-${idx}`);
    const curp = fila.querySelector('.externo-curp').value.trim().toUpperCase();
    const precargaDiv = fila.querySelector('.externo-precarga');

    if (curp.length !== 18) {
        precargaDiv.style.display = 'block';
        precargaDiv.className = 'externo-precarga mt-2 alert alert-warning py-1 small';
        precargaDiv.textContent = 'La CURP debe tener 18 caracteres.';
        return;
    }

    const filas = document.querySelectorAll('.externo-fila');
    for (let i = 0; i < filas.length; i++) {
        if (filas[i].id === `externo-${idx}`) continue;
        const otraCurp = filas[i].querySelector('.externo-curp').value.trim().toUpperCase();
        if (otraCurp === curp) {
            precargaDiv.style.display = 'block';
            precargaDiv.className = 'externo-precarga mt-2 alert alert-warning py-1 small';
            precargaDiv.textContent = 'Esta CURP ya fue agregada en otra fila. No la repita.';
            return;
        }
    }

    try {
        const res = await fetch(`{{ route('solicitudes.buscar-externo') }}?curp=${encodeURIComponent(curp)}`);
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
            precargaDiv.textContent = 'CURP no registrada. Se creará un nuevo registro al guardar.';
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
    const selectPlanta = document.getElementById('planta_origen_id');
    const hiddenPlanta = document.getElementById('planta_origen_id_hidden');
    const plantaOrigen = (selectPlanta && selectPlanta.value) ||
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

    const urlParams = `q=${encodeURIComponent(q)}&planta_id=${plantaOrigen}`;
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

function agregarEmpleado(id, nombre, turnoEntrada, turnoSalida, tipoEmp, estaEnPlanta) {
    if (empleadosAgregados.includes(id)) { alert('Ese empleado ya fue agregado.'); return; }
    if (parseInt(id) === miEmpleadoId) {
        alert('No puede agregarse a sí mismo. El sistema lo incluye automáticamente.');
        return;
    }

    const tipo = document.querySelector('.tipo-radio:checked')?.value;
    const paraQuien = document.querySelector('.para-quien-radio:checked')?.value;
    const paraCuando = document.querySelector('.para-cuando-radio:checked')?.value;

    // 1. Determinar turno de referencia
    if (tipo === 'pase_salida') {
        if (paraQuien === 'mi' || paraQuien === 'mi_y_otros') {
            turnoReferencia.entrada = creadorTurnoEntrada;
            turnoReferencia.salida = creadorTurnoSalida;
        } else {
            if (empleadosAgregados.length === 0) {
                turnoReferencia.entrada = turnoEntrada || null;
                turnoReferencia.salida = turnoSalida || null;
            }
        }
    }

    // 2. Validaciones para PASE DE SALIDA
    if (tipo === 'pase_salida') {
        // 2.1 Si es para HOY y está en planta, NO se agrega
        if (paraCuando === 'hoy' && estaEnPlanta) {
            alert(`${nombre} está actualmente en planta. No se puede agregar a la solicitud, pero puede salir justificando su salida con el guardia.`);
            return;
        }

        // 2.2 Validar solapamiento de turnos
        if (turnoReferencia.entrada && turnoReferencia.salida && turnoEntrada && turnoSalida) {
            const solapado = verificarSolapamiento(
                turnoReferencia.entrada, turnoReferencia.salida,
                turnoEntrada, turnoSalida
            );
            if (!solapado) {
                alert(`No se puede agregar a ${nombre} porque su turno (${turnoEntrada.substring(0,5)}-${turnoSalida.substring(0,5)}) no se solapa con el rango del permiso. Todos deben compartir un rango de horario común.`);
                return;
            }
        } else if (!turnoEntrada || !turnoSalida) {
            if (!confirm(`${nombre} no tiene turno asignado. Se le aplicarán las mismas horas. ¿Desea agregarlo de todas formas?`)) {
                return;
            }
        }

        // 2.3 Validar día laboral (si es otra fecha)
        if (paraCuando === 'otra') {
            const inputFecha = document.getElementById('fecha_inicio');
            if (inputFecha.value) {
                const fechaEvaluar = new Date(inputFecha.value + 'T00:00:00');
                const diaSemana = fechaEvaluar.getDay(); // 0=Dom, 6=Sab
                let esDiaLaboral = true;
                if (tipoEmp === 'administrativo' && (diaSemana === 0 || diaSemana === 6)) esDiaLaboral = false;
                if (tipoEmp === 'operativo' && diaSemana === 0) esDiaLaboral = false;

                if (!esDiaLaboral) {
                    alert(`No se puede agregar a ${nombre} porque el día seleccionado no es laboral para este empleado.`);
                    return;
                }
            }
        }
    } 
    // 3. Validaciones para INTERPLANTAS (Flexible)
    else if (tipo === 'interplantas') {
        if (!turnoEntrada || !turnoSalida) {
            if (!confirm(`${nombre} no tiene turno asignado. ¿Desea agregarlo de todas formas?`)) {
                return;
            }
        }
        // Solo advertir si es día no laboral, pero permitir
        if (paraCuando === 'otra') {
            const inputFecha = document.getElementById('fecha_inicio');
            if (inputFecha.value) {
                const fechaEvaluar = new Date(inputFecha.value + 'T00:00:00');
                const diaSemana = fechaEvaluar.getDay();
                let esDiaLaboral = true;
                if (tipoEmp === 'administrativo' && (diaSemana === 0 || diaSemana === 6)) esDiaLaboral = false;
                if (tipoEmp === 'operativo' && diaSemana === 0) esDiaLaboral = false;

                if (!esDiaLaboral) {
                    alert(`Aviso: ${nombre} está en día no laboral. Se agregará, pero el guardia deberá justificar en el escáner.`);
                }
            }
        }
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
        alert('Seleccione el tipo de solicitud.');
        return;
    }

    if (['visita', 'contratista'].includes(tipoSubmit)) {
        const filas = document.querySelectorAll('.externo-fila');
        if (filas.length === 0) {
            errores.push('Debe agregar al menos una persona.');
        } else {
            const nombresVacios = Array.from(filas).some(f => !f.querySelector('.externo-nombre').value.trim());
            if (nombresVacios) errores.push('Todas las personas deben tener un nombre.');

            // FIX G: Validar identificación obligatoria antes de enviar
            Array.from(filas).forEach((fila, index) => {
                const tipoId = fila.querySelector('.externo-tipo-id')?.value;
                const curpVal = fila.querySelector('.externo-curp')?.value.trim();
                const pasaporteVal = fila.querySelector('.externo-pasaporte')?.value.trim();
                const nombre = fila.querySelector('.externo-nombre')?.value.trim() || `Persona #${index + 1}`;

                if (tipoId === 'curp' && !curpVal) {
                    errores.push(`${nombre}: La CURP es obligatoria (o cambie el tipo a Pasaporte).`);
                }
                if (tipoId === 'pasaporte' && !pasaporteVal) {
                    errores.push(`${nombre}: El Pasaporte es obligatorio (o cambie el tipo a CURP).`);
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
    if (['pase_salida', 'interplantas'].includes(tipoSubmit)) {
        const paraQuien = document.querySelector('.para-quien-radio:checked')?.value;
        if (paraQuien === 'otros') {
            if (empleadosAgregados.length === 0) {
                errores.push('Debe agregar al menos una persona (seleccionó "Solo para otras personas").');
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
                    
                    alert('Atención: algunos empleados no tienen turno. Capture las fechas manualmente.');
                    e.preventDefault(); // Detener el envío para que el usuario llene las fechas
                }
            } catch (err) {
                errores.push('No se pudo validar los turnos. Intente de nuevo.');
            }
        }
    }

    if (errores.length > 0) {
        e.preventDefault();
        alert('No se puede crear la solicitud:\n\n• ' + errores.join('\n• '));
        return;
    }

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
</script>
@endpush
@endsection