<?php

namespace Rediphp;

use Rediphp\RedisPromise;

/**
 * AsyncRedissonClient - Asynchronous wrapper for RedissonClient
 * Provides Promise-based API for all Redis operations
 * Compatible with Redisson's async patterns
 */
class AsyncRedissonClient
{
    private RedissonClient $client;
    private bool $usePool = false;
    
    public function __construct(array $config = [], bool $usePool = false)
    {
        $this->client = new RedissonClient($config);
        $this->usePool = $usePool;
    }

    /**
     * Connect to Redis
     */
    public function connect(): RedisPromise
    {
        return new RedisPromise(function ($resolve, $reject) {
            try {
                $result = $this->client->connect();
                if ($result) {
                    $resolve($this);
                } else {
                    $reject(new \Exception('Failed to connect to Redis'));
                }
            } catch (\Exception $e) {
                $reject($e);
            }
        });
    }

    /**
     * Disconnect from Redis
     */
    public function disconnect(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $this->client->disconnect();
            $resolve(true);
        });
    }

    /**
     * Check if connected
     */
    public function isConnected(): bool
    {
        return $this->client->isConnected();
    }

    /**
     * Get bucket (string value wrapper)
     */
    public function getBucket(string $name): AsyncRBucket
    {
        return new AsyncRBucket($this->client->getBucket($name));
    }

    /**
     * Get map (hash wrapper)
     */
    public function getMap(string $name): AsyncRMap
    {
        return new AsyncRMap($this->client->getMap($name));
    }

    /**
     * Get list
     */
    public function getList(string $name): AsyncRList
    {
        return new AsyncRList($this->client->getList($name));
    }

    /**
     * Get set
     */
    public function getSet(string $name): AsyncRSet
    {
        return new AsyncRSet($this->client->getSet($name));
    }

    /**
     * Get sorted set
     */
    public function getSortedSet(string $name): AsyncRSortedSet
    {
        return new AsyncRSortedSet($this->client->getSortedSet($name));
    }

    /**
     * Get hyper log log
     */
    public function getHyperLogLog(string $name): AsyncRHyperLogLog
    {
        return new AsyncRHyperLogLog($this->client->getHyperLogLog($name));
    }

    /**
     * Get geo
     */
    public function getGeo(string $name): AsyncRGeo
    {
        return new AsyncRGeo($this->client->getGeo($name));
    }

    /**
     * Get bloom filter
     */
    public function getBloomFilter(string $name): AsyncRBloomFilter
    {
        return new AsyncRBloomFilter($this->client->getBloomFilter($name));
    }

    /**
     * Get atomic long
     */
    public function getAtomicLong(string $name): AsyncRAtomicLong
    {
        return new AsyncRAtomicLong($this->client->getAtomicLong($name));
    }

    /**
     * Get atomic double
     */
    public function getAtomicDouble(string $name): AsyncRAtomicDouble
    {
        return new AsyncRAtomicDouble($this->client->getAtomicDouble($name));
    }

    /**
     * Get lock
     */
    public function getLock(string $name): AsyncRLock
    {
        return new AsyncRLock($this->client->getLock($name));
    }

    /**
     * Get read write lock
     */
    public function getReadWriteLock(string $name): AsyncRReadWriteLock
    {
        return new AsyncRReadWriteLock($this->client->getReadWriteLock($name));
    }

    /**
     * Get semaphore
     */
    public function getSemaphore(string $name): AsyncRSemaphore
    {
        return new AsyncRSemaphore($this->client->getSemaphore($name));
    }

    /**
     * Get count down latch
     */
    public function getCountDownLatch(string $name): AsyncRCountDownLatch
    {
        return new AsyncRCountDownLatch($this->client->getCountDownLatch($name));
    }

    /**
     * Get topic
     */
    public function getTopic(string $name): AsyncRTopic
    {
        return new AsyncRTopic($this->client->getTopic($name));
    }

    /**
     * Get pattern topic
     */
    public function getPatternTopic(string $pattern): AsyncRPatternTopic
    {
        return new AsyncRPatternTopic($this->client->getPatternTopic($pattern));
    }

    /**
     * Get queue
     */
    public function getQueue(string $name): AsyncRQueue
    {
        return new AsyncRQueue($this->client->getQueue($name));
    }

    /**
     * Get deque
     */
    public function getDeque(string $name): AsyncRDeque
    {
        return new AsyncRDeque($this->client->getDeque($name));
    }

    /**
     * Get limit deque
     */
    public function getLimitDeque(string $name, int $maxSize): AsyncRLimitDeque
    {
        return new AsyncRLimitDeque($this->client->getLimitDeque($name, $maxSize));
    }

    /**
     * Get string
     */
    public function getString(string $name): AsyncRString
    {
        return new AsyncRString($this->client->getString($name));
    }

    /**
     * Get collection
     */
    public function getCollection(string $name): AsyncRCollection
    {
        return new AsyncRCollection($this->client->getCollection($name));
    }

    /**
     * Pipeline operations
     */
    public function pipeline(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            if (method_exists($this->client, 'pipeline')) {
                $resolve($this->client->pipeline());
            } else {
                $resolve(null);
            }
        });
    }

    /**
     * Get time series
     */
    public function getTimeSeries(string $name): AsyncRTimeSeries
    {
        return new AsyncRTimeSeries($this->client->getTimeSeries($name));
    }

    /**
     * Get stream
     */
    public function getStream(string $name): AsyncRStream
    {
        return new AsyncRStream($this->client->getStream($name));
    }

    /**
     * Execute multiple operations in parallel
     */
    public function batch(array $operations): RedisPromise
    {
        return RedisPromise::all(array_map(function ($operation) {
            if (is_array($operation) && isset($operation['method'])) {
                return $this->executeOperation($operation['method'], $operation['args'] ?? []);
            }
            return RedisPromise::reject(new \Exception('Invalid operation format'));
        }, $operations));
    }

    /**
     * Pipeline operations with commands
     */
    public function pipelineWithCommands(array $operations): RedisPromise
    {
        return new RedisPromise(function ($resolve, $reject) {
            try {
                $pipeline = $this->client->getPipeline();
                
                foreach ($operations as $operation) {
                    if (is_array($operation) && isset($operation['method'])) {
                        $method = $operation['method'];
                        $args = $operation['args'] ?? [];
                        
                        // Queue operation in pipeline
                        $pipeline->queueCommand($method, $args);
                    }
                }
                
                $results = $pipeline->execute();
                $resolve($results);
            } catch (\Exception $e) {
                $reject($e);
            }
        });
    }

    /**
     * Execute operation
     */
    private function executeOperation(string $method, array $args): RedisPromise
    {
        return new RedisPromise(function ($resolve, $reject) {
            try {
                if (method_exists($this->client, $method)) {
                    $result = call_user_func_array([$this->client, $method], $args);
                    $resolve($result);
                } else {
                    $reject(new \Exception("Method {$method} not found"));
                }
            } catch (\Exception $e) {
                $reject($e);
            }
        });
    }

    /**
     * Get underlying client
     */
    public function getClient(): RedissonClient
    {
        return $this->client;
    }

    /**
     * Execute Redis commands directly
     */
    public function executeRaw(string $command, ...$args): RedisPromise
    {
        return new RedisPromise(function ($resolve, $reject) {
            try {
                $result = $this->client->executeRaw($command, ...$args);
                $resolve($result);
            } catch (\Exception $e) {
                $reject($e);
            }
        });
    }

    /**
     * Eval Lua script
     */
    public function eval(string $script, int $keysCount = 0, ...$args): RedisPromise
    {
        return new RedisPromise(function ($resolve, $reject) {
            try {
                $result = $this->client->eval($script, $keysCount, ...$args);
                $resolve($result);
            } catch (\Exception $e) {
                $reject($e);
            }
        });
    }

    /**
     * Execute commands in transaction
     */
    public function transaction(callable $callback): RedisPromise
    {
        return new RedisPromise(function ($resolve, $reject) {
            try {
                $pipeline = $this->client->getPipeline();
                
                // Create a transaction context
                $context = new AsyncTransactionContext($pipeline);
                
                // Execute user callback
                $callback($context);
                
                // Execute pipeline
                $results = $pipeline->execute();
                $resolve($results);
            } catch (\Exception $e) {
                $reject($e);
            }
        });
    }
}

/**
 * Async transaction context
 */
class AsyncTransactionContext
{
    private $pipeline;
    
    public function __construct($pipeline)
    {
        $this->pipeline = $pipeline;
    }
    
    public function execute(callable $operation): void
    {
        $operation($this->pipeline);
    }
}