<?php

namespace App\Payments;

final readonly class PaymentNotice
{
    public function __construct(
        public string $channel,
        public string $eventId,
        public string $type,
        public ?string $orderNo = null,
        public ?string $providerReference = null,
        public ?int $amountCents = null,
        public ?string $currency = null,
    ) {}
}
