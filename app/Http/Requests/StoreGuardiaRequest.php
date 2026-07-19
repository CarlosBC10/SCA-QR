<?php
// app/Http/Requests/StoreGuardiaRequest.php
//
// Valida el alta de un usuario GUARDIA EXTERNO.
// Los guardias NO están en sitec.empleados; tienen su propia tabla (RN-09).
// El username se arma como 'G-' + numero_guardia (5 dígitos).

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreGuardiaRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'nombre_completo' => ['required', 'string', 'max:200'],
            'numero_guardia'  => ['required', 'string', 'size:5', 'regex:/^[0-9]+$/'],
            'planta_id'       => ['required', 'integer', 'exists:plantas,id'],
            'password'        => ['required', 'string', Password::min(8)->mixedCase()->numbers()->symbols()],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre_completo.required' => 'El nombre del guardia es obligatorio.',
            'numero_guardia.required'  => 'El número de guardia es obligatorio.',
            'numero_guardia.size'      => 'El número de guardia debe tener exactamente 5 dígitos.',
            'numero_guardia.regex'     => 'El número de guardia solo debe contener números.',
            'planta_id.required'       => 'Debe seleccionar una planta.',
            'planta_id.exists'         => 'La planta seleccionada no es válida.',
            'password.required'        => 'La contraseña es obligatoria.',
        ];
    }
}