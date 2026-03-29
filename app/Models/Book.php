<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BookStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * @property BookStatus $status
 * @property bool $is_featured
 * @property int $price
 * @property-read string|null $cover_url
 * @property-read string|null $cover_thumb_url
 */
class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'annotation',
        'excerpt',
        'fragment',
        'price',
        'currency',
        'cover_path',
        'cover_thumb_path',
        'epub_path',
        'status',
        'is_featured',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'status' => BookStatus::class,
            'is_featured' => 'boolean',
            'price' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getCoverUrlAttribute(): ?string
    {
        if (! $this->cover_path) {
            return null;
        }

        return Storage::disk('s3-public')->url($this->cover_path);
    }

    public function getCoverThumbUrlAttribute(): ?string
    {
        if (! $this->cover_thumb_path) {
            return null;
        }

        return Storage::disk('s3-public')->url($this->cover_thumb_path);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', BookStatus::Published);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order', 'asc');
    }

    /**
     * Check whether this book has any purchase records (user_books).
     * The user_books table is introduced in Phase 5. We check via schema to
     * avoid a hard dependency on a table that may not exist in test environments.
     */
    public function hasAnyPurchases(): bool
    {
        if (! Schema::hasTable('user_books')) {
            return false;
        }

        return DB::table('user_books')
            ->where('book_id', $this->id)
            ->exists();
    }
}
