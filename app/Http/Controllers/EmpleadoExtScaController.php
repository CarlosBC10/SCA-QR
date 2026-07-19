<?php
// app/Http/Controllers/EmpleadoExtScaController.php
//
// CONTROLADOR: EmpleadoExtScaController
//
// CAMBIO CLAVE:
// Antes consultábamos `empleados_ext_sca` para listar empleados.
// Como ahora hay 25,000 empleados sincronizados, consultamos directamente `sitec.empleados`.
// 
// ¿POR QUÉ?
// Así vemos a TODOS los empleados (tengan o no configuración en ext_sca).
// El Aprobador puede ver los "Sin asignar" para reclutarlos a su planta.

namespace App\Http\Controllers;

use App\Http\Requests\UpdateEmpleadoExtRequest;
use App\Models\EmpleadoExtSca;
use App\Models\Planta;
use App\Models\PuestoTipoEmpleado;
use App\Models\Sitec\Empleado;
use App\Models\LogSistema;
use Illuminate\Http\Request;

class EmpleadoExtScaController extends Controller
{
    /**
     * LISTA de empleados.
     * Consulta sitec.empleados (25,000+) con paginación y scoping.
     */
        /**
     * LISTA de empleados.
     * Consulta sitec.empleados (25,000+) con paginación y scoping.
     */
        public function index(Request $request)
    {
        // 1. Consultamos sitec.empleados
        $query = Empleado::query()->with(['puesto', 'turno', 'departamento']);

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(function ($q) use ($buscar) {
                $q->where('id_empleado', 'like', "%{$buscar}%")
                  ->orWhere('nombre_completo', 'like', "%{$buscar}%");
            });
        }

        // Obtenemos la página actual (25 empleados)
        $empleados = $query->orderBy('nombre_completo')->paginate(25);

        // 2. CARGA MANUAL DE LA EXTENSIÓN (Evita el error de permisos cruzados)
        $idsEmpleados = $empleados->pluck('id_empleado')->toArray();
        $exts = EmpleadoExtSca::with('planta')
            ->whereIn('empleado_id', $idsEmpleados)
            ->get()
            ->keyBy('empleado_id');

        // 3. Asignamos la extensión a cada empleado manualmente
        foreach ($empleados as $empleado) {
            $empleado->setRelation('ext', $exts->get($empleado->id_empleado));
        }

        // 4. Filtro por planta (si se seleccionó uno en el formulario)
        // Como ya tenemos los datos cargados, filtramos en la colección
        if ($request->filled('planta')) {
            $plantaId = $request->planta;
            $empleados->setCollection(
                $empleados->getCollection()->filter(function ($empleado) use ($plantaId) {
                    return $empleado->ext && $empleado->ext->planta_id == $plantaId;
                })
            );
        }

        // 5. Scoping del Aprobador (filtramos en la colección)
        $user = $request->user();
        if ($user->isAprobador()) {
            $plantaId = $user->planta_id;
            $empleados->setCollection(
                $empleados->getCollection()->filter(function ($empleado) use ($plantaId) {
                    // Ve empleados sin planta O empleados de su planta
                    return !$empleado->ext || $empleado->ext->planta_id === null || $empleado->ext->planta_id == $plantaId;
                })
            );
        }

        $plantas = Planta::where('activa', true)->orderBy('nombre')->get();

