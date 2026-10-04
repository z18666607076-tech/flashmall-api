<?php

namespace App\Payments;

use App\Exceptions\CommerceException;
use App\Exceptions\PaymentSignatureException;

final class WeChatPayNotification
{
    public function __construct(
        private WeChatPaySigner $signer,
        private WeChatPayCipher $cipher,
        private WeChatPayCredentials $credentials,
    ) {}

    public function open(string $body, string $timestamp, string $nonce, string $signature, string $serial): PaymentNotice
    {
        if ($timestamp === '' || $nonce === '' || $signature === '' || $serial === '') {
            throw new PaymentSignatureException('WeChat Pay notification is missing signature headers.');
        }

        if (! ctype_digit($timestamp)) {
            throw new PaymentSignatureException('WeChat Pay notification timestamp is invalid.');
        }

        $tolerance = (int) config('payments.webhook_tolerance_seconds');

        if (abs(time() - (int) $timestamp) > $tolerance) {
            throw new PaymentSignatureException('WeChat Pay notification timestamp is outside the tolerance window.');
        }

        if (! hash_equals($this->credentials->platformSerial(), $serial)) {
            throw new PaymentSignatureException('WeChat Pay platform certificate serial does not match.');
        }

        $message = $this->signer->notificationMessage($timestamp, $nonce, $body);

        if (! $this->signer->verify($message, $signature, $this->credentials->platformPublicKey())) {
            throw new PaymentSignatureException('WeChat Pay notification signature is invalid.');
        }

        $envelope = json_decode($body, true);

        if (! is_array($envelope)) {
            throw new PaymentSignatureException('WeChat Pay notification is not JSON.');
        }

        $eventId = $envelope['id'] ?? null;
        $eventType = $envelope['event_type'] ?? null;
        $resource = $envelope['resource'] ?? null;

        if (! is_string($eventId) || $eventId === '' || ! is_string($eventType) || ! is_array($resource)) {
            throw new PaymentSignatureException('WeChat Pay notification is missing an event id.');
        }

        $ciphertext = $resource['ciphertext'] ?? null;
        $resourceNonce = $resource['nonce'] ?? null;
        $associated = $resource['associated_data'] ?? '';

        if (! is_string($ciphertext) || ! is_string($resourceNonce) || ! is_string($associated)) {
            throw new PaymentSignatureException('WeChat Pay notification resource is incomplete.');
        }

        $plain = $this->cipher->decrypt($this->credentials->apiV3Key(), $resourceNonce, $associated, $ciphertext);
        $decoded = json_decode($plain, true);

        if (! is_array($decoded)) {
            throw new PaymentSignatureException('WeChat Pay notification resource is not JSON.');
        }

        return $this->notice($eventId, $eventType, $decoded);
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    private function notice(string $eventId, string $eventType, array $resource): PaymentNotice
    {
        $orderNo = $resource['out_trade_no'] ?? null;
        $orderNo = is_string($orderNo) ? $orderNo : null;

        if ($eventType === 'TRANSACTION.SUCCESS') {
            $state = $resource['trade_state'] ?? null;

            if ($state !== 'SUCCESS') {
                return new PaymentNotice('wechat', $eventId, 'ignored', $orderNo);
            }

            $transactionId = $resource['transaction_id'] ?? null;

            return new PaymentNotice(
                channel: 'wechat',
                eventId: $eventId,
                type: 'payment.succeeded',
                orderNo: $orderNo,
                providerReference: is_string($transactionId) ? $transactionId : null,
                amountCents: $this->amount($resource, 'total'),
                currency: $this->currency($resource),
            );
        }

        if ($eventType === 'REFUND.SUCCESS') {
            $status = $resource['refund_status'] ?? null;

            if ($status !== 'SUCCESS') {
                return new PaymentNotice('wechat', $eventId, 'ignored', $orderNo);
            }

            $refundId = $resource['refund_id'] ?? null;

            return new PaymentNotice(
                channel: 'wechat',
                eventId: $eventId,
                type: 'refund.succeeded',
                orderNo: $orderNo,
                providerReference: is_string($refundId) ? $refundId : null,
                amountCents: $this->amount($resource, 'refund'),
                currency: $this->currency($resource),
            );
        }

        return new PaymentNotice('wechat', $eventId, 'ignored', $orderNo);
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    private function amount(array $resource, string $field): int
    {
        $amount = $resource['amount'] ?? null;
        $value = is_array($amount) ? ($amount[$field] ?? null) : null;

        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            throw new CommerceException('Payment notification is missing an amount.', 422);
        }

        return (int) $value;
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    private function currency(array $resource): string
    {
        $amount = $resource['amount'] ?? null;
        $currency = is_array($amount) ? ($amount['currency'] ?? null) : null;

        if (! is_string($currency) || $currency === '') {
            throw new CommerceException('Payment notification is missing a currency.', 422);
        }

        return $currency;
    }
}
