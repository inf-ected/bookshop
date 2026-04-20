<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderTransaction>
 */
class OrderTransactionFactory extends Factory
{
    protected $model = OrderTransaction::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'provider' => 'stripe',
            'provider_data' => [
                'session_id' => 'cs_test_'.fake()->regexify('[a-zA-Z0-9]{40}'),
            ],
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
        ]);
    }

    public function succeeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'succeeded',
            'expires_at' => null,
            'provider_data' => [
                'session_id' => 'cs_test_'.fake()->regexify('[a-zA-Z0-9]{40}'),
                'transaction_id' => 'pi_test_'.fake()->regexify('[a-zA-Z0-9]{24}'),
            ],
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'expires_at' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'expires_at' => now()->subMinutes(5),
        ]);
    }
}
