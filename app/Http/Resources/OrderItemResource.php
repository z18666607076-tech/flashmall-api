<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku_id' => $this->sku_id,
            'quantity' => $this->quantity,
            'unit_price_cents' => $this->unit_price_cents,
            'currency' => $this->currency,
            'line_total_cents' => $this->unit_price_cents * $this->quantity,
            'snapshot' => $this->snapshot,
        ];
    }
}
