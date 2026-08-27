<?php
// app/Http/Requests/StoreSolicitudRequest.php
//
// Validación del formulario de nueva solicitud.
//
// CAMBIO V5:
//   - La 'empresa' se valida a nivel de solicitud (no por cada externo).
//   - Los externos ya no llevan 'empresa' ni 'tipo'.
//   - prepareForValidation() convierte strings vacíos a null.
//
// ⚠️ CAMBIO V7 (unificación de identificación oficial):
//   - Antes: externos.*.curp + externos.*.pasaporte + externos.*.tipo_id
//   - Ahora: externos.*.tipo_identificacion + externos.*.identificacion
//   - Tipos válidos: curp, credencial_votar, cedula_profesional, pasaporte

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSolicitudRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /**
     * Convierte strings vacíos a null ANTES de validar.
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
                    // CAMBIO V7: ya no procesamos 'curp', ahora 'identificacion'
                    foreach (['identificacion', 'telefono'] as $campo) {
                        if (isset($ext[$campo]) && $ext[$campo] === '') {
                            $externos[$idx][$campo] = null;
                        }
                    }
                    // Normalizar tipo_identificacion a lowercase
                    if (isset($externos[$idx]['tipo_identificacion'])) {
                        $externos[$idx]['tipo_identificacion'] = strtolower(trim($externos[$idx]['tipo_identificacion']));
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
            'fecha_inicio'      => ['required', 'date', function ($attribute, $value, $fail) {
                $inicio = \Carbon\Carbon::parse($value);
                $ahora = now();
                if ($inicio->lt($ahora->copy()->subMinutes(5))) {
                    $fail('La fecha de inicio no puede ser en el pasado.');
                }
            }],
            'fecha_fin'         => [$tipo === 'pase_salida' ? 'nullable' : 'required', 'date', 'after:fecha_inicio'],
            'motivo'            => ['required', 'string', 'max:1000'],

            'empleados'         => ['nullable', 'array'],
            'empleados.*'       => ['integer'],
            'incluirme'         => ['nullable', 'boolean'],

            'anfitrion_empleado_id' => ['nullable', 'integer'],
            'empresa'           => ['nullable', 'string', 'max:150'],
            'externos'          => ['nullable', 'array'],
            // CAMBIO V7: unificación de identificación oficial
            'externos.*.tipo_identificacion' => ['required', 'in:curp,credencial_votar,cedula_profesional,pasaporte'],
            'externos.*.identificacion'      => ['required', 'string', 'max:50'],
            'externos.*.nombre'              => ['nullable', 'string', 'max:200'],
            'externos.*.telefono'            => ['nullable', 'string', 'max:20'],
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

            // CAMBIO V7: tipo_identificacion + identificacion obligatorios para externos
            $rules['externos.*.tipo_identificacion'] = ['required', 'in:curp,credencial_votar,cedula_profesional,pasaporte'];
            $rules['externos.*.identificacion'] = ['required', 'string', 'max:50'];

            // Validación específica por tipo (CURP: 18 caracteres)
            $rules['externos.*.identificacion'] = array_merge(
                $rules['externos.*.identificacion'],
                [
                    function ($attribute, $value, $fail) {
                        $idx = explode('.', $attribute)[1];
                        $tipoId = $this->input("externos.{$idx}.tipo_identificacion");
                        if ($tipoId === 'curp' && strlen($value) !== 18) {
                            $fail('La CURP debe tener exactamente 18 caracteres.');
                        }
                    },
                ]
            );
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
            // CAMBIO V7: mensajes unificados
            'externos.*.tipo_identificacion.required' => 'Debe seleccionar el tipo de identificación oficial.',
            'externos.*.tipo_identificacion.in'       => 'El tipo de identificación no es válido.',
            'externos.*.identificacion.required'      => 'El número de identificación es obligatorio.',
            'externos.*.identificacion.max'           => 'La identificación no puede exceder 50 caracteres.',
        ];
    }
}