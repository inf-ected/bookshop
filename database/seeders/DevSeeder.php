<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DevSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@bookshop.local'],
            [
                'name' => 'Admin',
                'password' => 'password',
            ]
        );

        $admin->forceFill(['role' => UserRole::Admin])->save();
        $admin->markEmailAsVerified();

        $this->command->info('Dev admin: admin@bookshop.local / password');
    }
}
