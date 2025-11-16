<?php

namespace Rediphp;

use RedisCluster;
use RuntimeException;

/**
 * Redisson集群客户端
 * 支持Redis Cluster模式的Redisson兼容客户端
 */
class RedissonClusterClient
{
    private RedisClusterManager $clusterManager;
    private array $config;
    private bool $usePool = false;

    /**
     * 创建Redisson集群客户端实例
     *
     * @param array $config 集群配置数组，支持以下选项：
     *                      - cluster_nodes: 集群节点数组，格式为 ['host:port', 'host:port', ...]
     *                      - timeout: 连接超时时间（秒）
     *                      - read_timeout: 读取超时时间（秒）
     *                      - password: Redis密码
     *                      - persistent: 是否使用持久连接
     *                      - retry_interval: 重试间隔（毫秒）
     *                      - cluster_failover: 集群故障转移策略
     *                      - use_pool: 是否使用连接池（集群模式下暂不支持）
     */
    public function __construct(array $config = [])
    {
        $defaultConfig = [
            'cluster_nodes' => ['127.0.0.1:6379'],
            'timeout' => 5.0,
            'read_timeout' => 5.0,
            'password' => null,
            'persistent' => false,
            'retry_interval' => 100,
            'cluster_failover' => RedisCluster::FAILOVER_NONE,
            'use_pool' => false, // 集群模式下暂不支持连接池
        ];

        $this->config = array_merge($defaultConfig, $config);
        $this->usePool = (bool)$this->config['use_pool'];

        // 集群模式下不支持连接池
        if ($this->usePool) {
            throw new \InvalidArgumentException('Connection pooling is not supported in cluster mode');
        }

        // 创建集群配置
        $clusterConfig = new ClusterConfig([
            'nodes' => $this->config['cluster_nodes'],
            'timeout' => $this->config['timeout'],
            'read_timeout' => $this->config['read_timeout'],
            'password' => $this->config['password'],
            'persistent' => $this->config['persistent'],
            'retry_interval' => $this->config['retry_interval'],
            'cluster_failover' => $this->config['cluster_failover'],
        ]);

        $this->clusterManager = new RedisClusterManager($clusterConfig);
    }

    /**
     * 获取RedisCluster实例
     */
    public function getCluster(): RedisCluster
    {
        return $this->clusterManager->getCluster();
    }

    /**
     * 获取Redis连接（集群模式下返回RedisCluster实例）
     */
    public function getRedis(): RedisCluster
    {
        return $this->getCluster();
    }

