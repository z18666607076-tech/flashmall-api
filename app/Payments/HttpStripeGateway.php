<?php

namespace App\Payments;

use App\Contracts\PaymentGateway;
use App\Exceptions\CommerceException;
use App\Models\Order;
use Illuminate\Support\Facades\Http;

class HttpStripeGateway implements PaymentGateway
{
    public function channel(): string
    {
        return 'stripe';
    }

    public function initiate(Order $order): PaymentIntent
    {
        $payload = $this->post('/v1/payment_intents', [
            'amount' => $order->total_cents,
            'currency' => strtolower($order->currency),
            'metadata' => [
                'order_no' => $order->order_no,
            ],
            'automatic_payment_methods' => [
                'enabled' => 'true',
            ],
        ], 'pay_'.$order->order_no);

        $id = $payload['id'] ?? null;
        $secret = $payload['client_secret'] ?? null;
        $status = $payload['status'] ?? null;

        if (! is_string($id) || ! is_string($secret) || ! is_string($status)) {
            throw new CommerceException('Stripe did not return a payment intent.', 502);
        }

        return new PaymentIntent(
            channel: 'stripe',
            status: $status,
            clientSecret: $secret,
            providerReference: $id,
        );
    }

    public function refund(Order $order): PaymentRefund
    {
        if (! is_string($order->provider_reference) || ! str_starts_with($order->provider_reference, 'pi_')) {
            throw new CommerceException('This order has no Stripe payment intent to refund.', 422);
        }

        $payload = $this->post('/v1/refunds', [
            'payment_intent' => $order->provider_reference,
            'amount' => $order->total_cents,
            'metadata' => [
                'order_no' => $order->order_no,
            ],
        ], 'refund_'.$order->order_no);

        $id = $payload['id'] ?? null;
        $status = $payload['status'] ?? null;

        if (! is_string($id) || ! is_string($status)) {
            throw new CommerceException('Stripe did not return a refund.', 502);
        }

        return new PaymentRefund('stripe', $id, $status);
    }

    /**
     * @param  array<string, mixed>  $form
     * @return array<string, mixed>
     */
    private function post(string $path, array $form, string $idempotencyKey): array
    {
        $secret = config('payments.stripe.secret');

        if (! is_string($secret) || $secret === '') {
            throw new CommerceException('Stripe credentials are not configured.', 501);
        }

        $response = Http::withToken($secret)
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->asForm()
            ->timeout(10)
            ->post(rtrim((string) config('payments.stripe.base_url'), '/').$path, $form);

        if (! $response->successful()) {
            throw new CommerceException('Stripe rejected the request.', 502);
        }

        $decoded = $response->json();

        if (! is_array($decoded)) {
            return [];
        }

        $normalized = [];

        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
