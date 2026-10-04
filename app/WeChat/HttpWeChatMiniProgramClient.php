<?php

namespace App\WeChat;

use App\Contracts\WeChatMiniProgramClient;
use App\Exceptions\WeChatAuthException;
use Illuminate\Support\Facades\Http;

class HttpWeChatMiniProgramClient implements WeChatMiniProgramClient
{
    public function codeToSession(string $code): WeChatSession
    {
        $appId = config('wechat.mini_program.app_id');
        $secret = config('wechat.mini_program.secret');

        if (! is_string($appId) || $appId === '' || ! is_string($secret) || $secret === '') {
            throw new WeChatAuthException('WeChat mini program credentials are not configured.');
        }

        $response = Http::baseUrl((string) config('wechat.http.base_url'))
            ->timeout((int) config('wechat.http.timeout', 5))
            ->acceptJson()
            ->get('/sns/jscode2session', [
                'appid' => $appId,
                'secret' => $secret,
                'js_code' => $code,
                'grant_type' => 'authorization_code',
            ]);

        if ($response->failed()) {
            throw new WeChatAuthException('WeChat login request failed.');
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];
        $errcode = $payload['errcode'] ?? 0;

        if ($errcode !== 0 && $errcode !== '0') {
            $message = $payload['errmsg'] ?? null;

            throw new WeChatAuthException(is_string($message) && $message !== ''
                ? $message
                : 'WeChat login failed.');
        }

        $openid = $payload['openid'] ?? null;
        $sessionKey = $payload['session_key'] ?? null;

        if (! is_string($openid) || $openid === '' || ! is_string($sessionKey) || $sessionKey === '') {
            throw new WeChatAuthException('WeChat login response was incomplete.');
        }

        $unionid = $payload['unionid'] ?? null;

        return new WeChatSession(
            openid: $openid,
            unionid: is_string($unionid) && $unionid !== '' ? $unionid : null,
            sessionKey: $sessionKey,
        );
    }
}
