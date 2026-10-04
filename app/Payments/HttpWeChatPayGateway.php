<?php

namespace App\Payments;

use App\Contracts\PaymentGateway;
use App\Exceptions\CommerceException;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class HttpWeChatPayGateway implements PaymentGateway
{
    use AssertsWeChatCharge;

    public function __construct(
        private WeChatPaySigner $signer,
        private WeChatPayCredentials $credentials,
    ) {}

    public function channel(): string
    {
        return 'wechat';
    }

    public function initiate(Order $order): PaymentIntent
    {
        $openid = $this->assertWeChatOrder($order);
        $path = '/v3/pay/transactions/jsapi';
        $body = $this->encode([
            'appid' => $this->credentials->appId(),
            'mchid' => $this->credentials->mchId(),
            'description' => $this->description($order),
            'out_trade_no' => $order->order_no,
            'notify_url' => $this->credentials->notifyUrl(),
            'amount' => [
                'total' => $order->total_cents,
                'currency' => 'CNY',
            ],
            'payer' => [
                'openid' => $openid,
            ],
        ]);
        $payload = $this->post($path, $body);
        $prepayId = $payload['prepay_id'] ?? null;

        if (! is_string($prepayId) || $prepayId === '') {
            throw new CommerceException('WeChat Pay did not return a prepay id.', 502);
        }

        $timestamp = (string) time();
        $nonce = Str::random(32);
        $package = 'prepay_id='.$prepayId;
        $paySign = $this->signer->sign(
            $this->signer->clientMessage($this->credentials->appId(), $timestamp, $nonce, $package),
            $this->credentials->privateKey(),
        );

        return new PaymentIntent(
            channel: 'wechat',
            status: 'pending',
            providerReference: $prepayId,
            clientParams: [
                'appId' => $this->credentials->appId(),
                'timeStamp' => $timestamp,
                'nonceStr' => $nonce,
                'package' => $package,
                'signType' => 'RSA',
                'paySign' => $paySign,
            ],
        );
    }

    public function refund(Order $order): PaymentRefund
    {
        $this->assertWeChatOrder($order);
        $reference = 'RF'.Str::ulid();
        $this->post('/v3/refund/domestic/refunds', $this->encode([
            'out_trade_no' => $order->order_no,
            'out_refund_no' => $reference,
            'reason' => 'Order refund',
            'notify_url' => $this->credentials->notifyUrl(),
            'amount' => [
                'refund' => $order->total_cents,
                'total' => $order->total_cents,
                'currency' => 'CNY',
            ],
        ]));

        return new PaymentRefund('wechat', $reference, 'processing');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encode(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new CommerceException('Could not encode the WeChat Pay request.', 500);
        }

        return $json;
    }

    /**
     * @return array<string, mixed>
     */
    private function post(string $path, string $body): array
    {
        $timestamp = (string) time();
        $nonce = Str::random(32);
        $signature = $this->signer->sign(
            $this->signer->requestMessage('POST', $path, $timestamp, $nonce, $body),
            $this->credentials->privateKey(),
        );
        $authorization = sprintf(
            'WECHATPAY2-SHA256-RSA2048 mchid="%s",nonce_str="%s",signature="%s",timestamp="%s",serial_no="%s"',
            $this->credentials->mchId(),
            $nonce,
            $signature,
            $timestamp,
            $this->credentials->merchantSerial(),
        );

        $response = Http::withHeaders([
            'Authorization' => $authorization,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent' => 'FlashMall',
        ])->withBody($body, 'application/json')
            ->timeout(10)
            ->post($this->credentials->baseUrl().$path);

        if (! $response->successful()) {
            throw new CommerceException('WeChat Pay rejected the request.', 502);
        }

        $decoded = $response->json();

        if (! is_array($decoded)) {
            return [];
        }

        $normalized = [];

        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    private function description(Order $order): string
    {
        $order->loadMissing('items');
        $snapshot = $order->items->first()?->snapshot;
        $title = is_array($snapshot) ? ($snapshot['product_title'] ?? null) : null;
        $description = is_string($title) && $title !== '' ? $title : 'FlashMall '.$order->order_no;

        return mb_substr($description, 0, 127);
    }
}
