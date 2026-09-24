<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'text' => fake()->paragraph(),
            'page' => (string) fake()->numberBetween(1, 500),
        ];
    }

    /**
     * A Quote stored without writing down the page.
     */
    public function withoutPage(): static
    {
        return $this->state(fn (array $attributes) => [
            'page' => null,
        ]);
    }
}
