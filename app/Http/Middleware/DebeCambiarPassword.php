<?php
// app/Http/Middleware/DebeCambiarPassword.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DebeCambiarPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        if (str_starts_with($user->password, 'temp:')) {
            
            // Rutas permitidas sin haber cambiado la contraseña
            $rutaActual = $request->path();
            $rutasPermitidas = ['password/cambiar', 'logout', 'api/notificaciones'];

            foreach ($rutasPermitidas as $ruta) {
                if (str_starts_with($rutaActual, $ruta)) {
                    return $next($request);
                }
            }

            // Si intenta ir a otra ruta, lo redirigimos SIEMPRE al cambio de contraseña
            return redirect()->route('password.cambiar')
                ->with('warning', 'Debe cambiar su contraseña temporal antes de continuar.');
        }

        return $next($request);
    }
}