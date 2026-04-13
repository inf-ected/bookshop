<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 12.2 — Performance Indexes.
 *
 * Asserts every index added by the 2026_04_13_094414_add_performance_indexes migration
 * is present in the schema. The composite (status, sort_order) index on books must NOT
 * exist after the migration, replaced by individual indexes.
 */
class Phase122PerformanceIndexesTest extends TestCase
{
    use RefreshDatabase;

    public function test_books_status_index_exists(): void
    {
        $this->assertTrue(Schema::hasIndex('books', 'books_status_index'));
    }

    public function test_books_sort_order_index_exists(): void
    {
        $this->assertTrue(Schema::hasIndex('books', 'books_sort_order_index'));
    }

    public function test_books_is_available_index_exists(): void
    {
        $this->assertTrue(Schema::hasIndex('books', 'books_is_available_index'));
    }

    public function test_books_composite_status_sort_order_index_is_dropped(): void
    {
        $this->assertFalse(Schema::hasIndex('books', 'books_status_sort_order_index'));
    }

    public function test_orders_paid_at_index_exists(): void
    {
        $this->assertTrue(Schema::hasIndex('orders', 'orders_paid_at_index'));
    }

    public function test_order_transactions_order_id_index_exists(): void
    {
        $this->assertTrue(Schema::hasIndex('order_transactions', 'order_transactions_order_id_index'));
    }

    public function test_order_transactions_status_index_exists(): void
    {
        $this->assertTrue(Schema::hasIndex('order_transactions', 'order_transactions_status_index'));
    }

    public function test_download_logs_book_id_index_exists(): void
    {
        $this->assertTrue(Schema::hasIndex('download_logs', 'download_logs_book_id_index'));
    }

    public function test_users_role_index_exists(): void
    {
        $this->assertTrue(Schema::hasIndex('users', 'users_role_index'));
    }
}
