<?php

namespace App\Payments;

use App\Enums\OrderStatus;
use App\Exceptions\CommerceException;
use App\Models\Order;
use App\Models\User;

class PayOrder
{
    public function __construct(private PaymentGateways $gateways) {}

    public function execute(User $user, Order $order, string $channel): PaymentIntent
    {
        if ($order->user_id !== $user->id) {
            throw new CommerceException('Resource not found.', 404);
        }

        if ($order->status !== OrderStatus::PendingPayment) {
            throw new CommerceException('This order is not awaiting payment.', 409);
        }

        if ($order->expires_at !== null && $order->expires_at->isPast()) {
            throw new CommerceException('This order has expired.', 409);
        }

        $stored = $order->payment_payload;

        if ($order->payment_channel === $channel && is_array($stored) && $stored !== []) {
            return PaymentIntent::fromPayload($stored);
        }

        $intent = $this->gateways->get($channel)->initiate($order);
        $order->payment_channel = $channel;
        $order->provider_reference = $intent->providerReference;
        $order->payment_payload = $intent->toPayload();
        $order->save();

        return $intent;
    }
}
