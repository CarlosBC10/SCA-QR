<?php
// app/Http/Controllers/Auth/LoginController.php
//
// Controlador de autenticación.
//
// CORRECCIÓN BLOQUE 13:
//   El error "syntax error, unexpected string content" en línea 161 era por
//   comillas tipográficas (smart quotes " " ) que PHP no reconoce. Esto pasa
//   al copiar-pegar del chat. Se reescribió TODO el archivo usando comillas
//   simples y concatenación con '.' para evitar el problema.
//
// CAMBIOS V4 (ya aplicados en Bloque 9, se reiteran aquí):
//   - Auth::attempt ya no filtra por 'activo' (la columna se eliminó).
//     Se valida fecha_baja === null DESPUÉS del attempt.
//   - UsuarioSca::create sin 'activo' (la tabla usa fecha_alta DEFAULT).
//   - $usuario->update(['activo' => false]) → $usuario->darDeBaja().
//   - $usuario->guardia_id → $usuario->id_guardias.
//
// VALIDACIONES DE LOGIN (en orden):
//   1. Rate limiting: 5 intentos/minuto por IP + username
//   2. Auth::attempt: username + password (sin filtro activo)
//   3. Validar fecha_baja === null (usuario activo)
//   4. Si es guardia: valida guardia.fecha_baja === null
//   5. Si es empleado: valida sitec.empleados.estatus='Alta'
//   6. Auto-desactiva en sca si fue dado de baja en sitec o guardias

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegistroInicialRequest;
use App\Models\UsuarioSca;
use App\Models\RolSca;
use App\Models\Sitec\Empleado;
use App\Models\LogSistema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Muestra el formulario de login.
     * Si no hay usuarios en el sistema, redirige al registro inicial del Superadmin.
     */
    public function showLoginForm()
    {
        if (UsuarioSca::count() === 0) {
            return redirect()->route('register.initial');
        }
        return view('auth.login');
    }

    /**
     * Muestra el formulario de registro inicial (solo si no hay usuarios).
     */
    public function showInitialRegistration()
    {
        if (UsuarioSca::count() > 0) {
            return redirect()->route('login');
        }
        return view('auth.register_initial');
    }

    /**
     * Procesa el registro del primer Superadmin.
     * Solo se permite si la tabla usuarios_sca está vacía.
     *
     * CAMBIO V4: sin 'activo' => true (la tabla usa fecha_alta con DEFAULT).
    */
    public function initialRegistration(RegistroInicialRequest $request)
    {
        // Doble verificación: si ya hay usuarios, no permitir
        if (UsuarioSca::count() > 0) {
            return redirect()->route('login');
        }

        // Buscar el empleado en sitec
        $empleado = Empleado::find($request->id_empleado);
        if (!$empleado) {
            throw ValidationException::withMessages([
                'id_empleado' => 'El numero de empleado no existe en la base de datos.',
            ]);
        }

        // El empleado debe tener estatus='Alta' para ser Superadmin
        if (!$empleado->estaActivo()) {
            throw ValidationException::withMessages([
                'id_empleado' => 'El empleado no tiene estatus Alta en sitec.',
            ]);
        }

        // V5: Se eliminó la columna 'id_guardias' de usuarios_sca.
        // El enlace con guardias ahora es por 'username' (no requiere campo separado).
        // Para empleados internos, el username es simplemente su id_empleado.
        UsuarioSca::create([
            'username'    => (string) $request->id_empleado,
            'password'    => Hash::make($request->password),
            'id_rol'      => $rolSuperadmin->id_rol,
        ]);

        LogSistema::registrar(
            'REGISTER_INITIAL',
            'Registro inicial de Superadmin (empleado ' . $request->id_empleado . ')'
        );

        return redirect()->route('login')
            ->with('success', 'Superadmin creado. Ahora puede iniciar sesion.');
    }

    /**
     * Procesa el inicio de sesión.
     *
     * CAMBIO V4: Auth::attempt no filtra por 'activo' (columna eliminada).
     * Se valida fecha_baja === null manualmente después del attempt.
     *
     * Pasos:
     *   1. Rate limiting (5 intentos/minuto por IP + username).
     *   2. Auth::attempt (username + password).
     *   3. Validar que el usuario no tenga fecha_baja (activo).
     *   4. Si es guardia: validar que el guardia esté activo.
     *   5. Si es empleado: validar que esté activo en sitec.
     *   6. Forzar remember_me si es guardia (turnos largos).
     *   7. Registrar LOGIN en logs_sistema.
     *   8. Redirigir al dashboard según el rol.
     */
    public function login(LoginRequest $request)
    {
        $credentials = $request->only('username', 'password');
        $username = $credentials['username'];
        $password = $credentials['password'];

        // --- 1. RATE LIMITING (Anti fuerza bruta) ---
        $throttleKey = strtolower($request->ip() . '|' . $username);
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'username' => 'Demasiados intentos. Intente en ' . $seconds . ' segundos.',
            ]);
        }

        // --- 2. BUSCAR USUARIO Y VERIFICAR PREFIJO 'temp:' ---
        $usuario = UsuarioSca::where('username', $username)->first();
        $loginValido = false;

        if ($usuario) {
            // Si el password tiene el prefijo 'temp:'
            if (str_starts_with($usuario->password, 'temp:')) {
                // Extraer el hash real (quitar 'temp:')
                $hashReal = substr($usuario->password, 5);
                
                // Validar contraseña contra el hash real
                if (Hash::check($password, $hashReal)) {
                    $loginValido = true;
                    // Iniciar sesión manualmente
                    Auth::login($usuario, false);
                    
                    // FIX I: Forzar redirección inmediata al cambio de contraseña
                    // Esto evita depender del middleware global si hubo algún problema de registro
                    RateLimiter::clear($throttleKey);
                    $request->session()->regenerate();
                    return redirect()->route('password.cambiar')
                        ->with('warning', 'Debe cambiar su contraseña temporal antes de continuar.');
                }
            } else {
                // Password normal: usar Auth::attempt estándar
                if (Auth::attempt($credentials, false)) {
                    $loginValido = true;
                    $usuario = Auth::user(); // Refrescar instancia
                }
            }
        }
        // Si las credenciales son inválidas
        if (!$loginValido) {
            RateLimiter::hit($throttleKey, 60);
            throw ValidationException::withMessages([
                'username' => 'Credenciales incorrectas.',
            ]);
        }

        // --- 3. VALIDACIÓN DE USUARIO ACTIVO (fecha_baja === null) ---
        if ($usuario->fecha_baja !== null) {
            Auth::logout();
            $request->session()->invalidate();
            RateLimiter::hit($throttleKey, 60);
            throw ValidationException::withMessages([
                'username' => 'Su usuario ha sido desactivado. Contacte al administrador.',
            ]);
        }

        // Limpiar el contador de intentos fallidos y regenerar sesión
        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        // --- 4. VALIDACIÓN CRUZADA (Guardia o Empleado) ---
        if ($usuario->esGuardia()) {
            $guardia = $usuario->guardia;
            if (!$guardia || !$guardia->activo) {
                $usuario->darDeBaja();
                Auth::logout();
                $request->session()->invalidate();
                throw ValidationException::withMessages([
                    'username' => 'El guardia esta dado de baja.',
                ]);
            }
        } else {
            $empleado = Empleado::find($usuario->username);
            if (!$empleado || !$empleado->estaActivo()) {
                $usuario->darDeBaja();
                Auth::logout();
                $request->session()->invalidate();
                throw ValidationException::withMessages([
                    'username' => 'El empleado no esta activo en el sistema.',
                ]);
            }
        }

        // --- 5. REMEMBER ME SEGÚN ROL ---
        $remember = $request->boolean('remember') || $usuario->isGuardia();
        Auth::login($usuario, $remember);

        // --- 6. LOG DE AUDITORÍA ---
        LogSistema::registrar('LOGIN', 'Inicio de sesion de ' . $username);

        // --- 7. REDIRECCIÓN POR ROL ---
        // El middleware DebeCambiarPassword interceptará si tiene prefijo 'temp:'
        return $this->redirectByRole($usuario);
    }

    /**
     * Cierra la sesión del usuario.
     */
    public function logout(Request $request)
    {
        $username = $request->user() ? $request->user()->username : 'desconocido';
        LogSistema::registrar('LOGOUT', 'Cierre de sesion de ' . $username);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Redirige al dashboard correspondiente según el rol del usuario (SRS UI-03).
     */
    protected function redirectByRole(UsuarioSca $usuario)
    {
        $route = match ($usuario->rol->nombre) {
            'Superadmin' => 'superadmin.dashboard',
            'Admin'      => 'admin.dashboard',
            'Aprobador'  => 'aprobador.dashboard',
            'Empleado'   => 'empleado.dashboard',
            'Guardia'    => 'guardia.dashboard',
            default      => null,
        };

        return $route ? redirect()->route($route) : redirect('/');
    }

    /**
     * Muestra el formulario de "Olvidé mi contraseña".
     */
    public function showResetForm()
    {
        return view('auth.password_reset');
    }

    /**
     * Procesa el envío del correo de restablecimiento.
     */
    public function sendResetEmail(Request $request)
    {
        $request->validate([
            'username' => ['required', 'string'],
        ]);

        $usuario = UsuarioSca::where('username', $request->username)->first();

        // FIX I: Mensaje específico para guardias (no tienen correo institucional)
        if (!$usuario) {
            return back()->withErrors(['username' => 'No se encontró un usuario con ese nombre de usuario.'])->withInput();
        }
        
        if ($usuario->esGuardia()) {
            return back()->withErrors(['username' => 'Los guardias deben solicitar el reseteo de contraseña directamente al administrador.'])->withInput();
        }

        if (!$usuario->email_emp) {
            return back()->withErrors(['username' => 'Su usuario no tiene correo institucional registrado. Contacte al administrador.'])->withInput();
        }

        // Generar nueva contraseña temporal
        $passwordTemporal = $this->generarPasswordTemporalAleatoria();
        $usuario->password = 'temp:' . Hash::make($passwordTemporal);
        $usuario->save();

        // Enviar correo
        try {
            \Mail::to($usuario->email_emp)->send(new \App\Mail\PasswordTemporalMail(
                $usuario->nombre_completo,
                $usuario->username,
                $passwordTemporal,
                route('login')
            ));
        } catch (\Exception $e) {
            LogSistema::registrar('ERROR_MAIL', "No se pudo enviar correo de reseteo a {$usuario->email_emp}: " . $e->getMessage());
            return back()->withErrors(['username' => 'No se pudo enviar el correo. Intente más tarde o contacte al administrador.'])->withInput();
        }

        LogSistema::registrar('RESETEO_PASSWORD', "El usuario {$usuario->username} solicitó reseteo de contraseña.");

        return redirect()->route('login')->with('success', 'Se envió una contraseña temporal a su correo institucional.');
    }

    /**
     * Genera una contraseña temporal aleatoria.
     */
    private function generarPasswordTemporalAleatoria(int $longitud = 12): string
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
}