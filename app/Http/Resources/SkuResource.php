<?php

namespace App\Http\Resources;

use App\Models\Sku;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Sku
 */
class SkuResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'attrs' => $this->attrs ?? [],
            'price_cents' => $this->price_cents,
            'currency' => $this->currency,
            'stock' => $this->stock,
            'version' => $this->version,
        ];
    }
}
