@extends('layouts.app')

@section('title', 'Bitácora de Accesos')

@section('content')
@php
    $fechaHastaDefault = request('fecha_hasta') ?: now()->format('Y-m-d\\TH:i');
    $subtipoActual = request('subtipo', 'todos');
    $incidenciaActual = request('incidencia', '');
    $movimientoActual = request('tipo', '');
    $estadoActual = request('estado', '');
    $plantaActual = request('planta', '');
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="rounded-circle bg-success bg-opacity-10 text-success p-2"><i class="bi bi-journal-check fs-5"></i></span>
            <h1 class="h3 mb-0 fw-bold">Bitácora de Accesos</h1>
        </div>
        <p class="text-muted mb-0">Historial de entradas y salidas, con contexto del permiso utilizado.</p>
    </div>
    <div class="small text-muted d-flex align-items-center gap-2">
        <i class="bi bi-shield-check"></i>
        {{ $esSuperior ? 'Consulta global' : 'Consulta de planta asignada' }}
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl"><div class="card border-0 shadow-sm h-100 sca-card"><div class="card-body"><div class="text-muted small">Eventos</div><div class="fs-3 fw-bold">{{ number_format($resumen['total']) }}</div></div></div></div>
    <div class="col-6 col-xl"><div class="card border-0 shadow-sm h-100 sca-card"><div class="card-body"><div class="text-muted small">Entradas</div><div class="fs-3 fw-bold text-success">{{ number_format($resumen['entradas']) }}</div></div></div></div>
    <div class="col-6 col-xl"><div class="card border-0 shadow-sm h-100 sca-card"><div class="card-body"><div class="text-muted small">Salidas</div><div class="fs-3 fw-bold text-warning">{{ number_format($resumen['salidas']) }}</div></div></div></div>
    <div class="col-6 col-xl"><div class="card border-0 shadow-sm h-100 sca-card"><div class="card-body"><div class="text-muted small">Denegados</div><div class="fs-3 fw-bold text-danger">{{ number_format($resumen['denegados']) }}</div></div></div></div>
    <div class="col-6 col-xl"><div class="card border-0 shadow-sm h-100 sca-card"><div class="card-body"><div class="text-muted small">Pendientes</div><div class="fs-3 fw-bold text-info">{{ number_format($resumen['pendientes']) }}</div></div></div></div>
</div>

