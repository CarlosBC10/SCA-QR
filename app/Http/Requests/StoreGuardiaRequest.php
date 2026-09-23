<?php
// app/Http/Requests/StoreGuardiaRequest.php
//
// Validación para el alta de un usuario GUARDIA EXTERNO.
//
// CAMBIO V5:
//   - Se elimina 'numero_guardia'.
//   - El id_guardia (ej: "G-0001") es la PK de guardias y su usuario de login.
//
// M3 (flujo de contraseña temporal):
//   - Se elimina 'id_guardia' del form: el username se AUTOGENERA en el
//     servidor (UsuarioController::generarUsernameGuardia) al guardar.
//   - Se eliminan 'password' y 'password_confirmation': la contraseña se
//     genera automáticamente como TEMPORAL (prefijo 'temp:') y se envía
//     por correo al CREADOR del guardia. El guardia la cambia en su
//     primer login (middleware DebeCambiarPassword).

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGuardiaRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'nombre_completo'  => ['required', 'string', 'max:200'],
            // M2: CURP obligatoria para guardias (era opcional).
            // La unicidad aquí es la red de seguridad final: el panel de
            // búsqueda del form (usuarios.buscar.guardia) detecta duplicados
            // antes de enviar, pero esta regla es la que garantiza integridad.
            'curp'             => ['required', 'string', 'size:18', 'regex:/^[A-Z0-9]+$/i', 'unique:guardias,curp'],
            'id_planta'        => ['required', 'integer', 'exists:plantas,id_planta'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre_completo.required'  => 'El nombre del guardia es obligatorio.',
            'curp.required'             => 'La CURP es obligatoria.',
            'curp.size'                 => 'La CURP debe tener exactamente 18 caracteres.',
            'curp.regex'                => 'La CURP solo debe contener letras y números.',
            'curp.unique'               => 'Ya existe un guardia con esa CURP. Use el buscador para verificar su cuenta.',
            'id_planta.required'        => 'Debe seleccionar una planta.',
            'id_planta.exists'          => 'La planta seleccionada no es válida.',
        ];
    }
}