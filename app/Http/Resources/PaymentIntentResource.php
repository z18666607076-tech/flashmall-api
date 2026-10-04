<?php

namespace App\Http\Resources;

use App\Payments\PaymentIntent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentIntent
 */
class PaymentIntentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'channel' => $this->channel,
            'status' => $this->status,
            'client_secret' => $this->clientSecret,
            'provider_reference' => $this->providerReference,
            'wechat' => $this->channel === 'wechat' ? $this->clientParams : null,
        ];
    }
}
