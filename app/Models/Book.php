<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BookFileFormat;
use App\Enums\BookFileStatus;
use App\Enums\BookStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $annotation
 * @property string|null $excerpt
 * @property string|null $fragment
 * @property int $price
 * @property string $currency
 * @property string|null $cover_path
 * @property string|null $cover_thumb_path
 * @property BookStatus $status
 * @property bool $is_featured
 * @property bool $is_available
 * @property bool $is_adult
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, BookFile> $files
 * @property-read int|null $files_count
 * @property-read string|null $cover_thumb_url
 * @property-read string|null $cover_url
 * @property-read Collection<int, UserBook> $userBooks
 * @property-read int|null $user_books_count
 *
 * @method static \Database\Factories\BookFactory factory($count = null, $state = [])
 * @method static Builder<static>|Book featured()
 * @method static Builder<static>|Book newModelQuery()
 * @method static Builder<static>|Book newQuery()
 * @method static Builder<static>|Book ordered()
 * @method static Builder<static>|Book published()
 * @method static Builder<static>|Book query()
 * @method static Builder<static>|Book whereAnnotation($value)
 * @method static Builder<static>|Book whereCoverPath($value)
 * @method static Builder<static>|Book whereCoverThumbPath($value)
 * @method static Builder<static>|Book whereCreatedAt($value)
 * @method static Builder<static>|Book whereCurrency($value)
 * @method static Builder<static>|Book whereEpubPath($value)
 * @method static Builder<static>|Book whereExcerpt($value)
 * @method static Builder<static>|Book whereFragment($value)
 * @method static Builder<static>|Book whereId($value)
 * @method static Builder<static>|Book whereIsAdult($value)
 * @method static Builder<static>|Book whereIsAvailable($value)
 * @method static Builder<static>|Book whereIsFeatured($value)
 * @method static Builder<static>|Book wherePrice($value)
 * @method static Builder<static>|Book whereSlug($value)
 * @method static Builder<static>|Book whereSortOrder($value)
 * @method static Builder<static>|Book whereStatus($value)
 * @method static Builder<static>|Book whereTitle($value)
 * @method static Builder<static>|Book whereUpdatedAt($value)
 *
 * @mixin \Eloquent
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
        'status',
        'is_featured',
        'is_available',
        'is_adult',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'status' => BookStatus::class,
            'is_featured' => 'boolean',
            'is_available' => 'boolean',
            'is_adult' => 'boolean',
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

    /**
     * Scope for books visible in the public catalog:
     * published AND available for sale.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', BookStatus::Published)->where('is_available', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('id', 'asc');
    }

    /** Cached result of Schema::hasTable('user_books') — populated once per request. */
    private static ?bool $userBooksTableExists = null;

    public function userBooks(): HasMany
    {
        return $this->hasMany(UserBook::class);
    }

    /** @return HasMany<BookFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(BookFile::class);
    }

    /**
     * Returns true if the book has at least one client-accessible BookFile
     * (EPUB or FB2) with status=ready. Used as the publish guard.
     */
    public function hasClientReadyFile(): bool
    {
        return $this->files()
            ->where('status', BookFileStatus::Ready)
            ->whereIn('format', array_map(fn (BookFileFormat $f) => $f->value, BookFileFormat::clientAccessible()))
            ->exists();
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

    public function isPublished(): bool
    {
        return $this->status === BookStatus::Published;
    }

    public function isAvailable(): bool
    {
        return $this->isPublished() && $this->is_available;
    }

    public function isAdult(): bool
    {
        return $this->is_adult;
    }
}
