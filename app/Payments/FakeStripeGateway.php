<?php

namespace App\Payments;

use App\Contracts\PaymentGateway;
use App\Models\Order;

class FakeStripeGateway implements PaymentGateway
{
    public function channel(): string
    {
        return 'stripe';
    }

    public function initiate(Order $order): PaymentIntent
    {
        $reference = 'pi_fake_'.$order->order_no;

        return new PaymentIntent(
            channel: 'stripe',
            status: 'requires_payment_method',
            clientSecret: $reference.'_secret_test',
            providerReference: $reference,
        );
    }

    public function refund(Order $order): PaymentRefund
    {
        return new PaymentRefund('stripe', 're_fake_'.$order->order_no, 'succeeded');
    }
}
