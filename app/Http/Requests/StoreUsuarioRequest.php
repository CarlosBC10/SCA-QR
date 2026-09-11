<?php
// app/Http/Requests/StoreUsuarioRequest.php
//
// Valida el alta de un usuario EMPLEADO INTERNO (login web).
//
// CAMBIOS V4:
//   - exists:roles_sca,id → exists:roles_sca,id_rol (PK renombrada).
//   - planta_id eliminado (se deriva de sitec, ya estaba así).

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUsuarioRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'id_empleado'   => ['required', 'integer', 'min:1'],
            'email_emp'     => ['required', 'email', 'max:100'], // FIX I-10: Correo institucional obligatorio
            'password'      => ['nullable', 'string', Password::min(8)->mixedCase()->numbers()->symbols()],
            'id_rol'        => ['required', 'integer', 'exists:roles_sca,id_rol'],
            'tipo_empleado' => ['required', Rule::in(['operativo', 'administrativo', 'corporativo'])],
        ];
    }

    public function messages(): array
    {
        return [
            'id_empleado.required'   => 'El número de empleado es obligatorio.',
            'id_empleado.integer'    => 'El número de empleado debe ser numérico.',
            'password.required'      => 'La contraseña es obligatoria.',
            'id_rol.required'        => 'Debe seleccionar un rol.',
            'id_rol.exists'          => 'El rol seleccionado no es válido.',
            'tipo_empleado.required' => 'Debe seleccionar un tipo de empleado.',
            'tipo_empleado.in'       => 'El tipo de empleado no es válido.',
        ];
    }
}