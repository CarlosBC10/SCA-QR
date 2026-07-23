<?php
// app/Http/Requests/UpdateUsuarioRequest.php
//
// Valida la edición de un usuario existente.
// El password es OPCIONAL: si viene vacío, no se cambia.
//
// CAMBIO: planta_id ahora es NULLABLE — solo aplica a guardias.
// Para empleados se ignora (la planta se deriva de sitec).

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
            'activo'        => ['required', 'boolean'],
            'password'      => ['nullable', 'string', Password::min(8)->mixedCase()->numbers()->symbols()],
            // planta_id solo aplica a guardias; se valida en el controlador
            'planta_id'     => ['nullable', 'integer', 'exists:plantas,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_rol.required'    => 'Debe seleccionar un rol.',
            'id_rol.exists'      => 'El rol seleccionado no es válido.',
            'tipo_empleado.in'   => 'El tipo de empleado no es válido.',
            'activo.required'    => 'El estado es obligatorio.',
        ];
    }
}