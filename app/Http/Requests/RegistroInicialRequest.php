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

            // FIX I-10: correo institucional obligatorio (mismo criterio que StoreUsuarioRequest).
            // Sin correo, el Superadmin no podría resetear su contraseña ni crear guardias
            // (el password temporal del guardia se envía al correo del operador).
            'email_emp' => ['required', 'email', 'max:100'],

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
            'email_emp.required'   => 'El correo institucional es obligatorio.',
            'email_emp.email'      => 'El correo institucional no es válido.',
            'password.required'    => 'La contraseña es obligatoria.',
            'email_emp.required'   => 'El correo institucional es obligatorio.',
            'email_emp.email'      => 'El correo no tiene un formato válido (ej. nombre@empresa.com).',
        ];
    }
}