<?php
// app/Http/Requests/StorePuestoTipoRequest.php
//
// Valida la clasificación de un puesto (operativo/administrativo/corporativo).
// Al clasificar, se actualizan todos los empleados con ese puesto.

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePuestoTipoRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'id_puesto'     => ['required', 'integer', 'min:1'],
            'tipo_empleado' => ['required', Rule::in(['operativo', 'administrativo', 'corporativo'])],
        ];
    }

    public function messages(): array
    {
        return [
            'id_puesto.required'     => 'El puesto es obligatorio.',
            'tipo_empleado.required' => 'Debe seleccionar un tipo.',
            'tipo_empleado.in'       => 'El tipo seleccionado no es válido.',
        ];
    }
}