<?php

namespace Rediphp;

use Redis;
use Exception;
use InvalidArgumentException;
use RuntimeException;

/**
 * Redis连接池管理器
 * 提供Redis连接的重用和管理，优化性能和资源使用
 */
class RedisPool
{
    private array $config;
    private array $idleConnections = [];
    private array $activeConnections = [];
    private int $minSize;
    private int $maxSize;
    private int $currentSize = 0;
    private float $maxWaitTime;
    private bool $isClosed = false;
    
    // 性能统计
    private int $totalRequests = 0;
    private int $totalAcquires = 0;
    private float $totalWaitTime = 0.0;
    private float $maxAcquireTime = 0.0;
    private float $minAcquireTime = PHP_FLOAT_MAX;

    /**
     * 创建Redis连接池
     *
     * @param array $config 连接池配置
     *                      - host: Redis主机 (默认: 127.0.0.1)
     *                      - port: Redis端口 (默认: 6379)
     *                      - password: Redis密码 (默认: null)
     *                      - database: 数据库号 (默认: 0)
     *                      - timeout: 连接超时 (默认: 0.0)
     *                      - min_size: 最小连接数 (默认: 2)
     *                      - max_size: 最大连接数 (默认: 10)
     *                      - max_wait_time: 最大等待时间(秒) (默认: 30.0)
     */
    public function __construct(array $config = [])
    {
        $defaultConfig = [
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => (int)(getenv('REDIS_PORT') ?: 6379),
            'password' => getenv('REDIS_PASSWORD') ?: null,
            'database' => (int)(getenv('REDIS_DB') ?: getenv('REDIS_DATABASE') ?: 0),
            'timeout' => (float)(getenv('REDIS_TIMEOUT') ?: 0.0),
            'min_size' => 2,
            'max_size' => 10,
            'max_wait_time' => 30.0,
        ];

        $this->config = array_merge($defaultConfig, $config);
        $this->minSize = (int)$this->config['min_size'];
        $this->maxSize = (int)$this->config['max_size'];
        $this->maxWaitTime = (float)$this->config['max_wait_time'];

        // 验证配置
        if ($this->minSize < 0 || $this->maxSize < $this->minSize) {
            throw new InvalidArgumentException("Invalid pool size configuration: min_size={$this->minSize}, max_size={$this->maxSize}");
        }

        if ($this->maxWaitTime <= 0) {
            throw new InvalidArgumentException("max_wait_time must be greater than 0");
        }

        // 初始化最小连接数
        $this->initializePool();
    }

    /**
     * 获取连接
     *
     * @return PooledRedis
     * @throws RuntimeException 当无法获取连接时抛出
     */
    public function getConnection(): PooledRedis
    {
        if ($this->isClosed) {
            throw new RuntimeException("Connection pool is closed");
        }

        $this->totalRequests++;
        $startTime = microtime(true);

        // 首先尝试从空闲连接池获取
        if (!empty($this->idleConnections)) {
            $connection = array_shift($this->idleConnections);
            $this->activeConnections[spl_object_id($connection)] = $connection;
            $this->recordAcquireTime(microtime(true) - $startTime);
            return $connection;
        }

        // 如果当前连接数未达到最大值，创建新连接
        if ($this->currentSize < $this->maxSize) {
            $connection = $this->createConnection();
            $this->activeConnections[spl_object_id($connection)] = $connection;
            $this->recordAcquireTime(microtime(true) - $startTime);
            return $connection;
        }

        // 等待可用连接
        while (microtime(true) - $startTime < $this->maxWaitTime) {
            // 检查是否有连接归还
            if (!empty($this->idleConnections)) {
                $connection = array_shift($this->idleConnections);
                $this->activeConnections[spl_object_id($connection)] = $connection;
                $this->recordAcquireTime(microtime(true) - $startTime);
                return $connection;
            }

            // 如果有活跃连接完成操作，可以回收
            $this->recycleConnections();

            usleep(10000); // 等待10ms
        }

        throw new RuntimeException("Failed to acquire Redis connection within {$this->maxWaitTime} seconds");
    }

