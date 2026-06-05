<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSolicitudDocenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ci' => ['required', 'string', 'max:30'],
            'nombres' => ['required', 'string', 'max:120'],
            'apellidos' => ['nullable', 'string', 'max:120'],
            'correo' => ['required', 'email', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'materia_id' => ['required', 'integer', 'exists:materias,id'],
            'profesion' => ['required', 'string', 'max:150'],
            'documentos.ci' => ['required', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:4096'],
            'documentos.titulo_profesional' => ['required', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:4096'],
            'documentos.diplomado' => ['required', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:4096'],
            'documentos.maestria' => ['required', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:4096'],
            'documentos.cv' => ['required', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'materia_id.required' => 'Debe seleccionar la materia a la que postula.',
            'profesion.required' => 'Debe registrar su profesion.',
            'documentos.ci.required' => 'Debe adjuntar su carnet de identidad.',
            'documentos.titulo_profesional.required' => 'Debe adjuntar su titulo profesional.',
            'documentos.diplomado.required' => 'Debe adjuntar su diplomado.',
            'documentos.maestria.required' => 'Debe adjuntar su maestria.',
            'documentos.cv.required' => 'Debe adjuntar su CV.',
        ];
    }
}
