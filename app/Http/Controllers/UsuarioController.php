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

        // ── Scoping de visibilidad por rol del visor ──────────────────────
        // Superadmin: ve todos. Admin: ve todos menos Superadmin.
        // Aprobador: solo Aprobador/Empleado/Guardia de su planta (y a sí mismo).
        $user = $request->user();

        $rolesVisibles = match (true) {
            $user->isSuperadmin() => ['Superadmin', 'Admin', 'Aprobador', 'Empleado', 'Guardia'],
            $user->isAdmin()      => ['Admin', 'Aprobador', 'Empleado', 'Guardia'],
            default               => ['Aprobador', 'Empleado', 'Guardia'],
        };
        $query->whereHas('rol', fn($r) => $r->whereIn('nombre', $rolesVisibles));

        // Aprobador: además, solo usuarios de su planta (o su propia cuenta)
        if ($user->isAprobador()) {
            $plantaId = $user->planta_id;
            $query->where(function ($q) use ($plantaId, $user) {
                $q->where('id_usuario_sca', $user->id_usuario_sca)
                  ->orWhere(function ($q2) use ($plantaId) {
                      $q2->whereHas('guardia', fn($g) => $g->where('id_planta', $plantaId))
                         ->orWhereIn('username', function ($sub) use ($plantaId) {
                             $sub->select('empleado_id')
                                 ->from('empleados_ext_sca')
                                 ->where('id_planta', $plantaId);
                         });
                  });
            });
        }

        $usuarios = $query->orderBy('fecha_alta', 'desc')->paginate(15);

        // El dropdown de filtro solo ofrece los roles que el visor puede ver
        $roles = RolSca::whereIn('nombre', $rolesVisibles)->orderBy('id_rol')->get();
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
            // M2 [ScaModal] — Si es AJAX, devolver JSON con error
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'titulo'  => 'Error de correo',
                    'mensaje' => "No se pudo enviar el correo a {$emailEmp}. Verifique la configuración o intente con otro correo.",
                ], 422);
            }
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

        // M2 [ScaModal] — Construir mensaje detallado
        $mensaje = "<div class='text-start'>";
        $mensaje .= "<div class='mb-2'><strong>Usuario creado:</strong> {$empleado->nombre_completo} ({$empleado->id_empleado})</div>";
        $mensaje .= "<div class='mb-2'><strong>Rol:</strong> {$rol->nombre}</div>";
        $mensaje .= "<div class='mb-2'><strong>Correo enviado a:</strong> {$emailEmp}</div>";
        $mensaje .= "<div class='mt-2 alert alert-info py-2 mb-0'><i class='bi bi-info-circle-fill'></i> La contraseña temporal fue enviada por correo. El usuario debe cambiarla al iniciar sesión.</div>";
        $mensaje .= "</div>";

        // Option E: Setear flash en sesión ANTES de devolver JSON.
        session()->flash('sca_modal', sca_modal_flash('success', 'Usuario creado', $mensaje));

        if ($request->expectsJson()) {
            return response()->json([
                'success'  => true,
                'redirect' => route('usuarios.index'),
            ]);
        }

        return redirect()->route('usuarios.index')->with(
            'sca_modal',
            sca_modal_flash('success', 'Usuario creado', $mensaje)
        );
    }

    /**
     * Crea un guardia externo + su usuario web.
     *
     * CAMBIO V5: El id_guardia (ej: G-0001) es la PK de guardias.
     *
     * M3 (flujo de contraseña temporal para guardias):
     *   - El username (G-XXXX) se AUTOGENERA en el servidor
     *     (generarUsernameGuardia): máximo numérico existente + 1, con
     *     verificación de unicidad en guardias Y usuarios_sca.
     *   - La contraseña se genera automáticamente y es TEMPORAL
     *     (prefijo 'temp:'). NO se muestra en pantalla: se envía por
     *     correo al CREADOR (el operador logueado), quien la entrega
     *     al guardia.
     *   - El guardia deberá cambiarla en su primer login (middleware
     *     DebeCambiarPassword redirige a password/cambiar).
     *
     * M3 v2 (robustez ante fallos y concurrencia):
     *   - Alta TRANSACCIONAL: guardias + usuarios_sca se crean dentro de
     *     DB::beginTransaction/commit — nunca queda un alta a medias.
     *   - Reintento ante carrera de username: si dos admins crean a la vez
     *     y ambas peticiones generan el mismo G-XXXX, el INSERT perdedor
     *     recibe duplicate key (1062) y reintenta con el siguiente
     *     disponible (hasta 5 veces) en lugar de mostrar un error SQL.
     *   - El correo se envía DESPUÉS del commit. MySQL y SMTP no pueden
     *     compartir una transacción ACID: si el correo viajara antes y el
     *     alta fallara, el operador recibiría credenciales de una cuenta
     *     inexistente. Si el correo falla después del commit, la cuenta
     *     YA existe con su temporal vigente: se registra el fallo, se
     *     avisa al operador y el botón de la lista (reset unificado)
     *     sirve como "Reenviar credenciales".
     */
    public function storeGuardia(StoreGuardiaRequest $request)
    {
        $user = $request->user();

        // ============================================================
        // M3: El creador DEBE tener correo — la contraseña temporal del
        // guardia se envía ahí. (El Superadmin inicial puede no tener
        // email_emp registrado; se le pide configurarlo antes de crear.)
        // ============================================================
        if (!$user->email_emp) {
            $mensajeError = 'Su usuario no tiene correo registrado. La contraseña temporal del guardia se envía a SU correo, por lo que no puede crear guardias hasta que un Superadmin registre su email.';
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'titulo'  => 'Sin correo de creador',
                    'mensaje' => $mensajeError,
                ], 422);
            }
            return back()->withErrors(['email_creador' => $mensajeError]);
        }

        // Validar CURP única (red de seguridad final; el panel de búsqueda
        // del formulario ya la detecta antes de enviar).
        if (Guardia::where('curp', $request->curp)->exists()) {
            throw ValidationException::withMessages([
                'curp' => 'Ya existe un guardia con esa CURP. Use el buscador del formulario para verificar su cuenta.',
            ]);
        }

        // Si es aprobador, la planta se toma de su sesión; si no, del formulario
        $plantaId = $user->isAprobador() ? $user->planta_id : $request->id_planta;

        // M3 v2: contraseña temporal + username con ALTA TRANSACCIONAL y
        // reintento ante carrera de username (G-XXXX).
        $passwordTemporal = $this->generarPasswordTemporal();
        $rolGuardia = RolSca::where('nombre', 'Guardia')->firstOrFail();

        $idGuardia = null;
        $creado = false;
        $ultimoCandidato = null;
        $ultimoError = null;

        for ($intento = 1; $intento <= 5 && !$creado; $intento++) {
            // Cada intento recalcula el MAX+1: si otra petición ganó la
            // carrera y ya commiteó, el nuevo cálculo ve su fila y salta al
            // siguiente número disponible.
            $idGuardia = $this->generarUsernameGuardia();

            // Si la transacción rival AÚN no commitea, el MAX no la ve y
            // vuelve a salir el mismo candidato: forzar el siguiente.
            if ($idGuardia === $ultimoCandidato) {
                $num = (int) substr($idGuardia, 2) + 1;
                $idGuardia = 'G-' . str_pad((string) $num, 4, '0', STR_PAD_LEFT);
            }
            $ultimoCandidato = $idGuardia;

            try {
                \DB::beginTransaction();

                // Crear el guardia (id_guardia es la PK, autogenerado)
                Guardia::create([
                    'id_guardia'      => $idGuardia,
                    'nombre_completo' => $request->nombre_completo,
                    'curp'            => $request->curp ?? null,
                    'id_planta'       => $plantaId,
                ]);

                // M3: contraseña TEMPORAL con prefijo 'temp:' — el guardia
                // la cambia en su primer login (login la detecta y redirige).
                UsuarioSca::create([
                    'username'                => $idGuardia,
                    'password'                => 'temp:' . Hash::make($passwordTemporal),
                    'id_rol'                  => $rolGuardia->id_rol,
                ]);

                // Auditoría DENTRO de la transacción: si el alta se revierte,
                // el log tampoco queda (no hay rastro de algo que no existe).
                LogSistema::registrar(
                    'CREAR_GUARDIA',
                    "Guardia {$idGuardia} ({$request->nombre_completo}) creado por {$user->username}. Correo de credenciales pendiente de envío."
                );

                \DB::commit();
                $creado = true;
            } catch (\Illuminate\Database\QueryException $e) {
                \DB::rollBack();

                $esDuplicado = (($e->errorInfo[1] ?? null) === 1062)
                    || str_contains(strtolower($e->getMessage()), 'duplicate entry');

                if (!$esDuplicado) {
                    throw $e; // error real de BD: que se vea, no se oculta
                }

                // Carrera por la CURP (uk_curp_guardia): la CURP del request
                // es fija; reintentar solo cambiaría el username y volvería a
                // chocar → abortar con mensaje accionable.
                if (str_contains($e->getMessage(), 'uk_curp_guardia')) {
                    throw ValidationException::withMessages([
                        'curp' => 'Ya existe un guardia con esa CURP (alguien lo registró hace un momento). Use el buscador del formulario para verificar su cuenta.',
                    ]);
                }

                // Carrera por el username (PK guardias / uk_username):
                // reintentar con el siguiente G-XXXX disponible.
                $ultimoError = $e->getMessage();
            }
        }

        if (!$creado) {
            // 5 choques seguidos es estadísticamente improbable (indicaría
            // una BD degradada). Abortar SIN crear nada y auditar el fallo.
            LogSistema::registrar(
                'ERROR_CREAR_GUARDIA',
                "No se pudo asignar un username único tras 5 intentos para {$request->nombre_completo}. Último error: {$ultimoError}"
            );
            $mensajeError = 'No se pudo asignar un identificador único al guardia (varios intentos chocaron). Intente de nuevo en unos segundos.';
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'titulo'  => 'Error de concurrencia',
                    'mensaje' => $mensajeError,
                ], 422);
            }
            return back()->withErrors(['error' => $mensajeError]);
        }

        // ============================================================
        // M3 v2: COMMIT EXITOSO → enviar el correo FUERA de la
        // transacción. Si falla, la cuenta ya existe con su temporal
        // vigente: NO se revierte el alta. Se registra el fallo, se le
        // avisa al operador y el botón de la lista (reset unificado)
        // le permite reenviar credenciales cuando quiera.
        // ============================================================
        $correoEnviado = true;
        try {
            \Mail::to($user->email_emp)->send(new \App\Mail\PasswordGuardiaMail(
                $request->nombre_completo,
                $idGuardia,
                $passwordTemporal,
                route('login'),
                $user->nombre_completo ?? $user->username,
                'crear'
            ));
        } catch (\Exception $e) {
            $correoEnviado = false;
            LogSistema::registrar('ERROR_MAIL', "Guardia {$idGuardia} creado, pero el correo de credenciales no pudo enviarse a {$user->email_emp}: " . $e->getMessage());
        }

        $nombrePlanta = \App\Services\PlantaCacheService::nombrePlanta($plantaId);

        if ($correoEnviado) {
            // M3 v2 [ScaModal] — Éxito: SIN contraseña en pantalla.
            $mensaje = "<div class='text-start'>";
            $mensaje .= "<div class='mb-2'><strong>Usuario:</strong> <code class='fs-6'>{$idGuardia}</code></div>";
            $mensaje .= "<div class='mb-2'><strong>Guardia:</strong> {$request->nombre_completo} · {$nombrePlanta}</div>";
            $mensaje .= "<div class='mb-2'><i class='bi bi-envelope-fill text-success'></i> Credenciales enviadas al correo del operador: <strong>{$user->email_emp}</strong></div>";
            $mensaje .= "<div class='alert alert-info mt-2 mb-0 py-2'><small><i class='bi bi-info-circle-fill'></i> El guardia deberá cambiar la contraseña en su primer acceso al panel web.</small></div>";
            $mensaje .= "</div>";

            // Option E: Setear flash en sesión.
            session()->flash('sca_modal', sca_modal_flash('success', 'Guardia creado correctamente', $mensaje));

            if ($request->expectsJson()) {
                return response()->json([
                    'success'  => true,
                    'redirect' => route('usuarios.index'),
                ]);
            }

            return redirect()->route('usuarios.index')->with(
                'sca_modal',
                sca_modal_flash('success', 'Guardia creado correctamente', $mensaje)
            );
        }

        // M3 v2 [ScaModal] — El guardia SÍ fue creado (commit exitoso) pero
        // el correo no salió: advertencia + cómo reenviar credenciales.
        $mensaje = "<div class='text-start'>";
        $mensaje .= "<div class='mb-2'><strong>Usuario:</strong> <code class='fs-6'>{$idGuardia}</code></div>";
        $mensaje .= "<div class='mb-2'><strong>Guardia:</strong> {$request->nombre_completo} · {$nombrePlanta}</div>";
        $mensaje .= "<div class='alert alert-warning mt-2 mb-0 py-2'><i class='bi bi-exclamation-triangle-fill'></i> <strong>El correo con la contraseña temporal no pudo enviarse</strong> ({$user->email_emp}).<br><small>El guardia ya fue creado y su contraseña temporal quedó activa. Use el botón <strong>Resetear contraseña</strong> de la lista de usuarios para generar una nueva e intentar el envío de nuevo.</small></div>";
        $mensaje .= "</div>";

        session()->flash('sca_modal', sca_modal_flash('warning', 'Guardia creado — correo no enviado', $mensaje));

        if ($request->expectsJson()) {
            return response()->json([
                'success'  => true,
                'redirect' => route('usuarios.index'),
            ]);
        }

        return redirect()->route('usuarios.index')->with(
            'sca_modal',
            sca_modal_flash('warning', 'Guardia creado — correo no enviado', $mensaje)
        );
    }

    /**
     * M3: Genera el siguiente username de guardia disponible (G-XXXX).
     *
     * Estrategia: máximo numérico existente (en guardias Y usuarios_sca)
     * + 1, con bucle de verificación de unicidad. Las dos tablas se
     * revisan por si existiera una cuenta huérfana sin registro en
     * guardias (p. ej. de migraciones antiguas).
     *
     * La columna id_guardia es VARCHAR PK y username su espejo en
     * usuarios_sca. M3 v2: ante una carrera REAL (dos admins creando a
     * la vez con el mismo MAX+1), el EXISTS puede pasar en ambas
     * peticiones y el INSERT perdedor recibe duplicate key (1062) —
     * storeGuardia() lo captura y REINTENTA con el siguiente número
     * disponible. La PK única no solo evita corrupción: con el reintento
     * también evita que el operador vea un error SQL.
     */
    private function generarUsernameGuardia(): string
    {
        $maxGuardias = Guardia::where('id_guardia', 'like', 'G-%')
            ->selectRaw("MAX(CAST(SUBSTRING(id_guardia, 3) AS UNSIGNED)) as max_num")
            ->value('max_num') ?? 0;

        $maxUsuarios = UsuarioSca::where('username', 'like', 'G-%')
            ->selectRaw("MAX(CAST(SUBSTRING(username, 3) AS UNSIGNED)) as max_num")
            ->value('max_num') ?? 0;

        $siguiente = max((int) $maxGuardias, (int) $maxUsuarios) + 1;

        // Bucle de unicidad (red de seguridad ante carreras/IDs fuera de serie)
        do {
            $username = 'G-' . str_pad((string) $siguiente, 4, '0', STR_PAD_LEFT);
            $siguiente++;
        } while (
            Guardia::where('id_guardia', $username)->exists()
            || UsuarioSca::where('username', $username)->exists()
        );

        return $username;
    }

    /**
     * M3: Vista previa (referencial) del siguiente username de guardia.
     * Ruta: GET /usuarios/next-guardia-username
     *
     * La usa el form de crear guardia para mostrar el G-XXXX sugerido.
     * El username DEFINITIVO se genera de nuevo en storeGuardia() al
     * guardar — si otro admin crea un guardia mientras el form está
     * abierto, el guardado asigna el siguiente disponible y el modal de
     * éxito muestra el real.
     */
    public function nextGuardiaUsername()
    {
        return response()->json([
            'username' => $this->generarUsernameGuardia(),
        ]);
    }

    /**
     * M3: Búsqueda de guardias existentes (por CURP, ID G-XXXX o nombre).
     * Ruta: GET /usuarios/buscar-guardia?q=...
     *
     * La usa el panel "Verificar guardia existente" del form de crear
     * guardia para detectar duplicados ANTES de intentar el alta.
     *
     * M3 v2 — BÚSQUEDA COMBINADA: el mismo término se busca en paralelo
     * por ID (G-XXXX, por prefijo), CURP (por prefijo) y nombre (palabras
     * no consecutivas). Ya no se adivina el "tipo" del término: la
     * heurística anterior interpretaba "Juan" como prefijo de CURP antes
     * de llegar al nombre, y un nombre jamás debería perderse por
     * "parecer CURP". Orden de resultados: ID exacto > CURP exacta >
     * coincidencias parciales (alfabético por ID).
     *
     * Retorna el estado del guardia y de su cuenta para sugerir
     * editar/reactivar en lugar de duplicar.
     */
    public function buscarGuardia(Request $request)
    {
        $request->validate(['q' => 'required|string|min:2|max:40']);
        $q = trim($request->input('q'));
        $qUpper = mb_strtoupper($q);

        // El collation de la BD (utf8mb4_unicode_ci) hace LIKE insensible a
        // mayúsculas y acentos; se compara en mayúsculas por robustez ante
        // un eventual collation binario en id_guardia/curp.
        $resultados = Guardia::with('planta')
            ->where(function ($w) use ($q, $qUpper) {
                $w->where('id_guardia', 'like', $qUpper . '%')        // ID G-XXXX (prefijo)
                  ->orWhere('curp', 'like', $qUpper . '%')            // CURP (prefijo; 18 chars = exacta)
                  ->orWhere(function ($w2) use ($q) {                 // Nombre (palabras no consecutivas)
                      \App\Services\BusquedaService::aplicarBusquedaPalabras($w2, 'nombre_completo', $q);
                  });
            })
            ->orderByRaw("CASE WHEN id_guardia = ? THEN 0 WHEN curp = ? THEN 1 ELSE 2 END, id_guardia", [$qUpper, $qUpper])
            ->limit(10)
            ->get()
            ->map(function ($g) {
                $cuenta = UsuarioSca::where('username', $g->id_guardia)->first();
                return [
                    'id'            => $g->id_guardia,
                    'nombre'        => $g->nombre_completo,
                    'curp'          => $g->curp,
                    'planta'        => $g->planta?->nombre,
                    'activo'        => $g->activo, // accessor: fecha_alta/fecha_baja
                    'fecha_baja'    => $g->fecha_baja?->format('d/m/Y'),
                    'tiene_cuenta'  => (bool) $cuenta,
                    'cuenta_activa' => $cuenta?->activo ?? false, // accessor UsuarioSca
                    'debe_cambiar'  => $cuenta ? str_starts_with((string) $cuenta->password, 'temp:') : false,
                    'edit_url'      => $cuenta ? route('usuarios.edit', $cuenta->id_usuario_sca) : null,
                ];
            });

        return response()->json($resultados);
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

        // M2: Validar que el rol coincida con el tipo de usuario.
        // - Guardias solo pueden tener rol de Guardia.
        // - Empleados internos NO pueden tener rol de Guardia.
        if ($usuario->esGuardia() && $rol->nombre !== 'Guardia') {
            throw ValidationException::withMessages([
                'id_rol' => 'Los guardias solo pueden tener rol de Guardia.',
            ]);
        }
        if (!$usuario->esGuardia() && $rol->nombre === 'Guardia') {
            throw ValidationException::withMessages([
                'id_rol' => 'Los empleados internos no pueden tener rol de Guardia.',
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

        // FIX I-10 (edición): agregar/corregir correo institucional.
        // Solo para empleados internos: los guardias no manejan correo propio.
        if (!$usuario->esGuardia() && $request->filled('email_emp')) {
            $datos['email_emp'] = strtolower(trim($request->email_emp));
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

        // M2 [ScaModal] — Construir mensaje detallado.
        $nombreUsuario = $usuario->nombre_completo ?? $usuario->username;
        $mensaje = "<div class='text-start'>";
        $mensaje .= "<div class='mb-2'><strong>Usuario:</strong> {$nombreUsuario} ({$usuario->username})</div>";
        $mensaje .= "<div class='mb-2'><strong>Rol:</strong> {$rol->nombre}</div>";
        if ($request->filled('password')) {
            $mensaje .= "<div class='mb-2'><i class='bi bi-key text-success'></i> Contraseña actualizada</div>";
        }
        if ($activar && $usuario->fecha_baja === null) {
            $mensaje .= "<div class='mb-2'><i class='bi bi-check-circle text-success'></i> Usuario activado</div>";
        }
        $mensaje .= "</div>";

        // Option E: Setear flash en sesión.
        session()->flash('sca_modal', sca_modal_flash('success', 'Usuario actualizado', $mensaje));

        if ($request->expectsJson()) {
            return response()->json([
                'success'  => true,
                'redirect' => route('usuarios.index'),
            ]);
        }

        return redirect()->route('usuarios.index')->with(
            'sca_modal',
            sca_modal_flash('success', 'Usuario actualizado', $mensaje)
        );
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
            // M2 [ScaModal] — Si es AJAX, devolver JSON con error
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'titulo'  => 'Acción no permitida',
                    'mensaje' => 'No puede desactivar su propia cuenta.',
                ], 403);
            }
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

        // M2 [ScaModal] — Construir mensaje.
        $nombreUsuario = $usuario->nombre_completo ?? $usuario->username;
        $mensaje = "<div class='text-start'>";
        $mensaje .= "<div class='mb-2'><strong>Usuario desactivado:</strong> {$nombreUsuario} ({$usuario->username})</div>";
        $mensaje .= "<div class='alert alert-info mt-2 mb-0 py-2'><i class='bi bi-info-circle-fill'></i> El usuario fue desactivado y no podrá iniciar sesión. Puede reactivarse cuando sea necesario.</div>";
        $mensaje .= "</div>";

        // Option E: Setear flash en sesión.
        session()->flash('sca_modal', sca_modal_flash('success', 'Usuario desactivado', $mensaje));

        if ($request->expectsJson()) {
            return response()->json([
                'success'  => true,
                'redirect' => route('usuarios.index'),
            ]);
        }

        return redirect()->route('usuarios.index')->with(
            'sca_modal',
            sca_modal_flash('success', 'Usuario desactivado', $mensaje)
        );
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
     * FIX I + M3: Resetear contraseña de un usuario desde el panel de admin.
     * Genera una temporal, la guarda con prefijo 'temp:' y envía correo.
     *
     * M3 (unificado, cubre guardias):
     *   - EMPLEADO: la temporal se envía al correo DEL PROPIO USUARIO
     *     (igual que siempre).
     *   - GUARDIA: la temporal se envía al correo de QUIEN RESETEA
     *     (el operador logueado), porque los guardias no tienen correo
     *     propio. Además:
     *       1. Se cierran TODOS sus turnos/kioskos activos (el kiosko
     *          público se auto-apaga vía polling kioskoStatus, sin
     *          tocar código del kiosko).
     *       2. Se limpia su remember_token (el login de guardia lo
     *          fuerza, hay que revocarlo al resetear).
     *       3. Si el guardia está con la sesión abierta del panel, el
     *          middleware DebeCambiarPassword lo manda a cambiar
     *          contraseña en su siguiente click.
     *
     * FIX (orden enviar→guardar): antes se guardaba la temporal y LUEGO
     * se enviaba el correo — si el envío fallaba, el usuario quedaba
     * varado con una temporal que nadie recibió. Ahora se envía primero:
     * si falla, la cuenta conserva su contraseña anterior y funciona.
     *
     * M3 v2 (nota de diseño — orden inverso a la creación, a propósito):
     * la CREACIÓN de guardias hace commit primero y envía el correo
     * después (cuenta nueva: si el correo falla, ya existe y se recupera
     * con el botón de reset de la lista). El RESET hace lo contrario:
     * es una mutación destructiva de una cuenta EXISTENTE — si el correo
     * falla, la cuenta queda INTACTA con su contraseña anterior y nadie
     * queda varado. El UPDATE posterior a un envío exitoso es de bajo
     * riesgo (sin constraints de unicidad), a diferencia del doble
     * INSERT de la alta.
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

        $esGuardia = $usuario->esGuardia();

        // ============================================================
        // Destino del correo según el tipo de usuario:
        //   - Empleado → SU correo.
        //   - Guardia  → el correo del OPERADOR que resetea.
        // ============================================================
        $correoDestino = $esGuardia ? $user->email_emp : $usuario->email_emp;

        if (!$correoDestino) {
            $motivo = $esGuardia
                ? 'SU usuario no tiene correo registrado. La contraseña temporal del guardia se envía al correo de quien resetea.'
                : 'El usuario no tiene correo institucional registrado. Configure un correo antes de resetear la contraseña.';
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'titulo'  => 'Sin correo',
                    'mensaje' => $motivo,
                ], 422);
            }
            return back()->withErrors(['error' => $motivo]);
        }

        $passwordTemporal = $this->generarPasswordTemporal();

        // ============================================================
        // FIX enviar-antes-de-guardar: si el correo falla, la cuenta
        // queda INTACTA con su contraseña anterior (nadie varado).
        // ============================================================
        try {
            if ($esGuardia) {
                \Mail::to($correoDestino)->send(new \App\Mail\PasswordGuardiaMail(
                    $usuario->nombre_completo,
                    $usuario->username,
                    $passwordTemporal,
                    route('login'),
                    $user->nombre_completo ?? $user->username,
                    'resetear'
                ));
            } else {
                \Mail::to($correoDestino)->send(new \App\Mail\PasswordTemporalMail(
                    $usuario->nombre_completo,
                    $usuario->username,
                    $passwordTemporal,
                    route('login')
                ));
            }
        } catch (\Exception $e) {
            LogSistema::registrar('ERROR_MAIL', "No se pudo enviar correo de reseteo a {$correoDestino}: " . $e->getMessage());
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'titulo'  => 'Error de correo',
                    'mensaje' => 'No se pudo enviar el correo. La contraseña NO fue cambiada. Intente más tarde o verifique la configuración.',
                ], 422);
            }
            return back()->withErrors(['error' => 'No se pudo enviar el correo. La contraseña NO fue cambiada. Intente más tarde.']);
        }

        // ============================================================
        // M3: Solo para GUARDIAS — cerrar turnos/kioskos activos y
        // revocar el remember token. El kiosko público detecta el
        // cierre vía polling (api.kiosko.status) y se auto-apaga.
        // ============================================================
        $kioskosCerrados = 0;
        if ($esGuardia) {
            $kioskosCerrados = \App\Models\TurnoGuardia::where('id_guardia', $usuario->username)
                ->whereNull('fecha_hora_fin')
                ->update(['fecha_hora_fin' => now()]);

            if ($kioskosCerrados > 0) {
                LogSistema::registrar(
                    'FIN_TURNO_GUARDIA',
                    "{$kioskosCerrados} kiosko(s) del guardia {$usuario->username} cerrado(s) por reseteo de contraseña."
                );
            }

            $usuario->remember_token = null;
        }

        // Guardar la temporal (el correo ya viajó).
        $usuario->password = 'temp:' . Hash::make($passwordTemporal);
        $usuario->save();

        LogSistema::registrar('RESETEO_PASSWORD', "El admin {$user->username} reseteó la contraseña de {$usuario->username}" . ($esGuardia ? " (guardia — temporal enviada al correo del operador, {$kioskosCerrados} kiosko(s) cerrado(s))." : '.'));

        // M3 v2 [ScaModal] — Mensaje diferenciado guardia/empleado, con la
        // información en el orden que le importa al operador.
        $nombreUsuario = $usuario->nombre_completo ?? $usuario->username;
        $mensaje = "<div class='text-start'>";
        $mensaje .= "<div class='mb-2'><strong>Usuario:</strong> <code class='fs-6'>{$usuario->username}</code> — {$nombreUsuario}</div>";
        $mensaje .= "<div class='mb-2'>Se generó una nueva contraseña temporal.</div>";
        if ($esGuardia && $kioskosCerrados > 0) {
            $mensaje .= "<div class='mb-2'><strong>Sus kioskos activos fueron cerrados:</strong> {$kioskosCerrados}</div>";
        }
        if ($esGuardia) {
            $mensaje .= "<div class='mb-2'><i class='bi bi-envelope-fill text-success'></i> La nueva contraseña fue enviada al <strong>correo del operador</strong>: {$correoDestino}</div>";
            $mensaje .= "<div class='alert alert-info mt-2 mb-0 py-2'><small><i class='bi bi-info-circle-fill'></i> Entréguela al guardia por un medio seguro. Deberá cambiarla en su primer acceso al panel web.</small></div>";
        } else {
            $mensaje .= "<div class='mb-2'><i class='bi bi-envelope-fill text-success'></i> La nueva contraseña fue enviada a su correo: {$correoDestino}</div>";
            $mensaje .= "<div class='alert alert-info mt-2 mb-0 py-2'><small><i class='bi bi-info-circle-fill'></i> El usuario deberá cambiarla al iniciar sesión.</small></div>";
        }
        $mensaje .= "</div>";

        // Option E: Setear flash en sesión. Usamos back() para volver a la lista.
        session()->flash('sca_modal', sca_modal_flash('success', 'Contraseña restablecida', $mensaje));

        if ($request->expectsJson()) {
            return response()->json([
                'success'  => true,
                'redirect' => route('usuarios.index'),
            ]);
        }

        return back()->with(
            'sca_modal',
            sca_modal_flash('success', 'Contraseña restablecida', $mensaje)
        );
    }
}