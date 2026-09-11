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
// FIX Bug 7: Reducir a cada 1 minuto para minimizar latencia entre
// vencimiento y persistencia en BD (los accessors ya no escriben).
Schedule::command(ExpirarSolicitudes::class)->everyMinute();

// ============================================================
// V10.3 · CAMBIO #35 (nuevo): verificador de trazabilidad de
// bitacora.id_solicitud (FK lógico). Reporta huérfanos, ruteo
// incoherente y externos sin referencia. Solo REPORTA (no corrige).
// Diario a las 06:00; también manual:
//   php artisan sca:verificar-bitacora
//   php artisan sca:verificar-bitacora --desde=2026-09-15
// ============================================================
Schedule::command(\App\Console\Commands\VerificarBitacora::class)->dailyAt('06:00');