<?php
// app/Http/Controllers/EmpleadoExtScaController.php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateEmpleadoExtRequest;
use App\Models\EmpleadoExtSca;
use App\Models\Planta;
use App\Models\Sitec\Empleado;
use App\Models\LogSistema;
use Illuminate\Http\Request;

class EmpleadoExtScaController extends Controller
{
    /**
     * OPTIMIZADO: eager loading masivo + precalc de planta.
     */
    public function index(Request $request)
    {
        // 1. Query base con eager loading de TODAS las relaciones de sitec
        $query = Empleado::query()
            ->with(['puesto', 'turno', 'departamento'])
            ->activos();

        // 2. Búsqueda
        if ($request->filled('buscar')) {
            $query->buscar($request->buscar);
        }

        // 3. Paginación
        $empleados = $query->orderBy('nombre_completo')->paginate(50);

        // 4. Carga manual de ext_sca en UN solo query (evita N+1)
        $idsEmpleados = $empleados->pluck('id_empleado')->toArray();
        $exts = EmpleadoExtSca::whereIn('empleado_id', $idsEmpleados)
            ->get()
            ->keyBy('empleado_id');

        // 5. Precargar mapeo clasif→planta (cacheado, 0 queries si ya está en caché)
        $mapeo = \App\Services\PlantaCacheService::getMapeo();
        $plantasCache = \App\Services\PlantaCacheService::getPlantas();

        // 6. Asignar ext y precalc de planta a cada empleado
        foreach ($empleados as $empleado) {
            $empleado->setRelation('ext', $exts->get($empleado->id_empleado));
            $plantaId = $mapeo[$empleado->id_clasificacion] ?? null;
            $empleado->plantaDerivadaId = $plantaId;
            $empleado->plantaDerivadaNombre = $plantasCache[$plantaId] ?? 'Sin planta';
        }

        // 7. Filtro por planta derivada (en colección, ya precalculada)
        if ($request->filled('planta')) {
            $plantaIdFiltro = (int) $request->planta;
            $empleados->setCollection(
                $empleados->getCollection()->filter(fn($e) => $e->plantaDerivadaId === $plantaIdFiltro)
            );
        }

        // 8. Scoping del Aprobador
        $user = $request->user();
        if ($user->isAprobador()) {
            $plantaIdAprobador = $user->planta_id;
            $empleados->setCollection(
                $empleados->getCollection()->filter(function ($e) use ($plantaIdAprobador) {
                    $p = $e->plantaDerivadaId;
                    return $p === null || $p === $plantaIdAprobador;
                })
            );
        }

        $plantas = Planta::where('activa', true)->orderBy('nombre')->get();

        return view('empleados.index', compact('empleados', 'plantas'));
    }

    public function edit(int $empleadoId)
    {
        $user = request()->user();

        $empleado = Empleado::with(['puesto', 'turno', 'departamento'])
            ->find($empleadoId);
        if (!$empleado) {
            abort(404, 'Empleado no encontrado en sitec.');
        }

        $ext = $empleado->ext ?: EmpleadoExtSca::firstOrNew(['empleado_id' => $empleadoId]);

        if ($user->isAprobador()) {
            $plantaDerivada = $empleado->plantaId();
            if ($plantaDerivada !== null && $plantaDerivada !== $user->planta_id) {
                abort(403, 'No puede editar empleados de otra planta.');
            }
        }

        $tipoSugerido = \App\Services\TipoEmpleadoCacheService::tipoDePuesto($empleado->id_puesto);
        $plantas = Planta::where('activa', true)->orderBy('nombre')->get();
        $plantaDerivada = $empleado->plantaId();
        $plantaDerivadaNombre = $empleado->plantaNombre();

        return view('empleados.edit', compact(
            'empleado', 'ext', 'tipoSugerido', 'plantas',
            'plantaDerivada', 'plantaDerivadaNombre'
        ));
    }

    public function update(UpdateEmpleadoExtRequest $request, int $empleadoId)
    {
        $user = $request->user();
        $empleado = Empleado::find($empleadoId);
        if (!$empleado) {
            return back()->withErrors(['empleado' => 'Empleado no encontrado en sitec.']);
        }

        $ext = EmpleadoExtSca::find($empleadoId);

        if ($user->isAprobador()) {
            $plantaDerivada = $empleado->plantaId();
            if ($plantaDerivada !== null && $plantaDerivada !== $user->planta_id) {
                abort(403, 'No puede editar empleados de otra planta.');
            }
        }

        $datos = ['tipo_empleado' => $request->tipo_empleado];

        if ($request->boolean('resetear_manual')) {
            $datos['usos_manuales_restantes'] = 4;
            $datos['bloqueo_manual'] = false;
        } elseif (!$ext) {
            $datos['usos_manuales_restantes'] = 4;
            $datos['bloqueo_manual'] = false;
        }

        EmpleadoExtSca::updateOrCreate(
            ['empleado_id' => $empleadoId],
            $datos
        );

        LogSistema::registrar(
            'CONFIGURAR_EMPLEADO',
            "Empleado {$empleadoId} ({$empleado->nombre_completo}): tipo={$request->tipo_empleado}"
        );

        return redirect()->route('empleados.index')
            ->with('success', "Configuración de {$empleado->nombre_completo} actualizada.");
    }

    /**
     * OPTIMIZADO: usa caché para tipo_sugerido.
     */
    public function buscar(Request $request)
    {
        $request->validate(['id' => 'required|integer']);

        $empleado = Empleado::with(['puesto', 'turno', 'departamento'])
            ->find($request->id);

        if (!$empleado) {
            return response()->json(['encontrado' => false]);
        }

        $ext = $empleado->ext;

        return response()->json([
            'encontrado'      => true,
            'activo'          => $empleado->estaActivo(),
            'nombre'          => $empleado->nombre_completo,
            'puesto'          => $empleado->puesto?->nombre,
            'departamento'    => $empleado->departamento?->nombre,
            'turno'           => $empleado->turno?->nombre,
            'tiene_config'    => (bool) $ext,
            'tipo_actual'     => $ext?->tipo_empleado,
            'planta_actual'   => $empleado->plantaId(),
            'planta_nombre'   => $empleado->plantaNombre(),
            'tipo_sugerido'   => \App\Services\TipoEmpleadoCacheService::tipoDePuesto($empleado->id_puesto),
            'usos_restantes'  => $ext?->usos_manuales_restantes,
            'bloqueado'       => $ext?->bloqueo_manual,
        ]);
    }
}