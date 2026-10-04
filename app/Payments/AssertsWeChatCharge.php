<?php

namespace App\Payments;

use App\Exceptions\CommerceException;
use App\Models\Order;

trait AssertsWeChatCharge
{
    private function assertWeChatOrder(Order $order): string
    {
        if (strtoupper($order->currency) !== 'CNY') {
            throw new CommerceException('WeChat Pay only charges CNY orders.', 422);
        }

        $order->loadMissing('user');
        $openid = $order->user?->wechat_openid;

        if (! is_string($openid) || $openid === '') {
            throw new CommerceException('This user has no WeChat openid to pay with.', 422);
        }

        return $openid;
    }
}
