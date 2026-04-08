<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('provider', 30);
            $table->json('provider_data');
            $table->string('status', 20)->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('status');
        });

        // Data migration: create order_transactions for existing orders that have
        // a stripe_session_id set, preserving provider-specific data before we drop
        // those columns in the next migration step.
        $orders = DB::table('orders')
            ->whereNotNull('stripe_session_id')
            ->select(['id', 'status', 'stripe_session_id', 'stripe_payment_intent_id'])
            ->get();

        foreach ($orders as $order) {
            $transactionStatus = match ($order->status) {
                'paid' => 'succeeded',
                'failed' => 'failed',
                default => 'pending',
            };

            $providerData = ['session_id' => $order->stripe_session_id];

            if ($order->stripe_payment_intent_id !== null) {
                $providerData['payment_intent'] = $order->stripe_payment_intent_id;
            }

            DB::table('order_transactions')->insert([
                'order_id' => $order->id,
                'provider' => 'stripe',
                'provider_data' => json_encode($providerData),
                'status' => $transactionStatus,
                'expires_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Remove Stripe-specific columns from orders
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_stripe_session_id_unique');
            $table->dropColumn(['stripe_session_id', 'stripe_payment_intent_id', 'payment_provider']);
        });
    }

    public function down(): void
    {
        // Restore Stripe-specific columns on orders
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_provider', 30)->default('stripe')->after('currency');
            $table->string('stripe_session_id')->nullable()->after('payment_provider');
            $table->string('stripe_payment_intent_id')->nullable()->after('stripe_session_id');
            $table->unique('stripe_session_id', 'orders_stripe_session_id_unique');
        });

        // Restore data from order_transactions back to orders
        $transactions = DB::table('order_transactions')
            ->where('provider', 'stripe')
            ->get();

        foreach ($transactions as $tx) {
            $data = json_decode((string) $tx->provider_data, true);
            DB::table('orders')->where('id', $tx->order_id)->update([
                'payment_provider' => 'stripe',
                'stripe_session_id' => $data['session_id'] ?? null,
                'stripe_payment_intent_id' => $data['payment_intent'] ?? null,
            ]);
        }

        Schema::dropIfExists('order_transactions');
    }
};
