{{-- resources/views/dashboards/empleado.blade.php --}}
@extends('layouts.app')
@section('title', 'Panel Empleado')
@section('content')
<h1 class="h3 mb-3">Panel Empleado</h1>
<p class="text-muted">Bienvenido, {{ Auth::user()->nombre_completo }}.</p>
<p>Próximamente: crear y consultar tus solicitudes (pase de salida, interplantas, visita, contratista).</p>
@endsection