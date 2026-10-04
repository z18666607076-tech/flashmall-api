<?php

use App\Exceptions\WeChatAuthException;
use App\WeChat\HttpWeChatMiniProgramClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config([
        'wechat.mini_program.app_id' => 'wx-test-app',
        'wechat.mini_program.secret' => 'test-secret',
        'wechat.http.base_url' => 'https://api.weixin.qq.com',
    ]);
});

it('exchanges a code for an openid', function () {
    Http::fake([
        'https://api.weixin.qq.com/sns/jscode2session*' => Http::response([
            'openid' => 'openid-1',
            'session_key' => 'session-key-1',
            'unionid' => 'union-1',
        ]),
    ]);

    $session = app(HttpWeChatMiniProgramClient::class)->codeToSession('js-code');

    expect($session->openid)->toBe('openid-1')
        ->and($session->unionid)->toBe('union-1')
        ->and($session->sessionKey)->toBe('session-key-1');

    Http::assertSent(function ($request) {
        return $request['appid'] === 'wx-test-app'
            && $request['secret'] === 'test-secret'
            && $request['js_code'] === 'js-code'
            && $request['grant_type'] === 'authorization_code';
    });
});

it('rejects a wechat error payload', function () {
    Http::fake([
        'https://api.weixin.qq.com/sns/jscode2session*' => Http::response([
            'errcode' => 40029,
            'errmsg' => 'invalid code',
        ]),
    ]);

    expect(fn () => app(HttpWeChatMiniProgramClient::class)->codeToSession('bad'))
        ->toThrow(WeChatAuthException::class, 'invalid code');
});

it('rejects calls that have no credentials', function () {
    config([
        'wechat.mini_program.app_id' => '',
        'wechat.mini_program.secret' => '',
    ]);

    expect(fn () => app(HttpWeChatMiniProgramClient::class)->codeToSession('code'))
        ->toThrow(WeChatAuthException::class, 'credentials');
});
