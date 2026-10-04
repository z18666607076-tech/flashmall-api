<?php

namespace App\Demo;

use App\Models\User;

final class DemoUser
{
    public static function openid(): string
    {
        return 'o_'.substr(hash('sha256', self::code()), 0, 28);
    }

    public static function unionid(): string
    {
        return 'u_'.substr(hash('sha256', 'union:'.self::code()), 0, 28);
    }

    public static function ensure(): User
    {
        return User::query()->updateOrCreate(
            ['wechat_openid' => self::openid()],
            [
                'name' => 'Demo Shopper',
                'wechat_unionid' => self::unionid(),
                'is_admin' => false,
            ],
        );
    }

    private static function code(): string
    {
        $code = config('demo.login_code');

        return is_string($code) && $code !== '' ? $code : 'demo';
    }
}
