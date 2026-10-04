<?php

use App\Models\Order;
use App\Models\PaymentEvent;
use App\Models\Sku;
use App\Models\User;
use App\Orders\TransitionOrder;
use App\Payments\WeChatPaySigner;
use Illuminate\Support\Facades\Http;
use Tests\Support\RsaKeyPair;
use Tests\Support\WeChatPayFixture;

function paymentKeys(): RsaKeyPair
{
    $keys = RsaKeyPair::generate();

    config([
        'payments.wechat.app_id' => 'wx_test_app',
        'payments.wechat.mch_id' => '1900000109',
        'payments.wechat.mch_serial' => 'MERCHANTSERIAL',
        'payments.wechat.private_key' => $keys->privatePem,
        'payments.wechat.api_v3_key' => '0123456789abcdef0123456789abcdef',
        'payments.wechat.platform_public_key' => $keys->publicPem,
        'payments.wechat.platform_serial' => 'PUB_KEY_ID_TEST',
        'payments.stripe.secret' => 'sk_test_secret',
        'payments.stripe.webhook_secret' => 'whsec_test_secret',
    ]);

    return $keys;
}

function stripeSignature(string $payload, ?int $timestamp = null): string
{
    $timestamp ??= time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test_secret');

    return 't='.$timestamp.',v1='.$signature;
}

/**
 * @param  array<string, mixed>  $resource
 * @return array{body: string, headers: array<string, string>}
 */
function wechatNotification(string $privateKey, string $eventId, string $eventType, array $resource, string $associated): array
{
    return WeChatPayFixture::notification(
        $privateKey,
        '0123456789abcdef0123456789abcdef',
        'PUB_KEY_ID_TEST',
        $eventId,
        $eventType,
        $resource,
        $associated,
    );
}

function checkoutPending(User $user, int $stock = 4, int $price = 2500): array
{
    $sku = Sku::factory()->create(['stock' => $stock, 'price_cents' => $price, 'currency' => 'CNY']);

    test()->actingAs($user, 'sanctum')
        ->postJson('/api/v1/cart/items', ['sku_id' => $sku->id, 'quantity' => 1])
        ->assertOk();

    $orderId = (int) test()->actingAs($user, 'sanctum')
        ->postJson('/api/v1/orders')
        ->assertCreated()
        ->json('data.id');

    return [$sku, Order::query()->findOrFail($orderId)];
}

it('returns mini program pay params and marks the order paid from a signed notification', function () {
    $keys = paymentKeys();
    $buyer = User::factory()->wechat()->create();
    [$sku, $order] = checkoutPending($buyer);

    $this->actingAs($buyer, 'sanctum')
        ->postJson("/api/v1/orders/{$order->id}/pay", ['channel' => 'wechat'])
        ->assertOk()
        ->assertJsonPath('data.channel', 'wechat')
        ->assertJsonPath('data.wechat.signType', 'RSA')
        ->assertJsonPath('data.wechat.paySign', 'fake')
        ->assertJsonPath('data.wechat.package', 'prepay_id=wx_fake_'.$order->order_no);

    expect($order->refresh()->status->value)->toBe('pending_payment')
        ->and($sku->refresh()->stock)->toBe(3);

    $notice = wechatNotification($keys->privatePem, 'EV-PAY-1', 'TRANSACTION.SUCCESS', [
        'out_trade_no' => $order->order_no,
        'transaction_id' => '4200000001',
        'trade_state' => 'SUCCESS',
        'amount' => ['total' => 2500, 'currency' => 'CNY'],
    ], 'transaction');

    $this->call('POST', '/api/v1/payments/wechat/notify', [], [], [], headers($notice['headers']), $notice['body'])
        ->assertOk()
        ->assertJsonPath('code', 'SUCCESS');

    expect($order->refresh()->status->value)->toBe('paid')
        ->and($order->payment_channel)->toBe('wechat')
        ->and($order->provider_reference)->toBe('4200000001')
        ->and($sku->refresh()->stock)->toBe(3)
        ->and(PaymentEvent::query()->count())->toBe(1);

    $this->call('POST', '/api/v1/payments/wechat/notify', [], [], [], headers($notice['headers']), $notice['body'])
        ->assertOk();

    expect(PaymentEvent::query()->count())->toBe(1)
        ->and($order->refresh()->paid_at)->not->toBeNull();
});

