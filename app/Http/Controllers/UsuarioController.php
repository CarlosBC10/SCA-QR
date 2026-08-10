<?php
// app/Http/Controllers/UsuarioController.php
//
// CAMBIOS V4:
//   - 'activo' (bool) → fecha_baja (null=activo, not-null=inactivo).
//   - UsuarioSca::create sin 'activo' (fecha_alta tiene DEFAULT).
//   - Guardia::create sin 'activo' (fecha_alta tiene DEFAULT).
//   - $usuario->guardia_id → $usuario->id_guardias.
//   - Subqueries: planta_id → id_planta (en empleados_ext_sca y guardias).
//   - $usuario->update(['activo' => false]) → $usuario->darDeBaja().
//   - Guardia: hay que pasar curp (NOT NULL en V4, aunque puede ser único nullable).

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
     * Lista usuarios con filtros.
     *
     * Cambios V4:
     *   - Filtro 'estado' usa fecha_baja en lugar de 'activo'.
     *   - Subqueries usan id_planta.
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
                // Guardias de esa planta
                $q->whereHas('guardia', fn($g) => $g->where('id_planta', $plantaId))
                  // Empleados internos cuya ext_sca.id_planta = esa planta
                  ->orWhereIn('username', function ($sub) use ($plantaId) {
                      $sub->select('empleado_id')
                          ->from('empleados_ext_sca')
                          ->where('id_planta', $plantaId);
                  });
            });
        }

        // Filtro por estado: 'activo' = fecha_baja IS NULL, 'inactivo' = fecha_baja NOT NULL
        if ($request->filled('estado')) {
            if ($request->estado === 'activo') {
                $query->whereNull('fecha_baja');
            } elseif ($request->estado === 'inactivo') {
                $query->whereNotNull('fecha_baja');
            }
        }

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;

            // FIX H: Búsqueda por palabras no consecutivas en username, nombre de empleado o guardia
            $query->where(function ($q) use ($buscar) {
                // 1. Buscar por username
                $q->where('username', 'like', "%{$buscar}%");

                if (ctype_digit($buscar)) {
                    $q->orWhere('username', (string) $buscar);
                }

                // 2. Buscar por nombre en sitec.empleados (si el username es numérico)
                try {
                    $idsEmpleadosMatch = \App\Models\Sitec\Empleado::on('sitec')
                        ->where('estatus', 'Alta')
                        ->where(function ($sub) use ($buscar) {
                            \App\Services\BusquedaService::aplicarBusquedaPalabras($sub, 'nombre_completo', $buscar);
                        })
                        ->limit(50)
                        ->pluck('id_empleado')
                        ->map(fn($id) => (string) $id)
                        ->toArray();
                    
                    if (!empty($idsEmpleadosMatch)) {
                        $q->orWhereIn('username', $idsEmpleadosMatch);
                    }
                } catch (\Exception $e) {}

                // 3. Buscar por nombre en guardias
                try {
                    $usernamesGuardiasMatch = \App\Models\Guardia::whereNotNull('fecha_alta')
                        ->whereNull('fecha_baja')
                        ->where(function ($sub) use ($buscar) {
                            \App\Services\BusquedaService::aplicarBusquedaPalabras($sub, 'nombre_completo', $buscar);
                        })
                        ->limit(50)
                        ->pluck('id_guardia')
                        ->toArray();
                    
                    if (!empty($usernamesGuardiasMatch)) {
                        $q->orWhereIn('username', $usernamesGuardiasMatch);
                    }
                } catch (\Exception $e) {}
            });
        }

        // Scoping del Aprobador: solo ve usuarios de su planta
        $user = $request->user();
        if ($user->isAprobador()) {
            $plantaId = $user->planta_id;
            $query->where(function ($q) use ($plantaId) {
                $q->whereHas('guardia', fn($g) => $g->where('id_planta', $plantaId))
                  ->orWhereIn('username', function ($sub) use ($plantaId) {
                      $sub->select('empleado_id')
                          ->from('empleados_ext_sca')
                          ->where('id_planta', $plantaId);
                  });
            });
        }

        $usuarios = $query->orderBy('fecha_alta', 'desc')->paginate(15);

        $roles = RolSca::orderBy('id_rol')->get();
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

    /**
     * Crea un usuario interno (empleado de sitec).
     *
     * Cambio V4: sin 'activo' => true (fecha_alta tiene DEFAULT).
     */
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
        if (!$rolesPermitidos->contains('id_rol', $rol->id_rol)) {
            throw ValidationException::withMessages([
                'id_rol' => 'No tiene permisos para asignar este rol.',
            ]);
        }

        // FIX I-10: Generar contraseña temporal y preparar datos
        $passwordTemporal = $this->generarPasswordTemporal();
        $emailEmp = $request->input('email_emp');

        // Intentar enviar el correo PRIMERO. Si falla, no creamos el usuario.
        try {
            \Mail::to($emailEmp)->send(new \App\Mail\PasswordTemporalMail(
                $empleado->nombre_completo,
                (string) $request->id_empleado,
                $passwordTemporal,
                route('login')
            ));
        } catch (\Exception $e) {
            LogSistema::registrar('ERROR_MAIL', "No se pudo enviar correo a {$emailEmp}: " . $e->getMessage());
            return back()->withErrors(['email_emp' => 'No se pudo enviar el correo a ' . $emailEmp . '. Verifique la configuración o intente con otro correo.'])->withInput();
        }

        // Si el correo se envió con éxito, creamos el usuario
        UsuarioSca::create([
            'username'                => (string) $request->id_empleado,
            'email_emp'               => $emailEmp, // Guardamos el correo institucional
            'password'                => 'temp:' . Hash::make($passwordTemporal), // Prefijo temporal
            'id_rol'                  => $rol->id_rol,
        ]);

        // Crear/actualizar la extensión SCA del empleado
        EmpleadoExtSca::updateOrCreate(
            ['empleado_id' => $empleado->id_empleado],
            ['tipo_empleado' => $request->tipo_empleado]
        );

        // Clasificar el puesto
        PuestoTipoEmpleado::firstOrCreate(
            ['id_puesto' => $empleado->id_puesto],
            ['tipo_empleado' => $request->tipo_empleado]
        );

        \App\Services\TipoEmpleadoCacheService::invalidar();

        LogSistema::registrar(
            'CREAR_USUARIO',
            "Empleado {$empleado->id_empleado} ({$empleado->nombre_completo}) con rol {$rol->nombre}"
        );

        return redirect()->route('usuarios.index')
            ->with('success', "Usuario {$empleado->nombre_completo} creado. Se envió la contraseña temporal a {$emailEmp}.");
    }

    /**
     * Crea un guardia externo + su usuario web.
     *
     * CAMBIO V5: El id_guardia (ej: G-0001) es la PK de guardias.
     * FIX I-10: Guardias NO llevan prefijo 'temp:'. Se les entrega la contraseña personalmente.
     *            Sí guardamos su correo institucional.
     */
    public function storeGuardia(StoreGuardiaRequest $request)
    {
        $user = $request->user();
        $idGuardia = $request->id_guardia;

        // Validar que el username no exista ya en usuarios_sca
        if (UsuarioSca::where('username', $idGuardia)->exists()) {
            throw ValidationException::withMessages([
                'id_guardia' => 'Ya existe un usuario con ese nombre de usuario.',
            ]);
        }

        // Validar CURP única si se proporcionó
        if (!empty($request->curp)) {
            if (Guardia::where('curp', $request->curp)->exists()) {
                throw ValidationException::withMessages([
                    'curp' => 'Ya existe un guardia con esa CURP.',
                ]);
            }
        }

        // Si es aprobador, la planta se toma de su sesión; si no, del formulario
        $plantaId = $user->isAprobador() ? $user->planta_id : $request->id_planta;

        // Crear el guardia (id_guardia es la PK)
        $guardia = Guardia::create([
            'id_guardia'      => $idGuardia,
            'nombre_completo' => $request->nombre_completo,
            'curp'            => $request->curp ?? null,
            'id_planta'       => $plantaId,
        ]);

        $rolGuardia = RolSca::where('nombre', 'Guardia')->firstOrFail();

        // FIX I-9: Guardias NO llevan prefijo 'temp:'. Se les entrega la contraseña personalmente.
        $passwordTemporal = $this->generarPasswordTemporal();

        UsuarioSca::create([
            'username'                => $idGuardia,
            'email_emp'               => $request->input('email_emp'), // Guardamos el correo institucional
            'password'                => Hash::make($passwordTemporal), // Hash normal, sin prefijo temp
            'id_rol'                  => $rolGuardia->id_rol,
        ]);

        LogSistema::registrar(
            'CREAR_GUARDIA',
            "Guardia {$idGuardia} ({$request->nombre_completo})"
        );

        // Los guardias no reciben correo de credenciales, se les entrega personalmente
        return redirect()->route('usuarios.index')
            ->with('warning', "Guardia {$request->nombre_completo} ({$idGuardia}) creado. Contraseña temporal: {$passwordTemporal} (entréguela personalmente).");
    }

    public function edit(UsuarioSca $usuario)
    {
        $user = request()->user();

        // Scoping: aprobador solo edita usuarios de su planta
        if ($user->isAprobador() && $usuario->planta_id !== $user->planta_id) {
            abort(403, 'No puede editar usuarios de otra planta.');
        }

        $rolesPermitidos = $this->rolesPermitidos($user);
        $plantas = Planta::where('activa', true)->orderBy('nombre')->get();

        $tipoEmpleado = null;
        $plantaActual = null;
        if (!$usuario->esGuardia()) {
            // Empleado interno
            $ext = EmpleadoExtSca::find($usuario->username);
            $tipoEmpleado = $ext?->tipo_empleado;
            $empleado = Empleado::find($usuario->username);
            $plantaActual = $empleado?->plantaId();
        } else {
            // Guardia
            $plantaActual = $usuario->guardia?->id_planta;
        }

        return view('usuarios.edit', compact('usuario', 'rolesPermitidos', 'plantas', 'tipoEmpleado', 'plantaActual'));
    }

    /**
     * Actualiza un usuario.
     *
     * Cambios V4:
     *   - 'activo' (bool) → fecha_baja (null=activo).
     *   - Si activa un usuario que estaba inactivo: setear fecha_baja = null.
     *   - Si desactiva: setear fecha_baja = today.
     *   - FKs: guardia_id → id_guardias, planta_id → id_planta.
     */
    public function update(UpdateUsuarioRequest $request, UsuarioSca $usuario)
    {
        $user = $request->user();

        if ($user->isAprobador() && $usuario->planta_id !== $user->planta_id) {
            abort(403, 'No puede editar usuarios de otra planta.');
        }

        $rolesPermitidos = $this->rolesPermitidos($user);
        $rol = RolSca::find($request->id_rol);
        if (!$rolesPermitidos->contains('id_rol', $rol->id_rol)) {
            throw ValidationException::withMessages([
                'id_rol' => 'No tiene permisos para asignar este rol.',
            ]);
        }

        // Datos básicos
        $datos = [
            'id_rol' => $rol->id_rol,
        ];

        // Cambio de contraseña (si se proporcionó)
        if ($request->filled('password')) {
            $datos['password'] = Hash::make($request->password);
        }

        // Gestión de estado activo/inactivo via fecha_baja
        $activar = $request->boolean('activo');
        if ($activar && $usuario->fecha_baja !== null) {
            // Reactivar usuario
            $datos['fecha_baja'] = null;
        } elseif (!$activar && $usuario->fecha_baja === null) {
            // Desactivar usuario
            $datos['fecha_baja'] = now()->toDateString();
        }

        $usuario->update($datos);

        // Si es empleado interno, actualizar tipo_empleado
        if (!$usuario->esGuardia() && $request->filled('tipo_empleado')) {
            EmpleadoExtSca::updateOrCreate(
                ['empleado_id' => $usuario->username],
                ['tipo_empleado' => $request->tipo_empleado]
            );
            \App\Services\TipoEmpleadoCacheService::invalidar();
        }

        // Si es guardia, actualizar planta
        if ($usuario->esGuardia() && $request->filled('planta_id')) {
            $usuario->guardia->update(['id_planta' => $request->planta_id]);
        }

        LogSistema::registrar(
            'ACTUALIZAR_USUARIO',
            "Usuario {$usuario->username} actualizado"
        );

        return redirect()->route('usuarios.index')
            ->with('success', "Usuario {$usuario->username} actualizado correctamente.");
    }

    /**
     * Desactiva un usuario (soft delete via fecha_baja).
     *
     * Cambio V4: update(['activo' => false]) → darDeBaja().
     */
    public function destroy(UsuarioSca $usuario, Request $request)
    {
        $user = $request->user();

        // No puede desactivarse a sí mismo
        if ($usuario->id_usuario_sca === $user->id_usuario_sca) {
            return back()->withErrors(['error' => 'No puede desactivar su propia cuenta.']);
        }

        if ($user->isAprobador() && $usuario->planta_id !== $user->planta_id) {
            abort(403, 'No puede desactivar usuarios de otra planta.');
        }

        $usuario->darDeBaja();

        LogSistema::registrar(
            'DESACTIVAR_USUARIO',
            "Usuario {$usuario->username} desactivado"
        );

        return redirect()->route('usuarios.index')
            ->with('success', "Usuario {$usuario->username} desactivado.");
    }

    /**
     * API: busca empleados por ID o por nombre (para el formulario de crear usuario).
     * FIX H-3: Acepta ?id=N o ?q=texto
     */
    public function buscarEmpleado(Request $request)
    {
        // Si llega ?id=N, busca por ID exacto
        if ($request->filled('id')) {
            $request->validate(['id' => 'integer']);
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

        // Si llega ?q=texto, busca por nombre (palabras no consecutivas)
        $request->validate(['q' => 'required|string|min:2']);
        $q = $request->q;

        $query = Empleado::where('estatus', 'Alta');

        if (ctype_digit($q)) {
            $query->where('id_empleado', (int) $q);
        } else {
            \App\Services\BusquedaService::aplicarBusquedaPalabras($query, 'nombre_completo', $q);
        }

        $resultados = $query->with(['puesto', 'turno', 'departamento'])
            ->limit(10)
            ->get()
            ->map(function ($e) {
                $tieneCuenta = UsuarioSca::where('username', (string) $e->id_empleado)->exists();
                return [
                    'id'            => $e->id_empleado,
                    'encontrado'    => true,
                    'activo'        => $e->estaActivo(),
                    'nombre'        => $e->nombre_completo,
                    'puesto'        => $e->puesto?->nombre,
                    'departamento'  => $e->departamento?->nombre,
                    'turno'         => $e->turno?->nombre,
                    'tiene_cuenta'  => $tieneCuenta,
                    'tipo_sugerido' => \App\Services\TipoEmpleadoCacheService::tipoDePuesto($e->id_puesto),
                    'planta'        => $e->plantaNombre(),
                ];
            });

        return response()->json($resultados);
    }

    /**
     * Roles que el usuario actual puede asignar.
     */
    private function rolesPermitidos($user)
    {
        $query = RolSca::query();

        if ($user->isSuperadmin()) {
            return $query->orderBy('id_rol')->get();
        }

        if ($user->isAdmin()) {
            return $query->whereIn('nombre', ['Admin', 'Aprobador', 'Empleado', 'Guardia'])
                ->orderBy('id_rol')->get();
        }

        // Aprobador
        return $query->whereIn('nombre', ['Aprobador', 'Empleado', 'Guardia'])
            ->orderBy('id_rol')->get();
    }

    private function generarPasswordTemporal(int $longitud = 12): string
    {
        $mayus = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $minus = 'abcdefghjkmnpqrstuvwxyz';
        $nums  = '23456789';
        $simb  = '!@#$%^&*';
        $partes = [
            substr(str_shuffle($mayus), 0, 4),
            substr(str_shuffle($minus), 0, 4),
            substr(str_shuffle($nums), 0, 3),
            substr(str_shuffle($simb), 0, 1),
        ];
        return str_shuffle(implode('', $partes));
    }

    /**
     * FIX I: Resetear contraseña de un usuario desde el panel de admin.
     * Genera una temporal, la guarda con prefijo 'temp:' y envía correo.
     */
    public function resetPassword(UsuarioSca $usuario, Request $request)
    {
        $user = $request->user();

        // Permisos: Admin/Super pueden resetear a cualquiera. Aprobador solo a los de su planta.
        if (!$user->isAdmin() && !$user->isSuperadmin()) {
            if ($user->isAprobador() && $usuario->planta_id !== $user->planta_id) {
                abort(403, 'No puede resetear la contraseña de usuarios de otra planta.');
            }
        }

        if (!$usuario->email_emp) {
            return back()->withErrors(['error' => 'El usuario no tiene correo institucional registrado.']);
        }

        $passwordTemporal = $this->generarPasswordTemporal();
        $usuario->password = 'temp:' . Hash::make($passwordTemporal);
        $usuario->save();

        try {
            \Mail::to($usuario->email_emp)->send(new \App\Mail\PasswordTemporalMail(
                $usuario->nombre_completo,
                $usuario->username,
                $passwordTemporal,
                route('login')
            ));
        } catch (\Exception $e) {
            LogSistema::registrar('ERROR_MAIL', "No se pudo enviar correo de reseteo a {$usuario->email_emp}: " . $e->getMessage());
            return back()->withErrors(['error' => 'No se pudo enviar el correo. Intente más tarde.']);
        }

        LogSistema::registrar('RESETEO_PASSWORD', "El admin {$user->username} reseteó la contraseña de {$usuario->username}.");

        return back()->with('success', "Se envió una contraseña temporal a {$usuario->email_emp}.");
    }
}