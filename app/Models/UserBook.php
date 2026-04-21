<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserBookFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $book_id
 * @property int|null $order_id
 * @property Carbon $granted_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Book $book
 * @property-read Order|null $order
 * @property-read User $user
 *
 * @method static \Database\Factories\UserBookFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBook newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBook newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBook query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBook whereBookId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBook whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBook whereGrantedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBook whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBook whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBook whereRevokedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBook whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBook whereUserId($value)
 *
 * @mixin \Eloquent
 */
class UserBook extends Model
{
    /** @use HasFactory<UserBookFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'book_id',
        'order_id',
        'granted_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Book, $this> */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
