<?php

namespace Rediphp;

use Redis;

/**
 * Redisson-compatible distributed CountDownLatch implementation
 * Uses Redis for distributed countdown latch, compatible with Redisson's RCountDownLatch
 */
class RCountDownLatch
{
    private $redis;
    private string $name;

    public function __construct($redis, string $name)
    {
        $this->redis = $redis;
        $this->name = $name;
    }

    /**
     * Try to set the count if not already set
     *
     * @param int $count
     * @return bool True if count was set
     */
    public function trySetCount(int $count): bool
    {
        $redis = $this->redis->getRedis();
        $result = $redis->set($this->name, $count, ['NX']);
        $this->redis->returnRedis($redis);
        return $result !== false;
    }

    /**
     * Count down the latch
     */
    public function countDown(): void
    {
        $script = <<<LUA
local value = redis.call('get', KEYS[1])
if value ~= false then
    local current = tonumber(value)
    if current > 0 then
        redis.call('decr', KEYS[1])
    end
end
LUA;

        $redis = $this->redis->getRedis();
        $redis->eval($script, [$this->name], 1);
        $this->redis->returnRedis($redis);
    }

    /**
     * Get the current count
     *
     * @return int
     */
    public function getCount(): int
    {
        $redis = $this->redis->getRedis();
        $value = $redis->get($this->name);
        $this->redis->returnRedis($redis);
        return $value !== false ? max(0, (int)$value) : 0;
    }

    /**
     * Wait until count reaches zero
     *
     * @param int $timeout Timeout in milliseconds (0 for infinite)
     * @return bool True if count reached zero, false if timeout
     */
    public function await(int $timeout = 0): bool
    {
        $waitUntil = $timeout > 0 ? microtime(true) + ($timeout / 1000) : PHP_INT_MAX;

        while (microtime(true) < $waitUntil) {
            if ($this->getCount() <= 0) {
                return true;
            }
            usleep(100000); // Sleep for 100ms
        }

        return $this->getCount() <= 0;
    }

    /**
     * Delete the latch
     *
     * @return bool
     */
    public function delete(): bool
    {
        $redis = $this->redis->getRedis();
        $result = $redis->del($this->name) > 0;
        $this->redis->returnRedis($redis);
        return $result;
    }
}
