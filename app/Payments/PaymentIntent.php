<?php

namespace App\Payments;

use App\Exceptions\CommerceException;

final readonly class PaymentIntent
{
    /**
     * @param  array<string, string>  $clientParams
     */
    public function __construct(
        public string $channel,
        public string $status,
        public ?string $clientSecret = null,
        public ?string $providerReference = null,
        public array $clientParams = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'channel' => $this->channel,
            'status' => $this->status,
            'client_secret' => $this->clientSecret,
            'provider_reference' => $this->providerReference,
            'wechat' => $this->clientParams,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        $channel = $payload['channel'] ?? null;
        $status = $payload['status'] ?? null;

        if (! is_string($channel) || ! is_string($status)) {
            throw new CommerceException('Stored payment payload is invalid.', 409);
        }

        $params = [];
        $wechat = $payload['wechat'] ?? [];

        if (is_array($wechat)) {
            foreach ($wechat as $key => $value) {
                if (is_string($key) && is_string($value)) {
                    $params[$key] = $value;
                }
            }
        }

        $secret = $payload['client_secret'] ?? null;
        $reference = $payload['provider_reference'] ?? null;

        return new self(
            channel: $channel,
            status: $status,
            clientSecret: is_string($secret) ? $secret : null,
            providerReference: is_string($reference) ? $reference : null,
            clientParams: $params,
        );
    }
}
