<?php

namespace Rediphp;

/**
 * Redis Sentinel配置管理器
 * 支持Redis Sentinel模式的配置管理
 */
class SentinelConfig
{
    private array $config;

    /**
     * 创建Sentinel配置实例
     *
     * @param array $config Sentinel配置数组，支持以下选项：
     *                      - sentinels: Sentinel节点数组，格式为 ['host:port', 'host:port', ...]
     *                      - master_name: 主节点名称
     *                      - timeout: 连接超时时间（秒）
     *                      - read_timeout: 读取超时时间（秒）
     *                      - password: Redis密码
     *                      - database: Redis数据库编号
     *                      - retry_interval: 重试间隔（毫秒）
     *                      - sentinel_password: Sentinel密码（可选）
     */
    public function __construct(array $config = [])
    {
        $defaultConfig = [
            'sentinels' => ['127.0.0.1:26379'],
            'master_name' => 'mymaster',
            'timeout' => 5.0,
            'read_timeout' => 5.0,
            'password' => null,
            'database' => 0,
            'retry_interval' => 100,
            'sentinel_password' => null,
        ];

        $this->config = array_merge($defaultConfig, $config);
        $this->validateConfig();
    }

    /**
     * 验证配置有效性
     */
    private function validateConfig(): void
    {
        if (empty($this->config['sentinels'])) {
            throw new \InvalidArgumentException('Sentinel nodes cannot be empty');
        }

        foreach ($this->config['sentinels'] as $sentinel) {
            if (!preg_match('/^[^:]+:\d+$/', $sentinel)) {
                throw new \InvalidArgumentException("Invalid sentinel format: $sentinel. Expected format: host:port");
            }
        }

        if (empty($this->config['master_name'])) {
            throw new \InvalidArgumentException('Master name cannot be empty');
        }

        if ($this->config['timeout'] <= 0) {
            throw new \InvalidArgumentException('Timeout must be greater than 0');
        }

        if ($this->config['read_timeout'] <= 0) {
            throw new \InvalidArgumentException('Read timeout must be greater than 0');
        }

        if ($this->config['database'] < 0 || $this->config['database'] > 15) {
            throw new \InvalidArgumentException('Database must be between 0 and 15');
        }
    }

    /**
     * 获取Sentinel配置
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * 获取Sentinel节点列表
     */
    public function getSentinels(): array
    {
        return $this->config['sentinels'];
    }

    /**
     * 获取主节点名称
     */
    public function getMasterName(): string
    {
        return $this->config['master_name'];
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
     * 获取Redis密码
     */
    public function getPassword(): ?string
    {
        return $this->config['password'];
    }

    /**
     * 获取数据库编号
     */
    public function getDatabase(): int
    {
        return $this->config['database'];
    }

    /**
     * 获取重试间隔
     */
    public function getRetryInterval(): int
    {
        return $this->config['retry_interval'];
    }

    /**
     * 获取Sentinel密码
     */
    public function getSentinelPassword(): ?string
    {
        return $this->config['sentinel_password'];
    }

    /**
     * 从环境变量加载Sentinel配置
     */
    public static function fromEnvironment(): self
    {
        $sentinels = getenv('REDIS_SENTINELS');
        if (!$sentinels) {
            // 如果没有配置Sentinel节点，使用默认配置
            $sentinels = ['127.0.0.1:26379'];
        } else {
            $sentinels = array_map('trim', explode(',', $sentinels));
        }

        $config = [
            'sentinels' => $sentinels,
            'master_name' => getenv('REDIS_SENTINEL_MASTER') ?: 'mymaster',
            'timeout' => (float)(getenv('REDIS_TIMEOUT') ?: 5.0),
            'read_timeout' => (float)(getenv('REDIS_READ_TIMEOUT') ?: 5.0),
            'password' => getenv('REDIS_PASSWORD') ?: null,
            'database' => (int)(getenv('REDIS_DB') ?: getenv('REDIS_DATABASE') ?: 0),
            'retry_interval' => (int)(getenv('REDIS_RETRY_INTERVAL') ?: 100),
            'sentinel_password' => getenv('REDIS_SENTINEL_PASSWORD') ?: null,
        ];

        return new self($config);
    }
}