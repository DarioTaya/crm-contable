<?php

namespace Database\Factories;

use App\Models\Estudio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Estudio>
 */
class EstudioFactory extends Factory
{
    protected $model = Estudio::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'Estudio Contable '.$this->faker->lastName(),
            'ruc' => '20'.$this->faker->unique()->numerify('123456#'),
            'correo' => $this->faker->unique()->companyEmail(),
            'telefono' => $this->faker->numerify('9########'),
            'direccion' => $this->faker->streetAddress(),
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}
