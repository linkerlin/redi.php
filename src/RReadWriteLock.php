<?php

namespace Rediphp;

use Redis;

/**
 * Redisson-compatible distributed ReadWriteLock implementation
 * Uses Redis for distributed read-write locking, compatible with Redisson's RReadWriteLock
 */
class RReadWriteLock
{
    private Redis $redis;
    private string $name;

    public function __construct(Redis $redis, string $name)
    {
        $this->redis = $redis;
        $this->name = $name;
    }

    /**
     * Get a read lock
     *
     * @return ReadLock
     */
    public function readLock(): ReadLock
    {
        return new ReadLock($this->redis, $this->name . ':read');
    }

    /**
     * Get a write lock
     *
     * @return WriteLock
     */
    public function writeLock(): WriteLock
    {
        return new WriteLock($this->redis, $this->name . ':write');
    }
}

/**
 * Read lock for ReadWriteLock
 */
class ReadLock
{
    private Redis $redis;
    private string $name;
    private ?string $lockId = null;
    private int $defaultLeaseTime = 30000;

    public function __construct(Redis $redis, string $name)
    {
        $this->redis = $redis;
        $this->name = $name;
    }

    public function lock(int $leaseTime = -1): bool
    {
        if ($leaseTime < 0) {
            $leaseTime = $this->defaultLeaseTime;
        }

        $this->lockId = uniqid(gethostname() . '_', true);
        $ttl = (int)ceil($leaseTime / 1000);

        // Increment read lock counter
        $this->redis->hIncrBy($this->name, $this->lockId, 1);
        $this->redis->expire($this->name, $ttl);

        return true;
    }

    public function unlock(): bool
    {
        if ($this->lockId === null) {
            return false;
        }

        $count = $this->redis->hIncrBy($this->name, $this->lockId, -1);
        
        if ($count <= 0) {
            $this->redis->hDel($this->name, $this->lockId);
            
            // If no more read locks, delete the key
            if ($this->redis->hLen($this->name) === 0) {
                $this->redis->del($this->name);
            }
        }

        $this->lockId = null;
        return true;
    }

    public function isLocked(): bool
    {
        return $this->redis->exists($this->name) > 0;
    }
}

/**
 * Write lock for ReadWriteLock
 */
class WriteLock
{
    private Redis $redis;
    private string $name;
    private ?string $lockId = null;
    private int $defaultLeaseTime = 30000;

    public function __construct(Redis $redis, string $name)
    {
        $this->redis = $redis;
        $this->name = $name;
    }

    public function lock(int $leaseTime = -1): bool
    {
        if ($leaseTime < 0) {
            $leaseTime = $this->defaultLeaseTime;
        }

        $this->lockId = uniqid(gethostname() . '_', true);
        $ttl = (int)ceil($leaseTime / 1000);

        $result = $this->redis->set(
            $this->name,
            $this->lockId,
            ['NX', 'EX' => $ttl]
        );

        return $result !== false;
    }

    public function unlock(): bool
    {
        if ($this->lockId === null) {
            return false;
        }

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

    public function isLocked(): bool
    {
        return $this->redis->exists($this->name) > 0;
    }
}
