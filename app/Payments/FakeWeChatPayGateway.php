<?php

namespace App\Payments;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use Illuminate\Support\Str;

class FakeWeChatPayGateway implements PaymentGateway
{
    use AssertsWeChatCharge;

    public function channel(): string
    {
        return 'wechat';
    }

    public function initiate(Order $order): PaymentIntent
    {
        $this->assertWeChatOrder($order);
        $appId = config('payments.wechat.app_id');
        $timestamp = (string) time();
        $package = 'prepay_id=wx_fake_'.$order->order_no;

        return new PaymentIntent(
            channel: 'wechat',
            status: 'pending',
            providerReference: 'wx_fake_'.$order->order_no,
            clientParams: [
                'appId' => is_string($appId) && $appId !== '' ? $appId : 'wx_fake_app',
                'timeStamp' => $timestamp,
                'nonceStr' => 'fake-nonce',
                'package' => $package,
                'signType' => 'RSA',
                'paySign' => 'fake',
            ],
        );
    }

    public function refund(Order $order): PaymentRefund
    {
        return new PaymentRefund('wechat', 'RF'.Str::ulid(), 'succeeded');
    }
}
