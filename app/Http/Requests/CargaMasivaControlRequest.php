<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use App\Services\ControlImpuestos\SolicitudCargaMasiva;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class CargaMasivaControlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $archivo = $this->file('archivo');
        if (! $archivo instanceof UploadedFile || $this->esLibroExcel($archivo)) {
            return;
        }

        $ruta = $archivo->getRealPath();
        if ($ruta === false) {
            return;
        }

        $bytes = file_get_contents($ruta);
        if ($bytes === false || str_starts_with($bytes, 'PK')) {
            return;
        }

        $this->merge(['contenido' => $bytes]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('modo') !== 'plantilla' || $validator->errors()->isNotEmpty()) {
                return;
            }

            $archivo = $this->file('archivo');
            $esExcel = $archivo instanceof UploadedFile && $this->esLibroExcel($archivo);
            if (! $esExcel && ! $this->filled('contenido')) {
                $validator->errors()->add('archivo', 'Suba la plantilla CSV o pegue su contenido.');
            }
        });
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'modo' => ['required', 'in:estados,plantilla,honorarios'],
            'anio' => ['required', 'integer', 'between:2000,2100'],
            'politica' => ['required', 'in:conservar,reemplazar'],
            'mes_desde' => [Rule::requiredIf(fn (): bool => $this->esTramo()), 'nullable', 'integer', 'between:1,12'],
            'mes_hasta' => [Rule::requiredIf(fn (): bool => $this->esTramo()), 'nullable', 'integer', 'between:1,12', 'gte:mes_desde'],
            'estado' => [
                Rule::requiredIf(fn (): bool => $this->esTramo()),
                'nullable',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $this->esTramo() || ! is_string($value)) {
                        return;
                    }
                    $permitidos = $this->input('modo') === 'honorarios'
                        ? ['Pendiente', 'Pagado']
                        : ['Pendiente', 'Procesando', 'Declarado'];
                    if (! in_array($value, $permitidos, true)) {
                        $fail($this->input('modo') === 'honorarios'
                            ? 'El honorario debe quedar en Debe o Pagó.'
                            : 'El estado debe ser Pendiente, En proceso o Declarado.');
                    }
                },
            ],
            'obligaciones' => ['required_if:modo,estados', 'nullable', 'array', 'min:1'],
            'obligaciones.*' => ['in:sire,pdt,ple'],
            'alcance' => ['required_if:modo,estados,honorarios', 'nullable', 'in:todos,seleccion'],
            'cliente_ids' => ['required_if:alcance,seleccion', 'nullable', 'array', 'min:1', 'max:500'],
            'cliente_ids.*' => ['integer', Cliente::reglaPerteneceACartera()],
            'contenido' => ['nullable', 'string', 'max:2000000'],
            'archivo' => [
                'nullable',
                'file',
                'max:2048',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        return;
                    }
                    if ($this->esLibroExcel($value)) {
                        $fail('Guarde el libro como CSV UTF-8. En Excel: Archivo, Guardar como, CSV UTF-8. También se acepta punto y coma.');
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
            'modo.required' => 'Indique si va a cargar declaraciones o la plantilla.',
            'modo.in' => 'El tipo de carga no es válido.',
            'anio.required' => 'El ejercicio es obligatorio.',
            'anio.integer' => 'El ejercicio no es válido.',
            'anio.between' => 'El ejercicio no es válido.',
            'politica.required' => 'Indique si debe conservar lo ya registrado o reemplazarlo.',
            'politica.in' => 'La política de carga no es válida.',
            'mes_desde.required_if' => 'Indique el mes inicial del tramo.',
            'mes_hasta.required_if' => 'Indique el mes final del tramo.',
            'mes_hasta.gte' => 'El mes final no puede ser anterior al mes inicial.',
            'estado.required' => 'Indique el estado que quiere aplicar.',
            'obligaciones.required_if' => 'Elija al menos una declaración: SIRE, PDT 621 o PDT 601.',
            'obligaciones.min' => 'Elija al menos una declaración: SIRE, PDT 621 o PDT 601.',
            'alcance.required_if' => 'Indique si la carga es para todos los clientes o solo para algunos.',
            'cliente_ids.required_if' => 'Elija al menos un cliente.',
            'cliente_ids.*.exists' => 'Hay un cliente que no pertenece a su cartera.',
            'archivo.max' => 'La plantilla no puede pesar más de 2 MB.',
            'contenido.max' => 'La plantilla no puede pesar más de 2 MB.',
        ];
    }

    public function solicitud(): SolicitudCargaMasiva
    {
        $validado = $this->validated();

        return new SolicitudCargaMasiva(
            modo: (string) $validado['modo'],
            anio: (int) $validado['anio'],
            politica: (string) $validado['politica'],
            mesDesde: isset($validado['mes_desde']) ? (int) $validado['mes_desde'] : null,
            mesHasta: isset($validado['mes_hasta']) ? (int) $validado['mes_hasta'] : null,
            estado: $validado['estado'] ?? null,
            obligaciones: array_values($validado['obligaciones'] ?? []),
            alcance: (string) ($validado['alcance'] ?? 'todos'),
            clienteIds: array_map('intval', $validado['cliente_ids'] ?? []),
            contenido: $validado['contenido'] ?? null,
        );
    }

    private function esTramo(): bool
    {
        return in_array($this->input('modo'), ['estados', 'honorarios'], true);
    }

    private function esLibroExcel(UploadedFile $archivo): bool
    {
        $extension = strtolower($archivo->getClientOriginalExtension());
        if (in_array($extension, ['xlsx', 'xls'], true)) {
            return true;
        }

        $ruta = $archivo->getRealPath();
        if ($ruta === false) {
            return false;
        }

        $inicio = file_get_contents($ruta, false, null, 0, 2);

        return $inicio === 'PK';
    }
}
