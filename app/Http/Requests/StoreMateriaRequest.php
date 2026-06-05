<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMateriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Asumimos middleware de roles en la ruta
    }

    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:30', 'unique:materias,codigo'],
            'nombre' => ['required', 'string', 'max:120'],
            'activa' => ['boolean'],
        ];
    }
}
