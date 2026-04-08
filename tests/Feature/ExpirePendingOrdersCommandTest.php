<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpirePendingOrdersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_expires_pending_transactions_past_expires_at(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->pending()->create(['user_id' => $user->id]);
        $transaction = OrderTransaction::factory()->pending()->create([
            'order_id' => $order->id,
            'expires_at' => now()->subMinutes(5),
        ]);

        $this->artisan('app:expire-pending-orders')
            ->assertExitCode(0);

        $transaction->refresh();
        $this->assertSame('expired', $transaction->status);

        $order->refresh();
        $this->assertEquals(OrderStatus::Failed, $order->status);
    }

    public function test_does_not_expire_transactions_not_yet_past_expires_at(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->pending()->create(['user_id' => $user->id]);
        $transaction = OrderTransaction::factory()->pending()->create([
            'order_id' => $order->id,
            'expires_at' => now()->addMinutes(30),
        ]);

        $this->artisan('app:expire-pending-orders')
            ->assertExitCode(0);

        $transaction->refresh();
        $this->assertSame('pending', $transaction->status);

        $order->refresh();
        $this->assertEquals(OrderStatus::Pending, $order->status);
    }

    public function test_does_not_expire_transactions_with_null_expires_at(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->pending()->create(['user_id' => $user->id]);
        $transaction = OrderTransaction::factory()->pending()->create([
            'order_id' => $order->id,
            'expires_at' => null,
        ]);

        $this->artisan('app:expire-pending-orders')
            ->assertExitCode(0);

        $transaction->refresh();
        $this->assertSame('pending', $transaction->status);

        $order->refresh();
        $this->assertEquals(OrderStatus::Pending, $order->status);
    }

    public function test_does_not_change_already_settled_transactions(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->paid()->create(['user_id' => $user->id]);
        $transaction = OrderTransaction::factory()->succeeded()->create([
            'order_id' => $order->id,
            'expires_at' => now()->subMinutes(10),
        ]);

        $this->artisan('app:expire-pending-orders')
            ->assertExitCode(0);

        $transaction->refresh();
        $this->assertSame('succeeded', $transaction->status);

        $order->refresh();
        $this->assertEquals(OrderStatus::Paid, $order->status);
    }

    public function test_outputs_count_of_expired_orders(): void
    {
        $user = User::factory()->create();

        $order1 = Order::factory()->pending()->create(['user_id' => $user->id]);
        OrderTransaction::factory()->pending()->create([
            'order_id' => $order1->id,
            'expires_at' => now()->subHour(),
        ]);

        $order2 = Order::factory()->pending()->create(['user_id' => $user->id]);
        OrderTransaction::factory()->pending()->create([
            'order_id' => $order2->id,
            'expires_at' => now()->subHour(),
        ]);

        $this->artisan('app:expire-pending-orders')
            ->expectsOutput('Expired 2 pending order(s).')
            ->assertExitCode(0);
    }
}
