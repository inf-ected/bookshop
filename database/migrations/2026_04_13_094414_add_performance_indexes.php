<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 12.2 — Performance Indexes.
 *
 * books: drop composite (status, sort_order) → add individual status, sort_order, is_available
 * orders: add paid_at
 * order_transactions: order_id and status already exist (created in create_order_transactions migration)
 * download_logs: add book_id standalone (composite user_id+book_id exists but book_id alone does not)
 * users: add role
 */
return new class extends Migration
{
    public function up(): void
    {
        // books: replace composite index with individual indexes
        Schema::table('books', function (Blueprint $table): void {
            $table->dropIndex('books_status_sort_order_index');
            $table->index('status', 'books_status_index');
            $table->index('sort_order', 'books_sort_order_index');
            $table->index('is_available', 'books_is_available_index');
        });

        // orders: index paid_at for range queries
        Schema::table('orders', function (Blueprint $table): void {
            $table->index('paid_at', 'orders_paid_at_index');
        });

        // download_logs: standalone book_id index for FK lookups and per-book stats
        Schema::table('download_logs', function (Blueprint $table): void {
            $table->index('book_id', 'download_logs_book_id_index');
        });

        // users: index role for admin checks and filtering
        Schema::table('users', function (Blueprint $table): void {
            $table->index('role', 'users_role_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_role_index');
        });

        Schema::table('download_logs', function (Blueprint $table): void {
            $table->dropIndex('download_logs_book_id_index');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_paid_at_index');
        });

        Schema::table('books', function (Blueprint $table): void {
            $table->dropIndex('books_is_available_index');
            $table->dropIndex('books_sort_order_index');
            $table->dropIndex('books_status_index');
            $table->index(['status', 'sort_order'], 'books_status_sort_order_index');
        });
    }
};
