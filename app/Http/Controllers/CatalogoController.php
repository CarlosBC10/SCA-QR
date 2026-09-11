<?php
// app/Http/Controllers/CatalogoController.php
//
// CONTROLADOR DE CATÁLOGOS (API).
//
// Devuelve catálogos estáticos para los dropdowns del frontend.
// Por ahora solo tiene /api/justificaciones (para el panel del guardia).
// Si en el futuro necesitas más catálogos (roles, plantas, etc.),
// agrégalos aquí.

namespace App\Http\Controllers;

use App\Models\JustificacioneHorario;
use Illuminate\Http\Request;

class CatalogoController extends Controller
{
    /**
     * GET /api/justificaciones
     * Devuelve el catálogo de justificaciones de horario.
     * Lo usa el panel del guardia para llenar el dropdown cuando
     * debe justificar un acceso fuera de horario (estado='revision_horario').
     *
     * Acceso: cualquier usuario autenticado (el guardia necesita verlo).
     */
    public function justificaciones()
    {
        // FIX catálogo: devolver también la categoría para que el dropdown
        // del guardia pueda agrupar con <optgroup> y sea más fácil de buscar.
        // Ordenar por categoría primero, luego por nombre.
        $justificaciones = JustificacioneHorario::orderBy('categoria')
            ->orderBy('nombre')
            ->get(['id_justificacion', 'nombre', 'descripcion', 'categoria']);

        return response()->json($justificaciones);
    }
}