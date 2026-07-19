{{-- resources/views/dashboards/aprobador.blade.php --}}
@extends('layouts.app')
@section('title', 'Panel Aprobador')
@section('content')
<h1 class="h3 mb-3">Panel Aprobador</h1>
<p class="text-muted">Bienvenido, {{ Auth::user()->nombre_completo }}.</p>
<p>Próximamente: bandeja de solicitudes pendientes de aprobación.</p>
@endsection