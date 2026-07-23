<?php
// app/Http/Requests/UpdateEmpleadoExtRequest.php
//
// Valida la edición individual de empleados_ext_sca.
// Se usa para:
//   - Cambiar el tipo_empleado de un caso especial (override)
//   - Resetear el contador de búsquedas manuales (si se le repuso credencial)
//   - Desbloquear manualmente al empleado
//
// CAMBIO: planta_id ya NO se valida ni se guarda. Se deriva de sitec.

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
            // ELIMINADO: 'planta_id' — se deriva de sitec
            'resetear_manual' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_empleado.required' => 'El tipo de empleado es obligatorio.',
            'tipo_empleado.in'       => 'El tipo de empleado no es válido.',
        ];
    }
}