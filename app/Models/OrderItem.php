<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed> $snapshot
 */
#[Fillable(['order_id', 'sku_id', 'quantity', 'unit_price_cents', 'currency', 'snapshot'])]
class OrderItem extends Model
{
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'quantity' => 'integer',
            'unit_price_cents' => 'integer',
            'snapshot' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Sku, $this>
     */
    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }
}
