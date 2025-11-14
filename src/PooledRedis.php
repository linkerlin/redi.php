<?php

namespace Rediphp;

use Redis;
use Exception;
use RuntimeException;

/**
 * 池化的Redis连接对象
 * 包装Redis连接，提供连接池管理功能
 */
class PooledRedis
{
    private RedisPool $pool;
    private array $config;
    private Redis $redis;
    private bool $isConnected = false;
    private bool $isClosed = false;
    private float $lastUsedTime;

    /**
     * 创建池化的Redis连接
     *
     * @param RedisPool $pool 所属的连接池
     * @param array $config Redis连接配置
     */
    public function __construct(RedisPool $pool, array $config)
    {
        $this->pool = $pool;
        $this->config = $config;
        $this->redis = new Redis();
        $this->lastUsedTime = microtime(true);
    }

    /**
     * 连接到Redis服务器
     *
     * @return bool
     * @throws RuntimeException 当连接失败时抛出
     */
    public function connect(): bool
    {
        if ($this->isConnected) {
            return true;
        }

        try {
            // 验证数据库号
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
                throw new RuntimeException("Redis connection failed: " . ($error ?: 'Unknown error'));
            }

            if ($this->config['password'] !== null) {
                $this->redis->auth($this->config['password']);
            }

            // 选择数据库
            $this->redis->select($this->config['database']);

            $this->isConnected = true;
            $this->lastUsedTime = microtime(true);
            
            return true;
        } catch (Exception $e) {
            throw new RuntimeException("Redis connection error: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 关闭连接
     */
    public function close(): void
    {
        if ($this->isConnected && !$this->isClosed) {
            try {
                $this->redis->close();
            } catch (Exception $e) {
                // 忽略关闭时的异常
            }
            $this->isConnected = false;
            $this->isClosed = true;
        }
    }

    /**
     * 检查连接是否有效
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        if (!$this->isConnected || $this->isClosed) {
            return false;
        }

        try {
            // 发送PING命令检查连接状态
            $result = $this->redis->ping();
            return $result === 'PONG' || $result === true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 获取底层Redis实例
     * 注意：使用此方法后需要手动调用returnToPool()
     *
     * @return Redis
     */
    public function getRedis(): Redis
    {
        $this->lastUsedTime = microtime(true);
        return $this->redis;
    }

    /**
     * 将连接归还到连接池
     */
    public function returnToPool(): void
    {
        if (!$this->isClosed) {
            $this->pool->returnConnection($this);
        }
    }

    /**
     * 获取最后使用时间
     *
     * @return float
     */
    public function getLastUsedTime(): float
    {
        return $this->lastUsedTime;
    }

    /**
     * 获取连接空闲时间
     *
     * @return float
     */
    public function getIdleTime(): float
    {
        return microtime(true) - $this->lastUsedTime;
    }

    /**
     * 魔术方法，代理Redis方法调用
     *
     * @param string $name 方法名
     * @param array $arguments 参数
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        if (!$this->isConnected) {
            throw new RuntimeException("Redis connection is not established");
        }

        if ($this->isClosed) {
            throw new RuntimeException("Redis connection is closed");
        }

        try {
            $this->lastUsedTime = microtime(true);
            return call_user_func_array([$this->redis, $name], $arguments);
        } catch (Exception $e) {
            // 检查是否是连接错误
            if (strpos($e->getMessage(), 'connection') !== false) {
                $this->isConnected = false;
            }
            throw $e;
        }
    }

    /**
     * 析构函数，确保连接正确关闭
     */
    public function __destruct()
    {
        if (!$this->isClosed) {
            $this->close();
        }
    }
}