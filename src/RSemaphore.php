<?php

namespace Rediphp;

use Redis;

/**
 * Redisson-compatible distributed Semaphore implementation
 * Uses Redis for distributed semaphore, compatible with Redisson's RSemaphore
 */
class RSemaphore
{
    private Redis $redis;
    private string $name;

    public function __construct(Redis $redis, string $name)
    {
        $this->redis = $redis;
        $this->name = $name;
    }

    /**
     * Try to set permits if not already set
     *
     * @param int $permits
     * @return bool True if permits were set
     */
    public function trySetPermits(int $permits): bool
    {
        $result = $this->redis->set($this->name, $permits, ['NX']);
        return $result !== false;
    }

    /**
     * Acquire a permit
     *
     * @return bool
     */
    public function acquire(): bool
    {
        return $this->tryAcquire(1);
    }

    /**
     * Try to acquire a permit
     *
     * @param int $permits Number of permits to acquire
     * @return bool
     */
    public function tryAcquire(int $permits = 1): bool
    {
        $script = <<<LUA
local value = redis.call('get', KEYS[1])
if value == false then
    return 0
end
local current = tonumber(value)
if current >= tonumber(ARGV[1]) then
    redis.call('decrby', KEYS[1], ARGV[1])
    return 1
else
    return 0
end
LUA;

        $result = $this->redis->eval($script, [$this->name, $permits], 1);
        return $result === 1;
    }

    /**
     * Release a permit
     *
     * @param int $permits Number of permits to release
     */
    public function release(int $permits = 1): void
    {
        $this->redis->incrBy($this->name, $permits);
    }

    /**
     * Get available permits
     *
     * @return int
     */
    public function availablePermits(): int
    {
        $value = $this->redis->get($this->name);
        return $value !== false ? (int)$value : 0;
    }

    /**
     * Acquire all available permits
     *
     * @return int Number of permits acquired
     */
    public function drainPermits(): int
    {
        $script = <<<LUA
local value = redis.call('get', KEYS[1])
if value == false then
    return 0
end
local current = tonumber(value)
redis.call('set', KEYS[1], 0)
return current
LUA;

        $result = $this->redis->eval($script, [$this->name], 1);
        return (int)$result;
    }

    /**
     * Delete the semaphore
     *
     * @return bool
     */
    public function delete(): bool
    {
        return $this->redis->del($this->name) > 0;
    }
}
