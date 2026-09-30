<?php
// app/Http/Middleware/CheckRole.php
//
// Middleware de autorización por rol.
// Verifica que el usuario autenticado tenga uno de los roles permitidos.
// Si no, bloquea con error 403 (Forbidden).
//
// Sin este middleware, cualquier usuario autenticado (incluso un guardia)
// podría entrar a /superadmin/dashboard escribiendo la URL a mano.
// Esto cumple el SRS SE-03: "restringir el acceso según el perfil".

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Verifica que el usuario autenticado tenga uno de los roles permitidos.
     *
     * Uso en rutas: ->middleware('role:Superadmin,Admin')
     * Los roles llegan como parámetros y se convierten a array con 'string ...$roles'.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // 1. Obtener el usuario autenticado (debe pasar antes por middleware 'auth')
        $user = $request->user();

        // 2. Si no hay usuario o su rol no está en la lista permitida → bloquear
        if (!$user || !in_array($user->rol->nombre, $roles)) {
            abort(403, 'No tiene permisos para acceder a esta sección.');
        }

        // 3. Si todo OK, dejar pasar al controlador
        return $next($request);
    }
}