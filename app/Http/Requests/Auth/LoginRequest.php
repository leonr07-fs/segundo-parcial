<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * CU01: Autenticación de Usuario
 * Request que valida las credenciales de ingreso (numero_registro).
 */
class LoginRequest extends FormRequest
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
            'numero_registro' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'numero_registro.required' => 'El número de registro es obligatorio.',
            'password.required' => 'La contrasena es obligatoria.',
        ];
    }
}
