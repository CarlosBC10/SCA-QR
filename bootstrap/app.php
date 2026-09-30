<?php
// bootstrap/app.php
//
// Configuración del framework Laravel.
// Aquí registramos el alias 'role' para poder usar
// ->middleware('role:Superadmin,Admin') en las rutas.

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'debe_cambiar_password' => \App\Http\Middleware\DebeCambiarPassword::class,
        ]);

        // Middleware global para deshabilitar el caché del navegador.
        // Evita que el navegador muestre páginas viejas cuando cambias de usuario.
        $middleware->append(\App\Http\Middleware\NoCacheResponses::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();