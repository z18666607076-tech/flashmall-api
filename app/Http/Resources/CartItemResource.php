<?php

namespace App\Http\Resources;

use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CartItem
 */
class CartItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $price = $this->sku?->price_cents;

        return [
            'id' => $this->id,
            'sku_id' => $this->sku_id,
            'product_id' => $this->sku?->product_id,
            'product_title' => $this->sku?->product?->title,
            'attrs' => $this->sku?->attrs,
            'quantity' => $this->quantity,
            'unit_price_cents' => $price,
            'currency' => $this->sku?->currency,
            'line_total_cents' => $price === null ? null : $price * $this->quantity,
            'available_stock' => $this->sku?->stock,
        ];
    }
}
