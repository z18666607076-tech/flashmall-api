<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\CommerceException;
use App\Exceptions\PaymentSignatureException;
use App\Http\Controllers\Controller;
use App\Payments\SettlePaymentNotification;
use App\Payments\StripeNotification;
use App\Payments\StripeWebhookVerifier;
use App\Payments\WeChatPayNotification;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Payments', weight: 6)]
class PaymentWebhookController extends Controller
{
    #[HeaderParameter('Wechatpay-Timestamp', description: 'Unix timestamp from the WeChat Pay signature.', type: 'string', required: true)]
    #[HeaderParameter('Wechatpay-Nonce', description: 'Nonce from the WeChat Pay signature.', type: 'string', required: true)]
    #[HeaderParameter('Wechatpay-Signature', description: 'Base64 RSA-SHA256 signature of the notification.', type: 'string', required: true)]
    #[HeaderParameter('Wechatpay-Serial', description: 'WeChat Pay platform public key or certificate serial.', type: 'string', required: true)]
    public function wechat(Request $request, WeChatPayNotification $notifications, SettlePaymentNotification $settle): JsonResponse
    {
        try {
            $notice = $notifications->open(
                $request->getContent(),
                (string) $request->header('Wechatpay-Timestamp', ''),
                (string) $request->header('Wechatpay-Nonce', ''),
                (string) $request->header('Wechatpay-Signature', ''),
                (string) $request->header('Wechatpay-Serial', ''),
            );
            $settle->settle($notice);
        } catch (PaymentSignatureException $exception) {
            return response()->json(['code' => 'FAIL', 'message' => $exception->getMessage()], 401);
        } catch (CommerceException $exception) {
            return response()->json(['code' => 'FAIL', 'message' => $exception->getMessage()], $exception->status);
        }

        return response()->json(['code' => 'SUCCESS', 'message' => 'OK']);
    }

    #[HeaderParameter('Stripe-Signature', description: 'Stripe signature header: t=timestamp,v1=hmac.', type: 'string', required: true)]
    public function stripe(
        Request $request,
        StripeWebhookVerifier $verifier,
        StripeNotification $notifications,
        SettlePaymentNotification $settle,
    ): JsonResponse {
        $secret = config('payments.stripe.webhook_secret');

        try {
            $event = $verifier->parse(
                $request->getContent(),
                (string) $request->header('Stripe-Signature', ''),
                is_string($secret) ? $secret : '',
            );
            $settle->settle($notifications->notice($event));
        } catch (PaymentSignatureException $exception) {
            return response()->json(['message' => $exception->getMessage()], 400);
        } catch (CommerceException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status);
        }

        return response()->json(['received' => true]);
    }
}
