<?php

namespace Rediphp;

/**
 * 支持Redis Sentinel模式的Redisson客户端
 * 提供与Redisson兼容的Sentinel模式支持
 */
class RedissonSentinelClient
{
    private RedisSentinelManager $sentinelManager;
    private bool $connected = false;

    /**
     * 创建Redisson Sentinel客户端
     *
     * @param array $config Sentinel配置数组
     */
    public function __construct(array $config = [])
    {
        $sentinelConfig = new SentinelConfig($config);
        $this->sentinelManager = new RedisSentinelManager($sentinelConfig);
    }

    /**
     * 连接到Redis Sentinel集群
     */
    public function connect(): void
    {
        if ($this->connected) {
            return;
        }

        $this->sentinelManager->connect();
        $this->connected = true;
    }

    /**
     * 获取Sentinel管理器
     */
    public function getSentinelManager(): RedisSentinelManager
    {
        return $this->sentinelManager;
    }

    /**
     * 获取Redis实例
     */
    public function getRedis(): \Redis
    {
        return $this->sentinelManager->getRedis();
    }

    /**
     * 执行Redis命令
     *
     * @param string $command Redis命令
     * @param array $arguments 命令参数
     * @return mixed
     */
    public function execute(string $command, array $arguments = [])
    {
        return $this->sentinelManager->execute($command, $arguments);
    }

    /**
     * 获取分布式Map
     */
    public function getMap(string $name): RMap
    {
        return new RMap($this, $name);
    }

    /**
     * 获取分布式List
     */
    public function getList(string $name): RList
    {
        return new RList($this, $name);
    }

    /**
     * 获取分布式Set
     */
    public function getSet(string $name): RSet
    {
        return new RSet($this, $name);
    }

    /**
     * 获取分布式SortedSet
     */
    public function getSortedSet(string $name): RSortedSet
    {
        return new RSortedSet($this, $name);
    }

    /**
     * 获取分布式Lock
     */
    public function getLock(string $name): RLock
    {
        return new RLock($this, $name);
    }

    /**
     * 获取分布式AtomicLong
     */
    public function getAtomicLong(string $name): RAtomicLong
    {
        return new RAtomicLong($this, $name);
    }

    /**
     * 获取分布式AtomicDouble
     */
    public function getAtomicDouble(string $name): RAtomicDouble
    {
        return new RAtomicDouble($this, $name);
    }

    /**
     * 检查连接状态
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * 检查健康状态
     */
    public function isHealthy(): bool
    {
        return $this->sentinelManager->isHealthy();
    }

    /**
     * 获取Sentinel信息
     */
    public function getSentinelInfo(): array
    {
        return $this->sentinelManager->getSentinelInfo();
    }

    /**
     * 手动触发故障转移
     */
    public function failover(): bool
    {
        return $this->sentinelManager->failover();
    }

    /**
     * 关闭连接
     */
    public function close(): void
    {
        $this->sentinelManager->close();
        $this->connected = false;
    }

    /**
     * 析构函数
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * 魔术方法调用Redis命令
     */
    public function __call(string $method, array $arguments)
    {
        return $this->execute($method, $arguments);
    }

    /**
     * 魔术属性访问Redis命令
     */
    public function __get(string $property)
    {
        // 将属性访问转换为Redis命令调用
        return function (...$args) use ($property) {
            return $this->execute($property, $args);
        };
    }
}