<?php

namespace RediPhp;

use Exception;
use RuntimeException;
use Redis;
use RedisException;

/**
 * Redisson连接池实现
 * 基于Redisson客户端实现连接池功能
 */
class RedissonPool implements RedisPoolInterface
{
    /** @var Redis[] 空闲连接数组 */
    private array $idleConnections = [];
    
    /** @var Redis[] 活跃连接数组 */
    private array $activeConnections = [];
    
    /** @var array 连接池配置 */
    private array $config;
    
    /** @var int 当前连接池大小 */
    private int $currentSize = 0;
    
    /** @var int 连接池是否已关闭 */
    private bool $isClosed = false;
    
    /** @var int 总获取连接次数 */
    private int $totalAcquires = 0;
    
    /** @var float 总等待时间 */
    private float $totalWaitTime = 0.0;
    
    /** @var float 最大获取时间 */
    private float $maxAcquireTime = 0.0;
    
    /** @var float 最小获取时间 */
    private float $minAcquireTime = PHP_FLOAT_MAX;
    
    /** @var int 连接超时时间(毫秒) */
    private int $connectTimeout;
    
    /** @var int 读取超时时间(毫秒) */
    private int $readTimeout;
    
    /** @var string Redis服务器地址 */
    private string $host;
    
    /** @var int Redis服务器端口 */
    private int $port;
    
    /** @var string|null Redis密码 */
    private ?string $password;
    
    /** @var int Redis数据库 */
    private int $database;
    
    /** @var string|null Redis前缀 */
    private ?string $prefix;

    /**
     * 构造函数
     *
     * @param array $config 连接池配置
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'min_size' => 5,
            'max_size' => 20,
            'connect_timeout' => 5,
            'read_timeout' => 5,
            'wait_timeout' => 10,
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => null,
            'database' => 0,
            'prefix' => null,
        ], $config);
        
        $this->connectTimeout = $this->config['connect_timeout'] * 1000;
        $this->readTimeout = $this->config['read_timeout'] * 1000;
        $this->host = $this->config['host'];
        $this->port = $this->config['port'];
        $this->password = $this->config['password'];
        $this->database = $this->config['database'];
        $this->prefix = $this->config['prefix'];
        
        $this->initializePool();
    }

    /**
     * {@inheritdoc}
     */
    public function getConnection(): Redis
    {
        if ($this->isClosed) {
            throw new RuntimeException('Connection pool is closed');
        }
        
        $startTime = microtime(true);
        
        // 尝试从空闲连接中获取
        if (!empty($this->idleConnections)) {
            $connection = array_pop($this->idleConnections);
            
            // 检查连接是否仍然有效
            if ($this->isConnectionValid($connection)) {
                $this->activeConnections[] = $connection;
                $this->recordAcquireTime(microtime(true) - $startTime);
                return $connection;
            } else {
                // 连接无效，减少当前大小并继续尝试创建新连接
                $this->currentSize--;
            }
        }
        
        // 如果没有空闲连接且未达到最大连接数，创建新连接
        if ($this->currentSize < $this->config['max_size']) {
            $connection = $this->createConnection();
            $this->activeConnections[] = $connection;
            $this->recordAcquireTime(microtime(true) - $startTime);
            return $connection;
        }
        
        // 等待连接可用
        $timeout = $this->config['wait_timeout'];
        $waitTime = 0;
        $sleepInterval = 0.01; // 10ms
        
        while ($waitTime < $timeout) {
            usleep($sleepInterval * 1000000);
            $waitTime += $sleepInterval;
            
            // 回收失效连接
            $this->recycleConnections();
            
            if (!empty($this->idleConnections)) {
                $connection = array_pop($this->idleConnections);
                if ($this->isConnectionValid($connection)) {
                    $this->activeConnections[] = $connection;
                    $this->recordAcquireTime(microtime(true) - $startTime);
                    return $connection;
                } else {
                    $this->currentSize--;
                }
            }
        }
        
        throw new RuntimeException('Timeout while waiting for connection');
    }

