<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidarRepostulacionDocenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ci' => ['required', 'string', 'max:30'],
            'correo' => ['required', 'email', 'max:255'],
        ];
    }
}
