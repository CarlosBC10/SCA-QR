<?php
// app/Http/Requests/StoreGuardiaRequest.php
//
// Validación para el alta de un usuario GUARDIA EXTERNO.
//
// CAMBIO V5:
//   - Se elimina 'numero_guardia'.
//   - Se agrega 'id_guardia' como campo principal (ej: "G-0001").
//   - Este ID será su usuario para iniciar sesión.

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreGuardiaRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // Validamos que el ID tenga el formato correcto y sea único en la tabla guardias
            'id_guardia'       => ['required', 'string', 'max:30', 'unique:guardias,id_guardia', 'regex:/^G-\d{4,5}$/'],
            'nombre_completo'  => ['required', 'string', 'max:200'],
            // M2: CURP obligatoria para guardias (era opcional).
            'curp'             => ['required', 'string', 'size:18', 'regex:/^[A-Z0-9]+$/i', 'unique:guardias,curp'],
            'id_planta'        => ['required', 'integer', 'exists:plantas,id_planta'],
            // M2 [ScaModal] — Los guardias NO reciben correo. El admin define la contraseña.
            'password'         => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ];
    }

    public function messages(): array
    {
        return [
            'id_guardia.required'       => 'El usuario del guardia es obligatorio.',
            'id_guardia.regex'          => 'El formato debe ser G- seguido de 4 o 5 dígitos (ej: G-0001).',
            'id_guardia.unique'         => 'Ya existe un guardia con ese usuario.',
            'nombre_completo.required'  => 'El nombre del guardia es obligatorio.',
            'curp.required'             => 'La CURP es obligatoria.',
            'curp.size'                 => 'La CURP debe tener exactamente 18 caracteres.',
            'curp.regex'                => 'La CURP solo debe contener letras y números.',
            'curp.unique'               => 'Ya existe un guardia con esa CURP.',
            'id_planta.required'        => 'Debe seleccionar una planta.',
            'id_planta.exists'          => 'La planta seleccionada no es válida.',
            'password.required'         => 'La contraseña es obligatoria.',
            'password.confirmed'        => 'Las contraseñas no coinciden.',
        ];
    }
}