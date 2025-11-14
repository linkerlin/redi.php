<?php

namespace Rediphp;

use Redis;

/**
 * Base class for all Redis data structures
 * Handles connection pooling and Redis connection management
 */
abstract class RedisDataStructure
{
    protected Redis $redis;
    protected string $name;
    protected ?RedissonClient $client = null;
    protected bool $usingPool = false;

    /**
     * Constructor for data structures
     * Supports both direct Redis connections and RedissonClient with connection pooling
     *
     * @param Redis|RedissonClient $connection Redis connection or RedissonClient instance
     * @param string $name Name of the data structure
     */
    public function __construct($connection, string $name)
    {
        $this->name = $name;
        
        if ($connection instanceof RedissonClient) {
            $this->client = $connection;
            $this->usingPool = true;
            // 连接将在需要时从连接池获取
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
    protected function getRedis(): Redis
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
    protected function returnRedis(Redis $redis): void
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
    protected function executeWithPool(callable $operation)
    {
        $redis = $this->getRedis();
        
        try {
            return $operation($redis);
        } finally {
            $this->returnRedis($redis);
        }
    }

    /**
     * Encode key for storage (Redisson compatibility)
     *
     * @param mixed $key
     * @return string
     */
    protected function encodeKey($key): string
    {
        if (is_string($key)) {
            return $key;
        }
        return serialize($key);
    }

    /**
     * Decode key from storage (Redisson compatibility)
     *
     * @param string $key
     * @return mixed
     */
    protected function decodeKey(string $key)
    {
        // Try to unserialize, if it fails return as string
        $result = @unserialize($key);
        return $result !== false ? $result : $key;
    }

    /**
     * Encode value for storage (Redisson compatibility)
     *
     * @param mixed $value
     * @return string
     */
    protected function encodeValue($value): string
    {
        return serialize($value);
    }

    /**
     * Decode value from storage (Redisson compatibility)
     *
     * @param string $value
     * @return mixed
     */
    protected function decodeValue(string $value)
    {
        return unserialize($value);
    }

    /**
     * Get the name of this data structure
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Check if this data structure is using connection pooling
     *
     * @return bool
     */
    public function isUsingPool(): bool
    {
        return $this->usingPool;
    }
}