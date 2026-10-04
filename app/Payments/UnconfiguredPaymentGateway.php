<?php

namespace App\Payments;

use App\Contracts\PaymentGateway;
use App\Exceptions\CommerceException;
use App\Models\Order;

class UnconfiguredPaymentGateway implements PaymentGateway
{
    public function channel(): string
    {
        return 'unconfigured';
    }

    public function initiate(Order $order): PaymentIntent
    {
        throw new CommerceException(
            'No payment gateway is configured. WeChat Pay and Stripe are the next milestone.',
            501,
        );
    }
}
