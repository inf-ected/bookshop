<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('annotation')->nullable();
            $table->text('excerpt')->nullable();
            $table->longText('fragment')->nullable();
            $table->unsignedInteger('price')->default(0);
            $table->char('currency', 3)->default('RUB');
            $table->string('cover_path')->nullable();
            $table->string('cover_thumb_path')->nullable();
            $table->string('epub_path')->nullable();
            $table->string('status', 20)->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['status', 'sort_order']);
            $table->index('is_featured');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
