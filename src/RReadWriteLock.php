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

        if ($this->lockId === null) {
            $this->lockId = uniqid(gethostname() . '_', true);
        }
        
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
            $this->lockId = null;
        }

        return true;
    }

    public function isLocked(): bool
    {
        return $this->redis->hLen($this->name) > 0;
    }

    public function tryLock(int $waitTime = 0, int $leaseTime = -1): bool
    {
        if ($leaseTime < 0) {
            $leaseTime = $this->defaultLeaseTime;
        }

        if ($this->lockId === null) {
            $this->lockId = uniqid(gethostname() . '_', true);
        }
        
        $ttl = (int)ceil($leaseTime / 1000);
        $waitUntil = microtime(true) + $waitTime; // waitTime is in seconds

        // 检查是否已经有写锁存在
        $writeLockName = str_replace(':read', ':write', $this->name);
        
        // 添加调试输出
        error_log("[ReadLock::tryLock] name={$this->name}, lockId={$this->lockId}, waitTime={$waitTime}, leaseTime={$leaseTime}");
        
        // 首先检查当前写锁状态（立即检查一次）
        $writeLockExists = $this->redis->exists($writeLockName);
        error_log("[ReadLock::tryLock] initial check: writeLockExists={$writeLockExists}, writeLockName={$writeLockName}");
        
        if ($writeLockExists === 0) {
            // 写锁不存在，可以立即获取读锁
            $this->redis->hIncrBy($this->name, $this->lockId, 1);
            $this->redis->expire($this->name, $ttl);
            error_log("[ReadLock::tryLock] acquired read lock immediately");
            return true;
        }
        
        // 如果等待时间为0，且写锁存在，则直接返回false
        if ($waitTime === 0) {
            error_log("[ReadLock::tryLock] no wait time and write lock exists, returning false");
            return false;
        }
        
        // 等待指定时间，期间持续检查写锁状态
        while (microtime(true) < $waitUntil) {
            // 检查写锁是否存在
            $writeLockExists = $this->redis->exists($writeLockName);
            error_log("[ReadLock::tryLock] waiting: writeLockExists={$writeLockExists}, writeLockName={$writeLockName}");
            if ($writeLockExists === 0) {
                // 写锁不存在，可以获取读锁
                $this->redis->hIncrBy($this->name, $this->lockId, 1);
                $this->redis->expire($this->name, $ttl);
                error_log("[ReadLock::tryLock] acquired read lock after waiting");
                return true;
            }
            // 写锁仍然存在，继续等待
            usleep(100000); // Sleep for 100ms
        }
        
        // 超时，返回false（不获取读锁）
        error_log("[ReadLock::tryLock] timeout, returning false");
        return false;
    }

    public function isHeldByCurrentThread(): bool
    {
        if ($this->lockId === null) {
            return false;
        }

        $count = $this->redis->hGet($this->name, $this->lockId);
        return $count !== false && $count > 0;
    }

    public function forceUnlock(): bool
    {
        return $this->redis->del($this->name) > 0;
    }

    public function getName(): string
    {
        return $this->name;
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

        if ($this->lockId === null) {
            $this->lockId = uniqid(gethostname() . '_', true);
        }
        
        $ttl = (int)ceil($leaseTime / 1000);

        // 检查是否已经有读锁存在
        $readLockName = str_replace(':write', ':read', $this->name);
        if ($this->redis->exists($readLockName) > 0) {
            return false;
        }

        // 如果是重入，增加计数
        $currentCount = $this->redis->hGet($this->name, $this->lockId);
        if ($currentCount !== false) {
            $this->redis->hIncrBy($this->name, $this->lockId, 1);
            $this->redis->expire($this->name, $ttl);
            return true;
        }

        // 检查是否已经有其他写锁存在
        if ($this->redis->hLen($this->name) > 0) {
            return false;
        }

        // 创建新的写锁
        $this->redis->hSet($this->name, $this->lockId, 1);
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
            // 删除锁条目
            $this->redis->hDel($this->name, $this->lockId);
            
            // 如果没有其他锁，删除整个键
            if ($this->redis->hLen($this->name) === 0) {
                $this->redis->del($this->name);
            }
            
            // 只有在真正释放锁时才将lockId设为null
            $this->lockId = null;
        }

        return true;
    }

    public function isLocked(): bool
    {
        return $this->redis->exists($this->name) > 0;
    }

    public function tryLock(int $waitTime = 0, int $leaseTime = -1): bool
    {
        if ($leaseTime < 0) {
            $leaseTime = $this->defaultLeaseTime;
        }

        if ($this->lockId === null) {
            $this->lockId = uniqid(gethostname() . '_', true);
        }
        
        $ttl = (int)ceil($leaseTime / 1000);
        $waitUntil = microtime(true) + $waitTime; // waitTime is in seconds

        // 检查是否已经有读锁存在
        $readLockName = str_replace(':write', ':read', $this->name);
        
        do {
            // 检查是否已经有读锁存在
            if ($this->redis->exists($readLockName) > 0) {
                if ($waitTime > 0 && microtime(true) < $waitUntil) {
                    usleep(100000); // Sleep for 100ms
                    continue;
                }
                return false;
            }

            // 如果是重入，增加计数
            $currentCount = $this->redis->hGet($this->name, $this->lockId);
            if ($currentCount !== false) {
                $this->redis->hIncrBy($this->name, $this->lockId, 1);
                $this->redis->expire($this->name, $ttl);
                error_log("[WriteLock::tryLock] reentrant, ttl=$ttl");
                return true;
            }

            // 检查是否已经有其他写锁存在
            if ($this->redis->hLen($this->name) > 0) {
                if ($waitTime > 0 && microtime(true) < $waitUntil) {
                    usleep(100000); // Sleep for 100ms
                    continue;
                }
                return false;
            }

            // 创建新的写锁
            $this->redis->hSet($this->name, $this->lockId, 1);
            $this->redis->expire($this->name, $ttl);
            error_log("[WriteLock::tryLock] created new lock, ttl=$ttl, name={$this->name}");
            return true;

        } while (microtime(true) < $waitUntil);

        return false;
    }

    public function isHeldByCurrentThread(): bool
    {
        if ($this->lockId === null) {
            return false;
        }

        $count = $this->redis->hGet($this->name, $this->lockId);
        return $count !== false && $count > 0;
    }

    public function forceUnlock(): bool
    {
        return $this->redis->del($this->name) > 0;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