    /**
     * {@inheritdoc}
     */
    public function returnConnection(Redis $connection): void
    {
        if ($this->isClosed) {
            return;
        }
        
        // 从活跃连接中移除
        foreach ($this->activeConnections as $key => $activeConnection) {
            if ($activeConnection === $connection) {
                unset($this->activeConnections[$key]);
                break;
            }
        }
        
        // 检查连接是否仍然有效
        if ($this->isConnectionValid($connection)) {
            $this->idleConnections[] = $connection;
        } else {
            $this->currentSize--;
        }
        
        // 重新索引数组
        $this->activeConnections = array_values($this->activeConnections);
    }

    /**
     * {@inheritdoc}
     */
    public function close(): void
    {
        if ($this->isClosed) {
            return;
        }
        
        $this->isClosed = true;
        
        // 关闭所有空闲连接
        foreach ($this->idleConnections as $connection) {
            try {
                $connection->close();
            } catch (Exception $e) {
                // 忽略关闭时的错误
            }
        }
        
        // 关闭所有活跃连接
        foreach ($this->activeConnections as $connection) {
            try {
                $connection->close();
            } catch (Exception $e) {
                // 忽略关闭时的错误
            }
        }
        
        $this->idleConnections = [];
        $this->activeConnections = [];
        $this->currentSize = 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getPoolSize(): int
    {
        return $this->currentSize;
    }

    /**
     * {@inheritdoc}
     */
    public function getActiveConnections(): int
    {
        return count($this->activeConnections);
    }

    /**
     * {@inheritdoc}
     */
    public function getIdleConnections(): int
    {
        return count($this->idleConnections);
    }

    /**
     * {@inheritdoc}
     */
    public function getStats(): array
    {
        $avgWaitTime = $this->totalAcquires > 0 ? $this->totalWaitTime / $this->totalAcquires : 0;
        
        return [
            'total_acquires' => $this->totalAcquires,
            'avg_wait_time' => round($avgWaitTime, 4),
            'max_wait_time' => round($this->maxAcquireTime, 4),
            'min_wait_time' => $this->minAcquireTime === PHP_FLOAT_MAX ? 0 : round($this->minAcquireTime, 4),
            'pool_size' => $this->currentSize,
            'active_connections' => count($this->activeConnections),
            'idle_connections' => count($this->idleConnections),
            'is_closed' => $this->isClosed,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function warmUp(): void
    {
        while ($this->currentSize < $this->config['min_size']) {
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
     * @return Redis
     * @throws RuntimeException 当连接创建失败时抛出
     */
    private function createConnection(): Redis
    {
        try {
            $redis = new Redis();
            
            // 设置连接超时
            $redis->setOption(Redis::OPT_CONNECT_TIMEOUT, $this->connectTimeout);
            $redis->setOption(Redis::OPT_READ_TIMEOUT, $this->readTimeout);
            
            // 连接Redis服务器
            if (!$redis->connect($this->host, $this->port)) {
                throw new RuntimeException("Failed to connect to Redis server at {$this->host}:{$this->port}");
            }
            
            // 如果有密码，进行认证
            if ($this->password !== null) {
                if (!$redis->auth($this->password)) {
                    throw new RuntimeException("Redis authentication failed");
                }
            }
            
            // 选择数据库
            if ($this->database > 0) {
                $redis->select($this->database);
            }
            
            // 设置前缀
            if ($this->prefix !== null) {
                $redis->setOption(Redis::OPT_PREFIX, $this->prefix);
            }
            
            $this->currentSize++;
            return $redis;
        } catch (RedisException $e) {
            throw new RuntimeException("Failed to create Redis connection: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 检查连接是否有效
     *
     * @param Redis $connection Redis连接
     * @return bool 连接是否有效
     */
    private function isConnectionValid(Redis $connection): bool
    {
        try {
            // 使用ping命令检查连接是否有效
            return $connection->isConnected() && $connection->ping() === '+PONG';
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 回收失效连接
     */
    private function recycleConnections(): void
    {
        foreach ($this->idleConnections as $key => $connection) {
            if (!$this->isConnectionValid($connection)) {
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
        for ($i = 0; $i < $this->config['min_size']; $i++) {
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