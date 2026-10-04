<?php

namespace App\Http\Resources;

use App\Models\FlashSale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FlashSale
 */
class FlashSaleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku_id' => $this->sku_id,
            'product_id' => $this->sku?->product_id,
            'product_title' => $this->sku?->product?->title,
            'title' => $this->title,
            'price_cents' => $this->price_cents,
            'currency' => $this->currency,
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'total_stock' => $this->total_stock,
            'per_user_limit' => $this->per_user_limit,
            'sold_count' => $this->sold_count,
            'status' => $this->status->value,
            'open' => $this->isOpen(),
        ];
    }
}
