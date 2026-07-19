<?php
// app/Http/Controllers/Auth/LoginController.php
//
// Controlador de autenticación.
// Maneja:
//   - showLoginForm: mostrar el formulario de login
//   - showInitialRegistration: mostrar el registro inicial (solo si no hay usuarios)
//   - initialRegistration: procesar el registro del primer Superadmin
//   - login: procesar el inicio de sesión
//   - logout: cerrar sesión
//
// VALIDACIONES DE LOGIN (en orden):
//   1. Rate limiting: 5 intentos/minuto por IP + username (anti fuerza bruta)
//   2. Auth::attempt: username + password + usuarios_sca.activo=true
//   3. Si es guardia: valida guardias.activo=true
//   4. Si es empleado: valida sitec.empleados.estatus='Alta'
//   5. Auto-desactiva en sca si fue dado de baja en sitec o guardias

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
                'id_empleado' => 'El número de empleado no existe en la base de datos.',
            ]);
        }

        // El empleado debe tener estatus='Alta' para ser Superadmin
        if (!$empleado->estaActivo()) {
            throw ValidationException::withMessages([
                'id_empleado' => 'El empleado no tiene estatus "Alta" en sitec.',
            ]);
        }

        $rolSuperadmin = RolSca::where('nombre', 'Superadmin')->firstOrFail();

        // Crear el usuario Superadmin
        UsuarioSca::create([
            'username'   => (string) $request->id_empleado,
            'password'   => Hash::make($request->password),
            'id_rol'     => $rolSuperadmin->id,
            'guardia_id' => null,
            'activo'     => true,
        ]);

        LogSistema::registrar(
            'REGISTER_INITIAL',
            "Registro inicial de Superadmin (empleado {$request->id_empleado})"
        );

        return redirect()->route('login')
            ->with('success', 'Superadmin creado. Ahora puede iniciar sesión.');
    }

    /**
     * Procesa el inicio de sesión.
     *
     * Validaciones en orden:
     * 1. Rate limiting (5 intentos/minuto por IP + username)
     * 2. Auth::attempt (username + password + usuarios_sca.activo=true)
     * 3. Si es guardia: valida guardias.activo=true
     * 4. Si es empleado: valida sitec.empleados.estatus='Alta'
     * 5. Auto-desactiva en sca si fue dado de baja
     * 6. Registra LOGIN en logs_sistema
     */
    public function login(LoginRequest $request)
    {
        $credentials = $request->only('username', 'password');
        $credentials['activo'] = true;

        // --- RATE LIMITING (Anti fuerza bruta) ---
        $throttleKey = strtolower($request->ip() . '|' . $request->username);
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'username' => "Demasiados intentos. Intente en {$seconds} segundos.",
            ]);
        }

        // --- INTENTO DE AUTENTICACIÓN ---
        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);
            throw ValidationException::withMessages([
                'username' => 'Credenciales incorrectas o usuario inactivo.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        $usuario = Auth::user();

        // --- VALIDACIÓN CRUZADA (Guardia o Empleado) ---
        if ($usuario->guardia_id !== null) {
            $guardia = $usuario->guardia;
            if (!$guardia || !$guardia->activo) {
                $usuario->update(['activo' => false]);
                Auth::logout();
                $request->session()->invalidate();
                throw ValidationException::withMessages(['username' => 'El guardia está dado de baja.']);
            }
        } else {
            $empleado = Empleado::find($usuario->username);
            if (!$empleado || !$empleado->estaActivo()) {
                $usuario->update(['activo' => false]);
                Auth::logout();
                $request->session()->invalidate();
                throw ValidationException::withMessages(['username' => 'El empleado no está activo en el sistema.']);
            }
        }

        LogSistema::registrar('LOGIN', "Inicio de sesión de {$request->username}");

        return $this->redirectByRole($usuario);
    }

    /**
     * Cierra la sesión del usuario.
     */
    public function logout(Request $request)
    {
        LogSistema::registrar('LOGOUT', "Cierre de sesión de {$request->username}");

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
}