<?php
// app/Http/Requests/LoginRequest.php
//
// Form Request para validar el formulario de login.
// Saca la validación del controlador para que quede limpio.
// Si las reglas fallan, Laravel redirige automáticamente al formulario
// con los errores (no llega al controlador).

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    // ¿Este usuario tiene permiso de hacer esta petición?
    // El login es público, siempre true.
    public function authorize(): bool
    {
        return true;
    }

    // Reglas de validación
    public function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    // Mensajes personalizados en español
    public function messages(): array
    {
        return [
            'username.required' => 'El usuario es obligatorio.',
            'password.required' => 'La contraseña es obligatoria.',
        ];
    }
}