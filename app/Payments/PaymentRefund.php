<?php

namespace App\Payments;

final readonly class PaymentRefund
{
    public function __construct(
        public string $channel,
        public string $reference,
        public string $status,
    ) {}
}
