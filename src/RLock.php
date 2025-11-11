<?php

namespace Rediphp;

use Redis;

/**
 * Redisson-compatible distributed Lock implementation
 * Uses Redis for distributed locking, compatible with Redisson's RLock
 */
class RLock
{
    private Redis $redis;
    private string $name;
    private ?string $lockId = null;
    private int $defaultLeaseTime = 30000; // 30 seconds in milliseconds

    public function __construct(Redis $redis, string $name)
    {
        $this->redis = $redis;
        $this->name = $name;
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
            $result = $this->redis->set(
                $this->name,
                $this->lockId,
                ['NX', 'EX' => $ttl]
            );

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
     * Release the lock
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

        $result = $this->redis->eval($script, [$this->name, $this->lockId], 1);
        
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
