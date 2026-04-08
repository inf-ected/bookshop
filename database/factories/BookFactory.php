<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BookStatus;
use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    protected $model = Book::class;

    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'annotation' => fake()->paragraph(),
            'excerpt' => fake()->paragraphs(3, true),
            'fragment' => fake()->paragraphs(10, true),
            'price' => fake()->numberBetween(29900, 99900),
            'currency' => 'RUB',
            'cover_path' => null,
            'cover_thumb_path' => null,
            'epub_path' => null,
            'status' => BookStatus::Draft,
            'is_featured' => false,
            'is_available' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookStatus::Published,
            'epub_path' => 'epubs/'.fake()->uuid().'.epub',
        ]);
    }

    public function withEpub(): static
    {
        return $this->state(fn (array $attributes) => [
            'epub_path' => 'epubs/'.fake()->uuid().'.epub',
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => false,
        ]);
    }
}