    /**
     * 归还连接
     *
     * @param PooledRedis $connection
     * @return void
     */
    public function returnConnection(PooledRedis $connection): void
    {
        if ($this->isClosed) {
            return;
        }

        $objectId = spl_object_id($connection);
        
        if (isset($this->activeConnections[$objectId])) {
            unset($this->activeConnections[$objectId]);
            
            // 检查连接是否仍然有效
            if ($connection->isConnected()) {
                $this->idleConnections[] = $connection;
            } else {
                $this->currentSize--;
            }
        }
    }

    /**
     * 关闭连接池
     */
    public function close(): void
    {
        $this->isClosed = true;
        
        // 关闭所有连接
        foreach ($this->idleConnections as $connection) {
            $connection->close();
        }
        foreach ($this->activeConnections as $connection) {
            $connection->close();
        }
        
        $this->idleConnections = [];
        $this->activeConnections = [];
        $this->currentSize = 0;
    }

    /**
     * 获取统计信息
     *
     * @return array
     */
    public function getStats(): array
    {
        $avgAcquireTime = $this->totalAcquires > 0 ? round($this->totalWaitTime / $this->totalAcquires * 1000, 2) : 0;
        
        return [
            'idle_connections' => count($this->idleConnections),
            'active_connections' => count($this->activeConnections),
            'total_connections' => $this->currentSize,
            'min_size' => $this->minSize,
            'max_size' => $this->maxSize,
            'is_closed' => $this->isClosed,
            'total_requests' => $this->totalRequests,
            'total_acquires' => $this->totalAcquires,
            'avg_acquire_time_ms' => $avgAcquireTime,
            'max_acquire_time_ms' => $this->maxAcquireTime > 0 ? round($this->maxAcquireTime * 1000, 2) : 0,
            'min_acquire_time_ms' => $this->minAcquireTime < PHP_INT_MAX ? round($this->minAcquireTime * 1000, 2) : 0,
            'pool_utilization' => round(count($this->activeConnections) / max($this->currentSize, 1) * 100, 2) . '%',
        ];
    }

    /**
     * 预热连接池
     *
     * @return void
     */
    public function warmUp(): void
    {
        while ($this->currentSize < $this->minSize) {
            try {
                $connection = $this->createConnection();
                $this->idleConnections[] = $connection;
            } catch (Exception $e) {
                // 连接失败时停止预热
                break;
            }
        }
    }

    /**
     * 创建新的Redis连接
     *
     * @return PooledRedis
     * @throws RuntimeException 当连接创建失败时抛出
     */
    private function createConnection(): PooledRedis
    {
        try {
            $connection = new PooledRedis($this, $this->config);
            $connection->connect();
            $this->currentSize++;
            return $connection;
        } catch (Exception $e) {
            throw new RuntimeException("Failed to create Redis connection: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 回收失效连接
     */
    private function recycleConnections(): void
    {
        foreach ($this->idleConnections as $key => $connection) {
            if (!$connection->isConnected()) {
                unset($this->idleConnections[$key]);
                $this->currentSize--;
            }
        }
        
        // 重新索引数组
        $this->idleConnections = array_values($this->idleConnections);
    }

    /**
     * 记录连接获取时间
     *
     * @param float $time 耗时(秒)
     * @return void
     */
    private function recordAcquireTime(float $time): void
    {
        $this->totalAcquires++;
        $this->totalWaitTime += $time;
        $this->maxAcquireTime = max($this->maxAcquireTime, $time);
        $this->minAcquireTime = $this->minAcquireTime === PHP_FLOAT_MAX ? $time : min($this->minAcquireTime, $time);
    }

    /**
     * 初始化连接池，创建最小数量的连接
     */
    private function initializePool(): void
    {
        for ($i = 0; $i < $this->minSize; $i++) {
            try {
                $connection = $this->createConnection();
                $this->idleConnections[] = $connection;
            } catch (Exception $e) {
                // 初始化时连接失败不影响池的创建
                break;
            }
        }
    }

    /**
     * 析构函数，确保连接池正确关闭
     */
    public function __destruct()
    {
        if (!$this->isClosed) {
            $this->close();
        }
    }
}