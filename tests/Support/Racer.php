<?php

namespace Tests\Support;

use Symfony\Component\Process\Process;

class Racer
{
    /**
     * @param  list<string>  $command
     */
    public static function start(array $command): Process
    {
        return new Process($command, base_path(), self::environment());
    }

    /**
     * @return array<string, string>
     */
    public static function environment(): array
    {
        $connection = config('database.connections.mysql');
        $redis = config('database.redis.default');

        return [
            'PATH' => (string) getenv('PATH'),
            'APP_ENV' => 'testing',
            'APP_KEY' => (string) config('app.key'),
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => (string) $connection['host'],
            'DB_PORT' => (string) $connection['port'],
            'DB_DATABASE' => (string) $connection['database'],
            'DB_USERNAME' => (string) $connection['username'],
            'DB_PASSWORD' => (string) $connection['password'],
            'DB_URL' => '',
            'REDIS_CLIENT' => 'phpredis',
            'REDIS_HOST' => (string) $redis['host'],
            'REDIS_PORT' => (string) $redis['port'],
            'CACHE_STORE' => 'redis',
            'QUEUE_CONNECTION' => 'sync',
            'WECHAT_DRIVER' => 'fake',
        ];
    }
}
