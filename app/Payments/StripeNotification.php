<?php

namespace App\Payments;

use App\Exceptions\PaymentSignatureException;

final class StripeNotification
{
    /**
     * @param  array<string, mixed>  $event
     */
    public function notice(array $event): PaymentNotice
    {
        $eventId = $event['id'] ?? null;
        $type = $event['type'] ?? null;
        $object = is_array($event['data'] ?? null) ? ($event['data']['object'] ?? null) : null;

        if (! is_string($eventId) || $eventId === '' || ! is_string($type) || ! is_array($object)) {
            throw new PaymentSignatureException('Stripe event is missing an id or object.');
        }

        if ($type === 'payment_intent.succeeded') {
            $intentId = $object['id'] ?? null;

            return new PaymentNotice(
                channel: 'stripe',
                eventId: $eventId,
                type: 'payment.succeeded',
                orderNo: $this->orderNo($object),
                providerReference: is_string($intentId) ? $intentId : null,
                amountCents: $this->amount($object, 'amount'),
                currency: $this->currency($object),
            );
        }

        if ($type === 'refund.updated') {
            if (($object['status'] ?? null) !== 'succeeded') {
                return new PaymentNotice('stripe', $eventId, 'ignored', $this->orderNo($object));
            }

            $paymentIntent = $object['payment_intent'] ?? null;

            return new PaymentNotice(
                channel: 'stripe',
                eventId: $eventId,
                type: 'refund.succeeded',
                orderNo: $this->orderNo($object),
                providerReference: is_string($paymentIntent) ? $paymentIntent : null,
                amountCents: $this->amount($object, 'amount'),
                currency: $this->currency($object),
            );
        }

        if ($type === 'charge.refunded') {
            $paymentIntent = $object['payment_intent'] ?? null;

            return new PaymentNotice(
                channel: 'stripe',
                eventId: $eventId,
                type: 'refund.succeeded',
                orderNo: $this->orderNo($object),
                providerReference: is_string($paymentIntent) ? $paymentIntent : null,
                amountCents: $this->amount($object, 'amount_refunded'),
                currency: $this->currency($object),
            );
        }

        return new PaymentNotice('stripe', $eventId, 'ignored');
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function orderNo(array $object): ?string
    {
        $metadata = $object['metadata'] ?? null;
        $orderNo = is_array($metadata) ? ($metadata['order_no'] ?? null) : null;

        return is_string($orderNo) && $orderNo !== '' ? $orderNo : null;
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function amount(array $object, string $field): int
    {
        $value = $object[$field] ?? null;

        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            throw new PaymentSignatureException('Stripe event is missing an amount.');
        }

        return (int) $value;
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function currency(array $object): string
    {
        $currency = $object['currency'] ?? null;

        if (! is_string($currency) || $currency === '') {
            throw new PaymentSignatureException('Stripe event is missing a currency.');
        }

        return $currency;
    }
}
