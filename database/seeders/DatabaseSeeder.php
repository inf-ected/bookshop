<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // DatabaseSeeder is intended for testing/CI only.
        // For dev environment setup use: php artisan db:seed --class=DevSeeder
        if (app()->isProduction()) {
            $this->command->error('DatabaseSeeder must not run in production.');

            return;
        }

        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call(BookSeeder::class);
    }
}
