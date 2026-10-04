<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property OrderStatus $status
 * @property Carbon|null $expires_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $shipped_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $refunded_at
 * @property string|null $provider_reference
 * @property array<string, mixed>|null $payment_payload
 */
#[Fillable([
    'order_no',
    'user_id',
    'status',
    'total_cents',
    'currency',
    'payment_channel',
    'provider_reference',
    'payment_payload',
    'idempotency_key',
    'expires_at',
    'paid_at',
    'cancelled_at',
    'shipped_at',
    'completed_at',
    'refunded_at',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'status' => OrderStatus::class,
            'total_cents' => 'integer',
            'payment_payload' => 'array',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'shipped_at' => 'datetime',
            'completed_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    /**
     * @return HasOne<FlashSaleOrder, $this>
     */
    public function flashSaleOrder(): HasOne
    {
        return $this->hasOne(FlashSaleOrder::class);
    }
}
