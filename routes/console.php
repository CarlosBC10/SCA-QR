<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Console\Commands\ExpirarSolicitudes;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ============================================================
// TAREAS PROGRAMADAS
// ============================================================

// Expirar solicitudes cada 5 minutos.
Schedule::command(ExpirarSolicitudes::class)->everyFiveMinutes();