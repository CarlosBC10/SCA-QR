<?php
// app/Http/Controllers/EmpleadoExtScaController.php
//
// CAMBIOS V4:
//   - Eliminado 'bloqueo_manual' (se infiere de usos_manuales_restantes = 0).
//   - resetear_manual ahora setea fecha_ultimo_manual = null.
//   - buscar() retorna 'bloqueado' usando el accessor bloqueado_manual.
//   - FK: planta_id → id_planta (en subqueries, aunque aquí no se usa directo).

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
     * Lista empleados con su configuración SCA.
     */
    public function index(Request $request)
    {
        $query = Empleado::query()
            ->with(['puesto', 'turno', 'departamento'])
            ->activos();

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            // FIX H: Usar el servicio de búsqueda por palabras
            if (ctype_digit($buscar)) {
                $query->where('id_empleado', (int) $buscar);
            } else {
                \App\Services\BusquedaService::aplicarBusquedaPalabras($query, 'nombre_completo', $buscar);
            }
        }

        $empleados = $query->orderBy('nombre_completo')->paginate(50);

        // Carga manual de ext_sca en 1 query (evita N+1 cross-DB)
        $idsEmpleados = $empleados->pluck('id_empleado')->toArray();
        $exts = EmpleadoExtSca::whereIn('empleado_id', $idsEmpleados)
            ->get()
            ->keyBy('empleado_id');

        // Mapeo clasif→planta (cacheado)
        $mapeo = \App\Services\PlantaCacheService::getMapeo();
        $plantasCache = \App\Services\PlantaCacheService::getPlantas();

        foreach ($empleados as $empleado) {
            $ext = $exts->get($empleado->id_empleado);
            $empleado->setRelation('ext', $ext);
            
            // FIX E: Si el empleado tiene planta_forzada, mostramos esa.
            // Si no, mostramos la derivada de sitec.
            if ($ext && $ext->planta_forzada && $ext->id_planta) {
                $empleado->plantaDerivadaId = $ext->id_planta;
                $empleado->plantaDerivadaNombre = $plantasCache[$ext->id_planta] ?? 'Sin planta';
            } else {
                $plantaId = $mapeo[$empleado->id_clasificacion] ?? null;
                $empleado->plantaDerivadaId = $plantaId;
                $empleado->plantaDerivadaNombre = $plantasCache[$plantaId] ?? 'Sin planta';
            }
        }

        // Filtro por planta derivada
        if ($request->filled('planta')) {
            $plantaIdFiltro = (int) $request->planta;
            $empleados->setCollection(
                $empleados->getCollection()->filter(fn($e) => $e->plantaDerivadaId === $plantaIdFiltro)
            );
        }

        // Scoping del Aprobador
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

        $empleado = Empleado::with(['puesto', 'turno', 'departamento'])->find($empleadoId);
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
        // FIX E: Obtener la planta derivada REAL de sitec, no la que está guardada en ext_sca (que podría ser forzada)
        $plantaDerivada = \App\Services\PlantaCacheService::plantaDeClasificacion($empleado->id_clasificacion);
        $plantaDerivadaNombre = \App\Services\PlantaCacheService::nombrePlanta($plantaDerivada);

        return view('empleados.edit', compact(
            'empleado', 'ext', 'tipoSugerido', 'plantas',
            'plantaDerivada', 'plantaDerivadaNombre'
        ));
    }

    /**
     * Actualiza la configuración SCA de un empleado.
     *
     * FIX E: Si el Admin cambia la planta manualmente, se guarda con 
     * 'planta_forzada = true'. Esto indica que el sistema debe respetar 
     * esta asignación y no sobreescribirla si RH cambia la clasificación 
     * en sitec o si se hace un re-mapeo masivo (a menos que se fuerce).
     */
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

        // FIX E: Lógica de planta manual (Override)
        // Solo Admin/Super pueden cambiar la planta.
        if ($user->isAdmin() || $user->isSuperadmin()) {
            $plantaInput = $request->input('id_planta');
            
            // Si el admin elige "auto", derivamos la planta de sitec en este momento
            // y la guardamos físicamente, marcando planta_forzada = false.
            if ($plantaInput === 'auto' || $plantaInput === null || $plantaInput === '') {
                $plantaDerivada = \App\Services\PlantaCacheService::plantaDeClasificacion($empleado->id_clasificacion);
                if ($plantaDerivada !== null) {
                    $datos['id_planta'] = $plantaDerivada;
                } else {
                    $datos['id_planta'] = null;
                }
                $datos['planta_forzada'] = false;
            } else {
                // Si el admin elige una planta específica, la guardamos y marcamos como forzada
                $datos['id_planta'] = (int) $plantaInput;
                $datos['planta_forzada'] = true;
            }
        }

        // Resetear contador de manuales
        if ($request->boolean('resetear_manual')) {
            $datos['usos_manuales_restantes'] = 4;
            $datos['fecha_ultimo_manual'] = null;
        } elseif (!$ext) {
            $datos['usos_manuales_restantes'] = 4;
            $datos['fecha_ultimo_manual'] = null;
        }

        EmpleadoExtSca::updateOrCreate(
            ['empleado_id' => $empleadoId],
            $datos
        );

        LogSistema::registrar(
            'CONFIGURAR_EMPLEADO',
            "Empleado {$empleadoId} ({$empleado->nombre_completo}) modificado por {$user->username}: tipo={$request->tipo_empleado}" .
            (isset($datos['id_planta']) ? ', id_planta=' . ($datos['id_planta'] ?? 'NULL') . ', forzada=' . ($datos['planta_forzada'] ? 'Sí' : 'No') : '')
        );

        return redirect()->route('empleados.index')
            ->with('success', "Configuración de {$empleado->nombre_completo} actualizada.");
    }

    /**
     * API: busca un empleado por ID.
     *
     * Cambio V4: 'bloqueado' usa el accessor bloqueado_manual.
     */
    public function buscar(Request $request)
    {
        $request->validate(['id' => 'required|integer']);

        $empleado = Empleado::with(['puesto', 'turno', 'departamento'])->find($request->id);

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
            'bloqueado'       => $ext?->bloqueado_manual, // accessor: usos_manuales_restantes <= 0
        ]);
    }
}