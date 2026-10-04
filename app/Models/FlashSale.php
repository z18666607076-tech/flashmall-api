<?php

namespace App\Models;

use App\Enums\FlashSaleStatus;
use Database\Factories\FlashSaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property FlashSaleStatus $status
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 */
#[Fillable([
    'sku_id',
    'title',
    'price_cents',
    'currency',
    'starts_at',
    'ends_at',
    'total_stock',
    'per_user_limit',
    'sold_count',
    'status',
])]
class FlashSale extends Model
{
    /** @use HasFactory<FlashSaleFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'sku_id' => 'integer',
            'price_cents' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'total_stock' => 'integer',
            'per_user_limit' => 'integer',
            'sold_count' => 'integer',
            'status' => FlashSaleStatus::class,
        ];
    }

    public function isOpen(): bool
    {
        if (! in_array($this->status, [FlashSaleStatus::Scheduled, FlashSaleStatus::Active], true)) {
            return false;
        }

        return now()->gte($this->starts_at) && now()->lt($this->ends_at);
    }

    /**
     * @return BelongsTo<Sku, $this>
     */
    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }

    /**
     * @return HasMany<FlashSaleOrder, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(FlashSaleOrder::class);
    }
}
