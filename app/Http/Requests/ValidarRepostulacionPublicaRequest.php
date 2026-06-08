<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidarRepostulacionPublicaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ci' => ['required', 'string', 'max:20'],
            'correo' => ['required', 'email', 'max:255'],
        ];
    }
}
