<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property int $book_id
 * @property int $price
 * @property string $currency
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Order $order
 * @property-read Book $book
 */
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'book_id',
        'price',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Book, $this> */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
