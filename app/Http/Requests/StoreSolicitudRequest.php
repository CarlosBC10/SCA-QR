<?php
// app/Http/Requests/StoreSolicitudRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSolicitudRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        // Leemos qué tipo seleccionó el usuario en el formulario.
        // Esto nos permite aplicar reglas DIFERENTES según el tipo.
        $tipo = $this->input('tipo');
        
        // REGLAS BASE: aplican a TODOS los tipos de solicitud.
        // Si un campo no aplica para cierto tipo, lo dejamos 'nullable'
        // y lo validamos condicionalmente más abajo.
        $rules = [
            // El tipo debe ser uno de los 4 valores del enum en la BD
            'tipo' => ['required', 'in:pase_salida,interplantas,visita,contratista'],
            
            // Planta destino: nullable por ahora (pase_salida no la usa)
            'planta_destino_id' => ['nullable', 'integer', 'exists:plantas,id'],
            
            // Fecha de inicio: no puede ser en el pasado (after_or_equal:now)
            // Esto evita que alguien cree una solicitud retroactiva
            'fecha_inicio' => ['required', 'date', 'after_or_equal:now'],
            
            // Fecha de fin: debe ser POSTERIOR al inicio (after:fecha_inicio)
            'fecha_fin' => ['required', 'date', 'after:fecha_inicio'],
            
            // Motivo: obligatorio, máximo 1000 caracteres
            'motivo' => ['required', 'string', 'max:1000'],
            
            // Campos de persona (solo para visita/contratista)
            'persona_nombre' => ['nullable', 'string', 'max:200'],
            'persona_telefono' => ['nullable', 'string', 'max:20'],
            'persona_empresa' => ['nullable', 'string', 'max:150'],
            
            // Empleados (solo para interplantas grupal)
            // 'empleados.*' valida cada elemento del array
            'empleados' => ['nullable', 'array'],
            'empleados.*' => ['integer'],
        ];

        // ===== REGLAS CONDICIONALES SEGÚN EL TIPO =====
        
        // Si es interplantas, la planta destino es OBLIGATORIA
        // (tiene que decir a qué planta va)
        if ($tipo === 'interplantas') {
            $rules['planta_destino_id'] = ['required', 'integer', 'exists:plantas,id'];
        }
        
        if (in_array($tipo, ['visita', 'contratista'])) {
            $rules['persona_nombre'] = ['required', 'string', 'max:200'];
            
            // Solo Admin y Superadmin deben elegir planta destino
            if (auth()->user()->isAdmin() || auth()->user()->isSuperadmin()) {
                $rules['planta_destino_id'] = ['required', 'integer', 'exists:plantas,id'];
            }
        }
        
        // Si es contratista, la empresa es OBLIGATORIA
        // (un visitante puede no tener empresa, un contratista sí)
        if ($tipo === 'contratista') {
            $rules['persona_empresa'] = ['required', 'string', 'max:150'];
        }

        return $rules;
    }

    // Mensajes personalizados en español para cada regla
    public function messages(): array
    {
        return [
            'tipo.required' => 'Seleccione el tipo de solicitud.',
            'planta_destino_id.required' => 'Seleccione la planta destino.',
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.after_or_equal' => 'La fecha de inicio no puede ser en el pasado.',
            'fecha_fin.after' => 'La fecha de fin debe ser posterior a la de inicio.',
            'motivo.required' => 'El motivo es obligatorio.',
            'persona_nombre.required' => 'El nombre de la persona es obligatorio.',
            'persona_empresa.required' => 'La empresa es obligatoria para contratistas.',
        ];
    }
}