        return view('empleados.index', compact('empleados', 'plantas'));
    }

    /**
     * FORMULARIO de edición individual.
     */
    public function edit(int $empleadoId)
    {
        $user = request()->user();

        // Buscar el empleado en sitec
        $empleado = Empleado::with(['puesto', 'turno', 'departamento'])->find($empleadoId);
        if (!$empleado) {
            abort(404, 'Empleado no encontrado en sitec.');
        }

        // Buscar o inicializar la configuración en empleados_ext_sca
        $ext = EmpleadoExtSca::firstOrNew(['empleado_id' => $empleadoId]);

        // SCOPING: el Aprobador solo puede editar empleados de su planta 
        // o empleados sin planta (para asignarlos a la suya)
        if ($user->isAprobador()) {
            if ($ext->exists && $ext->planta_id !== null && $ext->planta_id !== $user->planta_id) {
                abort(403, 'No puede editar empleados de otra planta.');
            }
        }

        // Sugerir tipo según el puesto (si no tiene override manual)
        $tipoSugerido = PuestoTipoEmpleado::tipoDePuesto($empleado->id_puesto);

        $plantas = Planta::where('activa', true)->orderBy('nombre')->get();

        return view('empleados.edit', compact('empleado', 'ext', 'tipoSugerido', 'plantas'));
    }

    /**
     * GUARDAR/ACTUALIZAR configuración de acceso.
     */
    public function update(UpdateEmpleadoExtRequest $request, int $empleadoId)
    {
        $user = $request->user();

        $empleado = Empleado::find($empleadoId);
        if (!$empleado) {
            return back()->withErrors(['empleado' => 'Empleado no encontrado en sitec.']);
        }

        $ext = EmpleadoExtSca::find($empleadoId);

        // Scoping del Aprobador
        if ($user->isAprobador()) {
            $plantaAprobador = $user->planta_id;
            if ($ext && $ext->planta_id !== null && $ext->planta_id !== $plantaAprobador) {
                abort(403, 'No puede editar empleados de otra planta.');
            }
        }

        $datos = [
            'tipo_empleado' => $request->tipo_empleado,
            'planta_id'     => $request->planta_id,
        ];

        // El Aprobador siempre fuerza su planta al asignar
        if ($user->isAprobador()) {
            $datos['planta_id'] = $user->planta_id;
        }

        // Resetear contador manual si se marcó el checkbox
        if ($request->boolean('resetear_manual')) {
            $datos['usos_manuales_restantes'] = 4;
            $datos['bloqueo_manual'] = false;
        } elseif (!$ext) {
            // Si es un registro nuevo, inicializar el contador
            $datos['usos_manuales_restantes'] = 4;
            $datos['bloqueo_manual'] = false;
        }

        // Crear o actualizar (updateOrCreate porque el registro ya existe por la sync 1:1)
        EmpleadoExtSca::updateOrCreate(
            ['empleado_id' => $empleadoId],
            $datos
        );

        LogSistema::registrar(
            'CONFIGURAR_EMPLEADO',
            "Empleado {$empleadoId} ({$empleado->nombre_completo}): tipo={$request->tipo_empleado}, planta={$request->planta_id}"
        );

        return redirect()->route('empleados.index')
            ->with('success', "Configuración de {$empleado->nombre_completo} actualizada.");
    }

    /**
     * BUSCAR empleado en sitec (AJAX).
     */
    public function buscar(Request $request)
    {
        $request->validate(['id' => 'required|integer']);

        $empleado = Empleado::with(['puesto', 'turno', 'departamento'])->find($request->id);

        if (!$empleado) {
            return response()->json(['encontrado' => false]);
        }

        $ext = EmpleadoExtSca::find($empleado->id_empleado);

        return response()->json([
            'encontrado'      => true,
            'activo'          => $empleado->estaActivo(),
            'nombre'          => $empleado->nombre_completo,
            'puesto'          => $empleado->puesto?->nombre,
            'departamento'    => $empleado->departamento?->nombre,
            'turno'           => $empleado->turno?->nombre,
            'tiene_config'    => (bool) $ext,
            'tipo_actual'     => $ext?->tipo_empleado,
            'planta_actual'   => $ext?->planta_id,
            'tipo_sugerido'   => PuestoTipoEmpleado::tipoDePuesto($empleado->id_puesto),
            'usos_restantes'  => $ext?->usos_manuales_restantes,
            'bloqueado'       => $ext?->bloqueo_manual,
        ]);
    }
}