it('rejects a wechat notification whose amount does not match the order', function () {
    $keys = paymentKeys();
    $buyer = User::factory()->wechat()->create();
    [, $order] = checkoutPending($buyer);

    $notice = wechatNotification($keys->privatePem, 'EV-BAD-AMOUNT', 'TRANSACTION.SUCCESS', [
        'out_trade_no' => $order->order_no,
        'transaction_id' => '4200000002',
        'trade_state' => 'SUCCESS',
        'amount' => ['total' => 1, 'currency' => 'CNY'],
    ], 'transaction');

    $this->call('POST', '/api/v1/payments/wechat/notify', [], [], [], headers($notice['headers']), $notice['body'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Payment amount does not match the order.');

    expect($order->refresh()->status->value)->toBe('pending_payment')
        ->and(PaymentEvent::query()->count())->toBe(0);
});

it('rejects a wechat notification with a bad signature', function () {
    paymentKeys();
    $buyer = User::factory()->wechat()->create();
    [, $order] = checkoutPending($buyer);
    $other = RsaKeyPair::generate();
    $notice = wechatNotification($other->privatePem, 'EV-BAD-SIGN', 'TRANSACTION.SUCCESS', [
        'out_trade_no' => $order->order_no,
        'transaction_id' => '4200000003',
        'trade_state' => 'SUCCESS',
        'amount' => ['total' => 2500, 'currency' => 'CNY'],
    ], 'transaction');

    $this->call('POST', '/api/v1/payments/wechat/notify', [], [], [], headers($notice['headers']), $notice['body'])
        ->assertUnauthorized();

    expect($order->refresh()->status->value)->toBe('pending_payment');
});

it('refunds a paid wechat order from the admin action and from a replayed notification', function () {
    $keys = paymentKeys();
    $buyer = User::factory()->wechat()->create();
    $admin = User::factory()->admin()->create();
    [$sku, $order] = checkoutPending($buyer, stock: 5);

    $paid = wechatNotification($keys->privatePem, 'EV-PAY-2', 'TRANSACTION.SUCCESS', [
        'out_trade_no' => $order->order_no,
        'transaction_id' => '4200000004',
        'trade_state' => 'SUCCESS',
        'amount' => ['total' => 2500, 'currency' => 'CNY'],
    ], 'transaction');

    $this->call('POST', '/api/v1/payments/wechat/notify', [], [], [], headers($paid['headers']), $paid['body'])
        ->assertOk();

    $this->actingAs($buyer, 'sanctum')
        ->postJson("/api/v1/orders/{$order->id}/refund")
        ->assertForbidden();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/orders/{$order->id}/refund")
        ->assertOk()
        ->assertJsonPath('data.status', 'refunded');

    expect($sku->refresh()->stock)->toBe(5);

    $refund = wechatNotification($keys->privatePem, 'EV-REFUND-2', 'REFUND.SUCCESS', [
        'out_trade_no' => $order->order_no,
        'refund_id' => '5000000001',
        'refund_status' => 'SUCCESS',
        'amount' => ['refund' => 2500, 'total' => 2500, 'currency' => 'CNY'],
    ], 'refund');

    $this->call('POST', '/api/v1/payments/wechat/notify', [], [], [], headers($refund['headers']), $refund['body'])
        ->assertOk();

    expect($sku->refresh()->stock)->toBe(5)
        ->and($order->refresh()->status->value)->toBe('refunded');
});

it('does not mark an order paid when the refund notification arrives first', function () {
    $keys = paymentKeys();
    $buyer = User::factory()->wechat()->create();
    [$sku, $order] = checkoutPending($buyer);

    $refund = wechatNotification($keys->privatePem, 'EV-REFUND-FIRST', 'REFUND.SUCCESS', [
        'out_trade_no' => $order->order_no,
        'refund_id' => '5000000002',
        'refund_status' => 'SUCCESS',
        'amount' => ['refund' => 2500, 'total' => 2500, 'currency' => 'CNY'],
    ], 'refund');

    $this->call('POST', '/api/v1/payments/wechat/notify', [], [], [], headers($refund['headers']), $refund['body'])
        ->assertOk();

    expect($order->refresh()->status->value)->toBe('cancelled')
        ->and($sku->refresh()->stock)->toBe(4);

    $paid = wechatNotification($keys->privatePem, 'EV-PAY-LATE', 'TRANSACTION.SUCCESS', [
        'out_trade_no' => $order->order_no,
        'transaction_id' => '4200000005',
        'trade_state' => 'SUCCESS',
        'amount' => ['total' => 2500, 'currency' => 'CNY'],
    ], 'transaction');

    $this->call('POST', '/api/v1/payments/wechat/notify', [], [], [], headers($paid['headers']), $paid['body'])
        ->assertOk();

    expect($order->refresh()->status->value)->toBe('cancelled')
        ->and($sku->refresh()->stock)->toBe(4);
});

it('marks a stripe payment intent paid and refunds it without charging twice', function () {
    paymentKeys();
    $buyer = User::factory()->create();
    $admin = User::factory()->admin()->create();
    [$sku, $order] = checkoutPending($buyer);

    $this->actingAs($buyer, 'sanctum')
        ->postJson("/api/v1/orders/{$order->id}/pay", ['channel' => 'stripe'])
        ->assertOk()
        ->assertJsonPath('data.channel', 'stripe')
        ->assertJsonPath('data.client_secret', 'pi_fake_'.$order->order_no.'_secret_test');

    $paid = json_encode([
        'id' => 'evt_pay_1',
        'type' => 'payment_intent.succeeded',
        'data' => [
            'object' => [
                'id' => 'pi_fake_'.$order->order_no,
                'amount' => 2500,
                'currency' => 'cny',
                'metadata' => ['order_no' => $order->order_no],
            ],
        ],
    ], JSON_UNESCAPED_SLASHES);

    expect($paid)->toBeString();

    $this->call('POST', '/api/v1/payments/stripe/webhook', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => stripeSignature($paid),
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], $paid)
        ->assertOk()
        ->assertJsonPath('received', true);

    expect($order->refresh()->status->value)->toBe('paid')
        ->and($order->payment_channel)->toBe('stripe');

    $this->call('POST', '/api/v1/payments/stripe/webhook', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => 't='.time().',v1=not-a-signature',
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], $paid)->assertStatus(400);

    $mismatch = json_encode([
        'id' => 'evt_pay_mismatch',
        'type' => 'payment_intent.succeeded',
        'data' => [
            'object' => [
                'id' => 'pi_other',
                'amount' => 9,
                'currency' => 'usd',
                'metadata' => ['order_no' => $order->order_no],
            ],
        ],
    ]);

    expect($mismatch)->toBeString();

    $this->call('POST', '/api/v1/payments/stripe/webhook', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => stripeSignature($mismatch),
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], $mismatch)->assertStatus(422);

    expect($order->refresh()->status->value)->toBe('paid');

    $refund = json_encode([
        'id' => 'evt_refund_1',
        'type' => 'refund.updated',
        'data' => [
            'object' => [
                'id' => 're_1',
                'status' => 'succeeded',
                'amount' => 2500,
                'currency' => 'cny',
                'payment_intent' => 'pi_fake_'.$order->order_no,
                'metadata' => ['order_no' => $order->order_no],
            ],
        ],
    ]);

    expect($refund)->toBeString();

    $this->call('POST', '/api/v1/payments/stripe/webhook', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => stripeSignature($refund),
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], $refund)->assertOk();

    expect($order->refresh()->status->value)->toBe('refunded')
        ->and($sku->refresh()->stock)->toBe(4);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/orders/{$order->id}/refund")
        ->assertStatus(409);
});

