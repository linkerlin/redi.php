<?php

namespace Rediphp;

use Redis;

/**
 * Redisson-compatible distributed Lock implementation
 * Uses Redis for distributed locking, compatible with Redisson's RLock
 */
class RLock
{
    private $redis;
    private string $name;
    private ?string $lockId = null;
    private int $defaultLeaseTime = 30000; // 30 seconds in milliseconds
    private ?RedissonClient $client = null;
    private bool $usingPool = false;

    public function __construct($connection, string $name)
    {
        $this->name = $name;
        
        if ($connection instanceof RedissonClient) {
            $this->client = $connection;
            $this->usingPool = true;
        } else {
            $this->redis = $connection;
            $this->usingPool = false;
        }
    }

    /**
     * Acquire the lock
     *
     * @param int $leaseTime Lease time in milliseconds (-1 for default)
     * @return bool
     */
    public function lock(int $leaseTime = -1): bool
    {
        if ($leaseTime < 0) {
            $leaseTime = $this->defaultLeaseTime;
        }

        $this->lockId = $this->generateLockId();
        $ttl = (int)ceil($leaseTime / 1000); // Convert to seconds

        if ($this->usingPool && $this->client) {
            return $this->client->executeWithPool(function($redis) use ($ttl) {
                $result = $redis->set(
                    $this->name,
                    $this->lockId,
                    ['NX', 'EX' => $ttl]
                );
                return $result !== false;
            });
        }

        // Try to acquire lock with SET NX EX
        $result = $this->redis->set(
            $this->name,
            $this->lockId,
            ['NX', 'EX' => $ttl]
        );

        return $result !== false;
    }

    /**
     * Try to acquire the lock
     *
     * @param int $waitTime Wait time in milliseconds
     * @param int $leaseTime Lease time in milliseconds (-1 for default)
     * @return bool
     */
    public function tryLock(int $waitTime = 0, int $leaseTime = -1): bool
    {
        if ($leaseTime < 0) {
            $leaseTime = $this->defaultLeaseTime;
        }

        $this->lockId = $this->generateLockId();
        $ttl = (int)ceil($leaseTime / 1000);
        $waitUntil = microtime(true) + ($waitTime / 1000);

        do {
            if ($this->usingPool && $this->client) {
                $result = $this->client->executeWithPool(function($redis) use ($ttl) {
                    return $redis->set(
                        $this->name,
                        $this->lockId,
                        ['NX', 'EX' => $ttl]
                    );
                });
            } else {
                $result = $this->redis->set(
                    $this->name,
                    $this->lockId,
                    ['NX', 'EX' => $ttl]
                );
            }

            if ($result !== false) {
                return true;
            }

            if ($waitTime > 0) {
                usleep(100000); // Sleep for 100ms
            }
        } while (microtime(true) < $waitUntil);

        return false;
    }

    /**
     * Unlock the lock
     *
     * @return bool
     */
    public function unlock(): bool
    {
        if ($this->lockId === null) {
            return false;
        }

        // Use Lua script to ensure atomicity
        $script = <<<LUA
if redis.call("get", KEYS[1]) == ARGV[1] then
    return redis.call("del", KEYS[1])
else
    return 0
end
LUA;

        if ($this->usingPool && $this->client) {
            $result = $this->client->executeWithPool(function($redis) use ($script) {
                return $redis->eval($script, [$this->name, $this->lockId], 1);
            });
        } else {
            $result = $this->redis->eval($script, [$this->name, $this->lockId], 1);
        }
        
        if ($result > 0) {
            $this->lockId = null;
            return true;
        }

        return false;
    }

    /**
     * Check if the lock is held
     *
     * @return bool
     */
    public function isLocked(): bool
    {
        if ($this->usingPool && $this->client) {
            return $this->client->executeWithPool(function($redis) {
                return $redis->exists($this->name) > 0;
            });
        }
        
        return $this->redis->exists($this->name) > 0;
    }

    /**
     * Check if the lock is held by current thread
     *
     * @return bool
     */
    public function isHeldByCurrentThread(): bool
    {
        if ($this->lockId === null) {
            return false;
        }

        if ($this->usingPool && $this->client) {
            return $this->client->executeWithPool(function($redis) {
                $value = $redis->get($this->name);
                return $value === $this->lockId;
            });
        }

        $value = $this->redis->get($this->name);
        return $value === $this->lockId;
    }

    /**
     * Force unlock regardless of ownership
     *
     * @return bool
     */
    public function forceUnlock(): bool
    {
        if ($this->usingPool && $this->client) {
            return $this->client->executeWithPool(function($redis) {
                return $redis->del($this->name) > 0;
            });
        }
        
        return $this->redis->del($this->name) > 0;
    }

    /**
     * Generate a unique lock ID
     *
     * @return string
     */
    private function generateLockId(): string
    {
        return uniqid(gethostname() . '_', true);
    }
}
