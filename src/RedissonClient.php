<?php

namespace Rediphp;

use Redis;
use Rediphp\RedisPool;
use Rediphp\PooledRedis;
use Rediphp\RMap;
use Rediphp\RList;
use Rediphp\RSet;
use Rediphp\RSortedSet;
use Rediphp\RQueue;
use Rediphp\RDeque;
use Rediphp\RLock;
use Rediphp\RReadWriteLock;
use Rediphp\RSemaphore;
use Rediphp\RCountDownLatch;
use Rediphp\RAtomicLong;
use Rediphp\RAtomicDouble;
use Rediphp\RBucket;
use Rediphp\RBitSet;
use Rediphp\RBloomFilter;
use Rediphp\RTopic;
use Rediphp\RPatternTopic;
use Rediphp\RHyperLogLog;
use Rediphp\RGeo;
use Rediphp\RStream;
use Rediphp\RTimeSeries;

/**
 * Redisson-compatible Redis client for PHP
 * Main entry point for accessing distributed data structures
 * Supports both direct connections and connection pooling
 */
class RedissonClient
{
    private Redis $redis;
    private array $config;
    private ?RedisPool $pool = null;
    private bool $usePool = false;
    private ?PooledRedis $currentPooledConnection = null;

    /**
     * Create a new RedissonClient instance
     *
     * @param array $config Configuration array with keys:
     *                      - host: Redis host (default: from REDIS_HOST env or '127.0.0.1')
     *                      - port: Redis port (default: from REDIS_PORT env or 6379)
     *                      - password: Redis password (default: from REDIS_PASSWORD env or null)
     *                      - database: Redis database number (default: from REDIS_DATABASE env or 0)
     *                      - timeout: Connection timeout (default: from REDIS_TIMEOUT env or 0.0)
     *                      - use_pool: Whether to use connection pool (default: false)
     *                      - pool_config: Connection pool configuration (optional)
     */
    public function __construct(array $config = [])
    {
        $defaultConfig = [
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => (int)(getenv('REDIS_PORT') ?: 6379),
            'password' => getenv('REDIS_PASSWORD') ?: null,
            'database' => (int)(getenv('REDIS_DB') ?: getenv('REDIS_DATABASE') ?: 0),
            'timeout' => (float)(getenv('REDIS_TIMEOUT') ?: 0.0),
            'use_pool' => false,
            'pool_config' => [],
        ];

        // Merge configurations and ensure database is always an integer
        $this->config = array_merge($defaultConfig, $config);
        $this->config['database'] = (int)$this->config['database'];
        $this->usePool = (bool)$this->config['use_pool'];

        if ($this->usePool) {
            // 使用连接池
            $poolConfig = array_merge($this->config, $this->config['pool_config']);
            $this->pool = new RedisPool($poolConfig);
            
            // 获取连接并预热连接池
            $connection = $this->pool->getConnection();
            $this->redis = $connection->getRedis();
            
            // 连接在使用后需要归还到连接池
            $connection->returnToPool();
        } else {
            // 使用直接连接（向后兼容）
            $this->redis = new Redis();
            $this->connect();
        }
    }

