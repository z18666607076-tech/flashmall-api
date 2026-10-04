<?php

namespace App\WeChat;

/**
 * Result of a mini program code2session exchange.
 *
 * The session key is kept off the user record. It is only available to the
 * caller that just exchanged the code.
 */
final readonly class WeChatSession
{
    public function __construct(
        public string $openid,
        public ?string $unionid,
        public string $sessionKey,
    ) {}
}
