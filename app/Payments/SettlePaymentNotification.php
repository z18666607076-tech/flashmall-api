<?php

namespace App\Payments;

use App\Enums\OrderStatus;
use App\Exceptions\CommerceException;
use App\Models\Order;
use App\Models\PaymentEvent;
use App\Orders\CancelOrder;
use App\Orders\TransitionOrder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class SettlePaymentNotification
{
    public function __construct(
        private TransitionOrder $transitions,
        private CancelOrder $cancel,
    ) {}

    public function settle(PaymentNotice $notice): void
    {
        $order = $this->order($notice);

        if ($notice->type !== 'ignored') {
            if ($order === null) {
                throw new CommerceException('Payment notification does not match an order.', 404);
            }

            if ($notice->amountCents === null || $notice->currency === null) {
                throw new CommerceException('Payment notification is missing an amount.', 422);
            }

            $this->assertAmount($order, $notice->amountCents, $notice->currency);
        }

        DB::transaction(function () use ($notice, $order): void {
            try {
                PaymentEvent::query()->create([
                    'channel' => $notice->channel,
                    'event_id' => $notice->eventId,
                    'type' => $notice->type,
                    'order_id' => $order?->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                return;
            }

            if ($order === null || $notice->type === 'ignored') {
                return;
            }

            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();

            if ($locked === null) {
                return;
            }

            if ($notice->type === 'payment.succeeded') {
                $this->markPaid($locked, $notice);

                return;
            }

            if ($notice->type === 'refund.succeeded') {
                $this->refund($locked);
            }
        });
    }

    private function markPaid(Order $order, PaymentNotice $notice): void
    {
        if ($order->status !== OrderStatus::PendingPayment) {
            return;
        }

        $refundAlreadyRecorded = PaymentEvent::query()
            ->where('order_id', $order->id)
            ->where('type', 'refund.succeeded')
            ->exists();

        if ($refundAlreadyRecorded) {
            $this->cancel->execute($order);

            return;
        }

        $this->transitions->markPaid($order, $notice->channel, $notice->providerReference);
    }

    private function refund(Order $order): void
    {
        if ($order->status === OrderStatus::PendingPayment) {
            $this->cancel->execute($order);

            return;
        }

        if ($order->status->canTransitionTo(OrderStatus::Refunded)) {
            $this->transitions->refund($order);
        }
    }

    private function order(PaymentNotice $notice): ?Order
    {
        if (is_string($notice->orderNo) && $notice->orderNo !== '') {
            $byNumber = Order::query()->where('order_no', $notice->orderNo)->first();

            if ($byNumber !== null) {
                return $byNumber;
            }
        }

        if (is_string($notice->providerReference) && $notice->providerReference !== '') {
            return Order::query()->where('provider_reference', $notice->providerReference)->first();
        }

        return null;
    }

    private function assertAmount(Order $order, int $amountCents, string $currency): void
    {
        if ($amountCents !== $order->total_cents || strtoupper($currency) !== strtoupper($order->currency)) {
            throw new CommerceException('Payment amount does not match the order.', 422);
        }
    }
}
