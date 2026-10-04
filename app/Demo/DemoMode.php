<?php

namespace App\Demo;

final class DemoMode
{
    public static function enabled(): bool
    {
        return (bool) config('demo.enabled');
    }

    /**
     * A public demo must not call WeChat or a live payment API, even when the
     * HTTP driver variables are filled in by mistake.
     */
    public static function apply(): void
    {
        if (! self::enabled()) {
            return;
        }

        config([
            'wechat.driver' => 'fake',
            'payments.driver' => 'fake',
        ]);
    }
}
