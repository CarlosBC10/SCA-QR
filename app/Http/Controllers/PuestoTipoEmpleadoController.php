<?php
// app/Http/Controllers/PuestoTipoEmpleadoController.php

namespace App\Http\Controllers;

use App\Http\Requests\StorePuestoTipoRequest;
use App\Models\Sitec\Puesto;
use App\Models\PuestoTipoEmpleado;
use App\Models\LogSistema;
use Illuminate\Http\Request;

class PuestoTipoEmpleadoController extends Controller
{
    /**
     * FIX J-1: Solo lista puestos con al menos 1 empleado activo (no vacíos).
     */
    public function index()
    {
        // 1. Conteo de empleados por puesto en UNA sola query agrupada
        $conteoEmpleados = \App\Models\Sitec\Empleado::on('sitec')
            ->selectRaw('id_puesto, COUNT(*) as total')
            ->where('estatus', 'Alta')
            ->whereNotNull('id_puesto')
            ->groupBy('id_puesto')
            ->pluck('total', 'id_puesto');

        // FIX J-1: Solo puestos con al menos 1 empleado activo
        $puestos = Puesto::whereIn('id_puesto', $conteoEmpleados->keys())
            ->orderBy('nombre')
            ->get();

        // 2. Clasificaciones actuales (mapeo id_puesto => tipo)
        $clasificaciones = PuestoTipoEmpleado::pluck('tipo_empleado', 'id_puesto');

        return view('puestos.index', compact('puestos', 'clasificaciones', 'conteoEmpleados'));
    }

    public function store(StorePuestoTipoRequest $request)
    {
        $idPuesto = $request->id_puesto;
        $nuevoTipo = $request->tipo_empleado;
        $actualizados = PuestoTipoEmpleado::reclasificarPuesto($idPuesto, $nuevoTipo);

        LogSistema::registrar('CLASIFICAR_PUESTO', "Puesto {$idPuesto} clasificado como '{$nuevoTipo}'. {$actualizados} empleados actualizados.");

        // M2 [ScaModal] — Construir mensaje detallado con tipo y total.
        $tipoLabel = ucfirst($nuevoTipo);
        $mensaje = "<div class='text-start'>";
        $mensaje .= "<div class='mb-2'><strong>Puesto clasificado como:</strong> {$tipoLabel}</div>";
        $mensaje .= "<div class='mb-2'><strong>Empleados actualizados:</strong> {$actualizados}</div>";
        $mensaje .= "</div>";

        // Option E: Setear flash en sesión ANTES de devolver JSON.
        session()->flash('sca_modal', sca_modal_flash('success', 'Puesto clasificado', $mensaje));

        if ($request->expectsJson()) {
            return response()->json([
                'success'  => true,
                'redirect' => route('puestos.index'),
            ]);
        }

        return redirect()->route('puestos.index')->with(
            'sca_modal',
            sca_modal_flash('success', 'Puesto clasificado', $mensaje)
        );
    }

    /**
     * FIX J-2: Clasificación masiva.
    */
    public function clasificarMasivo(Request $request)
    {
        $request->validate([
            'id_puestos'    => ['required', 'array', 'min:1'],
            'id_puestos.*'  => ['integer'],
            'tipo_empleado' => ['required', 'in:operativo,administrativo,corporativo'],
        ]);

        $idPuestos = $request->id_puestos;
        $nuevoTipo = $request->tipo_empleado;
        $totalActualizados = 0;
        $procesados = 0;

        foreach ($idPuestos as $idPuesto) {
            $actualizados = PuestoTipoEmpleado::reclasificarPuesto($idPuesto, $nuevoTipo);
            $totalActualizados += $actualizados;
            $procesados++;
        }

        LogSistema::registrar('CLASIFICAR_PUESTOS_MASIVO', "Clasificación masiva: {$procesados} puestos → '{$nuevoTipo}'. {$totalActualizados} empleados actualizados.");

        // M2 [ScaModal] — Construir mensaje detallado con resumen.
        $tipoLabel = ucfirst($nuevoTipo);
        $mensaje = "<div class='text-start'>";
        $mensaje .= "<div class='mb-2'><strong>Tipo asignado:</strong> {$tipoLabel}</div>";
        $mensaje .= "<hr class='my-2'>";
        $mensaje .= "<div class='row text-center'>";
        $mensaje .= "<div class='col-6'><div class='h5 mb-0 text-success'>{$procesados}</div><small class='text-muted'>Puestos clasificados</small></div>";
        $mensaje .= "<div class='col-6'><div class='h5 mb-0 text-primary'>{$totalActualizados}</div><small class='text-muted'>Empleados actualizados</small></div>";
        $mensaje .= "</div></div>";

        // Option E: Setear flash en sesión ANTES de devolver JSON.
        session()->flash('sca_modal', sca_modal_flash('success', 'Clasificación masiva completada', $mensaje));

        if ($request->expectsJson()) {
            return response()->json([
                'success'  => true,
                'redirect' => route('puestos.index'),
            ]);
        }

        return redirect()->route('puestos.index')->with(
            'sca_modal',
            sca_modal_flash('success', 'Clasificación masiva completada', $mensaje)
        );
    }


    /**
     * FIX J-3: Lista los empleados que tienen un puesto específico.
     */
    public function empleadosDelPuesto(int $idPuesto)
    {
        $puesto = Puesto::find($idPuesto);
        if (!$puesto) return response()->json(['error' => 'Puesto no encontrado'], 404);

        $empleados = \App\Models\Sitec\Empleado::on('sitec')
            ->where('id_puesto', $idPuesto)
            ->where('estatus', 'Alta')
            ->with(['departamento'])
            ->orderBy('nombre_completo')
            ->limit(200)
            ->get()
            ->map(function ($e) {
                return [
                    'id'           => $e->id_empleado,
                    'nombre'       => $e->nombre_completo,
                    'departamento' => $e->departamento?->nombre ?? '—',
                    'planta'       => $e->plantaNombre(),
                ];
            });

        return response()->json([
            'puesto'    => $puesto->nombre,
            'id_puesto' => $puesto->id_puesto,
            'total'     => $empleados->count(),
            'empleados' => $empleados,
        ]);
    }

    public function contar(Request $request)
    {
        $request->validate(['id_puesto' => 'required|integer']);
        $cantidad = PuestoTipoEmpleado::contarEmpleadosConPuesto($request->id_puesto);
        return response()->json(['cantidad' => $cantidad]);
    }
}