<?php

namespace App\Http\Requests;

use App\Support\States\DocumentoState;
use Illuminate\Foundation\Http\FormRequest;

class ValidarDocumentosRequest extends FormRequest
{
    public function authorize(): bool
    {
        // El middleware role:admin ya protege la ruta, pero por seguridad:
        return $this->user()?->role === 'admin';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'revisiones' => ['required', 'array', 'min:1'],
            'revisiones.*.id' => ['required', 'integer', 'exists:documentos,id'],
            'revisiones.*.estado' => ['required', 'string', 'in:' . implode(',', DocumentoState::ALL)],
            'revisiones.*.observacion' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Configurar validaciones adicionales (como la regla required_if que es compleja de leer en string)
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $revisiones = $this->input('revisiones', []);
            foreach ($revisiones as $index => $rev) {
                $estado = $rev['estado'] ?? null;
                $obs = $rev['observacion'] ?? null;

                if (in_array($estado, [DocumentoState::OBSERVADO, DocumentoState::RECHAZADO]) && empty($obs)) {
                    $validator->errors()->add(
                        "revisiones.{$index}.observacion",
                        'La observación es obligatoria si el documento es rechazado u observado.'
                    );
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'revisiones.required' => 'Debe enviar los documentos a revisar.',
            'revisiones.*.id.exists' => 'Uno de los documentos no existe.',
            'revisiones.*.estado.in' => 'El estado proporcionado para un documento no es válido.',
        ];
    }
}
