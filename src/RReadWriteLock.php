<?php

namespace Rediphp;

use Redis;

/**
 * Redisson-compatible distributed ReadWriteLock implementation
 * Uses Redis for distributed read-write locking, compatible with Redisson's RReadWriteLock
 */
class RReadWriteLock
{
    private $redis;
    private string $name;
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
     * Get Redis connection from pool or direct connection
     * Only applicable when using connection pooling
     *
     * @return Redis The Redis connection
     */
    private function getRedis(): Redis
    {
        if ($this->usingPool && $this->client) {
            return $this->client->getRedis();
        }
        
        return $this->redis;
    }

    /**
     * Return Redis connection to pool after operation
     * Only applicable when using connection pooling
     *
     * @param Redis $redis The Redis connection to return
     */
    private function returnRedis(Redis $redis): void
    {
        if ($this->usingPool && $this->client) {
            $this->client->returnRedis($redis);
        }
    }

    /**
     * Execute a Redis operation with connection pooling support
     * This method handles connection acquisition and return automatically
     *
     * @param callable $operation The Redis operation to execute
     * @return mixed The result of the operation
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
     * Get a read lock
     *
     * @return ReadLock
     */
    public function readLock(): ReadLock
    {
        $connection = $this->usingPool ? $this->client : $this->redis;
        return new ReadLock($connection, $this->name . ':read');
    }

    /**
     * Get a write lock
     *
     * @return WriteLock
     */
    public function writeLock(): WriteLock
    {
        $connection = $this->usingPool ? $this->client : $this->redis;
        return new WriteLock($connection, $this->name . ':write');
    }
}

/**
 * Read lock for ReadWriteLock
 */
class ReadLock
{
    private $redis;
    private string $name;
    private ?string $lockId = null;
    private int $defaultLeaseTime = 30000;
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
     * Get Redis connection for operation
     * Handles connection pooling if enabled
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
     * Return Redis connection to pool after operation
     * Only applicable when using connection pooling
     *
     * @param Redis $redis The Redis connection to return
     */
    private function returnRedis(Redis $redis): void
    {
        if ($this->usingPool && $this->client) {
            $this->client->returnRedis($redis);
        }
    }

    /**
     * Execute a Redis operation with connection pooling support
     * This method handles connection acquisition and return automatically
     *
     * @param callable $operation The Redis operation to execute
     * @return mixed The result of the operation
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

    public function lock(int $leaseTime = -1): bool
    {
        if ($leaseTime < 0) {
            $leaseTime = $this->defaultLeaseTime;
        }

        if ($this->lockId === null) {
            $this->lockId = uniqid(gethostname() . '_', true);
        }
        
        $ttl = (int)ceil($leaseTime / 1000);

        return $this->executeWithPool(function(Redis $redis) use ($ttl) {
            // Increment read lock counter
            $redis->hIncrBy($this->name, $this->lockId, 1);
            $redis->expire($this->name, $ttl);

            return true;
        });
    }

    public function unlock(): bool
    {
        if ($this->lockId === null) {
            return false;
        }

        return $this->executeWithPool(function(Redis $redis) {
            $count = $redis->hIncrBy($this->name, $this->lockId, -1);
            
            if ($count <= 0) {
                $redis->hDel($this->name, $this->lockId);
                
                // If no more read locks, delete the key
                if ($redis->hLen($this->name) === 0) {
                    $redis->del($this->name);
                }
                $this->lockId = null;
            }

            return true;
        });
    }

    public function isLocked(): bool
    {
        return $this->executeWithPool(function(Redis $redis) {
            return $redis->hLen($this->name) > 0;
        });
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
        
        return $this->executeWithPool(function(Redis $redis) use ($ttl, $writeLockName, $waitUntil, $waitTime) {
            // 首先检查当前写锁状态（立即检查一次）
            $writeLockExists = $redis->exists($writeLockName);
            
            if ($writeLockExists === 0) {
                // 写锁不存在，可以立即获取读锁
                $redis->hIncrBy($this->name, $this->lockId, 1);
                $redis->expire($this->name, $ttl);
                return true;
            }
            
            // 如果等待时间为0，且写锁存在，则直接返回false
            if ($waitTime === 0) {
                return false;
            }
            
            $retryCount = 0;
            $baseDelay = 1000; // 1ms
            $maxDelay = 50000; // 50ms
            
            // 等待指定时间，期间持续检查写锁状态
            while (microtime(true) < $waitUntil) {
                // 检查写锁是否存在
                $writeLockExists = $redis->exists($writeLockName);
                if ($writeLockExists === 0) {
                    // 写锁不存在，可以获取读锁
                    $redis->hIncrBy($this->name, $this->lockId, 1);
                    $redis->expire($this->name, $ttl);
                    return true;
                }
                
                // 写锁仍然存在，使用指数退避算法等待
                $delay = min($baseDelay * pow(2, $retryCount), $maxDelay);
                usleep($delay);
                $retryCount++;
            }
            
            // 超时，返回false（不获取读锁）
            return false;
        });
    }

    public function isHeldByCurrentThread(): bool
    {
        if ($this->lockId === null) {
            return false;
        }

        return $this->executeWithPool(function(Redis $redis) {
            $count = $redis->hGet($this->name, $this->lockId);
            return $count !== false && $count > 0;
        });
    }

    public function forceUnlock(): bool
    {
        return $this->executeWithPool(function(Redis $redis) {
            return $redis->del($this->name) > 0;
        });
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
    private $redis;
    private string $name;
    private ?string $lockId = null;
    private int $defaultLeaseTime = 30000;
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
     * Get Redis connection from pool or direct connection
     * Only applicable when using connection pooling
     *
     * @return Redis The Redis connection
     */
    private function getRedis(): Redis
    {
        if ($this->usingPool && $this->client) {
            return $this->client->getRedis();
        }
        
        return $this->redis;
    }

