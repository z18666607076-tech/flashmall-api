<?php

namespace App\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Payments\PaymentGateways;

class RefundOrder
{
    public function __construct(
        private PaymentGateways $gateways,
        private TransitionOrder $transitions,
    ) {}

    public function execute(Order $order): Order
    {
        $order->refresh();

        if (! $order->status->canTransitionTo(OrderStatus::Refunded)) {
            return $this->transitions->refund($order);
        }

        if (is_string($order->payment_channel) && $order->payment_channel !== '') {
            $this->gateways->get($order->payment_channel)->refund($order);
        }

        return $this->transitions->refund($order);
    }
}
