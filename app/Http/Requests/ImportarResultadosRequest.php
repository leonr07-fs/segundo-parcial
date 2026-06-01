<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportarResultadosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'archivo' => [
                'required',
                'file',
                'mimes:csv,txt', // txt también aceptado a veces para CSV
                'max:5120', // 5MB max
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'archivo.required' => 'Debe seleccionar un archivo CSV.',
            'archivo.mimes' => 'El archivo debe ser un CSV válido.',
            'archivo.max' => 'El archivo no debe pesar más de 5MB.',
        ];
    }
}
