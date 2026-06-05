<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRepostulacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'postulante_id' => ['required', 'integer', 'exists:postulantes,id'],
            'gestion_id' => ['required', 'integer', 'exists:gestiones,id'],
            'opcion1_carrera_id' => ['required', 'integer', 'exists:carreras,id'],
            'opcion2_carrera_id' => ['required', 'integer', 'exists:carreras,id', 'different:opcion1_carrera_id'],
        ];
    }
}
