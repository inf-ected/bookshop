<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BookStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /** Cached result of Schema::hasTable('user_books') — populated once per request. */
    private static ?bool $userBooksTableExists = null;

    public function userBooks(): HasMany
    {
        return $this->hasMany(UserBook::class);
    }

    /**
     * Check whether this book has any purchase records (user_books).
     * The user_books table is introduced in Phase 5. We check via schema to
     * avoid a hard dependency on a table that may not exist in test environments.
     * The schema check result is cached in a static property so it only hits
     * the database once per request regardless of how many books are checked.
     */
    public function hasAnyPurchases(): bool
    {
        if (self::$userBooksTableExists === null) {
            self::$userBooksTableExists = Schema::hasTable('user_books');
        }

        if (! self::$userBooksTableExists) {
            return false;
        }

        return $this->userBooks()->exists();
    }
}
