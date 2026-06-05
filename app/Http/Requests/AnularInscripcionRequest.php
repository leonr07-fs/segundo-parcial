<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnularInscripcionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Debe indicar el motivo de la anulacion.',
            'motivo.min' => 'El motivo debe tener al menos 10 caracteres.',
        ];
    }
}