    /**
     * Return Redis connection to pool after operation
     * Only applicable when using connection pooling
     *
     * @param Redis $redis The Redis connection to return
     */
    private function returnRedis(Redis $redis): void
    {
        if ($this->usingPool && $this->client) {
            $this->client->returnRedis($redis);
        }
    }

    /**
     * Execute a Redis operation with connection pooling support
     * This method handles connection acquisition and return automatically
     *
     * @param callable $operation The Redis operation to execute
     * @return mixed The result of the operation
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

    public function lock(int $leaseTime = -1): bool
    {
        if ($leaseTime < 0) {
            $leaseTime = $this->defaultLeaseTime;
        }

        if ($this->lockId === null) {
            $this->lockId = uniqid(gethostname() . '_', true);
        }
        
        $ttl = (int)ceil($leaseTime / 1000);

        return $this->executeWithPool(function(Redis $redis) use ($ttl) {
            // 检查是否已经有读锁存在
            $readLockName = str_replace(':write', ':read', $this->name);
            if ($redis->exists($readLockName) > 0) {
                return false;
            }

            // 如果是重入，增加计数
            $currentCount = $redis->hGet($this->name, $this->lockId);
            if ($currentCount !== false) {
                $redis->hIncrBy($this->name, $this->lockId, 1);
                $redis->expire($this->name, $ttl);
                return true;
            }

            // 检查是否已经有其他写锁存在
            if ($redis->hLen($this->name) > 0) {
                return false;
            }

            // 创建新的写锁
            $redis->hSet($this->name, $this->lockId, 1);
            $redis->expire($this->name, $ttl);
            return true;
        });
    }

    public function unlock(): bool
    {
        if ($this->lockId === null) {
            return false;
        }

        return $this->executeWithPool(function(Redis $redis) {
            $count = $redis->hIncrBy($this->name, $this->lockId, -1);
            
            if ($count <= 0) {
                // 删除锁条目
                $redis->hDel($this->name, $this->lockId);
                
                // 如果没有其他锁，删除整个键
                if ($redis->hLen($this->name) === 0) {
                    $redis->del($this->name);
                }
                
                // 只有在真正释放锁时才将lockId设为null
                $this->lockId = null;
            }

            return true;
        });
    }

    public function isLocked(): bool
    {
        return $this->executeWithPool(function(Redis $redis) {
            return $redis->exists($this->name) > 0;
        });
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
        
        return $this->executeWithPool(function(Redis $redis) use ($ttl, $readLockName, $waitUntil, $waitTime) {
            $retryCount = 0;
            $baseDelay = 1000; // 1ms
            $maxDelay = 50000; // 50ms
            
            do {
                // 检查是否已经有读锁存在
                if ($redis->exists($readLockName) > 0) {
                    if ($waitTime > 0 && microtime(true) < $waitUntil) {
                        // 使用指数退避算法等待
                        $delay = min($baseDelay * pow(2, $retryCount), $maxDelay);
                        usleep($delay);
                        $retryCount++;
                        continue;
                    }
                    return false;
                }

                // 如果是重入，增加计数
                $currentCount = $redis->hGet($this->name, $this->lockId);
                if ($currentCount !== false) {
                    $redis->hIncrBy($this->name, $this->lockId, 1);
                    $redis->expire($this->name, $ttl);
                    return true;
                }

                // 检查是否已经有其他写锁存在
                if ($redis->hLen($this->name) > 0) {
                    if ($waitTime > 0 && microtime(true) < $waitUntil) {
                        // 使用指数退避算法等待
                        $delay = min($baseDelay * pow(2, $retryCount), $maxDelay);
                        usleep($delay);
                        $retryCount++;
                        continue;
                    }
                    return false;
                }

                // 创建新的写锁
                $redis->hSet($this->name, $this->lockId, 1);
                $redis->expire($this->name, $ttl);
                return true;

            } while (microtime(true) < $waitUntil);

            return false;
        });
    }

    public function isHeldByCurrentThread(): bool
    {
        if ($this->lockId === null) {
            return false;
        }

        return $this->executeWithPool(function(Redis $redis) {
            $count = $redis->hGet($this->name, $this->lockId);
            return $count !== false && $count > 0;
        });
    }

    public function forceUnlock(): bool
    {
        return $this->executeWithPool(function(Redis $redis) {
            return $redis->del($this->name) > 0;
        });
    }

    public function getName(): string
    {
        return $this->name;
    }
}
