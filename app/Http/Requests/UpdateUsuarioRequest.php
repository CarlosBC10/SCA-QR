<?php
// app/Http/Requests/UpdateUsuarioRequest.php
//
// Valida la edición de un usuario existente.
// El password es OPCIONAL: si viene vacío, no se cambia.

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUsuarioRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'id_rol'        => ['required', 'integer', 'exists:roles_sca,id'],
            'tipo_empleado' => ['nullable', Rule::in(['operativo', 'administrativo', 'corporativo'])],
            'planta_id'     => ['required', 'integer', 'exists:plantas,id'],
            'activo'        => ['required', 'boolean'],
            'password'      => ['nullable', 'string', Password::min(8)->mixedCase()->numbers()->symbols()],
        ];
    }

    public function messages(): array
    {
        return [
            'id_rol.required'    => 'Debe seleccionar un rol.',
            'id_rol.exists'      => 'El rol seleccionado no es válido.',
            'tipo_empleado.in'   => 'El tipo de empleado no es válido.',
            'planta_id.required' => 'La planta es obligatoria.',
            'planta_id.exists'   => 'La planta seleccionada no es válida.',
            'activo.required'    => 'El estado es obligatorio.',
        ];
    }
}