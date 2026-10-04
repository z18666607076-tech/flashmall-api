<?php

namespace App\Payments;

use App\Contracts\PaymentGateway;
use App\Demo\DemoMode;
use App\Exceptions\CommerceException;
use InvalidArgumentException;

class PaymentGateways
{
    public function __construct(
        private FakeWeChatPayGateway $fakeWechat,
        private HttpWeChatPayGateway $httpWechat,
        private FakeStripeGateway $fakeStripe,
        private HttpStripeGateway $httpStripe,
    ) {}

    public function get(string $channel): PaymentGateway
    {
        DemoMode::apply();

        $driver = config('payments.driver');

        if ($driver !== 'fake' && $driver !== 'http') {
            throw new InvalidArgumentException('Unknown payment driver ['.$driver.'].');
        }

        return match ($channel) {
            'wechat' => $driver === 'http' ? $this->httpWechat : $this->fakeWechat,
            'stripe' => $driver === 'http' ? $this->httpStripe : $this->fakeStripe,
            default => throw new CommerceException('Unknown payment channel.', 422),
        };
    }
}
