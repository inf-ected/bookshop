<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Book;
use Illuminate\Database\Seeder;

class BookSeeder extends Seeder
{
    public function run(): void
    {
        // 1 featured published book
        Book::factory()->published()->featured()->create();

        // 2 published non-featured books
        Book::factory()->published()->count(2)->create();

        // 1 draft book
        Book::factory()->create();
    }
}
