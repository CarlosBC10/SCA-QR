<?php
// app/Http/Controllers/ClasificacionPlantaController.php
//
// CAMBIO V4: FK planta_id → id_planta en validación y queries.

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
     * Lista clasificaciones de sitec con su mapeo actual.
     */
    public function index()
    {
        $clasificaciones = Clasificacion::orderBy('nombre')->get();
        $mapeos = ClasificacionPlanta::with('planta')
            ->get()
            ->keyBy('id_clasificacion');
        $plantas = Planta::where('activa', true)->orderBy('nombre')->get();

        // Conteo de empleados por clasificación (1 query agrupada a sitec)
        $conteoEmpleados = Empleado::selectRaw('id_clasificacion, COUNT(*) as total')
            ->whereNotNull('id_clasificacion')
            ->groupBy('id_clasificacion')
            ->pluck('total', 'id_clasificacion');

        return view('clasificaciones.index', compact(
            'clasificaciones', 'mapeos', 'plantas', 'conteoEmpleados'
        ));
    }

    /**
     * FIX E: Actualiza el mapeo de una clasificación a una planta.
     */
    public function update(Request $request, $idClasificacion)
    {
        $request->validate([
            'id_planta' => ['required', 'integer', 'exists:plantas,id_planta'],
            'forzar_todos' => ['nullable', 'boolean'],
        ]);

        $user = $request->user(); // FIX: Capturar quién hizo el cambio
        $idPlanta = (int) $request->id_planta;
        $forzarTodos = $request->boolean('forzar_todos');

        $clasificacion = \App\Models\Sitec\Clasificacion::on('sitec')->find($idClasificacion);
        $nombreClas = $clasificacion?->nombre ?? "Clasificación {$idClasificacion}";

        // 1. Actualizar la tabla de mapeo
        \App\Models\ClasificacionPlanta::updateOrCreate(
            ['id_clasificacion' => $idClasificacion],
            [
                'id_planta' => $idPlanta,
                'nombre_clasificacion' => $nombreClas
            ]
        );

        // 2. Buscar empleados en sitec con esta clasificación
        $empleadosIds = \App\Models\Sitec\Empleado::on('sitec')
            ->where('id_clasificacion', $idClasificacion)
            ->where('estatus', 'Alta')
            ->pluck('id_empleado');

        $empleadosActualizados = 0;

        if ($empleadosIds->isNotEmpty()) {
            $query = \App\Models\EmpleadoExtSca::whereIn('empleado_id', $empleadosIds);

            if (!$forzarTodos) {
                $query->where(function ($q) {
                    $q->where('planta_forzada', false)->orWhereNull('planta_forzada');
                });
            }

            $empleadosActualizados = $query->update([
                'id_planta' => $idPlanta,
                'planta_forzada' => false, 
            ]);
        }

        // 3. Registrar en Log de Sistema
        $plantaNombre = \App\Services\PlantaCacheService::nombrePlanta($idPlanta);
        \App\Models\LogSistema::registrar(
            'MAPEO_CLASIFICACION_PLANTA',
            "El usuario {$user->nombre_completo} ({$user->username}) mapeó la clasificación '{$nombreClas}' a la planta '{$plantaNombre}'. Empleados afectados: {$empleadosActualizados}. Forzado: " . ($forzarTodos ? 'Sí' : 'No')
        );

        // 4. Notificación global
        $this->notificarCambioMapeo($idClasificacion, $idPlanta, $empleadosActualizados, $forzarTodos, $user);

        return redirect()->route('clasificaciones.index')
            ->with('success', "Mapeo actualizado. {$empleadosActualizados} empleados afectados.");
    }

    /**
     * FIX E: Envía notificación a todos los Aprobadores, Admins y Supers.
     */
    private function notificarCambioMapeo($idClas, $idPlanta, $afectados, $forzado, $user)
    {
        $plantaNombre = \App\Services\PlantaCacheService::nombrePlanta($idPlanta);
        $clasificacion = \App\Models\Sitec\Clasificacion::on('sitec')->find($idClas);
        $clasNombre = $clasificacion?->nombre ?? "ID {$idClas}";

        $mensaje = "El usuario {$user->nombre_completo} actualizó el mapeo de la clasificación '{$clasNombre}' a la planta '{$plantaNombre}'. {$afectados} empleados afectados.";
        if ($forzado) {
            $mensaje .= " (Se aplicó a TODOS, incluyendo manuales)";
        }

        $usuarios = \App\Models\UsuarioSca::whereHas('rol', function ($q) {
            $q->whereIn('nombre', ['Aprobador', 'Admin', 'Superadmin']);
        })->whereNull('fecha_baja')->get();

        foreach ($usuarios as $u) {
            \App\Models\Notificacion::create([
                'id_usuario_sca' => $u->id_usuario_sca,
                'titulo'         => 'Cambio de Mapeo de Planta',
                'mensaje'        => $mensaje,
                'url'            => route('clasificaciones.index'),
                'leida'          => false,
                'tipo'           => 'sistema',
            ]);
        }
    }

    /**
     * Elimina un mapeo (la clasificación queda sin planta).
     */
    public function destroy(int $idClasificacion)
    {
        $mapeo = ClasificacionPlanta::find($idClasificacion);
        if (!$mapeo) {
            return back()->with('error', 'El mapeo no existía.');
        }

        $nombre = $mapeo->nombre_clasificacion;
        $mapeo->delete();

        LogSistema::registrar(
            'ELIMINAR_MAPEO',
            "Mapeo de '{$nombre}' eliminado"
        );

        \App\Services\PlantaCacheService::invalidar();

        return back()->with('success', "Mapeo de '{$nombre}' eliminado.");
    }
}