<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => OrderStatus::Pending,
            'total_amount' => fake()->numberBetween(29900, 199900),
            'currency' => 'RUB',
            'paid_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Pending,
            'paid_at' => null,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Failed,
            'paid_at' => null,
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Refunded,
            'paid_at' => now()->subDay(),
        ]);
    }

    /**
     * Attach a succeeded Stripe transaction to the order after creation.
     */
    public function withTransaction(string $sessionId = '', string $paymentIntent = ''): static
    {
        return $this->afterCreating(function (Order $order) use ($sessionId, $paymentIntent): void {
            $providerData = [
                'session_id' => $sessionId !== '' ? $sessionId : 'cs_test_'.fake()->regexify('[a-zA-Z0-9]{40}'),
            ];

            if ($paymentIntent !== '') {
                $providerData['payment_intent'] = $paymentIntent;
            }

            $transactionStatus = match ($order->status) {
                OrderStatus::Paid => 'succeeded',
                OrderStatus::Failed => 'failed',
                default => 'pending',
            };

            OrderTransaction::factory()->create([
                'order_id' => $order->id,
                'provider' => 'stripe',
                'provider_data' => $providerData,
                'status' => $transactionStatus,
                'expires_at' => $transactionStatus === 'pending' ? now()->addMinutes(30) : null,
            ]);
        });
    }
}
