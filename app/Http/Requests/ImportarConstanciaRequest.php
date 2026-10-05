<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class ImportarConstanciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cliente_id' => ['required', 'integer', Cliente::reglaPerteneceACartera()],
            'periodo' => ['required', 'string', 'regex:/^\d{2}-\d{4}$/'],
            'formulario' => ['required', 'in:621,601'],
            'archivos' => ['required', 'array', 'min:1', 'max:3'],
            'archivos.*' => [
                'required',
                'file',
                'max:8192',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        $fail('Suba la constancia en PDF.');

                        return;
                    }

                    $extension = strtolower($value->getClientOriginalExtension());
                    if (! in_array($extension, ['pdf', 'txt', 'csv'], true)) {
                        $fail('Suba la constancia en PDF, o el detalle en TXT o CSV.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cliente_id.required' => 'El cliente es obligatorio.',
            'cliente_id.exists' => 'El cliente no está en su cartera.',
            'periodo.required' => 'El periodo es obligatorio.',
            'periodo.regex' => 'El periodo debe tener el formato MM-AAAA.',
            'formulario.required' => 'Indique si el archivo es del PDT 621 o del PDT 601.',
            'formulario.in' => 'Solo se importa el PDT 621 o el PDT 601.',
            'archivos.required' => 'Suba la constancia de SUNAT.',
            'archivos.min' => 'Suba la constancia de SUNAT.',
            'archivos.max' => 'Suba como máximo la constancia y el detalle, hasta tres archivos.',
            'archivos.*.max' => 'Cada archivo puede pesar hasta 8 MB.',
        ];
    }
}