it('signs a live wechat prepay and a stripe payment intent without calling the networks', function () {
    $keys = paymentKeys();
    config(['payments.driver' => 'http']);
    $buyer = User::factory()->wechat()->create();
    [, $order] = checkoutPending($buyer, price: 1800);
    $signer = new WeChatPaySigner;

    Http::fake([
        'https://api.mch.weixin.qq.com/v3/pay/transactions/jsapi' => Http::response([
            'prepay_id' => 'wx_prepay_test',
        ]),
        'https://api.stripe.com/v1/payment_intents' => Http::response([
            'id' => 'pi_live_test',
            'client_secret' => 'pi_live_test_secret',
            'status' => 'requires_payment_method',
        ]),
        'https://api.stripe.com/v1/refunds' => Http::response([
            'id' => 're_live_test',
            'status' => 'succeeded',
        ]),
        'https://api.mch.weixin.qq.com/v3/refund/domestic/refunds' => Http::response([
            'refund_id' => '5000000099',
            'status' => 'PROCESSING',
        ]),
    ]);

    $wechat = $this->actingAs($buyer, 'sanctum')
        ->postJson("/api/v1/orders/{$order->id}/pay", ['channel' => 'wechat'])
        ->assertOk()
        ->assertJsonPath('data.wechat.package', 'prepay_id=wx_prepay_test');

    Http::assertSent(function ($request) use ($signer, $keys, $order): bool {
        if (! str_ends_with($request->url(), '/v3/pay/transactions/jsapi')) {
            return false;
        }

        $authorization = $request->header('Authorization');
        $header = is_array($authorization) ? ($authorization[0] ?? '') : '';
        preg_match('/timestamp="(\d+)",serial_no="([^"]+)"/', $header, $stamp);
        preg_match('/nonce_str="([^"]+)"/', $header, $nonce);
        preg_match('/signature="([^"]+)"/', $header, $signature);

        return str_starts_with($header, 'WECHATPAY2-SHA256-RSA2048')
            && ($stamp[2] ?? '') === 'MERCHANTSERIAL'
            && $signer->verify(
                $signer->requestMessage('POST', '/v3/pay/transactions/jsapi', $stamp[1] ?? '', $nonce[1] ?? '', $request->body()),
                $signature[1] ?? '',
                $keys->publicPem,
            )
            && str_contains($request->body(), '"total":1800')
            && str_contains($request->body(), $order->order_no);
    });

    $paySign = $wechat->json('data.wechat.paySign');
    $message = $signer->clientMessage(
        'wx_test_app',
        (string) $wechat->json('data.wechat.timeStamp'),
        (string) $wechat->json('data.wechat.nonceStr'),
        'prepay_id=wx_prepay_test',
    );

    expect(is_string($paySign) && $signer->verify($message, $paySign, $keys->publicPem))->toBeTrue();

    $stripeOrder = checkoutPending($buyer, price: 900)[1];

    $this->actingAs($buyer, 'sanctum')
        ->postJson("/api/v1/orders/{$stripeOrder->id}/pay", ['channel' => 'stripe'])
        ->assertOk()
        ->assertJsonPath('data.client_secret', 'pi_live_test_secret');

    Http::assertSent(function ($request) use ($stripeOrder): bool {
        return str_ends_with($request->url(), '/v1/payment_intents')
            && str_contains($request->body(), 'amount=900')
            && str_contains($request->body(), 'currency=cny')
            && str_contains($request->body(), rawurlencode($stripeOrder->order_no));
    });

    $stripeOrder->refresh();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/orders/{$order->id}/refund")
        ->assertStatus(409);

    app(TransitionOrder::class)->markPaid($stripeOrder, 'stripe', 'pi_live_test');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/orders/{$stripeOrder->id}/refund")
        ->assertOk()
        ->assertJsonPath('data.status', 'refunded');

    Http::assertSent(function ($request) use ($stripeOrder): bool {
        return str_ends_with($request->url(), '/v1/refunds')
            && str_contains($request->body(), 'payment_intent=pi_live_test')
            && str_contains($request->body(), 'amount='.$stripeOrder->total_cents);
    });

    app(TransitionOrder::class)->markPaid($order, 'wechat', 'wx_prepay_test');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/orders/{$order->id}/refund")
        ->assertOk()
        ->assertJsonPath('data.status', 'refunded');

    Http::assertSent(function ($request) use ($signer, $keys, $order): bool {
        if (! str_ends_with($request->url(), '/v3/refund/domestic/refunds')) {
            return false;
        }

        $authorization = $request->header('Authorization');
        $header = is_array($authorization) ? ($authorization[0] ?? '') : '';
        preg_match('/timestamp="(\d+)"/', $header, $stamp);
        preg_match('/nonce_str="([^"]+)"/', $header, $nonce);
        preg_match('/signature="([^"]+)"/', $header, $signature);

        return $signer->verify(
            $signer->requestMessage('POST', '/v3/refund/domestic/refunds', $stamp[1] ?? '', $nonce[1] ?? '', $request->body()),
            $signature[1] ?? '',
            $keys->publicPem,
        )
            && str_contains($request->body(), $order->order_no)
            && str_contains($request->body(), '"refund":1800');
    });
});

it('hides horizon from customers and opens it for an admin token', function () {
    $this->get('/horizon')->assertForbidden();

    $customer = User::factory()->create();

    $this->actingAs($customer, 'sanctum')->get('/horizon')->assertForbidden();

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin, 'sanctum')->get('/horizon')->assertOk();
});

/**
 * @param  array<string, string>  $headers
 * @return array<string, string>
 */
function headers(array $headers): array
{
    $server = [];

    foreach ($headers as $name => $value) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
    }

    $server['CONTENT_TYPE'] = 'application/json';
    $server['HTTP_ACCEPT'] = 'application/json';

    return $server;
}
