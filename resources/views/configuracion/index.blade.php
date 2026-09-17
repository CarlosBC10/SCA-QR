{{-- resources/views/configuracion/index.blade.php --}}
{{-- Módulo de Configuración del Sistema (Admin/Superadmin). --}}
{{-- Cada tarjeta = una sección = un formulario independiente. --}}
{{-- Los defaults y textos de ayuda vienen del catálogo --}}
{{-- ConfigSistemaService::DEFECTOS (única fuente de verdad). --}}
@extends('layouts.app')

@section('title', 'Configuración del sistema')

@section('content')
<div class="sca-page-head legacy-page-head">
    <div>
        <div class="sca-page-eyebrow"><i class="bi bi-gear-wide-connected" aria-hidden="true"></i> Sistema</div>
        <h1 class="sca-page-title">Configuración del sistema</h1>
        <p class="sca-page-subtitle">Parámetros operativos del control de acceso. Los cambios aplican de inmediato desde el próximo escaneo.</p>
    </div>
</div>

{{-- SCA-MODAL: el feedback de éxito/restauración llega como flash
     estructurado 'sca_modal' y lo dispara el LAYOUT (ScaModal) al
     cargar la página — no se pinta aquí. En esta zona solo quedan
     los errores de validación del último POST (estándar del sistema,
     igual que usuarios/create). --}}
@if($errors->any())
    <div class="alert alert-danger" role="alert">
        <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Corrige los valores marcados:</div>
        <ul class="mb-0 small">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@php
    // Etiqueta legible del default según tipo: int → "10%", ids → "nadie".
    $defaultLegible = function(array $c): string {
        if ($c['meta']['tipo'] === 'ids') {
            return $c['meta']['valor'] === '' ? 'nadie' : $c['meta']['valor'];
        }
        return $c['meta']['valor'] . $c['meta']['sufijo'];
    };
@endphp

<div class="row g-4">
@foreach($secciones as $idSeccion => $seccion)
    <div class="col-12">
        <div class="card border-0 shadow-sm sca-card" style="border-radius: 12px;">

            {{-- Cabecera: título + descripción + restaurar (formulario aparte, no anidado) --}}
            <div class="card-header bg-transparent border-0 pt-4 px-4 d-flex justify-content-between align-items-start gap-3">
                <div>
                    <h2 class="h5 fw-bold mb-1">
                        <i class="bi {{ $seccion['icono'] }} me-2 text-primary"></i>{{ $seccion['titulo'] }}
                    </h2>
                    <p class="text-muted small mb-0">{{ $seccion['descripcion'] }}</p>
                </div>
                {{-- SCA-MODAL: la confirmación la hace ScaModal.confirm()
                     en el JS del final (clase .sca-form-restaurar); se
                     eliminó el confirm() nativo del navegador. --}}
                <form method="POST" action="{{ route('configuracion.restaurar') }}"
                      class="sca-form-restaurar" data-titulo="{{ $seccion['titulo'] }}">
                    @csrf
                    <input type="hidden" name="seccion" value="{{ $idSeccion }}">
                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Restaurar por defecto
                    </button>
                </form>
            </div>

            {{-- Formulario de la sección: un POST guarda todos sus campos.
                 SCA-MODAL: el JS intercepta el submit (.sca-form-config)
                 y muestra el loading "Guardando..." durante la navegación. --}}
            <form method="POST" action="{{ route('configuracion.update') }}" class="sca-form-config">
                @csrf
                <input type="hidden" name="seccion" value="{{ $idSeccion }}">
                <div class="card-body px-4 py-3">
