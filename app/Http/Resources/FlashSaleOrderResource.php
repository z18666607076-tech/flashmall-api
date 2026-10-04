<?php

namespace App\Http\Resources;

use App\Models\FlashSaleOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FlashSaleOrder
 */
class FlashSaleOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'flash_sale_id' => $this->flash_sale_id,
            'quantity' => $this->quantity,
            'order_id' => $this->order_id,
            'status' => $this->order_id === null ? 'pending' : 'ordered',
        ];
    }
}
