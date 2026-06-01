<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePostulacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /* Gestión */
            'gestion_id' => ['required', 'integer', 'exists:gestiones,id'],

            /* Datos personales */
            'ci' => ['required', 'string', 'max:30'],
            'complemento' => ['nullable', 'string', 'max:10'],
            'nombres' => ['required', 'string', 'max:120'],
            'apellido_paterno' => ['required', 'string', 'max:80'],
            'apellido_materno' => ['nullable', 'string', 'max:80'],
            'fecha_nacimiento' => ['required', 'date', 'before:today'],
            'genero' => ['required', 'string', 'in:masculino,femenino,otro'],
            'correo' => ['required', 'email', 'max:150'],
            'telefono' => ['required', 'string', 'max:30'],
            'direccion' => ['nullable', 'string', 'max:255'],

            /* Datos académicos */
            'colegio_procedencia' => ['required', 'string', 'max:150'],
            'ciudad' => ['required', 'string', 'max:100'],

            /* Opciones de carrera */
            'carrera_primera_opcion_id' => ['required', 'integer', 'exists:carreras,id'],
            'carrera_segunda_opcion_id' => [
                'required',
                'integer',
                'exists:carreras,id',
                'different:carrera_primera_opcion_id',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            /* Gestión */
            'gestion_id.required' => 'Debe seleccionar una gestión académica.',
            'gestion_id.exists' => 'La gestión seleccionada no existe.',

            /* Datos personales */
            'ci.required' => 'El carnet de identidad es obligatorio.',
            'ci.max' => 'El carnet de identidad no puede exceder 30 caracteres.',
            'nombres.required' => 'Los nombres son obligatorios.',
            'nombres.max' => 'Los nombres no pueden exceder 120 caracteres.',
            'apellido_paterno.required' => 'El apellido paterno es obligatorio.',
            'apellido_paterno.max' => 'El apellido paterno no puede exceder 80 caracteres.',
            'apellido_materno.max' => 'El apellido materno no puede exceder 80 caracteres.',
            'fecha_nacimiento.required' => 'La fecha de nacimiento es obligatoria.',
            'fecha_nacimiento.date' => 'La fecha de nacimiento no tiene un formato válido.',
            'fecha_nacimiento.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
            'genero.required' => 'El género es obligatorio.',
            'genero.in' => 'El género debe ser masculino, femenino u otro.',
            'correo.required' => 'El correo electrónico es obligatorio.',
            'correo.email' => 'El correo electrónico no tiene un formato válido.',
            'correo.max' => 'El correo electrónico no puede exceder 150 caracteres.',
            'telefono.required' => 'El teléfono es obligatorio.',
            'telefono.max' => 'El teléfono no puede exceder 30 caracteres.',
            'direccion.max' => 'La dirección no puede exceder 255 caracteres.',

            /* Datos académicos */
            'colegio_procedencia.required' => 'El colegio de procedencia es obligatorio.',
            'colegio_procedencia.max' => 'El colegio de procedencia no puede exceder 150 caracteres.',
            'ciudad.required' => 'La ciudad es obligatoria.',
            'ciudad.max' => 'La ciudad no puede exceder 100 caracteres.',

            /* Opciones de carrera */
            'carrera_primera_opcion_id.required' => 'Debe seleccionar la primera opción de carrera.',
            'carrera_primera_opcion_id.exists' => 'La primera opción de carrera no existe.',
            'carrera_segunda_opcion_id.required' => 'Debe seleccionar la segunda opción de carrera.',
            'carrera_segunda_opcion_id.exists' => 'La segunda opción de carrera no existe.',
            'carrera_segunda_opcion_id.different' => 'La segunda opción de carrera debe ser diferente a la primera.',
        ];
    }
}
