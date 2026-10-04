<?php

use App\Payments\FakeStripeGateway;
use Tests\TestCase;

uses(TestCase::class);
use App\Payments\FakeWeChatPayGateway;
use App\Payments\HttpStripeGateway;
use App\Payments\PaymentGateways;

it('resolves fake payment gateways unless the http driver is selected', function () {
    $gateways = app(PaymentGateways::class);

    expect($gateways->get('wechat'))->toBeInstanceOf(FakeWeChatPayGateway::class)
        ->and($gateways->get('stripe'))->toBeInstanceOf(FakeStripeGateway::class)
        ->and($gateways->get('wechat')->channel())->toBe('wechat');

    config(['payments.driver' => 'http']);

    expect($gateways->get('stripe'))->toBeInstanceOf(HttpStripeGateway::class);
});
