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

    /**
     * RSemaphore constructor.
     *
     * @param Redis $redis Redis connection
     * @param string $name Semaphore name
     * @param int $permits Initial number of permits (default: 0)
     */
    public function __construct(Redis $redis, string $name, int $permits = 0)
    {
        $this->redis = $redis;
        $this->name = $name;
        
        // 如果指定了初始许可数且信号量不存在，则设置初始许可数
        if ($permits > 0 && !$this->exists()) {
            $this->trySetPermits($permits);
        }
    }

    /**
     * Try to set permits if not already set
     *
     * @param int $permits
     * @return bool True if permits were set
     */
    public function trySetPermits(int $permits): bool
    {
        // 如果信号量已存在，先删除旧值
        if ($this->exists()) {
            $this->clear();
        }
        
        // 设置新的许可数
        $result = $this->redis->set($this->name, $permits);
        // 同时存储总许可数
        $totalPermitsKey = $this->name . ':total';
        $this->redis->set($totalPermitsKey, $permits);
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

    /**
     * Check if semaphore exists
     *
     * @return bool
     */
    public function exists(): bool
    {
        return $this->redis->exists($this->name) > 0;
    }

    /**
     * Get the size (total permits) of the semaphore
     *
     * @return int
     */
    public function size(): int
    {
        // 获取总许可数，使用单独的键来存储总许可数
        $totalPermitsKey = $this->name . ':total';
        $value = $this->redis->get($totalPermitsKey);
        return $value !== false ? (int)$value : 0;
    }

    /**
     * Reduce permits
     *
     * @param int $permits
     * @return void
     */
    public function reducePermits(int $permits): void
    {
        $this->redis->decrBy($this->name, $permits);
        // 同时更新总许可数
        $totalPermitsKey = $this->name . ':total';
        $this->redis->decrBy($totalPermitsKey, $permits);
    }

    /**
     * Clear the semaphore (delete the key)
     *
     * @return void
     */
    public function clear(): void
    {
        $this->redis->del($this->name);
        // 同时清除总许可数
        $totalPermitsKey = $this->name . ':total';
        $this->redis->del($totalPermitsKey);
    }

    /**
     * Try to acquire with timeout (simplified implementation)
     *
     * @param int $permits
     * @param int $timeout
     * @return bool
     */
    public function tryAcquireWithTimeout(int $permits = 1, int $timeout = 0): bool
    {
        if ($timeout > 0) {
            // 简化的超时实现，实际应该使用更复杂的逻辑
            $start = microtime(true);
            while ((microtime(true) - $start) < $timeout) {
                if ($this->tryAcquire($permits)) {
                    return true;
                }
                usleep(10000); // 10ms
            }
            return false;
        }
        
        return $this->tryAcquire($permits);
    }
}
