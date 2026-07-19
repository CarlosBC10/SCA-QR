{{-- resources/views/dashboards/admin.blade.php --}}
@extends('layouts.app')
@section('title', 'Panel Admin')
@section('content')
<h1 class="h3 mb-3">Panel Admin</h1>
<p class="text-muted">Bienvenido, {{ Auth::user()->nombre_completo }}.</p>
<p>Acceso global a las 3 plantas. Próximamente: gestión de usuarios y aprobaciones.</p>
@endsection