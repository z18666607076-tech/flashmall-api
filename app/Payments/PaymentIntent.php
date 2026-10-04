<?php

namespace App\Payments;

final readonly class PaymentIntent
{
    public function __construct(
        public string $channel,
        public string $status,
        public ?string $clientSecret = null,
        public ?string $redirectUrl = null,
    ) {}
}
