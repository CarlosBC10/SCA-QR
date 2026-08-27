<?php
// app/Http/Middleware/NoCacheResponses.php
//
// Middleware que deshabilita el caché del navegador para TODAS las
// respuestas HTTP. Esto evita que el navegador muestre páginas viejas
// cacheadas cuando el usuario cambia de sesión.
//
// Especialmente importante para el panel de guardia, donde mostrar la
// planta del usuario anterior es un bug de seguridad.

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class NoCacheResponses
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Aplicar headers anti-caché a todas las respuestas
        $response->headers->set('Cache-Control', 'no-cache, no-store, max-age=0, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Fri, 01 Jan 1990 00:00:00 GMT');

        return $response;
    }
}