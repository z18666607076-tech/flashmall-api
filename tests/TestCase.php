<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // Compose exports QUEUE_CONNECTION=redis into $_SERVER. PHPUnit's
        // force="true" updates putenv and $_ENV, but Laravel reads $_SERVER
        // first, so the sync driver would never win inside the app container.
        foreach ([
            'APP_ENV' => 'testing',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
            'CACHE_STORE' => 'redis',
            'DB_CONNECTION' => 'mysql',
            'WECHAT_DRIVER' => 'fake',
            'PAYMENTS_DRIVER' => 'fake',
            'DB_URL' => '',
        ] as $name => $value) {
            putenv($name.'='.$value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }

        parent::setUp();
    }
}
