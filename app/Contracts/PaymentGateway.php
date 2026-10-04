<?php

namespace App\Contracts;

use App\Models\Order;
use App\Payments\PaymentIntent;
use App\Payments\PaymentRefund;

interface PaymentGateway
{
    public function channel(): string;

    public function initiate(Order $order): PaymentIntent;

    public function refund(Order $order): PaymentRefund;
}
