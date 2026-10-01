<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use App\Services\Clientes\LibroCargaMasivaClientes;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class CargaMasivaClienteLeerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Cliente::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $archivo = $this->file('archivo');
        if (! $archivo instanceof UploadedFile || $this->esLibroAntiguo($archivo)) {
            return;
        }

        $ruta = $archivo->getRealPath();
        if ($ruta === false) {
            return;
        }

        $bytes = file_get_contents($ruta);
        if ($bytes === false) {
            return;
        }

        if ($this->esLibroExcel($archivo, $bytes)) {
            $texto = (new LibroCargaMasivaClientes)->aTexto($bytes);
            if ($texto !== null) {
                $this->merge(['contenido' => $texto]);
            }

            return;
        }

        $this->merge(['contenido' => $bytes]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (! $this->filled('contenido')) {
                $archivo = $this->file('archivo');
                if ($archivo instanceof UploadedFile && ($this->esLibroAntiguo($archivo) || $this->esLibroExcel($archivo))) {
                    return;
                }

                $validator->errors()->add('archivo', 'Suba la plantilla de Excel o CSV, o pegue su contenido.');
            }
        });
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'contenido' => ['nullable', 'string', 'max:2000000'],
            'archivo' => [
                'nullable',
                'file',
                'max:2048',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        return;
                    }
                    if ($this->esLibroAntiguo($value)) {
                        $fail('Ese formato de Excel ya no se usa. Descargue de nuevo la plantilla (.xlsx) o guarde como CSV UTF-8.');
                    }
                    if ($this->esLibroExcel($value) && ! $this->filled('contenido')) {
                        $fail('No se pudo leer ese libro. Descargue de nuevo la plantilla o pegue las filas.');
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
            'archivo.max' => 'La plantilla no puede pesar más de 2 MB.',
            'contenido.max' => 'La plantilla no puede pesar más de 2 MB.',
        ];
    }

    private function esLibroAntiguo(UploadedFile $archivo): bool
    {
        return strtolower($archivo->getClientOriginalExtension()) === 'xls';
    }

    private function esLibroExcel(UploadedFile $archivo, ?string $bytes = null): bool
    {
        if (strtolower($archivo->getClientOriginalExtension()) === 'xlsx') {
            return true;
        }

        if ($bytes !== null) {
            return str_starts_with($bytes, 'PK');
        }

        $ruta = $archivo->getRealPath();
        if ($ruta === false) {
            return false;
        }

        $inicio = file_get_contents($ruta, false, null, 0, 2);

        return $inicio === 'PK';
    }
}
