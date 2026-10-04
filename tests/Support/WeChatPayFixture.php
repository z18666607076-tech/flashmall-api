<?php

namespace Tests\Support;

use App\Payments\WeChatPayCipher;
use App\Payments\WeChatPaySigner;

final class WeChatPayFixture
{
    /**
     * @param  array<string, mixed>  $resource
     * @return array{body: string, headers: array<string, string>}
     */
    public static function notification(
        string $privateKey,
        string $apiV3Key,
        string $serial,
        string $eventId,
        string $eventType,
        array $resource,
        string $associatedData,
    ): array {
        $cipher = new WeChatPayCipher;
        $signer = new WeChatPaySigner;
        $plain = json_encode($resource, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($plain === false) {
            throw new \RuntimeException('Could not encode the WeChat Pay resource.');
        }

        $resourceNonce = 'resource-nonce';
        $body = json_encode([
            'id' => $eventId,
            'create_time' => '2026-10-04T12:00:00+08:00',
            'resource_type' => 'encrypt-resource',
            'event_type' => $eventType,
            'summary' => 'test',
            'resource' => [
                'algorithm' => 'AEAD_AES_256_GCM',
                'ciphertext' => $cipher->encrypt($apiV3Key, $resourceNonce, $associatedData, $plain),
                'associated_data' => $associatedData,
                'nonce' => $resourceNonce,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($body === false) {
            throw new \RuntimeException('Could not encode the WeChat Pay notification.');
        }

        $timestamp = (string) time();
        $nonce = 'header-nonce';

        return [
            'body' => $body,
            'headers' => [
                'Wechatpay-Timestamp' => $timestamp,
                'Wechatpay-Nonce' => $nonce,
                'Wechatpay-Signature' => $signer->sign(
                    $signer->notificationMessage($timestamp, $nonce, $body),
                    $privateKey,
                ),
                'Wechatpay-Serial' => $serial,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ];
    }
}
