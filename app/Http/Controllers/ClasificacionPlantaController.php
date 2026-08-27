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

        // Conteo de empleados ACTIVOS por clasificación (1 query agrupada a sitec).
        // FIX: Filtramos por estatus='Alta' para que coincida con el conteo que procesa
        // el método update() (que también filtra por estatus='Alta'). Antes esto no filtraba
        // y el card mostraba "900" pero el update solo procesaba 777 (los activos).
        $conteoEmpleados = Empleado::selectRaw('id_clasificacion, COUNT(*) as total')
            ->whereNotNull('id_clasificacion')
            ->where('estatus', 'Alta')
            ->groupBy('id_clasificacion')
            ->pluck('total', 'id_clasificacion');

        // M2 [ScaModal] — Conteo de excepciones (empleados con planta_forzada=true)
        // por clasificación. Se muestran como botón en cada card para que el admin
        // pueda verlos en cualquier momento (no solo después de un update).
        //
        // Optimización: 2 queries en vez de N (una por clasificación).
        // 1. Todos los empleados activos con su id_clasificacion: [id_empleado => id_clasificacion]
        // 2. Todos los empleado_id con planta_forzada=true
        // Luego contamos en PHP cuántos forzados hay por clasificación.
        $empleadosActivos = Empleado::on('sitec')
            ->whereNotNull('id_clasificacion')
            ->where('estatus', 'Alta')
            ->pluck('id_clasificacion', 'id_empleado');

        $forzadosIds = \App\Models\EmpleadoExtSca::where('planta_forzada', true)
            ->pluck('empleado_id')
            ->toArray();

        $excepcionesPorClas = collect();
        foreach ($empleadosActivos as $empleadoId => $idClas) {
            if (in_array($empleadoId, $forzadosIds)) {
                $excepcionesPorClas[$idClas] = ($excepcionesPorClas[$idClas] ?? 0) + 1;
            }
        }

        return view('clasificaciones.index', compact(
            'clasificaciones', 'mapeos', 'plantas', 'conteoEmpleados', 'excepcionesPorClas'
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

        $user = $request->user();
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

        $totalEmpleados = $empleadosIds->count();
        $actualizados = 0;
        $nuevos = 0;
        $respetados = 0;

        if ($empleadosIds->isNotEmpty()) {

            // 2a. Crear registros faltantes en empleados_ext_sca para TODOS
            // los empleados de sitec con esta clasificación que no tengan registro.
            // Esto sincroniza sitec → sca y hace que el conteo sea consistente.
            $existentesIds = \App\Models\EmpleadoExtSca::whereIn('empleado_id', $empleadosIds)
                ->pluck('empleado_id')
                ->toArray();

            $faltantesIds = $empleadosIds->diff($existentesIds);

            foreach ($faltantesIds as $empId) {
                \App\Models\EmpleadoExtSca::create([
                    'empleado_id' => $empId,
                    'id_planta' => $idPlanta,
                    'planta_forzada' => false,
                    'tipo_empleado' => null,
                    'telefono' => null,
                    'usos_manuales_restantes' => 4,
                    'fecha_ultimo_manual' => null,
                ]);
                $nuevos++;
                $actualizados++;
            }

            // 2b. Actualizar los existentes que NO tienen planta_forzada
            // (o todos si forzarTodos=true)
            $queryUpdate = \App\Models\EmpleadoExtSca::whereIn('empleado_id', $existentesIds);

            if (!$forzarTodos) {
                $queryUpdate->where(function ($q) {
                    $q->where('planta_forzada', false)->orWhereNull('planta_forzada');
                });
            }

            $actualizadosExistentes = $queryUpdate->update([
                'id_planta' => $idPlanta,
                'planta_forzada' => false,
            ]);
            $actualizados += $actualizadosExistentes;

            // 2c. Contar respetados (los que tienen planta_forzada=true y no se actualizaron)
            if (!$forzarTodos) {
                $respetados = \App\Models\EmpleadoExtSca::whereIn('empleado_id', $existentesIds)
                    ->where('planta_forzada', true)
                    ->count();
            }
        }

        // 3. Registrar en Log de Sistema
        $plantaNombre = \App\Services\PlantaCacheService::nombrePlanta($idPlanta);
        \App\Models\LogSistema::registrar(
            'MAPEO_CLASIFICACION_PLANTA',
            "El usuario {$user->nombre_completo} ({$user->username}) mapeó la clasificación '{$nombreClas}' a la planta '{$plantaNombre}'. " .
            "Total: {$totalEmpleados}, Actualizados: {$actualizados}, Nuevos: {$nuevos}, Respetados: {$respetados}. Forzado: " . ($forzarTodos ? 'Sí' : 'No')
        );

        // 4. Notificación global
        $this->notificarCambioMapeo($idClasificacion, $idPlanta, $actualizados, $forzarTodos, $user);

        // M2 [ScaModal] — Construir mensaje detallado
        $mensaje = "<div class='text-start'>";
        $mensaje .= "<div class='mb-2'><strong>Clasificación:</strong> {$nombreClas}</div>";
        $mensaje .= "<div class='mb-2'><strong>Planta asignada:</strong> {$plantaNombre}</div>";
        $mensaje .= "<hr class='my-2'>";
        $mensaje .= "<div class='row text-center'>";
        $mensaje .= "<div class='col-4'><div class='h5 mb-0 text-success'>{$totalEmpleados}</div><small class='text-muted'>Total</small></div>";
        $mensaje .= "<div class='col-4'><div class='h5 mb-0 text-primary'>{$actualizados}</div><small class='text-muted'>Actualizados</small></div>";
        if ($nuevos > 0) {
            $mensaje .= "<div class='col-4'><div class='h5 mb-0 text-info'>{$nuevos}</div><small class='text-muted'>Nuevos</small></div>";
        } else if ($respetados > 0) {
            $mensaje .= "<div class='col-4'><div class='h5 mb-0 text-warning'>{$respetados}</div><small class='text-muted'>Respetados</small></div>";
        }
        $mensaje .= "</div>";
        // Aviso de excepciones (si las hay) dentro del mensaje
        if ($respetados > 0) {
            $mensaje .= "<div class='alert alert-warning mt-3 mb-0 py-2'><i class='bi bi-exclamation-triangle-fill'></i> " .
                "<strong>{$respetados} empleado(s)</strong> no se actualizaron (planta manual). " .
                "Podés ver la lista desde el botón \"X con planta manual\" en la tarjeta.</div>";
        }
        $mensaje .= "</div>";

        // M2 [ScaModal] — Devolver JSON con título, mensaje y redirect.
        // El JS del frontend muestra el success en esta misma página (sin Option E)
        // para evitar el gap durante la navegación.
        if ($request->expectsJson()) {
            return response()->json([
                'success'           => true,
                'titulo'             => 'Mapeo actualizado',
                'mensaje'            => $mensaje,
                'redirect'           => route('clasificaciones.index'),
                'tiene_excepciones'  => $respetados > 0,
                'total_excepciones'  => $respetados,
            ]);
        }

        return redirect()->route('clasificaciones.index')->with(
            'sca_modal',
            sca_modal_flash('success', 'Mapeo actualizado', $mensaje)
        );
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

    public function destroy(Request $request, int $idClasificacion)
    {
        // M2 [ScaModal] — Aceptamos Request para detectar AJAX.
        $mapeo = ClasificacionPlanta::find($idClasificacion);
        if (!$mapeo) {
            $errorMsg = 'El mapeo que intenta eliminar ya no existe. Puede que haya sido eliminado por otro usuario.';
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'titulo'  => 'Mapeo inexistente',
                    'mensaje' => $errorMsg,
                ], 404);
            }
            return back()->with(
                'sca_modal',
                sca_modal_flash('error', 'Mapeo inexistente', $errorMsg)
            );
        }

        $nombre = $mapeo->nombre_clasificacion;
        $mapeo->delete();

        LogSistema::registrar(
            'ELIMINAR_MAPEO',
            "Mapeo de '{$nombre}' eliminado"
        );

        \App\Services\PlantaCacheService::invalidar();

        $successMsg = "El mapeo de '<strong>{$nombre}</strong>' fue eliminado correctamente.";

        // M2 [ScaModal] — Option E: Setear flash en la sesión ANTES de devolver JSON.
        session()->flash('sca_modal', sca_modal_flash('success', 'Mapeo eliminado', $successMsg));

        if ($request->expectsJson()) {
            return response()->json([
                'success'  => true,
                'redirect' => route('clasificaciones.index'),
            ]);
        }

        return back()->with(
            'sca_modal',
            sca_modal_flash('success', 'Mapeo eliminado', $successMsg)
        );
    }

    /**
     * M2 [ScaModal] — Devuelve JSON con la lista de empleados que NO fueron
     * actualizados al cambiar el mapeo (los que tienen planta_forzada=true).
     *
     * Se llama vía AJAX desde el botón "X con planta manual" de cada card
     * en clasificaciones/index.blade.php.
     */
    public function excepciones(int $idClasificacion)
    {
        $clasificacion = \App\Models\Sitec\Clasificacion::on('sitec')->find($idClasificacion);
        $nombreClas = $clasificacion?->nombre ?? "Clasificación {$idClasificacion}";

        // Buscar empleados de sitec con esta clasificación (activos).
        // Hacemos eager loading de 'puesto' para evitar N+1 queries.
        $empleados = \App\Models\Sitec\Empleado::on('sitec')
            ->with('puesto')
            ->where('id_clasificacion', $idClasificacion)
            ->where('estatus', 'Alta')
            ->get()
            ->keyBy('id_empleado');

        // IDs de esos empleados
        $empleadosIds = $empleados->keys();

        // Buscar los que tienen planta_forzada=true (no se actualizaron)
        $excepciones = \App\Models\EmpleadoExtSca::with('planta')
            ->whereIn('empleado_id', $empleadosIds)
            ->where('planta_forzada', true)
            ->get();

        // Armar la lista usando la colección ya cargada (sin queries adicionales).
        $lista = [];
        foreach ($excepciones as $ext) {
            $emp = $empleados->get($ext->empleado_id);
            // FIX: Puesto tiene campo 'nombre', no 'descripcion'.
            $puestoNombre = $emp?->puesto?->nombre ?? 'Sin puesto';
            $lista[] = [
                'id_empleado' => $ext->empleado_id,
                'nombre' => $emp?->nombre_completo ?? "Empleado {$ext->empleado_id}",
                'puesto' => $puestoNombre,
                'planta' => $ext->planta?->nombre ?? 'Sin planta',
            ];
        }

        return response()->json([
            'success' => true,
            'clasificacion' => $nombreClas,
            'total' => count($lista),
            'excepciones' => $lista,
        ]);
    }
}