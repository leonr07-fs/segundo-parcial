<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAulaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:30', 'unique:aulas,codigo'],
            'nombre' => ['nullable', 'string', 'max:100'],
            'capacidad' => ['nullable', 'integer', 'min:1'],
            'ubicacion' => ['nullable', 'string', 'max:150'],
            'activa' => ['boolean'],
        ];
    }
}
