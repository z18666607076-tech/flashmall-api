<?php

namespace App\Payments;

use App\Exceptions\CommerceException;

final class WeChatPayCredentials
{
    public function appId(): string
    {
        return $this->required('payments.wechat.app_id');
    }

    public function mchId(): string
    {
        return $this->required('payments.wechat.mch_id');
    }

    public function merchantSerial(): string
    {
        return $this->required('payments.wechat.mch_serial');
    }

    public function privateKey(): string
    {
        return $this->material($this->required('payments.wechat.private_key'));
    }

    public function apiV3Key(): string
    {
        $key = $this->required('payments.wechat.api_v3_key');

        if (strlen($key) !== 32) {
            throw new CommerceException('WeChat Pay APIv3 key must be 32 bytes.', 501);
        }

        return $key;
    }

    public function platformPublicKey(): string
    {
        return $this->material($this->required('payments.wechat.platform_public_key'));
    }

    public function platformSerial(): string
    {
        return $this->required('payments.wechat.platform_serial');
    }

    public function notifyUrl(): string
    {
        $configured = config('payments.wechat.notify_url');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return rtrim((string) config('app.url'), '/').'/api/v1/payments/wechat/notify';
    }

    public function baseUrl(): string
    {
        return rtrim((string) config('payments.wechat.base_url'), '/');
    }

    private function required(string $key): string
    {
        $value = config($key);

        if (! is_string($value) || $value === '') {
            throw new CommerceException('WeChat Pay credentials are not configured.', 501);
        }

        return $value;
    }

    private function material(string $value): string
    {
        if (str_contains($value, 'BEGIN')) {
            return str_replace('\\n', "\n", $value);
        }

        if (is_file($value)) {
            $contents = file_get_contents($value);

            if ($contents === false || $contents === '') {
                throw new CommerceException('WeChat Pay credentials are not configured.', 501);
            }

            return $contents;
        }

        throw new CommerceException('WeChat Pay credentials are not configured.', 501);
    }
}
