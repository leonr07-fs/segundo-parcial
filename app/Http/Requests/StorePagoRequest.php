<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'monto' => ['required', 'numeric', 'min:0'],
            'metodo' => ['required', 'string', 'max:40'],
            'referencia' => ['required', 'string', 'max:100', 'unique:pagos,referencia'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'monto.required' => 'El monto del pago es obligatorio.',
            'referencia.required' => 'La referencia de la transacción es obligatoria.',
            'referencia.unique' => 'Esta referencia ya ha sido registrada en otro pago.',
        ];
    }
}
