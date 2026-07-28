<?php
// app/Http/Controllers/SolicitudController.php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSolicitudRequest;
use App\Models\Solicitud;
use App\Models\SolicitudEmpleado;
use App\Models\Persona;
use App\Models\Planta;
use App\Models\Sitec\Empleado;
use App\Models\LogSistema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SolicitudController extends Controller
{
    /**
     * NUEVO: HUB DE SOLICITUDES
     * Página principal que actúa como menú según el rol.
     */
    public function hub()
    {
        $user = request()->user();
        
        // Contar pendientes de aprobación (para el badge de la bandeja)
        $pendientesAprobar = 0;
        if ($user->isAprobador() || $user->isAdmin() || $user->isSuperadmin()) {
            $plantaId = $user->planta_id;
            $pendientesAprobar = Solicitud::where(function ($q) use ($plantaId) {
                $q->where('planta_origen_id', $plantaId)->where('estado', 'pendiente_origen')
                  ->orWhere('planta_destino_id', $plantaId)->where('estado', 'pendiente_destino');
            })->count();
        }

        return view('solicitudes.hub', compact('user', 'pendientesAprobar'));
    }

    /**
     * HISTORIAL DE SOLICITUDES
     * Muestra la lista filtrable. Aplica scoping por rol.
     */
    public function historial(Request $request)
    {
        $user = $request->user();
        $query = Solicitud::with(['persona', 'plantaOrigen', 'plantaDestino', 'empleados']);

        if ($user->isEmpleado()) {
            $query->where('creador_empleado_id', (int) $user->username);
        } elseif ($user->isAprobador()) {
            $plantaId = $user->planta_id;
            $query->where(function ($q) use ($plantaId) {
                $q->where('planta_origen_id', $plantaId)
                  ->orWhere('planta_destino_id', $plantaId);
            });
        }

        if ($request->filled('tipo')) $query->where('tipo', $request->tipo);
        if ($request->filled('estado')) $query->where('estado', $request->estado);

        $solicitudes = $query->orderBy('created_at', 'desc')->paginate(15);
        $plantas = Planta::where('activa', true)->orderBy('nombre')->get();

        return view('solicitudes.index', compact('solicitudes', 'plantas'));
    }

    /**
     * BANDEJA DE APROBACIONES
     */
    public function bandeja()
    {
        $user = request()->user();
        $plantaId = $user->planta_id; // Puede ser null si es corporativo

        $pendientesOrigen = collect();
        $pendientesDestino = collect();

        if ($plantaId) {
            $pendientesOrigen = Solicitud::with(['persona', 'plantaOrigen', 'plantaDestino', 'empleados'])
                ->where('planta_origen_id', $plantaId)
                ->where('estado', 'pendiente_origen')
                ->orderBy('created_at', 'desc')->get();

            $pendientesDestino = Solicitud::with(['persona', 'plantaOrigen', 'plantaDestino', 'empleados'])
                ->where('planta_destino_id', $plantaId)
                ->where('estado', 'pendiente_destino')
                ->orderBy('created_at', 'desc')->get();
        }

        return view('solicitudes.bandeja', compact('pendientesOrigen', 'pendientesDestino'));
    }


    /**
     * FORMULARIO DE NUEVA SOLICITUD
     */
    public function create()
    {
        $user = request()->user();
        $plantas = Planta::where('activa', true)->orderBy('nombre')->get();
        
        $plantaOrigenId = $user->planta_id;
        $plantaOrigenNombre = $user->planta_nombre;
        $tiposPermitidos = $this->tiposPermitidos($user);
        
        // NUEVO: Admin y Superadmin pueden elegir planta destino para externos
        $puedeElegirDestinoExterno = $user->isAdmin() || $user->isSuperadmin();

        return view('solicitudes.create', compact('plantas', 'plantaOrigenId', 'plantaOrigenNombre', 'tiposPermitidos', 'puedeElegirDestinoExterno'));
    }

    /**
     * GUARDAR NUEVA SOLICITUD
     */
    public function store(StoreSolicitudRequest $request)
    {
        $user = $request->user();
        $tipo = $request->tipo;

        if (!in_array($tipo, $this->tiposPermitidos($user))) {
            return back()->withErrors(['tipo' => 'No tiene permisos para crear este tipo de solicitud.'])->withInput();
        }

        DB::beginTransaction();
        try {
            $personaId = null;
            if (in_array($tipo, ['visita', 'contratista'])) {
                $persona = Persona::create([
                    'nombre_completo' => $request->persona_nombre,
                    'telefono'        => $request->persona_telefono,
                    'tipo'            => $tipo === 'visita' ? 'visitante' : 'contratista',
                    'empresa'         => $request->persona_empresa,
                ]);
                $personaId = $persona->id;
            }

            $plantaOrigenId = $user->planta_id;
            
            // LÓGICA DE PLANTA DESTINO
            if ($tipo === 'pase_salida') {
                $plantaDestinoId = $plantaOrigenId;
            } elseif (in_array($tipo, ['visita', 'contratista'])) {
                // Empleado/Aprobador: el destino es su planta (ellos reciben)
                // Admin/Super: eligen el destino
                if ($user->isAdmin() || $user->isSuperadmin()) {
                    $plantaDestinoId = $request->planta_destino_id;
                } else {
                    $plantaDestinoId = $plantaOrigenId;
                }
            } else {
                // interplantas
                $plantaDestinoId = $request->planta_destino_id;
            }

            if ($tipo === 'interplantas' && $plantaOrigenId === $plantaDestinoId) {
                return back()->withErrors(['planta_destino_id' => 'Para interplantas, la planta destino debe ser distinta a su planta origen.'])->withInput();
            }

            // LÓGICA DE ESTADO Y AUTO-APROBACIÓN
            $esAprobadorOSuperior = $user->isAprobador() || $user->isAdmin() || $user->isSuperadmin();
            $ahora = now();
            $userId = (int) $user->username;

            $estado = 'pendiente_origen'; // Default
            $aprobadorOrigen = null;
            $fechaAprobOrigen = null;
            $aprobadorDestino = null;
            $fechaAprobDestino = null;

            if ($esAprobadorOSuperior) {
                // AUTO-APROBACIÓN
                $estado = 'aprobado';
                
                if (in_array($tipo, ['pase_salida', 'interplantas'])) {
                    $aprobadorOrigen = $userId;
                    $fechaAprobOrigen = $ahora;
                }
                if (in_array($tipo, ['interplantas', 'visita', 'contratista'])) {
                    $aprobadorDestino = $userId;
                    $fechaAprobDestino = $ahora;
                }
            } else {
                // EMPLEADO: requiere aprobación
                if (in_array($tipo, ['visita', 'contratista'])) {
                    $estado = 'pendiente_destino';
                } else {
                    $estado = 'pendiente_origen';
                }
            }

            $solicitud = Solicitud::create([
                'creador_empleado_id' => $user->guardia_id ? null : $userId,
                'planta_origen_id'    => $plantaOrigenId,
                'tipo'                => $tipo,
                'persona_id'          => $personaId,
                'planta_destino_id'   => $plantaDestinoId,
                'fecha_inicio'        => $request->fecha_inicio,
                'fecha_fin'           => $request->fecha_fin,
                'motivo'              => $request->motivo,
                'estado'              => $estado,
                'aprobador_origen_empleado_id' => $aprobadorOrigen,
                'fecha_aprobacion_origen' => $fechaAprobOrigen,
                'aprobador_destino_empleado_id' => $aprobadorDestino,
                'fecha_aprobacion_destino' => $fechaAprobDestino,
            ]);

            if ($tipo === 'interplantas' && $request->has('empleados')) {
                foreach ($request->empleados as $empId) {
                    SolicitudEmpleado::create([
                        'solicitud_id' => $solicitud->id,
                        'empleado_id'  => $empId,
                        'estado'       => $esAprobadorOSuperior ? 'aprobado' : 'pendiente',
                    ]);
                }
            }

            DB::commit();
            LogSistema::registrar('CREAR_SOLICITUD', "Solicitud #{$solicitud->id} tipo={$tipo} estado={$estado}");

            return redirect()->route('solicitudes.show', $solicitud->id)
                ->with('success', 'Solicitud #' . $solicitud->id . ' creada correctamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Error al crear la solicitud: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * VER DETALLE DE UNA SOLICITUD
     */
    public function show(Solicitud $solicitud)
    {
        $user = request()->user();

        if ($user->isEmpleado() && $solicitud->creador_empleado_id !== (int) $user->username) {
            abort(403, 'No tiene permisos para ver esta solicitud.');
        }

        $solicitud->load(['persona', 'plantaOrigen', 'plantaDestino', 'empleados']);

        $empleadosDetalle = collect();
        if ($solicitud->tipo === 'interplantas' && $solicitud->empleados->isNotEmpty()) {
            $ids = $solicitud->empleados->pluck('empleado_id')->toArray();
            $empleadosDetalle = Empleado::whereIn('id_empleado', $ids)
                ->with(['puesto', 'departamento'])
                ->get()
                ->keyBy('id_empleado');
        }

        $creador = $solicitud->creador_empleado_id ? Empleado::find($solicitud->creador_empleado_id) : null;

        return view('solicitudes.show', compact('solicitud', 'empleadosDetalle', 'creador'));
    }

    /**
     * CANCELAR SOLICITUD PENDIENTE
     */
    public function cancel(Solicitud $solicitud, Request $request)
    {
        $user = $request->user();

        if ($user->isEmpleado() && $solicitud->creador_empleado_id !== (int) $user->username) {
            abort(403, 'No puede cancelar esta solicitud.');
        }

        if (!in_array($solicitud->estado, ['pendiente_origen', 'pendiente_destino'])) {
            return back()->withErrors(['error' => 'Solo se pueden cancelar solicitudes pendientes.']);
        }

        $solicitud->update(['estado' => 'rechazado', 'motivo_rechazo' => 'Cancelada por el creador']);
        LogSistema::registrar('CANCELAR_SOLICITUD', "Solicitud #{$solicitud->id} cancelada");

        return redirect()->route('solicitudes.hub')
            ->with('success', 'Solicitud #' . $solicitud->id . ' cancelada.');
    }

    public function buscarEmpleado(Request $request)
    {
        $request->validate(['q' => 'required', 'string', 'min:2']);
        $q = $request->q;

        // Si la búsqueda es numérica, buscamos por ID exacto. Si no, por nombre.
        $query = Empleado::where('estatus', 'Alta');
        
        if (ctype_digit($q)) {
            $query->where('id_empleado', (int) $q);
        } else {
            $query->where('nombre_completo', 'like', "%{$q}%");
        }

        $empleados = $query->with(['puesto', 'departamento'])
            ->limit(10)
            ->get()
            ->map(function ($e) {
                return [
                    'id'           => $e->id_empleado,
                    'nombre'       => $e->nombre_completo,
                    'puesto'       => $e->puesto?->nombre,
                    'departamento' => $e->departamento?->nombre,
                    'planta'       => $e->plantaNombre(),
                ];
            });

        return response()->json($empleados);
    }

    /**
     * HELPER PRIVADO: tiposPermitidos($user)
     * 
     * LÓGICA: Todos los usuarios autenticados (Empleado, Aprobador, Admin, Superadmin)
     * pueden crear cualquier tipo de solicitud.
     * - Las de externos (visita/contratista) quedan pendientes de que el 
     *   Aprobador de la planta destino las autorice.
     */
    private function tiposPermitidos($user): array
    {
        return ['pase_salida', 'interplantas', 'visita', 'contratista'];
    }

}