<?php

namespace Rediphp;

use Redis;

/**
 * Redisson-compatible distributed CountDownLatch implementation
 * Uses Redis for distributed countdown latch, compatible with Redisson's RCountDownLatch
 */
class RCountDownLatch
{
    private Redis $redis;
    private string $name;

    public function __construct(Redis $redis, string $name)
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
        $result = $this->redis->set($this->name, $count, ['NX']);
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

        $this->redis->eval($script, [$this->name], 1);
    }

    /**
     * Get the current count
     *
     * @return int
     */
    public function getCount(): int
    {
        $value = $this->redis->get($this->name);
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
        return $this->redis->del($this->name) > 0;
    }
}
