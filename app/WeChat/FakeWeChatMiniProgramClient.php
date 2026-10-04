<?php

namespace App\WeChat;

use App\Contracts\WeChatMiniProgramClient;
use App\Exceptions\WeChatAuthException;

class FakeWeChatMiniProgramClient implements WeChatMiniProgramClient
{
    public function codeToSession(string $code): WeChatSession
    {
        if ($code === '' || str_starts_with($code, 'invalid')) {
            throw new WeChatAuthException('Invalid WeChat login code.');
        }

        return new WeChatSession(
            openid: 'o_'.substr(hash('sha256', $code), 0, 28),
            unionid: 'u_'.substr(hash('sha256', 'union:'.$code), 0, 28),
            sessionKey: 'fake-session-key',
        );
    }
}
