<?php

namespace App\Contracts;

use App\Exceptions\WeChatAuthException;
use App\WeChat\WeChatSession;

interface WeChatMiniProgramClient
{
    /**
     * Exchange a wx.login code for the mini program session.
     *
     * @throws WeChatAuthException
     */
    public function codeToSession(string $code): WeChatSession;
}
