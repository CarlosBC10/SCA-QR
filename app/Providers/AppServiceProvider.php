<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // ===== LOGGER DE QUERIES (solo en local) =====
        // Cuenta queries y mide tiempo. Ver storage/logs/laravel.log
        // Desactivar en producción.
        if (app()->environment('local')) {
            DB::listen(function ($query) {
                $sql = $query->sql;
                $bindings = $query->bindings;
                $time = $query->time;

                // Solo loggear queries lentas (>50ms) o todas si quieres debug completo
                if ($time > 50) {
                    Log::channel('single')->debug("[SLOW QUERY {$time}ms] {$sql}", $bindings);
                }
            });
        }
    }
}