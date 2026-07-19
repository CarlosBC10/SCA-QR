<?php
// app/Http/Controllers/PuestoTipoEmpleadoController.php
//
// Controlador de clasificación de puestos (M2).
// Permite clasificar cada puesto de sitec en operativo, administrativo
// o corporativo. Al clasificar, se actualizan todos los empleados con
// ese puesto (con aviso previo de cuántos son).
//
// ACCESO: Superadmin, Admin, Aprobador (los 3 pueden clasificar puestos).

namespace App\Http\Controllers;

use App\Http\Requests\StorePuestoTipoRequest;
use App\Models\Sitec\Puesto;
use App\Models\PuestoTipoEmpleado;
use App\Models\LogSistema;
use Illuminate\Http\Request;

class PuestoTipoEmpleadoController extends Controller
{
    /**
     * LISTA de puestos con su clasificación actual.
     * GET /puestos
     */
    public function index()
    {
        $puestos = Puesto::orderBy('nombre')->get();
        $clasificaciones = PuestoTipoEmpleado::pluck('tipo_empleado', 'id_puesto');

        return view('puestos.index', compact('puestos', 'clasificaciones'));
    }

    /**
     * GUARDAR/ACTUALIZAR clasificación de un puesto.
     * POST /puestos
     */
    public function store(StorePuestoTipoRequest $request)
    {
        $idPuesto = $request->id_puesto;
        $nuevoTipo = $request->tipo_empleado;

        // Reclasificar (transacción: actualiza puesto_tipo_empleado + empleados_ext_sca)
        $actualizados = PuestoTipoEmpleado::reclasificarPuesto($idPuesto, $nuevoTipo);

        LogSistema::registrar(
            'CLASIFICAR_PUESTO',
            "Puesto {$idPuesto} clasificado como '{$nuevoTipo}'. {$actualizados} empleados actualizados."
        );

        return redirect()->route('puestos.index')
            ->with('success', "Puesto clasificado como '{$nuevoTipo}'. {$actualizados} empleados actualizados.");
    }

    /**
     * CONTAR empleados con un puesto (AJAX para el aviso previo).
     * GET /puestos/contar?id_puesto=1
     */
    public function contar(Request $request)
    {
        $request->validate(['id_puesto' => 'required|integer']);

        $cantidad = PuestoTipoEmpleado::contarEmpleadosConPuesto($request->id_puesto);

        return response()->json(['cantidad' => $cantidad]);
    }
}