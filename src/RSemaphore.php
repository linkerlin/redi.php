<?php

namespace Rediphp;

use Redis;

/**
 * Redisson-compatible distributed Semaphore implementation
 * Uses Redis for distributed semaphore, compatible with Redisson's RSemaphore
 */
class RSemaphore
{
    private $redis;
    private ?RedissonClient $client = null;
    private bool $usingPool = false;
    private string $name;

    /**
     * RSemaphore constructor.
     *
     * @param Redis|RedissonClient $connection Redis connection or RedissonClient
     * @param string $name Semaphore name
     * @param int $permits Initial number of permits (default: 0)
     */
    public function __construct($connection, string $name, int $permits = 0)
    {
        if ($connection instanceof RedissonClient) {
            $this->client = $connection;
            $this->usingPool = true;
        } elseif ($connection instanceof Redis) {
            $this->redis = $connection;
            $this->usingPool = false;
        } else {
            throw new \InvalidArgumentException('Connection must be Redis instance or RedissonClient');
        }
        
        $this->name = $name;
        
        // 如果指定了初始许可数且信号量不存在，则设置初始许可数
        if ($permits > 0 && !$this->exists()) {
            $this->trySetPermits($permits);
        }
    }

    /**
     * Get Redis connection (handles connection pool if using RedissonClient)
     *
     * @return Redis
     */
    private function getRedis(): Redis
    {
        if ($this->usingPool && $this->client) {
            return $this->client->getRedis();
        }
        return $this->redis;
    }

    /**
     * Return Redis connection to pool (if using RedissonClient)
     *
     * @param Redis $redis
     */
    private function returnRedis(Redis $redis): void
    {
        if ($this->usingPool && $this->client) {
            $this->client->returnRedis($redis);
        }
    }

    /**
     * Execute Redis operation with connection pool support
     *
     * @param callable $operation
     * @return mixed
     */
    private function executeWithPool(callable $operation)
    {
        $redis = $this->getRedis();
        try {
            return $operation($redis);
        } finally {
            $this->returnRedis($redis);
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
        return $this->executeWithPool(function(Redis $redis) use ($permits) {
            // 如果信号量已存在，先删除旧值
            if ($this->exists()) {
                $this->clear();
            }
            
            // 设置新的许可数
            $result = $redis->set($this->name, $permits);
            // 同时存储总许可数
            $totalPermitsKey = $this->name . ':total';
            $redis->set($totalPermitsKey, $permits);
            return $result !== false;
        });
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
        return $this->executeWithPool(function(Redis $redis) use ($permits) {
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

            $result = $redis->eval($script, [$this->name, $permits], 1);
            return $result === 1;
        });
    }

    /**
     * Release a permit
     *
     * @param int $permits Number of permits to release
     */
    public function release(int $permits = 1): void
    {
        $this->executeWithPool(function(Redis $redis) use ($permits) {
            $redis->incrBy($this->name, $permits);
        });
    }

    /**
     * Get available permits
     *
     * @return int
     */
    public function availablePermits(): int
    {
        return $this->executeWithPool(function(Redis $redis) {
            $value = $redis->get($this->name);
            return $value !== false ? (int)$value : 0;
        });
    }

    /**
     * Acquire all available permits
     *
     * @return int Number of permits acquired
     */
    public function drainPermits(): int
    {
        return $this->executeWithPool(function(Redis $redis) {
            $script = <<<LUA
local value = redis.call('get', KEYS[1])
if value == false then
    return 0
end
local current = tonumber(value)
redis.call('set', KEYS[1], 0)
return current
LUA;

            $result = $redis->eval($script, [$this->name], 1);
            return (int)$result;
        });
    }

    /**
     * Delete the semaphore
     *
     * @return bool
     */
    public function delete(): bool
    {
        return $this->executeWithPool(function(Redis $redis) {
            return $redis->del($this->name) > 0;
        });
    }

    /**
     * Check if semaphore exists
     *
     * @return bool
     */
    public function exists(): bool
    {
        return $this->executeWithPool(function(Redis $redis) {
            return $redis->exists($this->name) > 0;
        });
    }

    /**
     * Get the size (total permits) of the semaphore
     *
     * @return int
     */
    public function size(): int
    {
        return $this->executeWithPool(function(Redis $redis) {
            // 获取总许可数，使用单独的键来存储总许可数
            $totalPermitsKey = $this->name . ':total';
            $value = $redis->get($totalPermitsKey);
            return $value !== false ? (int)$value : 0;
        });
    }

    /**
     * Reduce permits
     *
     * @param int $permits
     * @return void
     */
    public function reducePermits(int $permits): void
    {
        $this->executeWithPool(function(Redis $redis) use ($permits) {
            $redis->decrBy($this->name, $permits);
            // 同时更新总许可数
            $totalPermitsKey = $this->name . ':total';
            $redis->decrBy($totalPermitsKey, $permits);
        });
    }

    /**
     * Clear the semaphore (delete the key)
     *
     * @return void
     */
    public function clear(): void
    {
        $this->executeWithPool(function(Redis $redis) {
            $redis->del($this->name);
            // 同时清除总许可数
            $totalPermitsKey = $this->name . ':total';
            $redis->del($totalPermitsKey);
        });
    }

    /**
     * Try to acquire with timeout (simplified implementation)
     *
     * @param int $permits
     * @param int $timeout Timeout in seconds
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
