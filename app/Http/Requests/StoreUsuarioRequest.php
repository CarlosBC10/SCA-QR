<?php
// app/Http/Requests/StoreUsuarioRequest.php
//
// Valida el alta de un usuario EMPLEADO INTERNO (login web).
//
// CAMBIO: planta_id ya NO se valida ni se asigna. La planta del
// empleado se deriva de sitec (id_clasificacion → clasificacion_planta).
// La regla RN-01 (corporativo accede a cualquier planta) se implementa
// con tipo_empleado='corporativo' en la lógica del escáner (M3).

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
            // ELIMINADO: 'planta_id' — se deriva de sitec
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