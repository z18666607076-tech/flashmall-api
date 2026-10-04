<?php

namespace App\Models;

use Database\Factories\SkuFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'attrs', 'price_cents', 'currency', 'stock', 'version'])]
class Sku extends Model
{
    /** @use HasFactory<SkuFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'attrs' => 'array',
            'price_cents' => 'integer',
            'stock' => 'integer',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Sku $sku): void {
            if ($sku->isDirty('stock')) {
                $sku->version = (int) $sku->getOriginal('version') + 1;
            }
        });
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
