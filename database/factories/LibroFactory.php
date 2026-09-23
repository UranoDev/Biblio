<?php

namespace Database\Factories;

use App\Enums\Fuente;
use App\Enums\Procedencia;
use App\Enums\Situacion;
use App\Models\Libro;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Libro>
 */
class LibroFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'isbn' => (string) fake()->unique()->numerify('978##########'),
            'titulo' => Str::title(rtrim(fake()->sentence(3), '.')),
            'autores' => [fake()->name()],
            'editorial' => fake()->company(),
            'anio' => fake()->numberBetween(1950, 2026),
            'edicion' => null,
            'descripcion' => fake()->paragraph(),
            'descripcion_procedencia' => Procedencia::Fuente,
            'fuente' => Fuente::OpenLibrary,
            'portada_ruta' => 'portadas/'.fake()->uuid().'.jpg',
            'situacion' => Situacion::Acervo,
            'leido' => false,
            'fecha_lectura' => null,
            'apunte' => null,
            'prestado_a' => null,
            'prestado_el' => null,
            'oculto' => false,
        ];
    }

    /**
     * Leído, con Fecha de lectura.
     */
    public function leido(): static
    {
        return $this->state(fn (array $attributes) => [
            'leido' => true,
            'fecha_lectura' => fake()->dateTimeBetween('-5 years')->format('Y-m-d'),
        ]);
    }

    /**
     * Leído hace años, sin saber cuándo.
     */
    public function leidoSinFecha(): static
    {
        return $this->state(fn (array $attributes) => [
            'leido' => true,
            'fecha_lectura' => null,
        ]);
    }

    /**
     * Un Libro que quiero y que todavía no tengo.
     */
    public function porComprar(): static
    {
        return $this->state(fn (array $attributes) => [
            'situacion' => Situacion::PorComprar,
        ]);
    }

    public function enAcervo(): static
    {
        return $this->state(fn (array $attributes) => [
            'situacion' => Situacion::Acervo,
        ]);
    }

    /**
     * Fuera de la Vitrina.
     */
    public function oculto(): static
    {
        return $this->state(fn (array $attributes) => [
            'oculto' => true,
        ]);
    }

    /**
     * Con la Descripción que trajo la Fuente.
     */
    public function descripcionDeFuente(): static
    {
        return $this->state(fn (array $attributes) => [
            'descripcion' => fake()->paragraph(),
            'descripcion_procedencia' => Procedencia::Fuente,
            'descripcion_proveedor' => null,
            'descripcion_modelo' => null,
        ]);
    }

    /**
     * Con la Descripción que escribió la IA, y con qué Modelo la escribió.
     */
    public function descripcionDeIa(string $proveedor = 'gemini', string $modelo = 'gemini-2.5-flash'): static
    {
        return $this->state(fn (array $attributes) => [
            'descripcion' => fake()->paragraph(),
            'descripcion_procedencia' => Procedencia::Ia,
            'descripcion_proveedor' => $proveedor,
            'descripcion_modelo' => $modelo,
        ]);
    }

    /**
     * Con la Descripción que escribí yo. Ninguna regeneración la toca.
     */
    public function descripcionMia(): static
    {
        return $this->state(fn (array $attributes) => [
            'descripcion' => fake()->paragraph(),
            'descripcion_procedencia' => Procedencia::Mia,
            'descripcion_proveedor' => null,
            'descripcion_modelo' => null,
        ]);
    }

    /**
     * Sin Descripción: ninguna Fuente la trajo y la IA todavía no la escribe.
     */
    public function sinDescripcion(): static
    {
        return $this->state(fn (array $attributes) => [
            'descripcion' => null,
            'descripcion_procedencia' => null,
            'descripcion_proveedor' => null,
            'descripcion_modelo' => null,
        ]);
    }

    /**
     * Con las Categorías ya generadas por un Modelo.
     */
    public function categoriasGeneradas(string $proveedor = 'gemini', string $modelo = 'gemini-2.5-flash'): static
    {
        return $this->state(fn (array $attributes) => [
            'categorias_proveedor' => $proveedor,
            'categorias_modelo' => $modelo,
        ]);
    }

    /**
     * Prestado, con la fecha en que salió.
     */
    public function prestado(?string $a = null): static
    {
        return $this->state(fn (array $attributes) => [
            'prestado_a' => $a ?? fake()->firstName(),
            'prestado_el' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
        ]);
    }

    /**
     * Sin ISBN: los Libros viejos que no traen código de barras.
     */
    public function sinIsbn(): static
    {
        return $this->state(fn (array $attributes) => [
            'isbn' => null,
            'fuente' => Fuente::Manual,
            'portada_ruta' => null,
        ]);
    }
}
