<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BookFileFormat;
use App\Enums\BookFileStatus;
use App\Models\Book;
use App\Models\BookFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookFile>
 */
class BookFileFactory extends Factory
{
    protected $model = BookFile::class;

    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'format' => fake()->randomElement(BookFileFormat::cases()),
            'status' => BookFileStatus::Pending,
            'path' => null,
            'is_source' => false,
            'error_message' => null,
        ];
    }

    public function source(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_source' => true,
            'status' => BookFileStatus::Ready,
            'path' => 'books/'.fake()->numberBetween(1, 100).'/'.fake()->uuid().'.'.(
                $attributes['format'] instanceof BookFileFormat
                    ? $attributes['format']->value
                    : $attributes['format']
            ),
        ]);
    }

    public function ready(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookFileStatus::Ready,
            'path' => 'books/'.fake()->numberBetween(1, 100).'/'.fake()->uuid().'.'.(
                $attributes['format'] instanceof BookFileFormat
                    ? $attributes['format']->value
                    : $attributes['format']
            ),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookFileStatus::Failed,
            'error_message' => fake()->sentence(),
        ]);
    }

    public function epub(): static
    {
        return $this->state(fn (array $attributes) => [
            'format' => BookFileFormat::Epub,
        ]);
    }

    public function fb2(): static
    {
        return $this->state(fn (array $attributes) => [
            'format' => BookFileFormat::Fb2,
        ]);
    }

    public function docx(): static
    {
        return $this->state(fn (array $attributes) => [
            'format' => BookFileFormat::Docx,
        ]);
    }
}
