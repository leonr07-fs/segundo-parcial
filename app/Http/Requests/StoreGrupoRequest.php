<?php

namespace App\Http\Requests;

use App\Models\GestionAcademica\Grupo;
use Illuminate\Foundation\Http\FormRequest;

class StoreGrupoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gestion_id' => ['required', 'integer', 'exists:gestiones,id'],
            'codigo' => ['required', 'string', 'max:30'],
            'nombre' => ['nullable', 'string', 'max:100'],
            'cupo_maximo' => ['integer', 'min:1', 'max:' . Grupo::CUPO_MAXIMO],
            'aula_id' => ['nullable', 'integer', 'exists:aulas,id'],
            'estado' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function messages(): array
    {
        return [
            'cupo_maximo.max' => 'Cada grupo puede tener como maximo ' . Grupo::CUPO_MAXIMO . ' estudiantes.',
        ];
    }
}
