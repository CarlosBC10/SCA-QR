<?php
// app/Http/Requests/UpdateEmpleadoExtRequest.php
//
// Valida la edición individual de empleados_ext_sca.
// Se usa para:
//   - Corregir la planta mal asignada (por auto-habilitación errónea)
//   - Cambiar el tipo_empleado de un caso especial (override)
//   - Resetear el contador de búsquedas manuales (si se le repuso credencial)
//   - Desbloquear manualmente al empleado

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmpleadoExtRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'tipo_empleado'   => ['required', Rule::in(['operativo', 'administrativo', 'corporativo'])],
            'planta_id'       => ['required', 'integer', 'exists:plantas,id'],
            // resetear_manual: si viene '1', se reinicia el contador a 4 y se desbloquea
            'resetear_manual' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_empleado.required' => 'El tipo de empleado es obligatorio.',
            'tipo_empleado.in'       => 'El tipo de empleado no es válido.',
            'planta_id.required'     => 'La planta es obligatoria.',
            'planta_id.exists'       => 'La planta seleccionada no es válida.',
        ];
    }
}