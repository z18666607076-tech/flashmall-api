<?php

use App\Exceptions\PaymentSignatureException;
use App\Payments\StripeWebhookVerifier;
use App\Payments\WeChatPayCipher;
use App\Payments\WeChatPaySigner;
use Tests\Support\RsaKeyPair;
use Tests\TestCase;

uses(TestCase::class);

it('signs wechat pay requests and mini program params with rsa', function () {
    $keys = RsaKeyPair::generate();
    $signer = new WeChatPaySigner;
    $message = $signer->requestMessage('POST', '/v3/pay/transactions/jsapi', '1700000000', 'nonce', '{"amount":1}');
    $signature = $signer->sign($message, $keys->privatePem);

    expect($signer->verify($message, $signature, $keys->publicPem))->toBeTrue()
        ->and($signer->verify($message.'tampered', $signature, $keys->publicPem))->toBeFalse();

    $client = $signer->clientMessage('wx123', '1700000000', 'nonce', 'prepay_id=wx999');
    $paySign = $signer->sign($client, $keys->privatePem);

    expect($signer->verify($client, $paySign, $keys->publicPem))->toBeTrue();
});

it('round trips a wechat pay notification with aes-gcm', function () {
    $cipher = new WeChatPayCipher;
    $key = '0123456789abcdef0123456789abcdef';
    $plain = '{"trade_state":"SUCCESS"}';
    $encoded = $cipher->encrypt($key, 'nonce-value', 'transaction', $plain);

    expect($cipher->decrypt($key, 'nonce-value', 'transaction', $encoded))->toBe($plain);
});

it('verifies a stripe webhook signature and rejects a bad one', function () {
    $secret = 'whsec_test_secret';
    $payload = '{"id":"evt_1","type":"payment_intent.succeeded"}';
    $timestamp = time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
    $header = 't='.$timestamp.',v1='.$signature;
    $verifier = new StripeWebhookVerifier;

    expect($verifier->parse($payload, $header, $secret)['id'])->toBe('evt_1');

    expect(fn () => $verifier->parse($payload, 't='.$timestamp.',v1=deadbeef', $secret))
        ->toThrow(PaymentSignatureException::class);

    $stale = $timestamp - 301;
    $staleSignature = hash_hmac('sha256', $stale.'.'.$payload, $secret);

    expect(fn () => $verifier->parse($payload, 't='.$stale.',v1='.$staleSignature, $secret))
        ->toThrow(PaymentSignatureException::class, 'tolerance');
});