<div class="card border-0 shadow-sm sca-card mb-4">
    <div class="card-header bg-white border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
        <div>
            <h5 class="mb-0 fw-bold"><i class="bi bi-funnel me-2 text-primary"></i>Filtros de búsqueda</h5>
            <small class="text-muted">La clasificación determina los filtros específicos disponibles.</small>
        </div>
        <a href="{{ route('bitacora.index') }}" class="btn btn-light border btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>Restablecer</a>
    </div>

    <div class="card-body">
        <form method="GET" id="form-filtros" action="{{ route('bitacora.index') }}">
            <div class="row g-3">
                <div class="col-12 col-md-3 col-xl-2">
                    <label for="select-clasificacion" class="form-label small fw-semibold text-muted">Clasificación</label>
                    <select class="form-select" name="clasificacion" id="select-clasificacion">
                        <option value="todos" @selected($clasificacion === 'todos')>Todos</option>
                        <option value="internos" @selected($clasificacion === 'internos')>Internos</option>
                        <option value="externos" @selected($clasificacion === 'externos')>Externos</option>
                    </select>
                </div>

                <div class="col-12 col-md-3 col-xl-2">
                    <label for="select-subtipo" class="form-label small fw-semibold text-muted">Tipo de acceso</label>
                    <select class="form-select" name="subtipo" id="select-subtipo">
                        <option value="todos" @selected($subtipoActual === 'todos')>Todos</option>
                        <option value="estandar" data-grupo="internos" @selected($subtipoActual === 'estandar')>Estándar</option>
                        <option value="pase_salida" data-grupo="internos" @selected($subtipoActual === 'pase_salida')>Pase de Salida</option>
                        <option value="interplantas" data-grupo="internos" @selected($subtipoActual === 'interplantas')>Interplantas</option>
                        {{-- V10.3 · CAMBIO #30: nuevo tipo de acceso interno --}}
                        <option value="permiso_entrada" data-grupo="internos" @selected($subtipoActual === 'permiso_entrada')>Permiso de Entrada</option>
                        <option value="visita" data-grupo="externos" @selected($subtipoActual === 'visita')>Visita</option>
                        <option value="contratista" data-grupo="externos" @selected($subtipoActual === 'contratista')>Contratista</option>
                    </select>
                </div>

                <div class="col-12 col-md-3 col-xl-2">
                    <label for="fecha_desde" class="form-label small fw-semibold text-muted">Desde</label>
                    <input type="datetime-local" class="form-control" name="fecha_desde" id="fecha_desde" value="{{ request('fecha_desde') }}">
                </div>

                <div class="col-12 col-md-3 col-xl-2">
                    <label for="fecha_hasta" class="form-label small fw-semibold text-muted">Hasta</label>
                    <input type="datetime-local" class="form-control" name="fecha_hasta" id="fecha_hasta" value="{{ $fechaHastaDefault }}">
                </div>

                <div class="col-12 col-md-4 col-xl-2">
                    <label for="planta" class="form-label small fw-semibold text-muted">Planta evento</label>
                    <select class="form-select" name="planta" id="planta">
                        <option value="">Todas</option>
                        @foreach($plantas as $planta)
                            <option value="{{ $planta->id_planta }}" @selected($plantaActual == $planta->id_planta)>{{ $planta->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-8 col-xl-4">
                    <label for="buscar-texto" class="form-label small fw-semibold text-muted">Buscar persona / ID</label>
                    <div class="position-relative">
                        <input type="text" class="form-control" name="buscar" id="buscar-texto" value="{{ request('buscar') }}" placeholder="Mínimo 3 caracteres. Ej.: car, los cast, 23846, E-12" autocomplete="off">
                        <div id="resultados-busqueda" class="list-group position-absolute start-0 end-0 shadow-sm" style="z-index:1100;display:none;"></div>
                    </div>
                    <div class="form-text">Busca internos y externos. Con 3+ caracteres puede usar varios términos.</div>
                </div>

                <div class="col-12 col-md-4 col-xl-2">
                    <label for="tipo" class="form-label small fw-semibold text-muted">Movimiento</label>
                    <select class="form-select" name="tipo" id="tipo">
                        <option value="">Todos</option>
                        <option value="entrada" @selected($movimientoActual === 'entrada')>Entrada</option>
                        <option value="salida" @selected($movimientoActual === 'salida')>Salida</option>
                    </select>
                </div>

                <div class="col-12 col-md-4 col-xl-2">
                    <label for="estado" class="form-label small fw-semibold text-muted">Estado evento</label>
                    <select class="form-select" name="estado" id="estado">
                        <option value="">Todos</option>
                        <option value="autorizado" @selected($estadoActual === 'autorizado')>Autorizado</option>
                        <option value="denegado" @selected($estadoActual === 'denegado')>Denegado</option>
                        {{-- V10.3.11 (P6): estados intermedios que la BD sí contiene --}}
                        <option value="pendiente_revision" @selected($estadoActual === 'pendiente_revision')>Pendiente</option>
                        <option value="revision_horario" @selected($estadoActual === 'revision_horario')>En revisión</option>
                    </select>
                </div>

                <div class="col-12 col-md-4 col-xl-2">
                    {{-- V10.3.6-fix: etiqueta "Excepción" renombrada a "Incidencias"
                         para coincidir con la columna de la tabla y el CSV (el
                         parámetro del backend ya se llamaba "incidencia"). --}}
                    <label for="incidencia" class="form-label small fw-semibold text-muted">Incidencias</label>
                    <select class="form-select" name="incidencia" id="incidencia">
                        <option value="">Todas</option>
                        <option value="revision" @selected($incidenciaActual === 'revision')>Revisión</option>
                        <option value="justificacion" @selected($incidenciaActual === 'justificacion')>Justificación</option>
                        {{-- V10.3.11 (P2): incidencia nueva — solo eventos con hallazgo F10 registrado --}}
                        <option value="hallazgos" @selected($incidenciaActual === 'hallazgos')>Solo hallazgos F10</option>
                    </select>
                </div>

                <div class="col-12 col-md-4 col-xl-2 filtro-especifico" id="filtro-motivo">
                    <label for="motivo" class="form-label small fw-semibold text-muted">Motivo denegación</label>
                    <select class="form-select" name="motivo" id="motivo">
                        <option value="">Todos</option>
                        <option value="sin_clasificar" @selected(request('motivo') === 'sin_clasificar')>Sin clasificar</option>
                        <option value="sin_planta" @selected(request('motivo') === 'sin_planta')>Sin planta</option>
                        <option value="planta_no_autorizada" @selected(request('motivo') === 'planta_no_autorizada')>Planta no autorizada</option>
                        <option value="salida_temprana" @selected(request('motivo') === 'salida_temprana')>Salida temprana</option>
                        <option value="salida_antes_turno" @selected(request('motivo') === 'salida_antes_turno')>Salida antes del turno</option>
                        <option value="llegada_tardia" @selected(request('motivo') === 'llegada_tardia')>Llegada tardía</option>
                        {{-- V10.3 · CAMBIO #30: motivos nuevos del motor V10.3 --}}
                        <option value="llegada_temprana" @selected(request('motivo') === 'llegada_temprana')>Llegada temprana</option>
                        <option value="entrada_sin_permiso" @selected(request('motivo') === 'entrada_sin_permiso')>Entrada sin permiso</option>
                        <option value="salida_tardia" @selected(request('motivo') === 'salida_tardia')>Salida tardía</option>
                        <option value="credencial_expirada" @selected(request('motivo') === 'credencial_expirada')>Credencial expirada</option>
                        <option value="planta_inconsistente" @selected(request('motivo') === 'planta_inconsistente')>Planta inconsistente</option>
                        {{-- V10.3.11 (P6): motivos que el motor escribe pero faltaban en el dropdown --}}
                        <option value="empleado_inactivo" @selected(request('motivo') === 'empleado_inactivo')>Empleado inactivo</option>
                        <option value="manual_bloqueado" @selected(request('motivo') === 'manual_bloqueado')>Manual bloqueado</option>
                        <option value="pendiente_sin_resolver" @selected(request('motivo') === 'pendiente_sin_resolver')>Pendiente sin resolver</option>
                        <option value="rechazado_guardia" @selected(request('motivo') === 'rechazado_guardia')>Rechazado por guardia</option>
                        <option value="cerrado_sin_resolver" @selected(request('motivo') === 'cerrado_sin_resolver')>Cerrado sin resolver</option>
                        <option value="sin_turno" @selected(request('motivo') === 'sin_turno')>Sin turno</option>
                        <option value="dia_no_laboral" @selected(request('motivo') === 'dia_no_laboral')>Día no laboral</option>
                    </select>
                </div>

                <div class="col-12 col-md-4 col-xl-2 filtro-especifico" id="filtro-planta-origen">
                    <label for="planta_origen" class="form-label small fw-semibold text-muted">Planta origen</label>
                    <select class="form-select" name="planta_origen" id="planta_origen">
                        <option value="">Todas</option>
                        @foreach($plantas as $planta)
                            <option value="{{ $planta->id_planta }}" @selected(request('planta_origen') == $planta->id_planta)>{{ $planta->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-4 col-xl-2 filtro-especifico" id="filtro-planta-destino">
                    <label for="planta_destino" class="form-label small fw-semibold text-muted">Planta destino</label>
                    <select class="form-select" name="planta_destino" id="planta_destino">
                        <option value="">Todas</option>
                        @foreach($plantas as $planta)
                            <option value="{{ $planta->id_planta }}" @selected(request('planta_destino') == $planta->id_planta)>{{ $planta->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-4 col-xl-2 filtro-especifico externos-filter" id="filtro-empresa">
                    <label for="empresa" class="form-label small fw-semibold text-muted">Empresa</label>
                    <input type="text" class="form-control" name="empresa" id="empresa" value="{{ request('empresa') }}" placeholder="Ej. TechCorp">
                </div>

                <div class="col-12 col-md-4 col-xl-2 filtro-especifico externos-filter" id="filtro-anfitrion">
                    <label for="anfitrion" class="form-label small fw-semibold text-muted">Anfitrión</label>
                    <input type="text" class="form-control" name="anfitrion" id="anfitrion" value="{{ request('anfitrion') }}" placeholder="Nombre del anfitrión">
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 align-items-center mt-4 pt-3 border-top">
                <button type="submit" class="btn btn-primary px-4"><i class="bi bi-funnel me-1"></i>Aplicar filtros</button>
                <a href="{{ route('bitacora.index') }}" class="btn btn-light border">Limpiar</a>
                <div class="ms-md-auto d-flex flex-wrap align-items-center gap-2">
                    <label for="perPage" class="small text-muted mb-0">Resultados por página</label>
                    <select name="perPage" id="perPage" class="form-select form-select-sm" style="width:90px;">
                        @foreach([15,25,50,100,500] as $n)
                            <option value="{{ $n }}" @selected($perPage == $n)>{{ $n }}</option>
                        @endforeach
                    </select>
                    <select id="export_scope" class="form-select form-select-sm" style="width:205px;">
                        <option value="filtered">Todos los resultados filtrados</option>
                        <option value="page">Solo esta página</option>
                    </select>
                    <button type="button" class="btn btn-success btn-sm px-3" id="btn-exportar">
                        <i class="bi bi-file-earmark-excel me-1"></i>Exportar Excel
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- V10.3.11 (P7): chips de filtros rápidos — un clic setea los campos y aplica --}}
<div class="d-flex flex-wrap align-items-center gap-2 mb-4">
    <span class="small text-muted fw-semibold"><i class="bi bi-lightning-charge me-1 text-warning"></i>Accesos rápidos:</span>
    <button type="button" class="btn btn-sm btn-outline-secondary" data-chip="hoy">Hoy</button>
    <button type="button" class="btn btn-sm btn-outline-secondary" data-chip="ayer">Ayer</button>
    <button type="button" class="btn btn-sm btn-outline-secondary" data-chip="semana">Últimos 7 días</button>
</div>

{{-- V10.3.11 (P5): resumen de uso por persona — solo visible cuando el filtro
     activo es "Permiso de Entrada" (usa la MISMA consulta ya filtrada) --}}
@if(isset($usoPermiso) && $usoPermiso !== null && $usoPermiso->isNotEmpty())
<div class="card border-0 shadow-sm sca-card mb-4">
    <div class="card-header bg-white border-bottom py-3">
        <h5 class="mb-0 fw-bold"><i class="bi bi-clipboard-data me-2 text-primary"></i>Uso de Permiso de Entrada por persona</h5>
        <small class="text-muted">Top {{ $usoPermiso->count() }} · sobre los eventos ya filtrados.</small>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">Persona</th>
                    <th class="text-center">Eventos</th>
                    <th class="text-center">Entradas</th>
                    <th class="text-center">Salidas</th>
                    <th class="pe-3 text-end">Último movimiento</th>
                </tr>
            </thead>
            <tbody>
                @foreach($usoPermiso as $u)
                    <tr>
                        <td class="ps-3 fw-semibold">{{ $u->persona }}</td>
                        <td class="text-center">{{ number_format($u->eventos) }}</td>
                        <td class="text-center text-success fw-semibold">{{ number_format($u->entradas) }}</td>
                        <td class="text-center text-warning-emphasis fw-semibold">{{ number_format($u->salidas) }}</td>
                        <td class="pe-3 text-end text-muted">{{ $u->ultimo }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<div class="card border-0 shadow-sm sca-card">
    <div class="card-header bg-white border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
        <div>
            <h5 class="mb-0 fw-bold">Resultados</h5>
            <small class="text-muted">{{ $bitacoras->total() }} evento(s) encontrado(s)</small>
        </div>
        @if($bitacoras->hasPages())
            <span class="small text-muted">Página {{ $bitacoras->currentPage() }} de {{ $bitacoras->lastPage() }}</span>
        @endif
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">Fecha y Hr</th>
                    <th>Movimiento</th>
                    <th>Estado</th>
                    <th class="text-center">Incidencias</th>
                    <th>Persona</th>
                    <th>Planta</th>
                    <th class="text-end pe-3">Detalle</th>
                </tr>
            </thead>
            <tbody>
                @forelse($bitacoras as $b)
                    @php
                        $esInterno = !str_starts_with((string)$b->persona_id, 'E-');
                        $tipoAcceso = $b->tipo_acceso ?? 'estandar';
                        $tipoAccesoLabel = match($tipoAcceso) {
                            'pase_salida' => 'Pase de Salida',
                            'interplantas' => 'Interplantas',
                            'permiso_entrada' => 'Permiso de Entrada', // V10.3 · CAMBIO #30
                            'visita' => 'Visita',
                            'contratista' => 'Contratista',
                            default => 'Estándar',
                        };
                        $tipoPersona = $esInterno
                            ? 'Interno' . ($b->persona_obj?->puesto?->nombre ? ' · ' . $b->persona_obj->puesto->nombre : '')
                            : (($tipoAcceso === 'contratista' ? 'Contratista' : 'Visita') . ($b->permiso_contexto?->empresa ? ' · ' . $b->permiso_contexto->empresa : ''));
                        $estado = match($b->estado_evento) {
                            'autorizado' => ['Autorizado', 'bg-success bg-opacity-10 text-success border border-success border-opacity-25'],
                            'denegado' => ['Denegado', 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25'],
                            'revision_horario' => ['En revisión', 'bg-warning bg-opacity-10 text-warning-emphasis border border-warning border-opacity-25'],
                            'pendiente_revision' => ['Pendiente', 'bg-info bg-opacity-10 text-info-emphasis border border-info border-opacity-25'],
                            default => [ucfirst(str_replace('_', ' ', $b->estado_evento)), 'bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25'],
                        };
                    @endphp
                    <tr>
                        <td class="ps-3 text-nowrap"><span class="fw-semibold">{{ $b->fecha_hora?->format('d/m/Y') }}</span><small class="d-block text-muted">{{ $b->fecha_hora?->format('H:i') }}</small></td>
                        <td>
                            @if($b->tipo_evento === 'entrada')
                                <span class="text-success fw-semibold"><i class="bi bi-box-arrow-in-right me-1"></i>Entrada</span>
                            @else
                                <span class="text-warning-emphasis fw-semibold"><i class="bi bi-box-arrow-right me-1"></i>Salida</span>
                            @endif
                        </td>
                        <td><span class="badge rounded-pill px-2 py-1 {{ $estado[1] }}">{{ $estado[0] }}</span></td>
                        <td class="text-center text-nowrap">
                            {{-- V10.3.11 (P3): los tooltips ahora muestran el TEXTO real de la
                                 justificación/hallazgo, y debajo de los íconos aparece una línea
                                 visible con ambos (truncada) — antes solo se veía en el detalle. --}}
                            @if($b->revision_exhaustiva || $b->hallazgo)
                                <i class="bi bi-search text-primary fs-6" data-bs-toggle="tooltip" title="{{ $b->hallazgo ? 'F10: ' . $b->hallazgo->resultado . ($b->hallazgo->descripcion_hallazgo ? ' — ' . \Illuminate\Support\Str::limit($b->hallazgo->descripcion_hallazgo, 80) : '') : 'Revisión exhaustiva' }}"></i>
                            @endif
                            @if($b->id_justificacion || $b->estado_evento === 'revision_horario')
                                <i class="bi bi-clock-history text-warning fs-6 ms-1" data-bs-toggle="tooltip" title="{{ $b->justificacion?->nombre ? 'Justificación: ' . $b->justificacion->nombre : 'Justificación de horario' }}"></i>
                            @endif
                            @if(!$b->revision_exhaustiva && !$b->hallazgo && !$b->id_justificacion && $b->estado_evento !== 'revision_horario')
                                <span class="text-muted">—</span>
                            @endif
                            @if($b->justificacion?->nombre || $b->hallazgo?->resultado)
                                <div class="small text-muted text-truncate" style="max-width:150px" title="{{ ($b->justificacion?->nombre ? 'Justificación: ' . $b->justificacion->nombre . ' ' : '') . ($b->hallazgo?->resultado ? 'F10: ' . $b->hallazgo->resultado : '') }}">
                                    @if($b->justificacion?->nombre)<span class="text-warning-emphasis">{{ \Illuminate\Support\Str::limit($b->justificacion->nombre, 24) }}</span>@endif
                                    @if($b->justificacion?->nombre && $b->hallazgo?->resultado) · @endif
                                    @if($b->hallazgo?->resultado)<span class="text-primary-emphasis">{{ \Illuminate\Support\Str::limit($b->hallazgo->resultado, 24) }}</span>@endif
                                </div>
                            @endif
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $b->persona_obj?->nombre_completo ?? 'Desconocido' }}</div>
                            <div class="small text-muted">{{ $tipoPersona }}</div>
                            <div class="small text-primary-emphasis opacity-75">
                                {{ $tipoAccesoLabel }}
                                {{-- V10.3 · CAMBIO #30: referencia directa + badge de inconsistencia --}}
                                {{-- Botón a solicitud: si el acceso fue autorizado por una
                                     solicitud, el código se vuelve un badge clicable que
                                     abre la solicitud en OTRA pestaña (no se pierden los
                                     filtros de bitácora). Funciona para registros nuevos
                                     (id_solicitud del escáner) Y históricos (reglas),
                                     porque clasificar() deja el modelo resuelto en
                                     permiso_contexto. Ruteo por persona_id: "E-x" → EXT,
                                     numérico → INT (convención de
                                     Bitacora::solicitudReferenciada()). --}}
                                @php
                                    $ctxSol = $b->permiso_contexto;
                                    $esExtSol = str_starts_with((string) $b->persona_id, 'E-');
                                    $idSol = $ctxSol
                                        ? ($esExtSol ? ($ctxSol->id_solicitud_ext ?? null) : ($ctxSol->id_solicitud_int ?? null))
                                        : null;
                                    $urlSol = $idSol
                                        ? route($esExtSol ? 'solicitudes.show.ext' : 'solicitudes.show.int', $idSol)
                                        : null;
                                @endphp
                                @if($urlSol)
                                    <a href="{{ $urlSol }}" target="_blank" rel="noopener"
                                       class="badge rounded-pill text-bg-primary text-decoration-none ms-1"
                                       title="Ver la solicitud que autorizó este acceso">
                                        <i class="bi bi-box-arrow-up-right"></i> {{ ($esExtSol ? 'EXT-' : 'INT-') . $idSol }}
                                    </a>
                                @elseif(!empty($b->id_solicitud))
                                    <span class="text-muted" title="Solicitud vinculada">· {{ str_starts_with((string)$b->persona_id, 'E-') ? 'EXT' : 'INT' }}-{{ $b->id_solicitud }}</span>
                                @endif
                                @if($b->solicitud_inconsistente ?? false)
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 ms-1" title="La solicitud referenciada no existe"><i class="bi bi-exclamation-triangle"></i> Inconsistente</span>
                                @endif
                            </div>
                        </td>
                        <td>{{ $b->planta?->nombre ?? 'N/A' }}</td>
                        <td class="text-end pe-3"><button type="button" class="btn btn-sm btn-outline-primary" onclick="verDetalle({{ $b->id_bitacora }})" title="Ver detalle"><i class="bi bi-eye"></i></button></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center py-5"><i class="bi bi-inbox fs-1 text-muted opacity-25"></i><h6 class="fw-bold mt-3 mb-1">No hay registros</h6><p class="text-muted small mb-0">No se encontraron eventos con los filtros seleccionados.</p></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($bitacoras->hasPages())
        <div class="card-footer bg-white border-top d-flex justify-content-center py-3">{{ $bitacoras->links() }}</div>
    @endif
</div>

<div class="modal fade" id="modalDetalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-eye text-primary me-2"></i>Detalle de acceso</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body pt-0" id="detalle-content"></div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(() => {
    const form = document.getElementById('form-filtros');
    const clasificacion = document.getElementById('select-clasificacion');
    const subtipo = document.getElementById('select-subtipo');
    // V10.3.6-fix: eliminado el filtro "Estado permiso" (no está en
    // requisitos; los accesos con permiso ya se identifican con el filtro
    // "Tipo de acceso" — una solicitud pendiente/rechazada jamás deja
    // registro vinculado porque no autoriza nada).
    const externosFilters = document.querySelectorAll('.externos-filter');
    const plantaOrigen = document.getElementById('filtro-planta-origen');
    const plantaDestino = document.getElementById('filtro-planta-destino');
    const motivo = document.getElementById('filtro-motivo');

    const opciones = {
        todos: [{v:'todos',t:'Todos'}],
        internos: [
            {v:'todos',t:'Todos'},
            {v:'estandar',t:'Estándar'},
            {v:'pase_salida',t:'Pase de Salida'},
            {v:'interplantas',t:'Interplantas'},
            // V10.3 · CAMBIO #30: opción nueva en el rebuild JS del subtipo.
            {v:'permiso_entrada',t:'Permiso de Entrada'}
        ],
        externos: [
            {v:'todos',t:'Todos'},
            {v:'visita',t:'Visita'},
            {v:'contratista',t:'Contratista'}
        ]
    };

    function rebuildSubtype() {
        const grupo = clasificacion.value;
        const current = subtipo.value;
        subtipo.innerHTML = '';
        (opciones[grupo] || opciones.todos).forEach(o => {
            const opt = new Option(o.t, o.v);
            if (o.v === current) opt.selected = true;
            subtipo.add(opt);
        });
        if (![...subtipo.options].some(o => o.selected)) subtipo.value = 'todos';
        updateSpecificFilters();
    }

    function toggle(el, visible) {
        if (!el) return;
        el.style.display = visible ? '' : 'none';
        el.querySelectorAll('input,select').forEach(control => {
            control.disabled = !visible;
            if (!visible) {
                // Un filtro oculto jamás debe viajar en la URL y luego afectar
                // una consulta con otra clasificación.
                if (control.tagName === 'SELECT') control.value = '';
                else control.value = '';
            }
        });
    }

    function updateSpecificFilters() {
        const c = clasificacion.value;
        const s = subtipo.value;

        toggle(plantaOrigen, c === 'internos' && s === 'interplantas');
        toggle(plantaDestino, c === 'internos' && s === 'interplantas');
        externosFilters.forEach(el => toggle(el, c === 'externos'));
        toggle(motivo, true);
    }

    clasificacion.addEventListener('change', rebuildSubtype);
    subtipo.addEventListener('change', updateSpecificFilters);
    rebuildSubtype();

    document.getElementById('perPage').addEventListener('change', () => form.submit());

    // V10.3.11 (P7): chips de filtros rápidos — setean campos del form y lo
    // envían. Hoy = 00:00 a ahora; Ayer = día completo de ayer; 7 días = hace
    // 6 días a ahora (7 días naturales incluidos).
    const p2 = v => String(v).padStart(2, '0');
    const isoLocal = d => `${d.getFullYear()}-${p2(d.getMonth() + 1)}-${p2(d.getDate())}T${p2(d.getHours())}:${p2(d.getMinutes())}`;
    const inicioDe = n => { const d = new Date(); d.setHours(0, 0, 0, 0); d.setDate(d.getDate() - n); return d; };
    const fechaDesde = document.getElementById('fecha_desde');
    const fechaHasta = document.getElementById('fecha_hasta');
    const estadoSel = document.getElementById('estado');
    const incidenciaSel = document.getElementById('incidencia');

    document.querySelectorAll('[data-chip]').forEach(btn => {
        btn.addEventListener('click', () => {
            const c = btn.dataset.chip;
            if (c === 'hoy') {
                fechaDesde.value = isoLocal(inicioDe(0));
                fechaHasta.value = isoLocal(new Date());
            } else if (c === 'ayer') {
                const a = inicioDe(1), f = new Date(a);
                f.setHours(23, 59, 0, 0);
                fechaDesde.value = isoLocal(a);
                fechaHasta.value = isoLocal(f);
            } else if (c === 'semana') {
                fechaDesde.value = isoLocal(inicioDe(6));
                fechaHasta.value = isoLocal(new Date());
            } else if (c === 'denegados') {
                estadoSel.value = 'denegado';
            } else if (c === 'pendientes') {
                estadoSel.value = 'pendiente_revision';
            } else if (c === 'hallazgos') {
                incidenciaSel.value = 'hallazgos';
            }
            form.submit();
        });
    });

    document.getElementById('btn-exportar').addEventListener('click', () => {
        const scope = document.getElementById('export_scope').value;
        const params = new URLSearchParams(new FormData(form));
        params.set('export_scope', scope);
        params.set('page', '{{ $bitacoras->currentPage() }}');
        window.location.href = '{{ route('bitacora.exportar') }}?' + params.toString();
    });

    const buscar = document.getElementById('buscar-texto');
    const resultados = document.getElementById('resultados-busqueda');
    let timer = null;

    if (buscar) {
        buscar.addEventListener('input', () => {
            clearTimeout(timer);
            const q = buscar.value.trim();
            if (q.length < 3) {
                resultados.style.display = 'none';
                return;
            }
            timer = setTimeout(() => {
                fetch(`{{ route('bitacora.buscar') }}?q=${encodeURIComponent(q)}`, {headers:{'Accept':'application/json'}})
                    .then(r => r.ok ? r.json() : [])
                    .then(data => {
                        resultados.innerHTML = '';
                        if (!Array.isArray(data) || data.length === 0) {
                            resultados.style.display = 'none';
                            return;
                        }
                        data.forEach(persona => {
                            const a = document.createElement('button');
                            a.type = 'button';
                            a.className = 'list-group-item list-group-item-action';
                            a.innerHTML = `<strong>${persona.nombre ?? persona.texto}</strong><small class="d-block text-muted">${persona.tipo ?? ''} · ID: ${persona.id}</small>`;
                            a.addEventListener('click', () => {
                                buscar.value = persona.id;
                                resultados.style.display = 'none';
                            });
                            resultados.appendChild(a);
                        });
                        resultados.style.display = 'block';
                    })
                    .catch(() => resultados.style.display = 'none');
            }, 250);
        });
        document.addEventListener('click', e => {
            if (!buscar.contains(e.target) && !resultados.contains(e.target)) resultados.style.display = 'none';
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
    });
})();

async function verDetalle(id) {
    const modalEl = document.getElementById('modalDetalle');
    const content = document.getElementById('detalle-content');
    const modal = new bootstrap.Modal(modalEl);
    content.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div><p class="text-muted small mt-3 mb-0">Cargando detalle...</p></div>';
    modal.show();

    try {
        const res = await fetch(`/bitacora/detalle/${id}`, {headers:{'Accept':'application/json'}});
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        const e = data.evento;
        let html = '';

        if (e.estado === 'denegado' && e.motivo_denegacion) {
            html += `<div class="alert alert-danger"><i class="bi bi-x-octagon me-2"></i><strong>Acceso denegado</strong><div class="small mt-1">${e.motivo_denegacion}</div></div>`;
        }

        // V10.3 · CAMBIO #30: aviso si la referencia absoluta quedó huérfana.
        if (e.solicitud_inconsistente) {
            html += `<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i><strong>Referencia inconsistente</strong><div class="small mt-1">${e.detalle_inconsistencia ?? 'La solicitud referenciada no existe.'}</div></div>`;
        }

        html += `<div class="row g-3 mb-3">
            <div class="col-6 col-md-3"><small class="text-muted d-block">Fecha/Hora</small><strong>${e.fecha_hora}</strong></div>
            <div class="col-6 col-md-3"><small class="text-muted d-block">Movimiento</small><strong>${e.tipo}</strong></div>
            <div class="col-6 col-md-3"><small class="text-muted d-block">Estado</small><strong>${e.estado}</strong></div>
            <div class="col-6 col-md-3"><small class="text-muted d-block">Tipo de acceso</small><strong>${e.tipo_acceso ?? 'Estándar'}</strong></div>
            <div class="col-6 col-md-3"><small class="text-muted d-block">Planta</small><strong>${e.planta}</strong></div>
            <div class="col-6 col-md-3"><small class="text-muted d-block">Guardia</small><strong>${e.guardia}</strong></div>
            ${e.solicitud_codigo ? `<div class="col-6 col-md-3"><small class="text-muted d-block">Solicitud vinculada</small><strong>#${e.solicitud_codigo}</strong></div>` : ''}
        </div>`;

        if (data.persona) {
            html += `<div class="d-flex align-items-center gap-3 p-3 rounded bg-light mb-3">
                <img src="${data.persona.foto}" width="64" height="64" class="rounded-circle" style="object-fit:cover" alt="Foto">
                <div><h6 class="mb-1 fw-bold">${data.persona.nombre}</h6><div class="small text-muted">${data.persona.tipo} · ID: ${data.persona.id}</div></div>
            </div>`;
            html += '<div class="row g-2 mb-3">';
            Object.entries(data.persona.extras || {}).forEach(([k,v]) => { html += `<div class="col-6"><small class="text-muted d-block">${k}</small><strong>${v ?? 'N/A'}</strong></div>`; });
            html += '</div>';
        }

        if (data.contexto) {
            html += `<div class="border rounded p-3 bg-success bg-opacity-10"><h6 class="fw-bold text-success"><i class="bi bi-file-earmark-check me-1"></i>Contexto del permiso</h6>
                <div class="row g-2 small">
                    <div class="col-6"><span class="text-muted d-block">Tipo</span><strong>${data.contexto.tipo_permiso}</strong></div>
                    <div class="col-6"><span class="text-muted d-block">Estado</span><strong>${data.contexto.estado}</strong></div>
                    <div class="col-12"><span class="text-muted d-block">Vigencia</span><strong>${data.contexto.vigencia}</strong></div>
                    ${data.contexto.ruta ? `<div class="col-12"><span class="text-muted d-block">Ruta</span><strong>${data.contexto.ruta}</strong></div>` : ''}
                    ${data.contexto.empresa ? `<div class="col-12"><span class="text-muted d-block">Empresa</span><strong>${data.contexto.empresa}</strong></div>` : ''}
                    ${data.contexto.anfitrion ? `<div class="col-12"><span class="text-muted d-block">Anfitrión</span><strong>${data.contexto.anfitrion}</strong></div>` : ''}
                    ${data.contexto.motivo ? `<div class="col-12"><span class="text-muted d-block">Motivo</span>${data.contexto.motivo}</div>` : ''}
                    ${data.contexto.creador ? `<div class="col-12"><span class="text-muted d-block">Creador</span><strong>${data.contexto.creador}</strong></div>` : ''}
                    ${data.contexto.url_solicitud ? `<div class="col-12 mt-2"><a href="${data.contexto.url_solicitud}" target="_blank" rel="noopener" class="btn btn-sm btn-success"><i class="bi bi-box-arrow-up-right me-1"></i>Ver solicitud completa</a></div>` : ''}
                </div></div>`;
        }

        if (e.justificacion) html += `<div class="alert alert-warning mt-3 mb-0"><i class="bi bi-clock-history me-1"></i><strong>Justificación:</strong> ${e.justificacion}</div>`;
        if (e.f10) html += `<div class="alert alert-info mt-3 mb-0"><i class="bi bi-search me-1"></i><strong>Revisión:</strong> ${e.f10.resultado}${e.f10.desc ? ` — ${e.f10.desc}` : ''}</div>`;
        content.innerHTML = html;
    } catch (err) {
        content.innerHTML = '<div class="alert alert-danger mb-0">No fue posible cargar el detalle del evento.</div>';
    }
}
</script>
@endpush
@endsection
