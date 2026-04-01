<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('download_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('book_id');
            $table->string('ip_address', 45);
            $table->timestamp('downloaded_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('book_id')->references('id')->on('books')->restrictOnDelete();

            $table->index(['user_id', 'book_id']);
            $table->index('downloaded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('download_logs');
    }
};