    /**
     * Connect to Redis server
     *
     * @return bool
     */
    public function connect(): bool
    {
        try {
            // Validate database number
            $database = $this->config['database'];
            if (!is_int($database) || $database < 0 || $database > 15) {
                throw new \InvalidArgumentException("Invalid database number: $database. Must be an integer between 0 and 15.");
            }

            $connected = $this->redis->connect(
                $this->config['host'],
                $this->config['port'],
                $this->config['timeout']
            );

            if (!$connected) {
                $error = $this->redis->getLastError();
                throw new \RuntimeException("Redis connection failed: " . ($error ?: 'Unknown error'));
            }

            if ($this->config['password'] !== null) {
                $this->redis->auth($this->config['password']);
            }

            // Always select the database, even if it's 0, to ensure isolation
            $this->redis->select($this->config['database']);

            return true;
        } catch (\Exception $e) {
            throw new \RuntimeException("Redis connection error: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get a Redis connection for use in data structures
     * This method handles both direct connections and connection pooling
     *
     * @return Redis
     */
    public function getRedis(): Redis
    {
        if ($this->usePool && $this->pool) {
            // 从连接池获取连接
            $this->currentPooledConnection = $this->pool->getConnection();
            return $this->currentPooledConnection->getRedis();
        }
        
        return $this->redis;
    }

    /**
     * Return a Redis connection to the pool after use
     * This is required when using connection pooling
     *
     * @param Redis $redis The Redis connection to return
     */
    public function returnRedis(Redis $redis): void
    {
        if ($this->usePool && $this->pool && $this->currentPooledConnection) {
            // 归还连接池对象
            $this->pool->returnConnection($this->currentPooledConnection);
            $this->currentPooledConnection = null;
        }
    }



    /**
     * Get the configured database number
     *
     * @return int The database number (0-15)
     */
    public function getDatabase(): int
    {
        return $this->config['database'];
    }

    /**
     * Get a distributed Map
     *
     * @param string $name Name of the map
     * @return RMap
     */
    public function getMap(string $name): RMap
    {
        return new RMap($this, $name);
    }

    /**
     * Get a distributed List
     *
     * @param string $name Name of the list
     * @return RList
     */
    public function getList(string $name): RList
    {
        return new RList($this, $name);
    }

    /**
     * Get a distributed Set
     *
     * @param string $name Name of the set
     * @return RSet
     */
    public function getSet(string $name): RSet
    {
        return new RSet($this, $name);
    }

    /**
     * Get a distributed SortedSet
     *
     * @param string $name Name of the sorted set
     * @return RSortedSet
     */
    public function getSortedSet(string $name): RSortedSet
    {
        return new RSortedSet($this, $name);
    }

    /**
     * Get a distributed Queue
     *
     * @param string $name Name of the queue
     * @return RQueue
     */
    public function getQueue(string $name): RQueue
    {
        return new RQueue($this, $name);
    }

    /**
     * Get a distributed Deque
     *
     * @param string $name Name of the deque
     * @return RDeque
     */
    public function getDeque(string $name): RDeque
    {
        return new RDeque($this, $name);
    }

    /**
     * Get a distributed Lock
     *
     * @param string $name Name of the lock
     * @return RLock
     */
    public function getLock(string $name): RLock
    {
        return new RLock($this, $name);
    }

    /**
     * Get a distributed ReadWriteLock
     *
     * @param string $name Name of the lock
     * @return RReadWriteLock
     */
    public function getReadWriteLock(string $name): RReadWriteLock
    {
        return new RReadWriteLock($this, $name);
    }

    /**
     * Get a distributed Semaphore
     *
     * @param string $name Name of the semaphore
     * @return RSemaphore
     */
    public function getSemaphore(string $name): RSemaphore
    {
        return new RSemaphore($this, $name);
    }

    /**
     * Get a distributed CountDownLatch
     *
     * @param string $name Name of the latch
     * @return RCountDownLatch
     */
    public function getCountDownLatch(string $name): RCountDownLatch
    {
        return new RCountDownLatch($this, $name);
    }

    /**
     * Get a distributed AtomicLong
     *
     * @param string $name Name of the atomic long
     * @return RAtomicLong
     */
    public function getAtomicLong(string $name): RAtomicLong
    {
        return new RAtomicLong($this, $name);
    }

    /**
     * Get a distributed AtomicDouble
     *
     * @param string $name Name of the atomic double
     * @return RAtomicDouble
     */
    public function getAtomicDouble(string $name): RAtomicDouble
    {
        return new RAtomicDouble($this, $name);
    }

    /**
     * Get a distributed Bucket (object holder)
     *
     * @param string $name Name of the bucket
     * @return RBucket
     */
    public function getBucket(string $name): RBucket
    {
        return new RBucket($this, $name);
    }

    /**
     * Get a distributed BitSet
     *
     * @param string $name Name of the bitset
     * @return RBitSet
     */
    public function getBitSet(string $name): RBitSet
    {
        return new RBitSet($this, $name);
    }

    /**
     * Get a distributed BloomFilter
     *
     * @param string $name Name of the bloom filter
     * @return RBloomFilter
     */
    public function getBloomFilter(string $name): RBloomFilter
    {
        return new RBloomFilter($this, $name);
    }

    /**
     * Get a distributed Topic for pub/sub
     *
     * @param string $name Name of the topic
     * @return RTopic
     */
    public function getTopic(string $name): RTopic
    {
        return new RTopic($this, $name);
    }

    /**
     * Get a distributed PatternTopic for pattern-based pub/sub
     *
     * @param string $pattern Pattern for the topic
     * @return RPatternTopic
     */
    public function getPatternTopic(string $pattern): RPatternTopic
    {
        return new RPatternTopic($this, $pattern);
    }

    /**
     * Get a distributed HyperLogLog for cardinality estimation
     *
     * @param string $name Name of the hyperloglog
     * @return RHyperLogLog
     */
    public function getHyperLogLog(string $name): RHyperLogLog
    {
        return new RHyperLogLog($this, $name);
    }

    /**
     * Get a distributed Geo for geographic data
     *
     * @param string $name Name of the geo set
     * @return RGeo
     */
    public function getGeo(string $name): RGeo
    {
        return new RGeo($this, $name);
    }

    /**
     * Get a distributed Stream for log-like data
     *
     * @param string $name Name of the stream
     * @return RStream
     */
    public function getStream(string $name): RStream
    {
        return new RStream($this, $name);
    }

    /**
     * Get a distributed TimeSeries for time-series data
     *
     * @param string $name Name of the time series
     * @return RTimeSeries
     */
    public function getTimeSeries(string $name): RTimeSeries
    {
        return new RTimeSeries($this, $name);
    }

    /**
     * 获取连接池统计信息
     *
     * @return array|null 如果未使用连接池则返回null
     */
    public function getConnectionPoolStats(): ?array
    {
        if ($this->usePool && $this->pool !== null) {
            return $this->pool->getStats();
        }
        
        return null;
    }

    /**
     * 检查是否使用连接池
     *
     * @return bool
     */
    public function isUsingPool(): bool
    {
        return $this->usePool;
    }

    /**
     * Shutdown and close the connection
     */
    public function shutdown(): void
    {
        $this->redis->close();
    }
}