    /**
     * 执行Redis操作
     *
     * @param callable $operation 要执行的操作
     * @return mixed
     */
    public function execute(callable $operation)
    {
        $cluster = $this->getCluster();
        
        try {
            return $operation($cluster);
        } catch (\Exception $e) {
            throw new RuntimeException("Redis cluster operation failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 获取分布式Map（集群兼容版本）
     *
     * @param string $name Map名称
     * @return RMap
     */
    public function getMap(string $name): RMap
    {
        return new RMap($this, $name);
    }

    /**
     * 获取分布式List（集群兼容版本）
     *
     * @param string $name List名称
     * @return RList
     */
    public function getList(string $name): RList
    {
        return new RList($this, $name);
    }

    /**
     * 获取分布式Set（集群兼容版本）
     *
     * @param string $name Set名称
     * @return RSet
     */
    public function getSet(string $name): RSet
    {
        return new RSet($this, $name);
    }

    /**
     * 获取分布式SortedSet（集群兼容版本）
     *
     * @param string $name SortedSet名称
     * @return RSortedSet
     */
    public function getSortedSet(string $name): RSortedSet
    {
        return new RSortedSet($this, $name);
    }

    /**
     * 获取分布式Queue（集群兼容版本）
     *
     * @param string $name Queue名称
     * @return RQueue
     */
    public function getQueue(string $name): RQueue
    {
        return new RQueue($this, $name);
    }

    /**
     * 获取分布式Deque（集群兼容版本）
     *
     * @param string $name Deque名称
     * @return RDeque
     */
    public function getDeque(string $name): RDeque
    {
        return new RDeque($this, $name);
    }

    /**
     * 获取分布式Lock（集群兼容版本）
     *
     * @param string $name Lock名称
     * @return RLock
     */
    public function getLock(string $name): RLock
    {
        return new RLock($this, $name);
    }

    /**
     * 获取分布式ReadWriteLock（集群兼容版本）
     *
     * @param string $name Lock名称
     * @return RReadWriteLock
     */
    public function getReadWriteLock(string $name): RReadWriteLock
    {
        return new RReadWriteLock($this, $name);
    }

    /**
     * 获取分布式Semaphore（集群兼容版本）
     *
     * @param string $name Semaphore名称
     * @return RSemaphore
     */
    public function getSemaphore(string $name): RSemaphore
    {
        return new RSemaphore($this, $name);
    }

    /**
     * 获取分布式CountDownLatch（集群兼容版本）
     *
     * @param string $name Latch名称
     * @return RCountDownLatch
     */
    public function getCountDownLatch(string $name): RCountDownLatch
    {
        return new RCountDownLatch($this, $name);
    }

    /**
     * 获取分布式AtomicLong（集群兼容版本）
     *
     * @param string $name AtomicLong名称
     * @return RAtomicLong
     */
    public function getAtomicLong(string $name): RAtomicLong
    {
        return new RAtomicLong($this, $name);
    }

    /**
     * 获取分布式AtomicDouble（集群兼容版本）
     *
     * @param string $name AtomicDouble名称
     * @return RAtomicDouble
     */
    public function getAtomicDouble(string $name): RAtomicDouble
    {
        return new RAtomicDouble($this, $name);
    }

    /**
     * 获取分布式Bucket（集群兼容版本）
     *
     * @param string $name Bucket名称
     * @return RBucket
     */
    public function getBucket(string $name): RBucket
    {
        return new RBucket($this, $name);
    }

    /**
     * 获取分布式BitSet（集群兼容版本）
     *
     * @param string $name BitSet名称
     * @return RBitSet
     */
    public function getBitSet(string $name): RBitSet
    {
        return new RBitSet($this, $name);
    }

    /**
     * 获取分布式BloomFilter（集群兼容版本）
     *
     * @param string $name BloomFilter名称
     * @return RBloomFilter
     */
    public function getBloomFilter(string $name): RBloomFilter
    {
        return new RBloomFilter($this, $name);
    }

    /**
     * 获取分布式Topic（集群兼容版本）
     *
     * @param string $name Topic名称
     * @return RTopic
     */
    public function getTopic(string $name): RTopic
    {
        return new RTopic($this, $name);
    }

    /**
     * 获取分布式PatternTopic（集群兼容版本）
     *
     * @param string $pattern PatternTopic名称
     * @return RPatternTopic
     */
    public function getPatternTopic(string $pattern): RPatternTopic
    {
        return new RPatternTopic($this, $pattern);
    }

    /**
     * 获取分布式HyperLogLog（集群兼容版本）
     *
     * @param string $name HyperLogLog名称
     * @return RHyperLogLog
     */
    public function getHyperLogLog(string $name): RHyperLogLog
    {
        return new RHyperLogLog($this, $name);
    }

    /**
     * 获取分布式Geo（集群兼容版本）
     *
     * @param string $name Geo名称
     * @return RGeo
     */
    public function getGeo(string $name): RGeo
    {
        return new RGeo($this, $name);
    }

    /**
     * 获取分布式Stream（集群兼容版本）
     *
     * @param string $name Stream名称
     * @return RStream
     */
    public function getStream(string $name): RStream
    {
        return new RStream($this, $name);
    }

    /**
     * 获取分布式TimeSeries（集群兼容版本）
     *
     * @param string $name TimeSeries名称
     * @return RTimeSeries
     */
    public function getTimeSeries(string $name): RTimeSeries
    {
        return new RTimeSeries($this, $name);
    }

    /**
     * 获取集群管理器
     */
    public function getClusterManager(): RedisClusterManager
    {
        return $this->clusterManager;
    }

    /**
     * 获取集群信息
     */
    public function getClusterInfo(): array
    {
        return $this->clusterManager->getClusterInfo();
    }

    /**
     * 获取集群节点信息
     */
    public function getNodes(): array
    {
        return $this->clusterManager->getNodes();
    }

    /**
     * 检查集群健康状态
     */
    public function isHealthy(): bool
    {
        return $this->clusterManager->isHealthy();
    }

    /**
     * 关闭集群连接
     */
    public function close(): void
    {
        $this->clusterManager->close();
    }

    /**
     * 析构函数
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * 检查是否已连接
     */
    public function isConnected(): bool
    {
        return $this->clusterManager->isConnected();
    }

    /**
     * 获取配置信息
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}