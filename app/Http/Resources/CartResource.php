<?php

namespace App\Http\Resources;

use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Cart
 */
class CartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = $this->items;
        $currency = $items->first()?->sku?->currency;
        $total = 0;

        foreach ($items as $item) {
            $sku = $item->sku;
            $total += ($sku === null ? 0 : $sku->price_cents) * $item->quantity;
        }

        return [
            'id' => $this->id,
            'currency' => $currency,
            'item_count' => (int) $items->sum('quantity'),
            'total_cents' => $total,
            'items' => CartItemResource::collection($items),
        ];
    }
}
