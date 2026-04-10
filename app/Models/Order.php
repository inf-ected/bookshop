<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property OrderStatus $status
 * @property Carbon|null $paid_at
 * @property int $total_amount
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',
        'total_amount',
        'currency',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'paid_at' => 'datetime',
            'total_amount' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<UserBook, $this> */
    public function userBooks(): HasMany
    {
        return $this->hasMany(UserBook::class);
    }

    /** @return HasMany<OrderTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(OrderTransaction::class);
    }

    /**
     * The single active/latest transaction for this order.
     * Safe while one checkout = one order = one transaction.
     *
     * TODO: when checkout is refactored to reuse pending orders (multiple
     * transactions per order), replace latestOfMany() with priority-based
     * selection (succeeded > failed > expired > pending).
     *
     * @return HasOne<OrderTransaction, $this>
     */
    public function transaction(): HasOne
    {
        return $this->hasOne(OrderTransaction::class)->latestOfMany();
    }
}
