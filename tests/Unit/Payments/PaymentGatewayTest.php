<?php

use App\Contracts\PaymentGateway;
use App\Exceptions\CommerceException;
use App\Models\Order;
use App\Payments\UnconfiguredPaymentGateway;
use Tests\TestCase;

uses(TestCase::class);

it('binds a payment gateway that does not charge yet', function () {
    $gateway = app(PaymentGateway::class);

    expect($gateway)->toBeInstanceOf(UnconfiguredPaymentGateway::class)
        ->and($gateway->channel())->toBe('unconfigured');

    expect(fn () => $gateway->initiate(new Order))
        ->toThrow(CommerceException::class, 'No payment gateway is configured');
});
