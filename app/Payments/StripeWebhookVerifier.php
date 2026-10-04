<?php

namespace App\Payments;

use App\Exceptions\CommerceException;
use App\Exceptions\PaymentSignatureException;

final class StripeWebhookVerifier
{
    /**
     * @return array<string, mixed>
     */
    public function parse(string $payload, string $header, string $secret, ?int $now = null): array
    {
        if ($secret === '') {
            throw new CommerceException('Stripe webhook secret is not configured.', 501);
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            $pieces = explode('=', trim($part), 2);

            if (count($pieces) !== 2) {
                continue;
            }

            if ($pieces[0] === 't') {
                $timestamp = $pieces[1];
            }

            if ($pieces[0] === 'v1') {
                $signatures[] = $pieces[1];
            }
        }

        if ($timestamp === null || $signatures === [] || ! ctype_digit($timestamp)) {
            throw new PaymentSignatureException('Stripe webhook signature header is invalid.');
        }

        $now ??= time();
        $tolerance = (int) config('payments.webhook_tolerance_seconds');

        if (abs($now - (int) $timestamp) > $tolerance) {
            throw new PaymentSignatureException('Stripe webhook timestamp is outside the tolerance window.');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        $valid = false;

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                $valid = true;
                break;
            }
        }

        if (! $valid) {
            throw new PaymentSignatureException('Stripe webhook signature does not match.');
        }

        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            throw new PaymentSignatureException('Stripe webhook payload is not JSON.');
        }

        return $decoded;
    }
}
