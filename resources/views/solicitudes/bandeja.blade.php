{{-- resources/views/solicitudes/bandeja.blade.php --}}
@extends('layouts.app')

@section('title', 'Bandeja de Aprobaciones')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Bandeja de Aprobaciones</h1>
        <p class="text-muted mb-0">Solicitudes pendientes de su autorización</p>
    </div>
    <a href="javascript:history.back()" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

@if(session('success'))
<div class="alert alert-success" style="border-radius:12px;">{{ session('success') }}</div>
@endif
@if(session('error') || $errors->any())
<div class="alert alert-danger" style="border-radius:12px;">
    {{ session('error') }}
    @foreach ($errors->all() as $error) <br>{{ $error }} @endforeach
</div>
@endif

{{-- FORMULARIO DE FILTROS --}}
<form method="GET" class="card border-0 shadow-sm mb-4 sca-card" style="border-radius:12px;">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-muted">Tipo de Solicitud</label>
                <select name="tipo" class="form-select">
                    <option value="">Todas</option>
                    <option value="pase_salida" @if(request('tipo')=='pase_salida') selected @endif>Pase de Salida</option>
                    <option value="interplantas" @if(request('tipo')=='interplantas') selected @endif>Interplantas</option>
                    <option value="visita" @if(request('tipo')=='visita') selected @endif>Visita</option>
                    <option value="contratista" @if(request('tipo')=='contratista') selected @endif>Contratista</option>
                </select>
            </div>
            @if(Auth::user()->isAdmin() || Auth::user()->isSuperadmin())
            <div class="col-md-4">
                <label class="form-label small text-muted">Planta (Origen o Destino)</label>
                <select name="planta" class="form-select">
                    <option value="">Todas</option>
                    @foreach($plantas as $p)
                        <option value="{{ $p->id_planta }}" @if(request('planta')==$p->id_planta) selected @endif>{{ $p->nombre }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-md-4">
                <button type="submit" class="btn btn-outline-success w-100">
                    <i class="bi bi-search"></i> Filtrar
                </button>
            </div>
        </div>
    </div>
</form>

{{-- ===================================================== --}}
{{-- 1. PENDIENTES DE ORIGEN (solicitudes_int) --}}
{{-- ===================================================== --}}
<div class="card border-0 shadow-sm mb-4 sca-card" style="border-radius:12px;">
    <div class="card-header bg-transparent py-3 d-flex align-items-center">
        <i class="bi bi-box-arrow-right text-warning me-2"></i>
        <h5 class="mb-0 fw-bold">Pendientes de Origen</h5>
        <span class="badge bg-warning text-dark ms-auto">{{ $pendientesOrigen->count() }}</span>
    </div>
    <div class="card-body p-0">
        @forelse($pendientesOrigen as $s)
        <div class="d-flex align-items-center justify-content-between px-3 py-3 border-bottom flex-wrap gap-2">
            <div class="flex-grow-1">
                <strong>Solicitud #{{ $s->id_solicitud_int }}</strong>
                <span class="badge bg-secondary ms-1">{{ ucfirst($s->tipo) }}</span>
                @if($s->empleados->isNotEmpty())
                    <span class="badge bg-info text-dark ms-1">{{ $s->empleados->count() }} emp.</span>
                @endif
                <br>
                <small class="text-muted">
                    Para: {{ $s->plantaDestino?->nombre }} · Creada: {{ $s->created_at->format('d/m H:i') }}
                </small>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('solicitudes.show.int', $s->id_solicitud_int) }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-eye"></i> Revisar
                </a>
            </div>
        </div>
        @empty
        <div class="text-center text-muted py-4">
            <i class="bi bi-check-circle fs-1 text-success opacity-50"></i>
            <p class="mt-2 mb-0">No hay solicitudes pendientes de origen</p>
        </div>
        @endforelse
    </div>
</div>

{{-- ===================================================== --}}
{{-- 2. PENDIENTES DE DESTINO (solicitudes_int) --}}
{{-- ===================================================== --}}
<div class="card border-0 shadow-sm mb-4 sca-card" style="border-radius:12px;">
    <div class="card-header bg-transparent py-3 d-flex align-items-center">
        <i class="bi bi-box-arrow-in-right text-info me-2"></i>
        <h5 class="mb-0 fw-bold">Pendientes de Destino (Interplantas)</h5>
        <span class="badge bg-info text-dark ms-auto">{{ $pendientesDestino->count() }}</span>
    </div>
    <div class="card-body p-0">
        @forelse($pendientesDestino as $s)
        <div class="d-flex align-items-center justify-content-between px-3 py-3 border-bottom flex-wrap gap-2">
            <div class="flex-grow-1">
                <strong>Solicitud #{{ $s->id_solicitud_int }}</strong>
                <span class="badge bg-secondary ms-1">Interplantas</span>
                @if($s->empleados->isNotEmpty())
                    <span class="badge bg-info text-dark ms-1">{{ $s->empleados->count() }} emp.</span>
                @endif
                <br>
                <small class="text-muted">
                    De: {{ $s->plantaOrigen?->nombre }} · Creada: {{ $s->created_at->format('d/m H:i') }}
                </small>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('solicitudes.show.int', $s->id_solicitud_int) }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-eye"></i> Revisar
                </a>
            </div>
        </div>
        @empty
        <div class="text-center text-muted py-4">
            <i class="bi bi-check-circle fs-1 text-success opacity-50"></i>
            <p class="mt-2 mb-0">No hay solicitudes pendientes de destino</p>
        </div>
        @endforelse
    </div>
</div>

{{-- ===================================================== --}}
{{-- 3. PENDIENTES DE EXTERNOS (solicitudes_ext: visita/contratista) --}}
{{-- ===================================================== --}}
<div class="card border-0 shadow-sm sca-card" style="border-radius:12px;">
    <div class="card-header bg-transparent py-3 d-flex align-items-center">
        <i class="bi bi-people text-success me-2"></i>
        <h5 class="mb-0 fw-bold">Pendientes de Visitantes/Contratistas</h5>
        <span class="badge bg-success ms-auto">{{ $pendientesExt->count() }}</span>
    </div>
    <div class="card-body p-0">
        @forelse($pendientesExt as $s)
        <div class="d-flex align-items-center justify-content-between px-3 py-3 border-bottom flex-wrap gap-2">
            <div class="flex-grow-1">
                <strong>Solicitud #{{ $s->id_solicitud_ext }}</strong>
                <span class="badge bg-secondary ms-1">{{ ucfirst($s->tipo) }}</span>
                @if($s->externos->isNotEmpty())
                    <span class="badge bg-info text-dark ms-1">{{ $s->externos->count() }} persona(s)</span>
                @endif
                <br>
                <small class="text-muted">
                    @if($s->externos->isNotEmpty())
                        {{ $s->externos->first()->externo?->nombre_completo }}
                        @if($s->externos->count() > 1) y {{ $s->externos->count() - 1 }} más @endif ·
                    @endif
                    Planta: {{ $s->plantaDestino?->nombre }} · Creada: {{ $s->created_at->format('d/m H:i') }}
                </small>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('solicitudes.show.ext', $s->id_solicitud_ext) }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-eye"></i> Revisar
                </a>
            </div>
        </div>
        @empty
        <div class="text-center text-muted py-4">
            <i class="bi bi-check-circle fs-1 text-success opacity-50"></i>
            <p class="mt-2 mb-0">No hay solicitudes de visitantes/contratistas pendientes</p>
        </div>
        @endforelse
    </div>
</div>

@endsection