<?php

namespace App\FlashSales;

use App\Enums\ReserveOutcome;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;

class FlashSaleInventory
{
    private const RESERVE_SCRIPT = <<<'LUA'
local stock = redis.call('GET', KEYS[1])
if not stock then
    return -1
end
if redis.call('HEXISTS', KEYS[2], ARGV[1]) == 1 then
    return -2
end
local qty = tonumber(ARGV[2])
local limit = tonumber(ARGV[3])
if qty > limit then
    return -4
end
if tonumber(stock) < qty then
    return -3
end
redis.call('DECRBY', KEYS[1], qty)
redis.call('HSET', KEYS[2], ARGV[1], qty)
return tonumber(stock) - qty
LUA;

    private const RELEASE_SCRIPT = <<<'LUA'
local bought = redis.call('HGET', KEYS[2], ARGV[1])
if not bought then
    return 0
end
redis.call('HDEL', KEYS[2], ARGV[1])
redis.call('INCRBY', KEYS[1], tonumber(bought))
return tonumber(bought)
LUA;

    private const TAKE_REMAINING_SCRIPT = <<<'LUA'
local stock = redis.call('GET', KEYS[1])
if not stock then
    return 0
end
redis.call('SET', KEYS[1], 0)
return tonumber(stock)
LUA;

    public function forget(int $saleId): void
    {
        $this->redis()->del($this->stockKey($saleId), $this->buyersKey($saleId));
    }

    public function seed(int $saleId, int $stock, int $ttlSeconds): void
    {
        $redis = $this->redis();
        $redis->set($this->stockKey($saleId), $stock);
        $redis->del($this->buyersKey($saleId));

        if ($ttlSeconds > 0) {
            $redis->expire($this->stockKey($saleId), $ttlSeconds);
            $redis->expire($this->buyersKey($saleId), $ttlSeconds);
        }
    }

    public function tryReserve(int $saleId, int $userId, int $quantity, int $perUserLimit): ReserveOutcome
    {
        $code = $this->evaluate(self::RESERVE_SCRIPT, [
            $this->stockKey($saleId),
            $this->buyersKey($saleId),
        ], [
            (string) $userId,
            (string) $quantity,
            (string) $perUserLimit,
        ]);

        return match ($code) {
            -1 => ReserveOutcome::NotSeeded,
            -2 => ReserveOutcome::DuplicateUser,
            -3 => ReserveOutcome::InsufficientStock,
            -4 => ReserveOutcome::OverLimit,
            default => $code < 0 ? ReserveOutcome::NotSeeded : ReserveOutcome::Reserved,
        };
    }

    public function release(int $saleId, int $userId): int
    {
        return $this->evaluate(self::RELEASE_SCRIPT, [
            $this->stockKey($saleId),
            $this->buyersKey($saleId),
        ], [
            (string) $userId,
        ]);
    }

    public function takeRemaining(int $saleId): int
    {
        return $this->evaluate(self::TAKE_REMAINING_SCRIPT, [
            $this->stockKey($saleId),
        ], []);
    }

    public function remaining(int $saleId): ?int
    {
        $value = $this->redis()->get($this->stockKey($saleId));

        return $value === null || $value === false ? null : (int) $value;
    }

    public function buyerCount(int $saleId): int
    {
        return (int) $this->redis()->hlen($this->buyersKey($saleId));
    }

    public function stockKey(int $saleId): string
    {
        return config('flash_sales.key_prefix').":{$saleId}:stock";
    }

    public function buyersKey(int $saleId): string
    {
        return config('flash_sales.key_prefix').":{$saleId}:buyers";
    }

    private function redis(): Connection
    {
        return Redis::connection();
    }

    /**
     * @param  list<string>  $keys
     * @param  list<string>  $arguments
     */
    private function evaluate(string $script, array $keys, array $arguments): int
    {
        return (int) $this->redis()->command('eval', [
            $script,
            [...$keys, ...$arguments],
            count($keys),
        ]);
    }
}
