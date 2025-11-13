<?php

namespace Rediphp;

use Redis;

/**
 * Redisson-compatible Redis client for PHP
 * Main entry point for accessing distributed data structures
 */
class RedissonClient
{
    private Redis $redis;
    private array $config;

    /**
     * Create a new RedissonClient instance
     *
     * @param array $config Configuration array with keys:
     *                      - host: Redis host (default: from REDIS_HOST env or '127.0.0.1')
     *                      - port: Redis port (default: from REDIS_PORT env or 6379)
     *                      - password: Redis password (default: from REDIS_PASSWORD env or null)
     *                      - database: Redis database number (default: from REDIS_DATABASE env or 0)
     *                      - timeout: Connection timeout (default: from REDIS_TIMEOUT env or 0.0)
     */
    public function __construct(array $config = [])
    {
        $defaultConfig = [
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => (int)(getenv('REDIS_PORT') ?: 6379),
            'password' => getenv('REDIS_PASSWORD') ?: null,
            'database' => (int)(getenv('REDIS_DB') ?: getenv('REDIS_DATABASE') ?: 0),
            'timeout' => (float)(getenv('REDIS_TIMEOUT') ?: 0.0),
        ];

        // Merge configurations and ensure database is always an integer
        $this->config = array_merge($defaultConfig, $config);
        $this->config['database'] = (int)$this->config['database'];

        // Always create a new Redis instance to ensure isolation
        $this->redis = new Redis();
        
        // Auto-connect to ensure database selection takes effect
        $this->connect();
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
     * Get the underlying Redis instance
     *
     * @return Redis
     */
    public function getRedis(): Redis
    {
        return $this->redis;
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
        return new RMap($this->redis, $name);
    }

    /**
     * Get a distributed List
     *
     * @param string $name Name of the list
     * @return RList
     */
    public function getList(string $name): RList
    {
        return new RList($this->redis, $name);
    }

    /**
     * Get a distributed Set
     *
     * @param string $name Name of the set
     * @return RSet
     */
    public function getSet(string $name): RSet
    {
        return new RSet($this->redis, $name);
    }

    /**
     * Get a distributed SortedSet
     *
     * @param string $name Name of the sorted set
     * @return RSortedSet
     */
    public function getSortedSet(string $name): RSortedSet
    {
        return new RSortedSet($this->redis, $name);
    }

    /**
     * Get a distributed Queue
     *
     * @param string $name Name of the queue
     * @return RQueue
     */
    public function getQueue(string $name): RQueue
    {
        return new RQueue($this->redis, $name);
    }

    /**
     * Get a distributed Deque
     *
     * @param string $name Name of the deque
     * @return RDeque
     */
    public function getDeque(string $name): RDeque
    {
        return new RDeque($this->redis, $name);
    }

    /**
     * Get a distributed Lock
     *
     * @param string $name Name of the lock
     * @return RLock
     */
    public function getLock(string $name): RLock
    {
        return new RLock($this->redis, $name);
    }

    /**
     * Get a distributed ReadWriteLock
     *
     * @param string $name Name of the lock
     * @return RReadWriteLock
     */
    public function getReadWriteLock(string $name): RReadWriteLock
    {
        return new RReadWriteLock($this->redis, $name);
    }

    /**
     * Get a distributed Semaphore
     *
     * @param string $name Name of the semaphore
     * @return RSemaphore
     */
    public function getSemaphore(string $name): RSemaphore
    {
        return new RSemaphore($this->redis, $name);
    }

    /**
     * Get a distributed CountDownLatch
     *
     * @param string $name Name of the latch
     * @return RCountDownLatch
     */
    public function getCountDownLatch(string $name): RCountDownLatch
    {
        return new RCountDownLatch($this->redis, $name);
    }

    /**
     * Get a distributed AtomicLong
     *
     * @param string $name Name of the atomic long
     * @return RAtomicLong
     */
    public function getAtomicLong(string $name): RAtomicLong
    {
        return new RAtomicLong($this->redis, $name);
    }

    /**
     * Get a distributed AtomicDouble
     *
     * @param string $name Name of the atomic double
     * @return RAtomicDouble
     */
    public function getAtomicDouble(string $name): RAtomicDouble
    {
        return new RAtomicDouble($this->redis, $name);
    }

    /**
     * Get a distributed Bucket (object holder)
     *
     * @param string $name Name of the bucket
     * @return RBucket
     */
    public function getBucket(string $name): RBucket
    {
        return new RBucket($this->redis, $name);
    }

    /**
     * Get a distributed BitSet
     *
     * @param string $name Name of the bitset
     * @return RBitSet
     */
    public function getBitSet(string $name): RBitSet
    {
        return new RBitSet($this->redis, $name);
    }

    /**
     * Get a distributed BloomFilter
     *
     * @param string $name Name of the bloom filter
     * @return RBloomFilter
     */
    public function getBloomFilter(string $name): RBloomFilter
    {
        return new RBloomFilter($this->redis, $name);
    }

    /**
     * Get a distributed Topic for pub/sub
     *
     * @param string $name Name of the topic
     * @return RTopic
     */
    public function getTopic(string $name): RTopic
    {
        return new RTopic($this->redis, $name);
    }

    /**
     * Get a distributed PatternTopic for pattern-based pub/sub
     *
     * @param string $pattern Pattern for the topic
     * @return RPatternTopic
     */
    public function getPatternTopic(string $pattern): RPatternTopic
    {
        return new RPatternTopic($this->redis, $pattern);
    }

    /**
     * Get a distributed HyperLogLog for cardinality estimation
     *
     * @param string $name Name of the hyperloglog
     * @return RHyperLogLog
     */
    public function getHyperLogLog(string $name): RHyperLogLog
    {
        return new RHyperLogLog($this->redis, $name);
    }

    /**
     * Get a distributed Geo for geographic data
     *
     * @param string $name Name of the geo set
     * @return RGeo
     */
    public function getGeo(string $name): RGeo
    {
        return new RGeo($this->redis, $name);
    }

    /**
     * Get a distributed Stream for log-like data
     *
     * @param string $name Name of the stream
     * @return RStream
     */
    public function getStream(string $name): RStream
    {
        return new RStream($this->redis, $name);
    }

    /**
     * Get a distributed TimeSeries for time-series data
     *
     * @param string $name Name of the time series
     * @return RTimeSeries
     */
    public function getTimeSeries(string $name): RTimeSeries
    {
        return new RTimeSeries($this->redis, $name);
    }

    /**
     * Shutdown and close the connection
     */
    public function shutdown(): void
    {
        $this->redis->close();
    }
}