@foreach(\App\Services\ConfigSistemaService::clavesDeSeccion($idSeccion) as $clave)
                    @php($c = $config[$clave])
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-1">
                            <label for="{{ $clave }}" class="form-label mb-0 fw-semibold">{{ $c['meta']['etiqueta'] }}</label>
                            @if($c['es_default'])
                                {{-- Sin fila en BD → está en default (valor del catálogo) --}}
                                <span class="badge text-bg-secondary">
                                    Por defecto · {{ $defaultLegible($c) }}
                                </span>
                            @else
                                {{-- Desviación guardada: mostrar quién/cuándo para auditoría rápida --}}
                                <span class="badge text-bg-warning text-dark" data-bs-toggle="tooltip"
                                      title="Valor actual distinto al default del sistema">
                                    Modificado{{ $c['actualizado_nombre'] ? ' · ' . $c['actualizado_nombre'] : '' }}{{ $c['updated_at'] ? ' · ' . $c['updated_at']->format('d/m/Y H:i') : '' }}
                                </span>
                            @endif
                        </div>

                        @if($c['meta']['tipo'] === 'int')
                            <input type="number" class="form-control {{ $errors->has($clave) ? 'is-invalid' : '' }}"
                                   id="{{ $clave }}" name="{{ $clave }}" value="{{ old($clave, $c['valor_input']) }}"
                                   min="{{ $c['meta']['min'] }}" max="{{ $c['meta']['max'] }}"
                                   style="max-width: 220px;">
                        @else
                            <input type="text" class="form-control {{ $errors->has($clave) ? 'is-invalid' : '' }}"
                                   id="{{ $clave }}" name="{{ $clave }}" value="{{ old($clave, $c['valor_input']) }}"
                                   placeholder="Ej: 1001,23846" style="max-width: 320px;">
                        @endif

                        <div class="form-text" style="max-width: 720px;">{{ $c['meta']['descripcion'] }}</div>

                        {{-- Pista en vivo SOLO para la probabilidad (parámetro más delicado) --}}
                        @if($clave === 'probabilidad_revision')
                            <div class="small mt-1" id="hint-probabilidad">
                                <i class="bi bi-info-circle me-1"></i>
                                <span id="hint-probabilidad-texto"></span>
                            </div>
                        @endif

                        @error($clave)
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
@endforeach
                </div>
                <div class="card-footer bg-transparent border-0 px-4 pb-4 pt-0">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2 me-1"></i>Guardar «{{ $seccion['titulo'] }}»
                    </button>
                </div>
            </form>
        </div>
    </div>
@endforeach
</div>
@endsection

@push('scripts')
<script>
// ============================================================
// Pista en vivo de la probabilidad F10: traduce el número a
// lenguaje operativo ("de cada 100 escaneos, ~10 caerán") y
// advierte en los extremos (0 = desactivada, 100 = saturación).
// ============================================================
(function () {
    const input   = document.getElementById('probabilidad_revision');
    const hintTxt = document.getElementById('hint-probabilidad-texto');
    const hintBox = document.getElementById('hint-probabilidad');
    if (!input || !hintTxt || !hintBox) return;

    function actualizar() {
        const v = parseInt(input.value, 10);
        if (isNaN(v)) { hintTxt.textContent = ''; return; }

        if (v === 0) {
            hintTxt.textContent = 'Revisión desactivada: ningún escaneo caerá en revisión aleatoria.';
            hintBox.className = 'small mt-1 text-danger';
        } else if (v >= 100) {
            hintTxt.textContent = 'Advertencia: TODOS los escaneos de operativos/administrativos caerán en revisión (satura al guardia).';
            hintBox.className = 'small mt-1 text-danger';
        } else if (v > 30) {
            hintTxt.textContent = 'De cada 100 escaneos, ~' + v + ' caerán en revisión (frecuencia alta).';
            hintBox.className = 'small mt-1 text-warning-emphasis';
        } else {
            hintTxt.textContent = 'De cada 100 escaneos, ~' + v + ' caerán en revisión.';
            hintBox.className = 'small mt-1 text-muted';
        }
    }

    input.addEventListener('input', actualizar);
    actualizar(); // estado inicial
})();

// ============================================================
// SCA-MODAL — Integración del módulo de configuración.
//
// 1) Guardar (.sca-form-config): al enviar mostramos el loading
//    "Guardando...". sca-modal.js congela ese loading durante la
//    navegación (evento beforeunload) y la página nueva dispara el
//    modal de éxito transportado por el flash 'sca_modal' que puso
//    el controller. Si la validación falla, la página recarga con
//    los errores pintados (alerta roja + campos is-invalid), igual
//    que el resto de formularios del sistema.
//
// 2) Restaurar (.sca-form-restaurar): ScaModal.confirm (warning)
//    reemplaza al confirm() nativo del navegador. Al confirmar:
//    loading + submit real. OJO: form.submit() programático NO
//    redispara el evento submit, por eso no entra en loop.
// ============================================================
document.addEventListener('DOMContentLoaded', function () {

    // ===== 1. Guardar: loading durante el POST =====
    document.querySelectorAll('.sca-form-config').forEach(form => {
        form.addEventListener('submit', function () {
            ScaModal.loading('Guardando configuración', 'Aplicando los cambios en caliente...');
        });
    });

    // ===== 2. Restaurar: confirmación con ScaModal =====
    document.querySelectorAll('.sca-form-restaurar').forEach(form => {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const titulo = form.dataset.titulo;

            ScaModal.confirm({
                titulo: '¿Restaurar valores por defecto?',
                mensaje: 'La sección <strong>' + titulo + '</strong> volverá a sus valores de fábrica.'
                tipo: 'warning',
                textoConfirmar: 'Sí, restaurar',
                textoCancelar: 'Cancelar',
                onConfirm: () => {
                    ScaModal.loading('Restaurando', 'Devolviendo los valores por defecto...');
                    form.submit();
                }
            });
        });
    });
});
</script>
@endpush