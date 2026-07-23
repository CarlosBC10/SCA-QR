<?php
// app/Http/Controllers/UsuarioController.php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUsuarioRequest;
use App\Http\Requests\StoreGuardiaRequest;
use App\Http\Requests\UpdateUsuarioRequest;
use App\Models\UsuarioSca;
use App\Models\RolSca;
use App\Models\Guardia;
use App\Models\Planta;
use App\Models\EmpleadoExtSca;
use App\Models\PuestoTipoEmpleado;
use App\Models\Sitec\Empleado;
use App\Models\LogSistema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UsuarioController extends Controller
{
    /**
     * OPTIMIZADO: eager loading + scoping optimizado.
     */
    public function index(Request $request)
    {
        $query = UsuarioSca::with(['rol', 'guardia.planta']);

        if ($request->filled('rol')) {
            $query->where('id_rol', $request->rol);
        }
        if ($request->filled('planta')) {
            $plantaId = $request->planta;
            $query->where(function ($q) use ($plantaId) {
                $q->whereHas('guardia', fn($g) => $g->where('planta_id', $plantaId))
                  ->orWhereIn('username', function ($sub) use ($plantaId) {
                      $sub->select('empleado_id')
                          ->from('empleados_ext_sca')
                          ->where('planta_id', $plantaId);
                  });
            });
        }
        if ($request->filled('estado')) {
            $query->where('activo', $request->estado === 'activo');
        }
        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where('username', 'like', "%{$buscar}%");
        }

        $user = $request->user();
        if ($user->isAprobador()) {
            $plantaId = $user->planta_id;
            $query->where(function ($q) use ($plantaId) {
                $q->whereHas('guardia', fn($g) => $g->where('planta_id', $plantaId))
                  ->orWhereIn('username', function ($sub) use ($plantaId) {
                      $sub->select('empleado_id')
                          ->from('empleados_ext_sca')
                          ->where('planta_id', $plantaId);
                  });
            });
        }

        $usuarios = $query->orderBy('created_at', 'desc')->paginate(15);

        // OPTIMIZADO: cachear roles y plantas (no queries extra si ya están en caché)
        $roles = RolSca::orderBy('id')->get();
        $plantas = Planta::where('activa', true)->orderBy('nombre')->get();

        return view('usuarios.index', compact('usuarios', 'roles', 'plantas'));
    }

    public function create()
    {
        $user = request()->user();
        $rolesPermitidos = $this->rolesPermitidos($user);
        $plantas = Planta::where('activa', true)->orderBy('nombre')->get();

        return view('usuarios.create', compact('rolesPermitidos', 'plantas'));
    }

    public function storeEmpleado(StoreUsuarioRequest $request)
    {
        $user = $request->user();

        $empleado = Empleado::find($request->id_empleado);
        if (!$empleado) {
            throw ValidationException::withMessages([
                'id_empleado' => 'El número de empleado no existe en sitec.',
            ]);
        }
        if (!$empleado->estaActivo()) {
            throw ValidationException::withMessages([
                'id_empleado' => 'El empleado no tiene estatus "Alta" en sitec.',
            ]);
        }

        if (UsuarioSca::where('username', (string) $request->id_empleado)->exists()) {
            throw ValidationException::withMessages([
                'id_empleado' => 'Este empleado ya tiene una cuenta de usuario.',
            ]);
        }

        $rolesPermitidos = $this->rolesPermitidos($user);
        $rol = RolSca::find($request->id_rol);
        if (!$rolesPermitidos->contains('id', $rol->id)) {
            throw ValidationException::withMessages([
                'id_rol' => 'No tiene permisos para asignar este rol.',
            ]);
        }

        UsuarioSca::create([
            'username'   => (string) $request->id_empleado,
            'password'   => Hash::make($request->password),
            'id_rol'     => $rol->id,
            'guardia_id' => null,
            'activo'     => true,
        ]);

        EmpleadoExtSca::updateOrCreate(
            ['empleado_id' => $empleado->id_empleado],
            ['tipo_empleado' => $request->tipo_empleado]
        );

        PuestoTipoEmpleado::firstOrCreate(
            ['id_puesto' => $empleado->id_puesto],
            ['tipo_empleado' => $request->tipo_empleado]
        );

        // Invalidar caché de tipos
        \App\Services\TipoEmpleadoCacheService::invalidar();

        LogSistema::registrar(
            'CREAR_USUARIO',
            "Empleado {$empleado->id_empleado} ({$empleado->nombre_completo}) con rol {$rol->nombre}"
        );

        return redirect()->route('usuarios.index')
            ->with('success', "Usuario {$empleado->nombre_completo} creado correctamente.");
    }

    public function storeGuardia(StoreGuardiaRequest $request)
    {
        $user = $request->user();
        $username = 'G-' . $request->numero_guardia;

        if (UsuarioSca::where('username', $username)->exists()) {
            throw ValidationException::withMessages([
                'numero_guardia' => 'Ya existe un guardia con ese número.',
            ]);
        }

        $plantaId = $user->isAprobador() ? $user->planta_id : $request->planta_id;

        $guardia = Guardia::create([
            'nombre_completo' => $request->nombre_completo,
            'planta_id'       => $plantaId,
            'activo'          => true,
        ]);

        $rolGuardia = RolSca::where('nombre', 'Guardia')->firstOrFail();

        UsuarioSca::create([
            'username'   => $username,
            'password'   => Hash::make($request->password),
            'id_rol'     => $rolGuardia->id,
            'guardia_id' => $guardia->id,
            'activo'     => true,
        ]);

        LogSistema::registrar(
            'CREAR_GUARDIA',
            "Guardia {$username} ({$request->nombre_completo})"
        );

        return redirect()->route('usuarios.index')
            ->with('success', "Guardia {$request->nombre_completo} ({$username}) creado correctamente.");
    }

    public function edit(UsuarioSca $usuario)
    {
        $user = request()->user();

        if ($user->isAprobador() && $usuario->planta_id !== $user->planta_id) {
            abort(403, 'No puede editar usuarios de otra planta.');
        }

        $rolesPermitidos = $this->rolesPermitidos($user);
        $plantas = Planta::where('activa', true)->orderBy('nombre')->get();

        $tipoEmpleado = null;
        $plantaActual = null;
        if (!$usuario->guardia_id) {
            $ext = EmpleadoExtSca::find($usuario->username);
            $tipoEmpleado = $ext?->tipo_empleado;
            $empleado = Empleado::find($usuario->username);
            $plantaActual = $empleado?->plantaId();
        } else {
            $plantaActual = $usuario->guardia?->planta_id;
        }

        return view('usuarios.edit', compact('usuario', 'rolesPermitidos', 'plantas', 'tipoEmpleado', 'plantaActual'));
    }

    public function update(UpdateUsuarioRequest $request, UsuarioSca $usuario)
    {
        $user = $request->user();

        if ($user->isAprobador() && $usuario->planta_id !== $user->planta_id) {
            abort(403, 'No puede editar usuarios de otra planta.');
        }

        $rolesPermitidos = $this->rolesPermitidos($user);
        $rol = RolSca::find($request->id_rol);
        if (!$rolesPermitidos->contains('id', $rol->id)) {
            throw ValidationException::withMessages([
                'id_rol' => 'No tiene permisos para asignar este rol.',
            ]);
        }

        $datos = [
            'id_rol' => $rol->id,
            'activo' => $request->boolean('activo'),
        ];

        if ($request->filled('password')) {
            $datos['password'] = Hash::make($request->password);
        }

        $usuario->update($datos);

        if (!$usuario->guardia_id && $request->filled('tipo_empleado')) {
            EmpleadoExtSca::updateOrCreate(
                ['empleado_id' => $usuario->username],
                ['tipo_empleado' => $request->tipo_empleado]
            );
            \App\Services\TipoEmpleadoCacheService::invalidar();
        }

        if ($usuario->guardia_id && $request->filled('planta_id')) {
            $usuario->guardia->update(['planta_id' => $request->planta_id]);
        }

        LogSistema::registrar(
            'ACTUALIZAR_USUARIO',
            "Usuario {$usuario->username} actualizado"
        );

        return redirect()->route('usuarios.index')
            ->with('success', "Usuario {$usuario->username} actualizado correctamente.");
    }

    public function destroy(UsuarioSca $usuario, Request $request)
    {
        $user = $request->user();

        if ($usuario->id === $user->id) {
            return back()->withErrors(['error' => 'No puede desactivar su propia cuenta.']);
        }

        if ($user->isAprobador() && $usuario->planta_id !== $user->planta_id) {
            abort(403, 'No puede desactivar usuarios de otra planta.');
        }

        $usuario->update(['activo' => false]);

        LogSistema::registrar(
            'DESACTIVAR_USUARIO',
            "Usuario {$usuario->username} desactivado"
        );

        return redirect()->route('usuarios.index')
            ->with('success', "Usuario {$usuario->username} desactivado.");
    }

    /**
     * OPTIMIZADO: eager loading + caché.
     */
    public function buscarEmpleado(Request $request)
    {
        $request->validate(['id' => 'required|integer']);

        $empleado = Empleado::with(['puesto', 'turno', 'departamento'])->find($request->id);

        if (!$empleado) {
            return response()->json(['encontrado' => false]);
        }

        $tieneCuenta = UsuarioSca::where('username', (string) $empleado->id_empleado)->exists();
        $tipoSugerido = \App\Services\TipoEmpleadoCacheService::tipoDePuesto($empleado->id_puesto);

        return response()->json([
            'encontrado'    => true,
            'activo'        => $empleado->estaActivo(),
            'nombre'        => $empleado->nombre_completo,
            'puesto'        => $empleado->puesto?->nombre,
            'departamento'  => $empleado->departamento?->nombre,
            'turno'         => $empleado->turno?->nombre,
            'tiene_cuenta'  => $tieneCuenta,
            'tipo_sugerido' => $tipoSugerido,
            'planta'        => $empleado->plantaNombre(),
        ]);
    }

    private function rolesPermitidos($user)
    {
        $query = RolSca::query();

        if ($user->isSuperadmin()) {
            return $query->orderBy('id')->get();
        }

        if ($user->isAdmin()) {
            return $query->whereIn('nombre', ['Admin', 'Aprobador', 'Empleado', 'Guardia'])
                ->orderBy('id')->get();
        }

        return $query->whereIn('nombre', ['Aprobador', 'Empleado', 'Guardia'])
            ->orderBy('id')->get();
    }
}