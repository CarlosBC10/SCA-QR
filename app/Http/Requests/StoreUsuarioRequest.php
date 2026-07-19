<?php
// app/Http/Requests/StoreUsuarioRequest.php
//
// Valida el alta de un usuario EMPLEADO INTERNO (login web).
//
// IMPORTANTE: planta_id es OBLIGATORIA para todos los tipos de empleado.
// La regla RN-01 (corporativo accede a cualquier planta) NO se implementa
// con planta_id=null, sino con tipo_empleado='corporativo' en la lógica
// del escáner QR (M3). Esto evita la ambigüedad de null y el riesgo de
// que un empleado sin planta asignada quede con acceso total por error.

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
            'password'      => ['required', 'string', Password::min(8)->mixedCase()->numbers()->symbols()],
            'id_rol'        => ['required', 'integer', 'exists:roles_sca,id'],
            'tipo_empleado' => ['required', Rule::in(['operativo', 'administrativo', 'corporativo'])],
            'planta_id'     => ['required', 'integer', 'exists:plantas,id'],
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
            'planta_id.required'     => 'La planta es obligatoria para todos los empleados.',
            'planta_id.exists'       => 'La planta seleccionada no es válida.',
        ];
    }
}