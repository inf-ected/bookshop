<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OrderTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property string $provider
 * @property array<string, mixed> $provider_data
 * @property string $status
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Order|null $order
 */
class OrderTransaction extends Model
{
    /** @use HasFactory<OrderTransactionFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'provider',
        'provider_data',
        'status',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'provider_data' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
