{{-- resources/views/solicitudes/create.blade.php --}}
@extends('layouts.app')

@section('title', 'Nueva Solicitud')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-0 fw-bold">Nueva Solicitud</h1>
    <p class="text-muted mb-0">Complete los datos según el tipo de solicitud</p>
</div>
{{-- Mostrar errores de validación --}}
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
    
    {{-- CARD 1: SELECCIÓN DE TIPO --}}
    <div class="card border-0 shadow-sm mb-3 sca-card" style="border-radius:12px;">
        <div class="card-header bg-transparent py-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-file-earmark-text text-success"></i> Tipo de Solicitud</h5>
        </div>
        <div class="card-body">
            <div class="row g-2 mb-3">
                @php
                    $tiposLabels = [
                        'pase_salida' => ['Pase de Salida', 'bi-box-arrow-right', 'Permiso para salir'],
                        'interplantas' => ['Interplantas', 'bi-arrow-left-right', 'Ir a otra planta'],
                        'visita' => ['Visita', 'bi-person', 'Recibir un visitante'],
                        'contratista' => ['Contratista', 'bi-person-badge', 'Recibir contratista'],
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

    {{-- CARD 2: DATOS DE LA SOLICITUD --}}
    <div class="card border-0 shadow-sm mb-3 sca-card" style="border-radius:12px;">
        <div class="card-header bg-transparent py-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-card-checklist text-success"></i> Datos de la Solicitud</h5>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Planta Origen (Su Planta)</label>
                    <input type="text" class="form-control bg-light" readonly value="{{ $plantaOrigenNombre }}">
                </div>
                
                <div class="col-md-6" id="campo-planta-destino" style="display:none;">
                    <label class="form-label">Planta Destino</label>
                    <select name="planta_destino_id" id="planta_destino_id" class="form-select">
                        <option value="">Seleccionar...</option>
                        @foreach($plantas as $p)
                            <option value="{{ $p->id }}" @if(old('planta_destino_id')==$p->id) selected @endif>{{ $p->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Fecha y hora de inicio</label>
                    <input type="datetime-local" name="fecha_inicio" class="form-control" required value="{{ old('fecha_inicio') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Fecha y hora de fin</label>
                    <input type="datetime-local" name="fecha_fin" class="form-control" required value="{{ old('fecha_fin') }}">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Motivo</label>
                <textarea name="motivo" class="form-control" rows="3" required maxlength="1000" placeholder="Explique el motivo...">{{ old('motivo') }}</textarea>
            </div>

            <div id="campos-persona" style="display:none;">
                <hr>
                <h6 class="fw-bold mb-3"><i class="bi bi-person text-success"></i> Datos de la Persona</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nombre completo <span class="text-danger">*</span></label>
                        <input type="text" name="persona_nombre" class="form-control" value="{{ old('persona_nombre') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Teléfono</label>
                        <div class="input-group">
                            <span class="input-group-text">+52</span>
                            <input type="tel" name="persona_telefono" class="form-control" maxlength="10" pattern="\d{10}" title="Solo 10 dígitos numéricos" placeholder="1234567890" value="{{ old('persona_telefono') }}" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                        </div>
                        <small class="text-muted">10 dígitos. Si es extranjero, ignora el +52 y pon el número completo.</small>
                    </div>

                    <div class="col-md-6" id="campo-empresa" style="display:none;">
                        <label class="form-label">Empresa <span class="text-danger">*</span></label>
                        <input type="text" name="persona_empresa" class="form-control" value="{{ old('persona_empresa') }}">
                    </div>
                </div>
            </div>

            <div id="campos-empleados" style="display:none;">
                <hr>
                <h6 class="fw-bold mb-3"><i class="bi bi-people text-success"></i> Empleados (Interplantas Grupal)</h6>
                <div class="alert alert-info py-2 small">
                    <i class="bi bi-info-circle"></i> Deje vacío si es solo para usted. Agregue empleados si es grupal.
                </div>
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

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-success">
            <i class="bi bi-check-circle"></i> Crear Solicitud
        </button>
        <a href="{{ route('solicitudes.hub') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<style>
/* NUEVO: Estilo para la card seleccionada */
.tipo-card {
    border: 2px solid transparent !important;
    border-radius: 12px !important;
    cursor: pointer;
    transition: all 0.2s ease;
    color: #6c757d;
}
.tipo-card:hover {
    border-color: rgba(46,125,50,0.3) !important;
    color: #2e7d32;
}
/* Cuando el radio está checado, aplicamos estilo al hermano .tipo-card */
.tipo-radio:checked + .tipo-card {
    border-color: #2e7d32 !important;
    background-color: rgba(46,125,50,0.05) !important;
    color: #2e7d32;
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(46,125,50,0.15) !important;
}
.tipo-radio:checked + .tipo-card i {
    color: #2e7d32 !important;
}
</style>

@push('scripts')
<script>
let empleadosAgregados = [];
const puedeElegirDestinoExterno = {{ $puedeElegirDestinoExterno ? 'true' : 'false' }};

document.addEventListener('DOMContentLoaded', function() {
    function actualizarCampos() {
        const tipo = document.querySelector('.tipo-radio:checked')?.value;
        if (!tipo) return;

        const divPlantaDestino = document.getElementById('campo-planta-destino');
        const divPersona = document.getElementById('campos-persona');
        const divEmpleados = document.getElementById('campos-empleados');
        const campoEmpresa = document.getElementById('campo-empresa');

        // Lógica de Planta Destino
        let mostrarDestino = false;
        if (tipo === 'interplantas') {
            mostrarDestino = true;
        } else if (['visita', 'contratista'].includes(tipo)) {
            // Solo admin/super eligen destino para externos
            mostrarDestino = puedeElegirDestinoExterno;
        }
        
        divPlantaDestino.style.display = mostrarDestino ? 'block' : 'none';
        document.getElementById('planta_destino_id').required = mostrarDestino;

        // Persona
        divPersona.style.display = ['visita', 'contratista'].includes(tipo) ? 'block' : 'none';
        campoEmpresa.style.display = (tipo === 'contratista') ? 'block' : 'none';
        
        // Empleados
        divEmpleados.style.display = (tipo === 'interplantas') ? 'block' : 'none';
    }

    document.querySelectorAll('.tipo-radio').forEach(function(radio) {
        radio.addEventListener('change', actualizarCampos);
    });

    const checked = document.querySelector('.tipo-radio:checked');
    if (checked) checked.dispatchEvent(new Event('change'));

    document.getElementById('btn-buscar-emp').addEventListener('click', async function() {
        const q = document.getElementById('buscar-emp').value.trim();
        const div = document.getElementById('resultados-busqueda');
        if (q.length < 2) return;

        try {
            const res = await fetch(`{{ route('solicitudes.buscar-empleado') }}?q=${encodeURIComponent(q)}`);
            const data = await res.json();

            if (data.length === 0) {
                div.innerHTML = '<div class="alert alert-secondary py-2 small">No se encontraron empleados.</div>';
                return;
            }

            div.innerHTML = data.map(e => `
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div>
                        <strong>${e.nombre}</strong>
                        <small class="text-muted d-block">ID: ${e.id} · ${e.puesto || ''} · ${e.planta}</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-success" onclick="agregarEmpleado(${e.id}, '${e.nombre.replace(/'/g, "\\'")}')">
                        <i class="bi bi-plus"></i> Agregar
                    </button>
                </div>
            `).join('');
        } catch(err) {
            div.innerHTML = '<div class="alert alert-danger py-2 small">Error al buscar.</div>';
        }
    });

    document.getElementById('buscar-emp').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('btn-buscar-emp').click();
        }
    });
});

function agregarEmpleado(id, nombre) {
    if (empleadosAgregados.includes(id)) {
        alert('Ese empleado ya fue agregado.');
        return;
    }
    empleadosAgregados.push(id);

    const div = document.getElementById('empleados-seleccionados');
    const item = document.createElement('div');
    item.className = 'list-group-item d-flex justify-content-between align-items-center';
    item.id = `emp-${id}`;
    item.innerHTML = `
        <div><i class="bi bi-person-check text-success"></i> ${nombre} <small class="text-muted">(ID: ${id})</small></div>
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="quitarEmpleado(${id})">
            <i class="bi bi-x"></i>
        </button>
        <input type="hidden" name="empleados[]" value="${id}">
    `;
    div.appendChild(item);

    document.getElementById('buscar-emp').value = '';
    document.getElementById('resultados-busqueda').innerHTML = '';
}

function quitarEmpleado(id) {
    empleadosAgregados = empleadosAgregados.filter(e => e !== id);
    document.getElementById(`emp-${id}`).remove();
}
</script>
@endpush
@endsection