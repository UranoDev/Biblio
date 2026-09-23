<?php

namespace Database\Factories;

use App\Models\Categoria;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Categoria>
 */
class CategoriaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = Str::ucfirst(rtrim(fake()->unique()->sentence(2), '.'));

        return [
            'nombre' => $nombre,
            'slug' => Str::slug($nombre),
        ];
    }

    public function llamada(string $nombre): static
    {
        return $this->state(fn (array $attributes) => [
            'nombre' => $nombre,
            'slug' => Str::slug($nombre),
        ]);
    }
}
