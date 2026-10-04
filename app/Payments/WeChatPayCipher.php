<?php

namespace App\Payments;

use App\Exceptions\CommerceException;
use App\Exceptions\PaymentSignatureException;

final class WeChatPayCipher
{
    public function encrypt(string $apiV3Key, string $nonce, string $associatedData, string $plaintext): string
    {
        $this->assertKey($apiV3Key);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $apiV3Key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $associatedData,
            16,
        );

        if ($ciphertext === false || strlen($tag) !== 16) {
            throw new CommerceException('Could not encrypt the WeChat Pay payload.', 500);
        }

        return base64_encode($ciphertext.$tag);
    }

    public function decrypt(string $apiV3Key, string $nonce, string $associatedData, string $ciphertext): string
    {
        $this->assertKey($apiV3Key);
        $decoded = base64_decode($ciphertext, true);

        if ($decoded === false || strlen($decoded) <= 16) {
            throw new PaymentSignatureException('Could not decrypt the WeChat Pay notification.');
        }

        $tag = substr($decoded, -16);
        $data = substr($decoded, 0, -16);
        $plain = openssl_decrypt(
            $data,
            'aes-256-gcm',
            $apiV3Key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $associatedData,
        );

        if ($plain === false) {
            throw new PaymentSignatureException('Could not decrypt the WeChat Pay notification.');
        }

        return $plain;
    }

    private function assertKey(string $apiV3Key): void
    {
        if (strlen($apiV3Key) !== 32) {
            throw new CommerceException('WeChat Pay APIv3 key must be 32 bytes.', 501);
        }
    }
}
