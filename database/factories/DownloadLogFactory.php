<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Book;
use App\Models\DownloadLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DownloadLog>
 */
class DownloadLogFactory extends Factory
{
    protected $model = DownloadLog::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'book_id' => Book::factory(),
            'ip_address' => $this->faker->ipv4(),
            'downloaded_at' => now(),
        ];
    }
}
