<?php

namespace Rediphp;

use RedisCluster;
use Rediphp\RedissonClusterClient;
use Rediphp\RedissonSentinelClient;

/**
 * 环境配置管理器
 * 统一管理Redis连接配置
 */
class Config
{
    private static ?array $config = null;

    /**
     * 加载配置
     * 优先级：.env文件 > 环境变量 > 默认值
     */
    public static function load(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        // 尝试加载.env文件
        self::loadEnvFile();

        // 检查是否为集群模式
        $clusterNodes = getenv('REDIS_CLUSTER_NODES');
        $isClusterMode = !empty($clusterNodes);

        // 检查是否为Sentinel模式
        $sentinelNodes = getenv('REDIS_SENTINELS');
        $isSentinelMode = !empty($sentinelNodes);

        // 构建配置
        self::$config = [
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => (int)(getenv('REDIS_PORT') ?: 6379),
            'password' => getenv('REDIS_PASSWORD') ?: null,
            'database' => (int)(getenv('REDIS_DB') ?: getenv('REDIS_DATABASE') ?: 0),
            'timeout' => (float)(getenv('REDIS_TIMEOUT') ?: 0.0),
            'is_cluster' => $isClusterMode,
            'cluster_nodes' => $isClusterMode ? array_map('trim', explode(',', $clusterNodes)) : [],
            'cluster_timeout' => (float)(getenv('REDIS_CLUSTER_TIMEOUT') ?: 5.0),
            'cluster_read_timeout' => (float)(getenv('REDIS_CLUSTER_READ_TIMEOUT') ?: 5.0),
            'cluster_failover' => (int)(getenv('REDIS_CLUSTER_FAILOVER') ?: RedisCluster::FAILOVER_NONE),
            'is_sentinel' => $isSentinelMode,
            'sentinel_nodes' => $isSentinelMode ? array_map('trim', explode(',', $sentinelNodes)) : [],
            'sentinel_master_name' => getenv('REDIS_SENTINEL_MASTER') ?: 'mymaster',
            'sentinel_timeout' => (float)(getenv('REDIS_SENTINEL_TIMEOUT') ?: 5.0),
            'sentinel_read_timeout' => (float)(getenv('REDIS_SENTINEL_READ_TIMEOUT') ?: 5.0),
            'sentinel_retry_interval' => (int)(getenv('REDIS_SENTINEL_RETRY_INTERVAL') ?: 100),
            'sentinel_password' => getenv('REDIS_SENTINEL_PASSWORD') ?: null,
        ];

        return self::$config;
    }

    /**
     * 加载.env文件
     */
    private static function loadEnvFile(): void
    {
        $envFile = __DIR__ . '/../.env';
        if (!file_exists($envFile)) {
            $envFile = __DIR__ . '/../../.env';
        }

        if (!file_exists($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // 跳过注释行
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // 解析 KEY=VALUE 格式
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // 只设置尚未设置的环境变量
                if (!getenv($key)) {
                    putenv("$key=$value");
                }
            }
        }
    }

    /**
     * 获取Redis配置
     */
    public static function getRedisConfig(): array
    {
        return self::load();
    }

    /**
     * 创建RedissonClient实例
     * 根据配置自动选择单节点模式、集群模式或Sentinel模式
     */
    public static function createClient(array $additionalConfig = []): RedissonClient
    {
        $config = array_merge(self::load(), $additionalConfig);
        
        // 检查是否为Sentinel模式
        if ($config['is_sentinel'] && !empty($config['sentinel_nodes'])) {
            return new RedissonSentinelClient($config);
        }
        
        // 检查是否为集群模式
        if ($config['is_cluster'] && !empty($config['cluster_nodes'])) {
            return new RedissonClusterClient($config);
        }
        
        return new RedissonClient($config);
    }

    /**
     * 创建集群客户端实例
     */
    public static function createClusterClient(array $additionalConfig = []): RedissonClusterClient
    {
        $config = array_merge(self::load(), $additionalConfig);
        return new RedissonClusterClient($config);
    }

    /**
     * 创建Sentinel客户端实例
     */
    public static function createSentinelClient(array $additionalConfig = []): RedissonSentinelClient
    {
        $config = array_merge(self::load(), $additionalConfig);
        return new RedissonSentinelClient($config);
    }
}