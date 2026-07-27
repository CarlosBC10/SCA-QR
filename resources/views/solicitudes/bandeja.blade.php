{{-- resources/views/solicitudes/bandeja.blade.php --}}
@extends('layouts.app')

@section('title', 'Bandeja de Aprobaciones')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Bandeja de Aprobaciones</h1>
        <p class="text-muted mb-0">Solicitudes pendientes de su autorización</p>
    </div>
    <a href="{{ route('solicitudes.hub') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

{{-- Pendientes de Origen --}}
<div class="card border-0 shadow-sm mb-4 sca-card" style="border-radius:12px;">
    <div class="card-header bg-transparent py-3 d-flex align-items-center">
        <i class="bi bi-box-arrow-right text-warning me-2"></i>
        <h5 class="mb-0 fw-bold">Pendientes de Origen</h5>
        <span class="badge bg-warning text-dark ms-auto">{{ $pendientesOrigen->count() }}</span>
    </div>
    <div class="card-body p-0">
        @forelse($pendientesOrigen as $s)
        <div class="d-flex align-items-center justify-content-between px-3 py-3 border-bottom">
            <div>
                <strong>Solicitud #{{ $s->id }}</strong> 
                <span class="badge bg-secondary ms-1">{{ ucfirst($s->tipo) }}</span><br>
                <small class="text-muted">
                    @if($s->persona) {{ $s->persona->nombre_completo }} · @endif
                    Para: {{ $s->plantaDestino?->nombre }} · Creada: {{ $s->created_at->format('d/m H:i') }}
                </small>
            </div>
            <a href="{{ route('solicitudes.show', $s->id) }}" class="btn btn-sm btn-outline-success">
                Revisar <i class="bi bi-arrow-right"></i>
            </a>
        </div>
        @empty
        <div class="text-center text-muted py-4">
            <i class="bi bi-check-circle fs-1 text-success opacity-50"></i>
            <p class="mt-2 mb-0">No hay solicitudes pendientes de origen</p>
        </div>
        @endforelse
    </div>
</div>

{{-- Pendientes de Destino --}}
<div class="card border-0 shadow-sm sca-card" style="border-radius:12px;">
    <div class="card-header bg-transparent py-3 d-flex align-items-center">
        <i class="bi bi-box-arrow-in-right text-info me-2"></i>
        <h5 class="mb-0 fw-bold">Pendientes de Destino</h5>
        <span class="badge bg-info text-dark ms-auto">{{ $pendientesDestino->count() }}</span>
    </div>
    <div class="card-body p-0">
        @forelse($pendientesDestino as $s)
        <div class="d-flex align-items-center justify-content-between px-3 py-3 border-bottom">
            <div>
                <strong>Solicitud #{{ $s->id }}</strong> 
                <span class="badge bg-secondary ms-1">{{ ucfirst($s->tipo) }}</span><br>
                <small class="text-muted">
                    @if($s->persona) {{ $s->persona->nombre_completo }} · @endif
                    De: {{ $s->plantaOrigen?->nombre ?? 'Externo' }} · Creada: {{ $s->created_at->format('d/m H:i') }}
                </small>
            </div>
            <a href="{{ route('solicitudes.show', $s->id) }}" class="btn btn-sm btn-outline-success">
                Revisar <i class="bi bi-arrow-right"></i>
            </a>
        </div>
        @empty
        <div class="text-center text-muted py-4">
            <i class="bi bi-check-circle fs-1 text-success opacity-50"></i>
            <p class="mt-2 mb-0">No hay solicitudes pendientes de destino</p>
        </div>
        @endforelse
    </div>
</div>

@endsection