<?php
// app/Http/Requests/StoreSolicitudRequest.php
//
// Validación del formulario de nueva solicitud.
//
// CAMBIO V5:
//   - La 'empresa' se valida a nivel de solicitud (no por cada externo).
//   - Los externos ya no llevan 'empresa' ni 'tipo'.
//   - prepareForValidation() convierte strings vacíos a null para evitar errores de validación.

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSolicitudRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /**
     * Convierte strings vacíos a null ANTES de validar.
     * Los formularios HTML envían "" para los campos que el usuario no llenó.
     * Si no los convertimos a null, las reglas 'integer' o 'size:18' fallarían.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('anfitrion_empleado_id') && $this->anfitrion_empleado_id === '') {
            $this->merge(['anfitrion_empleado_id' => null]);
        }

        if ($this->has('externos')) {
            $externos = $this->input('externos');
            if (is_array($externos)) {
                foreach ($externos as $idx => $ext) {
                    foreach (['curp', 'telefono'] as $campo) {
                        if (isset($ext[$campo]) && $ext[$campo] === '') {
                            $externos[$idx][$campo] = null;
                        }
                    }
                }
                $this->merge(['externos' => $externos]);
            }
        }

        if ($this->has('empresa') && $this->empresa === '') {
            $this->merge(['empresa' => null]);
        }
    }

    public function rules(): array
    {
        $tipo = $this->input('tipo');

        $rules = [
            'tipo'              => ['required', 'in:pase_salida,interplantas,visita,contratista'],
            // FIX A-1: Validación custom con tolerancia de 5 minutos
            'fecha_inicio'      => ['required', 'date', function ($attribute, $value, $fail) {
                $inicio = \Carbon\Carbon::parse($value);
                $ahora = now();
                if ($inicio->lt($ahora->copy()->subMinutes(5))) {
                    $fail('La fecha de inicio no puede ser en el pasado.');
                }
            }],
            // FIX F-2: fecha_fin es obligatorio EXCEPTO para pase_salida
            // (en pase_salida se calcula automáticamente en el controlador)
            'fecha_fin'         => [$tipo === 'pase_salida' ? 'nullable' : 'required', 'date', 'after:fecha_inicio'],
            'motivo'            => ['required', 'string', 'max:1000'],

            'empleados'         => ['nullable', 'array'],
            'empleados.*'       => ['integer'],
            'incluirme'         => ['nullable', 'boolean'],

            'anfitrion_empleado_id' => ['nullable', 'integer'],
            'empresa'           => ['nullable', 'string', 'max:150'],
            'externos'          => ['nullable', 'array'],
            'externos.*.curp'   => ['nullable', 'string', 'size:18'],
            'externos.*.nombre' => ['nullable', 'string', 'max:200'],
            'externos.*.telefono' => ['nullable', 'string', 'max:20'],
        ];

        if ($tipo === 'interplantas') {
            $rules['planta_destino_id'] = ['required', 'integer', 'exists:plantas,id_planta'];
        }

        if (in_array($tipo, ['pase_salida', 'interplantas'])) {
            if (auth()->user()->isAdmin() || auth()->user()->isSuperadmin()) {
                $rules['planta_origen_id'] = ['required', 'integer', 'exists:plantas,id_planta'];
            }
        }

        if (in_array($tipo, ['visita', 'contratista'])) {
            if (auth()->user()->isAdmin() || auth()->user()->isSuperadmin()) {
                $rules['planta_destino_id'] = ['required', 'integer', 'exists:plantas,id_planta'];
            }
            $rules['externos'] = ['required', 'array', 'min:1'];
            $rules['externos.*.nombre'] = ['required', 'string', 'max:200'];
            
            // FIX G: La CURP o el Pasaporte son obligatorios (al menos uno)
            $rules['externos.*.curp'] = ['nullable', 'string', 'size:18', 'regex:/^[A-Z]{4}\d{6}[HM][A-Z]{5}[A-Z0-9]\d$/', 'required_without:externos.*.pasaporte'];
            $rules['externos.*.pasaporte'] = ['nullable', 'string', 'min:5', 'max:50', 'required_without:externos.*.curp'];
            $rules['externos.*.tipo_id'] = ['required', 'in:curp,pasaporte'];
        }

        if ($tipo === 'contratista') {
            $rules['empresa'] = ['required', 'string', 'max:150'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'tipo.required'              => 'Seleccione el tipo de solicitud.',
            'planta_destino_id.required' => 'Seleccione la planta destino.',
            'fecha_inicio.required'      => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.after_or_equal'=> 'La fecha de inicio no puede ser en el pasado.',
            'fecha_fin.after'            => 'La fecha de fin debe ser posterior a la de inicio.',
            'motivo.required'            => 'El motivo es obligatorio.',
            'externos.required'          => 'Debe agregar al menos una persona.',
            'externos.min'               => 'Debe agregar al menos una persona.',
            'externos.*.nombre.required' => 'El nombre de cada persona es obligatorio.',
            'empresa.required'           => 'La empresa es obligatoria para contratistas.',
            'externos.*.curp.regex'      => 'El formato de la CURP no es válido.',
            'externos.*.tipo_id.required'=> 'Debe seleccionar un tipo de identificación (CURP o Pasaporte).',
            'externos.*.curp.required_without' => 'Debe ingresar la CURP o el Pasaporte.',
            'externos.*.pasaporte.required_without' => 'Debe ingresar el Pasaporte o la CURP.',
        ];
    }
}