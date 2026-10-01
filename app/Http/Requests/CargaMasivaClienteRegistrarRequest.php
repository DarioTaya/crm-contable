<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CargaMasivaClienteRegistrarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Cliente::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $campos = [
            'ruc',
            'regimen',
            'correo',
            'telefono',
            'usuario_sol',
            'clave_sol',
            'sunat_client_id',
            'sunat_client_secret',
            'url_afp',
            'url_bn',
        ];
        $ajustes = [];
        foreach ($campos as $campo) {
            if (is_string($this->input($campo))) {
                $ajustes[$campo] = trim($this->input($campo));
            }
        }
        if ($ajustes !== []) {
            $this->merge($ajustes);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ruc' => ['required', 'string', 'max:40'],
            'regimen' => ['required', 'string', 'max:80'],
            'correo' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'usuario_sol' => ['nullable', 'string', 'max:255'],
            'clave_sol' => ['nullable', 'string', 'max:255'],
            'sunat_client_id' => ['nullable', 'string', 'max:255'],
            'sunat_client_secret' => ['nullable', 'string', 'max:500'],
            'url_afp' => ['nullable', 'string', 'max:500', 'url:http,https'],
            'url_bn' => ['nullable', 'string', 'max:500', 'url:http,https'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ruc.required' => 'Indique el RUC.',
            'regimen.required' => 'Indique el régimen: NRUS, RER, RMT o General.',
            'correo.email' => 'El correo no es válido.',
            'telefono.max' => 'El teléfono no puede pasar de 20 caracteres.',
            'url_afp.url' => 'El enlace de AFPnet debe comenzar con http:// o https://.',
            'url_bn.url' => 'El enlace de detracciones debe comenzar con http:// o https://.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        return $this->validated();
    }
}
