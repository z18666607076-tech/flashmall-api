<?php

namespace App\Contracts;

use App\Models\Order;
use App\Payments\PaymentIntent;

interface PaymentGateway
{
    public function channel(): string;

    /**
     * Start a charge for an order that is still pending payment.
     * WeChat Pay and Stripe implement this in the next milestone.
     */
    public function initiate(Order $order): PaymentIntent;
}
