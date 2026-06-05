<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePostulanteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // CU05: Sólo campos permitidos a ser actualizados
        return [
            'nombres' => ['sometimes', 'required', 'string', 'max:100'],
            'apellido_paterno' => ['nullable', 'string', 'max:100'],
            'apellido_materno' => ['nullable', 'string', 'max:100'],
            'fecha_nacimiento' => ['sometimes', 'required', 'date'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'colegio_procedencia' => ['nullable', 'string', 'max:150'],
            'ciudad' => ['nullable', 'string', 'max:100'],
            'direccion' => ['nullable', 'string', 'max:255'],
        ];
    }
}
