<?php

namespace Database\Factories;

use App\Models\Author;
use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'isbn' => fake()->unique()->isbn13(),
            'published_year' => fake()->year(),
            'price' => fake()->randomFloat(2, 50000, 500000),
            'stock' => fake()->numberBetween(1, 100),
            'author_id' => Author::factory(),
        ];
    }
}
