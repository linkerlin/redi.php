<?php

namespace Rediphp;

/**
 * Redis集群配置管理器
 * 支持Redis Cluster模式的配置管理
 */
class ClusterConfig
{
    private array $config;

    /**
     * 创建集群配置实例
     *
     * @param array $config 集群配置数组，支持以下选项：
     *                      - nodes: 集群节点数组，格式为 ['host:port', 'host:port', ...]
     *                      - timeout: 连接超时时间（秒）
     *                      - read_timeout: 读取超时时间（秒）
     *                      - password: Redis密码
     *                      - persistent: 是否使用持久连接
     *                      - retry_interval: 重试间隔（毫秒）
     *                      - cluster_failover: 集群故障转移策略
     */
    public function __construct(array $config = [])
    {
        $defaultConfig = [
            'nodes' => ['127.0.0.1:6379'],
            'timeout' => 5.0,
            'read_timeout' => 5.0,
            'password' => null,
            'persistent' => false,
            'retry_interval' => 100,
            'cluster_failover' => RedisCluster::FAILOVER_NONE,
        ];

        $this->config = array_merge($defaultConfig, $config);
        $this->validateConfig();
    }

    /**
     * 验证配置有效性
     */
    private function validateConfig(): void
    {
        if (empty($this->config['nodes'])) {
            throw new \InvalidArgumentException('Cluster nodes cannot be empty');
        }

        foreach ($this->config['nodes'] as $node) {
            if (!preg_match('/^[^:]+:\d+$/', $node)) {
                throw new \InvalidArgumentException("Invalid node format: $node. Expected format: host:port");
            }
        }

        if ($this->config['timeout'] <= 0) {
            throw new \InvalidArgumentException('Timeout must be greater than 0');
        }

        if ($this->config['read_timeout'] <= 0) {
            throw new \InvalidArgumentException('Read timeout must be greater than 0');
        }
    }

    /**
     * 获取集群配置
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * 获取集群节点列表
     */
    public function getNodes(): array
    {
        return $this->config['nodes'];
    }

    /**
     * 获取连接超时时间
     */
    public function getTimeout(): float
    {
        return $this->config['timeout'];
    }

    /**
     * 获取读取超时时间
     */
    public function getReadTimeout(): float
    {
        return $this->config['read_timeout'];
    }

    /**
     * 获取密码
     */
    public function getPassword(): ?string
    {
        return $this->config['password'];
    }

    /**
     * 是否使用持久连接
     */
    public function isPersistent(): bool
    {
        return $this->config['persistent'];
    }

    /**
     * 获取重试间隔
     */
    public function getRetryInterval(): int
    {
        return $this->config['retry_interval'];
    }

    /**
     * 获取故障转移策略
     */
    public function getClusterFailover(): int
    {
        return $this->config['cluster_failover'];
    }

    /**
     * 从环境变量加载集群配置
     */
    public static function fromEnvironment(): self
    {
        $nodes = getenv('REDIS_CLUSTER_NODES');
        if (!$nodes) {
            // 如果没有配置集群节点，使用单节点配置
            $host = getenv('REDIS_HOST') ?: '127.0.0.1';
            $port = getenv('REDIS_PORT') ?: 6379;
            $nodes = ["$host:$port"];
        } else {
            $nodes = array_map('trim', explode(',', $nodes));
        }

        $config = [
            'nodes' => $nodes,
            'timeout' => (float)(getenv('REDIS_TIMEOUT') ?: 5.0),
            'read_timeout' => (float)(getenv('REDIS_READ_TIMEOUT') ?: 5.0),
            'password' => getenv('REDIS_PASSWORD') ?: null,
            'persistent' => (bool)getenv('REDIS_PERSISTENT'),
            'retry_interval' => (int)(getenv('REDIS_RETRY_INTERVAL') ?: 100),
            'cluster_failover' => (int)(getenv('REDIS_CLUSTER_FAILOVER') ?: RedisCluster::FAILOVER_NONE),
        ];

        return new self($config);
    }
}