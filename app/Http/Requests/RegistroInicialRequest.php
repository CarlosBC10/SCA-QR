<?php
// app/Http/Requests/RegistroInicialRequest.php
//
// Form Request para validar el registro inicial del Superadmin.
// Exige contraseña fuerte: mínimo 8, mayúsculas, minúsculas, números, símbolos.

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegistroInicialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // id_empleado: numérico entero positivo
            'id_empleado' => ['required', 'integer', 'min:1'],

            // password: mínimo 8, con mayúsculas, minúsculas, números y símbolos
            'password' => ['required', 'string', Password::min(8)
                ->mixedCase()    // al menos una mayúscula y una minúscula
                ->numbers()      // al menos un número
                ->symbols()],    // al menos un símbolo
        ];
    }

    public function messages(): array
    {
        return [
            'id_empleado.required' => 'El número de empleado es obligatorio.',
            'id_empleado.integer'  => 'El número de empleado debe ser numérico.',
            'password.required'    => 'La contraseña es obligatoria.',
        ];
    }
}