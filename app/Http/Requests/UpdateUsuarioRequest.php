<?php
// app/Http/Requests/UpdateUsuarioRequest.php
//
// Valida la edición de un usuario existente.
// El password es OPCIONAL: si viene vacío, no se cambia.
//
// CAMBIOS V4:
//   - exists:roles_sca,id → exists:roles_sca,id_rol.
//   - exists:plantas,id → exists:plantas,id_planta.
//   - 'activo' sigue siendo boolean (lo maneja el controlador via fecha_baja).

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
            'id_rol'        => ['required', 'integer', 'exists:roles_sca,id_rol'],
            'tipo_empleado' => ['nullable', Rule::in(['operativo', 'administrativo', 'corporativo'])],
            'activo'        => ['required', 'boolean'],
            'password'      => ['nullable', 'string', Password::min(8)->mixedCase()->numbers()->symbols()],
            // id_planta solo aplica a guardias; se valida en el controlador
            'planta_id'     => ['nullable', 'integer', 'exists:plantas,id_planta'],

            // FIX I-10 (edición): permitir agregar/corregir el correo institucional.
            // Nullable porque los guardias no manejan correo propio (sus credenciales
            // se envían al correo del operador que los crea).
            'email_emp'     => ['nullable', 'email', 'max:100'],
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