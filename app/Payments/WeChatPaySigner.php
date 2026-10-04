<?php

namespace App\Payments;

use App\Exceptions\CommerceException;

final class WeChatPaySigner
{
    public function sign(string $message, string $privateKeyPem): string
    {
        $key = openssl_pkey_get_private($privateKeyPem);

        if ($key === false) {
            throw new CommerceException('WeChat Pay private key is invalid.', 501);
        }

        $signature = '';

        if (! openssl_sign($message, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new CommerceException('Could not sign the WeChat Pay request.', 501);
        }

        return base64_encode($signature);
    }

    public function verify(string $message, string $signatureBase64, string $publicKeyPem): bool
    {
        $key = openssl_pkey_get_public($publicKeyPem);
        $raw = base64_decode($signatureBase64, true);

        if ($key === false || $raw === false) {
            return false;
        }

        return openssl_verify($message, $raw, $key, OPENSSL_ALGO_SHA256) === 1;
    }

    public function requestMessage(string $method, string $path, string $timestamp, string $nonce, string $body): string
    {
        return $method."\n".$path."\n".$timestamp."\n".$nonce."\n".$body."\n";
    }

    public function clientMessage(string $appId, string $timestamp, string $nonce, string $package): string
    {
        return $appId."\n".$timestamp."\n".$nonce."\n".$package."\n";
    }

    public function notificationMessage(string $timestamp, string $nonce, string $body): string
    {
        return $timestamp."\n".$nonce."\n".$body."\n";
    }
}
