<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('book_id');
            $table->string('format', 10);
            $table->string('status', 20)->default('pending');
            $table->string('path')->nullable();
            $table->boolean('is_source')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('book_id')->references('id')->on('books')->cascadeOnDelete();

            $table->unique(['book_id', 'format'], 'book_files_book_id_format_unique');
            $table->index('status', 'book_files_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_files');
    }
};
