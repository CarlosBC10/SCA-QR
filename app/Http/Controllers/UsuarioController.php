<?php
// app/Http/Controllers/UsuarioController.php
//
// Controlador de gestión de usuarios web (M2).
// Gestiona USUARIOS_SCA (login web), no empleados_ext_sca.
//
// JERARQUÍA DE PERMISOS (SRS §2.3.1):
//   - Superadmin: crea cualquier rol, ve todos los usuarios
//   - Admin: crea Admin, Aprobador, Empleado, Guardia; ve todos
//   - Aprobador: crea Aprobador, Empleado, Guardia; ve SOLO su planta
//
// SCOPING DEL APROBADOR:
//   - Solo ve usuarios de su planta (guardias con su planta_id o
//     empleados con empleados_ext_sca.planta_id = su planta).

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
     * LISTA de usuarios con filtros y scoping.
     * GET /usuarios
     */
    public function index(Request $request)
    {
        $query = UsuarioSca::with(['rol', 'guardia.planta']);

        // --- Filtros (UI-22) ---
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

        // --- Scoping: el Aprobador solo ve usuarios de su planta ---
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

        $roles = RolSca::orderBy('id')->get();
        $plantas = Planta::where('activa', true)->orderBy('nombre')->get();

        return view('usuarios.index', compact('usuarios', 'roles', 'plantas'));
    }

    /**
     * FORMULARIO de creación (con tabs: empleado / guardia).
     * GET /usuarios/create
     */
    public function create()
    {
        $user = request()->user();
        $rolesPermitidos = $this->rolesPermitidos($user);
        $plantas = Planta::where('activa', true)->orderBy('nombre')->get();

        return view('usuarios.create', compact('rolesPermitidos', 'plantas'));
    }

        /**
     * GUARDAR empleado interno (usuario web).
     * POST /usuarios/empleado
     */
    public function storeEmpleado(StoreUsuarioRequest $request)
    {
        $user = $request->user();

        // 1. Validar que el empleado exista en sitec y esté activo
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

        // 2. Validar que no tenga ya una cuenta de usuario web
        if (UsuarioSca::where('username', (string) $request->id_empleado)->exists()) {
            throw ValidationException::withMessages([
                'id_empleado' => 'Este empleado ya tiene una cuenta de usuario.',
            ]);
        }

        // 3. Validar jerarquía de roles
        $rolesPermitidos = $this->rolesPermitidos($user);
        $rol = RolSca::find($request->id_rol);
        if (!$rolesPermitidos->contains('id', $rol->id)) {
            throw ValidationException::withMessages([
                'id_rol' => 'No tiene permisos para asignar este rol.',
            ]);
        }

        // 4. Planta: si es Aprobador, forzar su planta
        $plantaId = $request->planta_id;
        if ($user->isAprobador()) {
            $plantaId = $user->planta_id;
        }

        // 5. Crear el usuario web en usuarios_sca
        UsuarioSca::create([
            'username'   => (string) $request->id_empleado,
            'password'   => Hash::make($request->password),
            'id_rol'     => $rol->id,
            'guardia_id' => null,
            'activo'     => true,
        ]);

        // 6. ACTUALIZAR empleados_ext_sca.
        // 
        // CAMBIO CLAVE: Usamos updateOrCreate en lugar de firstOrCreate.
        // 
        // ¿POR QUÉ?
        // Porque el registro del empleado YA EXISTE en empleados_ext_sca
        // (gracias a la sincronización 1:1 con sitec que poblar 25,000 registros).
        // Al darle un login web, solo necesitamos actualizarle el tipo_empleado
        // y planta_id según lo que eligió el admin.
        EmpleadoExtSca::updateOrCreate(
            ['empleado_id' => $empleado->id_empleado],
            [
                'tipo_empleado' => $request->tipo_empleado,
                'planta_id'     => $plantaId,
            ]
        );

        // 7. Si el puesto no estaba clasificado en el catálogo maestro, clasificarlo ahora
        PuestoTipoEmpleado::firstOrCreate(
            ['id_puesto' => $empleado->id_puesto],
            ['tipo_empleado' => $request->tipo_empleado]
        );

        LogSistema::registrar(
            'CREAR_USUARIO',
            "Empleado {$empleado->id_empleado} ({$empleado->nombre_completo}) con rol {$rol->nombre}"
        );

        return redirect()->route('usuarios.index')
            ->with('success', "Usuario {$empleado->nombre_completo} creado correctamente.");
    }

    /**
     * GUARDAR guardia externo.
     * POST /usuarios/guardia
     */
    public function storeGuardia(StoreGuardiaRequest $request)
    {
        $user = $request->user();

        // 1. Construir el username: G-XXXXX
        $username = 'G-' . $request->numero_guardia;

        // 2. Validar que no exista ya ese username
        if (UsuarioSca::where('username', $username)->exists()) {
            throw ValidationException::withMessages([
                'numero_guardia' => 'Ya existe un guardia con ese número.',
            ]);
        }

        // 3. Si es Aprobador, forzar planta = su planta
        $plantaId = $request->planta_id;
        if ($user->isAprobador()) {
            $plantaId = $user->planta_id;
        }

        // 4. Crear el guardia en la tabla guardias
        $guardia = Guardia::create([
            'nombre_completo' => $request->nombre_completo,
            'planta_id'       => $plantaId,
            'activo'          => true,
        ]);

        // 5. Crear el usuario en usuarios_sca
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

    /**
     * FORMULARIO de edición.
     * GET /usuarios/{id}/edit
     */
    public function edit(UsuarioSca $usuario)
    {
        $user = request()->user();

        // Scoping: el Aprobador no puede editar usuarios de otra planta
        if ($user->isAprobador() && $usuario->planta_id !== $user->planta_id) {
            abort(403, 'No puede editar usuarios de otra planta.');
        }

        $rolesPermitidos = $this->rolesPermitidos($user);
        $plantas = Planta::where('activa', true)->orderBy('nombre')->get();

        // Obtener tipo_empleado y planta del empleado (si es interno)
        $tipoEmpleado = null;
        $plantaActual = null;
        if (!$usuario->guardia_id) {
            $ext = EmpleadoExtSca::find($usuario->username);
            $tipoEmpleado = $ext?->tipo_empleado;
            $plantaActual = $ext?->planta_id;
        } else {
            $plantaActual = $usuario->guardia?->planta_id;
        }

        return view('usuarios.edit', compact('usuario', 'rolesPermitidos', 'plantas', 'tipoEmpleado', 'plantaActual'));
    }

    /**
     * ACTUALIZAR usuario.
     * PUT /usuarios/{id}
     */
    public function update(UpdateUsuarioRequest $request, UsuarioSca $usuario)
    {
        $user = $request->user();

        // Scoping
        if ($user->isAprobador() && $usuario->planta_id !== $user->planta_id) {
            abort(403, 'No puede editar usuarios de otra planta.');
        }

        // Validar jerarquía de roles
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

        // Planta (siempre presente, validada como required)
        $plantaId = $request->planta_id;
        if ($user->isAprobador()) {
            $plantaId = $user->planta_id;
        }

        // Si es empleado interno, actualizar empleados_ext_sca
        if (!$usuario->guardia_id && $request->filled('tipo_empleado')) {
            EmpleadoExtSca::updateOrCreate(
                ['empleado_id' => $usuario->username],
                [
                    'tipo_empleado' => $request->tipo_empleado,
                    'planta_id'     => $plantaId,
                ]
            );
        }

        // Si es guardia, actualizar planta
        if ($usuario->guardia_id) {
            $usuario->guardia->update(['planta_id' => $plantaId]);
        }

        LogSistema::registrar(
            'ACTUALIZAR_USUARIO',
            "Usuario {$usuario->username} actualizado"
        );

        return redirect()->route('usuarios.index')
            ->with('success', "Usuario {$usuario->username} actualizado correctamente.");
    }

    /**
     * DESACTIVAR usuario (baja lógica, no borra).
     * DELETE /usuarios/{id}
     */
    public function destroy(UsuarioSca $usuario, Request $request)
    {
        $user = $request->user();

        // No puede desactivarse a sí mismo
        if ($usuario->id === $user->id) {
            return back()->withErrors(['error' => 'No puede desactivar su propia cuenta.']);
        }

        // Scoping
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
     * BUSCAR empleado en sitec (AJAX).
     * GET /usuarios/buscar-empleado?id=1001
     */
    public function buscarEmpleado(Request $request)
    {
        $request->validate(['id' => 'required|integer']);

        $empleado = Empleado::with(['puesto', 'turno', 'departamento'])->find($request->id);

        if (!$empleado) {
            return response()->json(['encontrado' => false]);
        }

        $tieneCuenta = UsuarioSca::where('username', (string) $empleado->id_empleado)->exists();
        $tipoSugerido = PuestoTipoEmpleado::tipoDePuesto($empleado->id_puesto);

        return response()->json([
            'encontrado'    => true,
            'activo'        => $empleado->estaActivo(),
            'nombre'        => $empleado->nombre_completo,
            'puesto'        => $empleado->puesto?->nombre,
            'departamento'  => $empleado->departamento?->nombre,
            'turno'         => $empleado->turno?->nombre,
            'tiene_cuenta'  => $tieneCuenta,
            'tipo_sugerido' => $tipoSugerido,
        ]);
    }

    // ============================================================
    // HELPERS PRIVADOS
    // ============================================================

    /**
     * Devuelve los roles que el usuario logueado puede crear/asignar.
     * Según la jerarquía del SRS §2.3.1:
     *   - Superadmin: todos los roles
     *   - Admin: Admin, Aprobador, Empleado, Guardia (no Superadmin)
     *   - Aprobador: Aprobador, Empleado, Guardia
     */
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

        // Aprobador
        return $query->whereIn('nombre', ['Aprobador', 'Empleado', 'Guardia'])
            ->orderBy('id')->get();
    }
}