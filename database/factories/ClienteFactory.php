<?php

namespace Database\Factories;

use App\Enums\EstadoCliente;
use App\Enums\RegimenTributario;
use App\Models\Cliente;
use App\Models\Estudio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    protected $model = Cliente::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'estudio_id' => Estudio::factory(),
            'razon_social' => mb_strtoupper($this->faker->company()).' S.A.C.',
            'ruc' => $this->rucValido(),
            'regimen_tributario' => $this->faker->randomElement(RegimenTributario::cases()),
            'estado' => EstadoCliente::Activo,
            'estado_contribuyente' => 'ACTIVO',
            'condicion_domicilio' => 'HABIDO',
            'tipo_contribuyente' => 'SOCIEDAD ANONIMA CERRADA',
            'direccion_fiscal' => $this->faker->streetAddress(),
            'ubigeo' => $this->faker->numerify('######'),
            'distrito' => $this->faker->city(),
            'provincia' => 'LIMA',
            'departamento' => 'LIMA',
            'correo' => $this->faker->unique()->companyEmail(),
            'telefono' => $this->faker->numerify('9########'),
            'es_buen_contribuyente' => false,
        ];
    }

    /**
     * Genera un RUC de 11 digitos con digito verificador valido segun el
     * algoritmo Modulo 11 que emplea SUNAT.
     */
    private function rucValido(): string
    {
        $base = '20'.$this->faker->unique()->numerify('76543###');
        $pesos = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];

        $suma = 0;
        foreach ($pesos as $i => $peso) {
            $suma += ((int) $base[$i]) * $peso;
        }

        $resto = 11 - ($suma % 11);
        $verificador = match (true) {
            $resto === 10 => 0,
            $resto === 11 => 1,
            default => $resto,
        };

        return $base.$verificador;
    }

    public function suspendido(): static
    {
        return $this->state(fn () => [
            'estado' => EstadoCliente::Suspendido,
            'motivo_baja' => 'Suspensión temporal de actividades',
        ]);
    }

    public function retirado(): static
    {
        return $this->state(fn () => [
            'estado' => EstadoCliente::Retirado,
            'fecha_baja' => now()->subMonth()->toDateString(),
            'mes_baja' => now()->subMonth()->format('Y-m'),
            'motivo_baja' => 'Cambio de contador',
        ]);
    }

    public function regimen(RegimenTributario $regimen): static
    {
        return $this->state(fn () => ['regimen_tributario' => $regimen]);
    }

    /**
     * Cliente obligado a llevar libros electronicos y presentar balances.
     */
    public function conBalances(): static
    {
        return $this->regimen(RegimenTributario::Rmt);
    }

    public function buenContribuyente(): static
    {
        return $this->state(fn () => ['es_buen_contribuyente' => true]);
    }

    /**
     * Ancla el cliente a un estudio concreto en lugar de crear uno nuevo.
     */
    public function paraEstudio(Estudio|int $estudio): static
    {
        return $this->state(fn () => [
            'estudio_id' => $estudio instanceof Estudio ? $estudio->id : $estudio,
        ]);
    }
}
