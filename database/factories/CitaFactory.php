<?php

namespace Database\Factories;

use App\Models\Cita;
use App\Models\Libro;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cita>
 */
class CitaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'libro_id' => Libro::factory(),
            'texto' => fake()->paragraph(),
            'pagina' => fake()->numberBetween(1, 500),
        ];
    }

    /**
     * Una Cita que se guardó sin anotar la página.
     */
    public function sinPagina(): static
    {
        return $this->state(fn (array $attributes) => [
            'pagina' => null,
        ]);
    }
}
