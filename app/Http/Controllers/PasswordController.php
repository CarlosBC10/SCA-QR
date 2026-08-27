<?php
// app/Http/Controllers/PasswordController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PasswordController extends Controller
{
    public function edit()
    {
        $user = auth()->user();
        if (!str_starts_with($user->password, 'temp:')) {
            return redirect()->route('home');
        }
        return view('auth.cambiar_password');
    }

    public function update(Request $request)
    {
        $request->validate([
            'password_actual'   => ['required', 'string'],
            'password_nuevo'    => ['required', 'string', 'min:8', 'confirmed',
                function ($attribute, $value, $fail) {
                    if (!preg_match('/[A-Z]/', $value)) $fail('La contraseña debe contener al menos una mayúscula.');
                    if (!preg_match('/[a-z]/', $value)) $fail('La contraseña debe contener al menos una minúscula.');
                    if (!preg_match('/[0-9]/', $value)) $fail('La contraseña debe contener al menos un número.');
                },
            ],
        ], [
            'password_nuevo.min' => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'password_nuevo.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        $user = $request->user();

        // Validar contraseña actual (quitando el prefijo 'temp:')
        $hashReal = substr($user->password, 5);
        if (!Hash::check($request->password_actual, $hashReal)) {
            // M2 [ScaModal] — Si es AJAX, devolver JSON con error de campo
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'errors'  => ['password_actual' => ['La contraseña actual es incorrecta.']],
                ], 422);
            }
            return back()->withErrors(['password_actual' => 'La contraseña actual es incorrecta.'])->withInput();
        }

        if (Hash::check($request->password_nuevo, $hashReal)) {
            // M2 [ScaModal] — Si es AJAX, devolver JSON con error de campo
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'errors'  => ['password_nuevo' => ['La nueva contraseña no puede ser igual a la actual.']],
                ], 422);
            }
            return back()->withErrors(['password_nuevo' => 'La nueva contraseña no puede ser igual a la actual.'])->withInput();
        }

        // Guardar nueva contraseña SIN el prefijo 'temp:'
        $user->password = Hash::make($request->password_nuevo);
        $user->save();

        \App\Models\LogSistema::registrar('CAMBIO_PASSWORD', "Usuario {$user->username} cambió su contraseña temporal");

        // M2 [ScaModal] — Option E: Setear flash en la sesión ANTES de devolver JSON.
        // Así, cuando el JS haga window.location.href, la página destino (dashboard)
        // recibe el flash y muestra el success modal automáticamente.
        // El loading actual se queda visible durante la navegación (feedback continuo).
        session()->flash('sca_modal', sca_modal_flash(
            'success',
            'Contraseña cambiada',
            'Tu contraseña se actualizó correctamente. Bienvenido(a).'
        ));

        if ($request->expectsJson()) {
            return response()->json([
                'success'  => true,
                'redirect' => route('home'),
            ]);
        }

        // Fallback: redirect con flash (para navegadores sin JS)
        return redirect()->route('home')->with(
            'sca_modal',
            sca_modal_flash('success', 'Contraseña cambiada', 'Tu contraseña se actualizó correctamente. Bienvenido(a).')
        );
    }
}