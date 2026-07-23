<?php
// app/Http/Controllers/ClasificacionPlantaController.php

namespace App\Http\Controllers;

use App\Models\ClasificacionPlanta;
use App\Models\Planta;
use App\Models\Sitec\Clasificacion;
use App\Models\Sitec\Empleado;
use App\Models\LogSistema;
use Illuminate\Http\Request;

class ClasificacionPlantaController extends Controller
{
    /**
     * Lista todas las clasificaciones de sitec con su mapeo actual.
     */
    public function index()
    {
        $clasificaciones = Clasificacion::orderBy('nombre')->get();
        $mapeos = ClasificacionPlanta::with('planta')
            ->get()
            ->keyBy('id_clasificacion');
        $plantas = Planta::where('activa', true)->orderBy('nombre')->get();
        $conteoEmpleados = Empleado::selectRaw('id_clasificacion, COUNT(*) as total')
            ->whereNotNull('id_clasificacion')
            ->groupBy('id_clasificacion')
            ->pluck('total', 'id_clasificacion');

        return view('clasificaciones.index', compact(
            'clasificaciones', 'mapeos', 'plantas', 'conteoEmpleados'
        ));
    }

    /**
     * Guarda/actualiza el mapeo de una clasificación a una planta.
     */
    public function update(Request $request, int $idClasificacion)
    {
        $request->validate([
            'planta_id' => 'required|integer|exists:plantas,id',
        ]);

        $clasif = Clasificacion::find($idClasificacion);
        if (!$clasif) {
            // No invalidamos caché porque no modificamos nada
            return back()->withErrors(['error' => 'La clasificación no existe en sitec.']);
        }

        ClasificacionPlanta::updateOrCreate(
            ['id_clasificacion' => $idClasificacion],
            [
                'nombre_clasificacion' => $clasif->nombre,
                'planta_id'            => $request->planta_id,
            ]
        );

        LogSistema::registrar(
            'MAPEAR_CLASIFICACION',
            "Clasificación '{$clasif->nombre}' (ID {$idClasificacion}) → Planta ID {$request->planta_id}"
        );

        // Invalidar caché solo cuando SÍ se modificó el mapeo
        \App\Services\PlantaCacheService::invalidar();

        return back()->with('success', "Mapeo de '{$clasif->nombre}' actualizado.");
    }

    /**
     * Elimina un mapeo (la clasificación queda sin planta).
     */
    public function destroy(int $idClasificacion)
    {
        $mapeo = ClasificacionPlanta::find($idClasificacion);
        if (!$mapeo) {
            // No invalidamos caché porque no había nada que borrar
            return back()->with('error', 'El mapeo no existía.');
        }

        $nombre = $mapeo->nombre_clasificacion;
        $mapeo->delete();

        LogSistema::registrar(
            'ELIMINAR_MAPEO',
            "Mapeo de '{$nombre}' eliminado"
        );

        // Invalidar caché solo cuando SÍ se borró
        \App\Services\PlantaCacheService::invalidar();

        return back()->with('success', "Mapeo de '{$nombre}' eliminado.");
    }
}