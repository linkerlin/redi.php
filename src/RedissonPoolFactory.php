<?php

namespace RediPhp;

/**
 * RedissonPool工厂类
 * 用于创建和配置RedissonPool实例
 */
class RedissonPoolFactory
{
    /**
     * 创建RedissonPool实例
     *
     * @param array $config 连接配置数组
     * @return RedissonPoolInterface
     */
    public static function create(array $config): RedissonPoolInterface
    {
        // 验证必需的配置参数
        self::validateConfig($config);

        // 设置默认值
        $config = array_merge([
            'host' => '127.0.0.1',
            'port' => 6379,
            'timeout' => 3.0,
            'read_timeout' => 3.0,
            'password' => null,
            'database' => 0,
            'pool_size' => 10,
            'min_connections' => 2,
            'max_connections' => 20,
            'connection_timeout' => 5.0,
            'idle_timeout' => 60.0,
            'retry_interval' => 1.0,
            'max_retries' => 3,
            'auth' => null,
            'prefix' => null,
            'serializer' => null,
            'compression' => null,
            'cluster' => null,
            'sentinel' => null,
            'lazy_connect' => false,
            'persistent' => false,
            'name' => null,
            'context' => null,
            'ssl' => null,
            'unix_socket' => null,
        ], $config);

        return new RedissonPool($config);
    }

    /**
     * 创建单机Redis连接池
     *
     * @param string $host Redis服务器地址
     * @param int $port Redis服务器端口
     * @param array $options 其他配置选项
     * @return RedissonPoolInterface
     */
    public static function createSingle(string $host = '127.0.0.1', int $port = 6379, array $options = []): RedissonPoolInterface
    {
        $config = array_merge([
            'host' => $host,
            'port' => $port,
        ], $options);

        return self::create($config);
    }

    /**
     * 创建集群Redis连接池
     *
     * @param array $nodes 集群节点列表，每个元素包含host和port
     * @param array $options 其他配置选项
     * @return RedissonPoolInterface
     */
    public static function createCluster(array $nodes, array $options = []): RedissonPoolInterface
    {
        $config = array_merge([
            'cluster' => $nodes,
        ], $options);

        return self::create($config);
    }

    /**
     * 创建哨兵Redis连接池
     *
     * @param array $sentinels 哨兵节点列表，每个元素包含host和port
     * @param string $master_name 主节点名称
     * @param array $options 其他配置选项
     * @return RedissonPoolInterface
     */
    public static function createSentinel(array $sentinels, string $master_name, array $options = []): RedissonPoolInterface
    {
        $config = array_merge([
            'sentinel' => [
                'nodes' => $sentinels,
                'master' => $master_name,
            ],
        ], $options);

        return self::create($config);
    }

    /**
     * 从URL创建Redis连接池
     *
     * @param string $url Redis连接URL，格式: redis://[password@]host:port[/db]
     * @param array $options 其他配置选项
     * @return RedissonPoolInterface
     */
    public static function fromUrl(string $url, array $options = []): RedissonPoolInterface
    {
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['host'])) {
            throw new \InvalidArgumentException("Invalid Redis URL: {$url}");
        }

        $config = [
            'host' => $parsed['host'],
            'port' => $parsed['port'] ?? 6379,
        ];

        if (isset($parsed['pass'])) {
            $config['password'] = $parsed['pass'];
        }

        if (isset($parsed['path']) && $parsed['path'] !== '/') {
            $config['database'] = (int)ltrim($parsed['path'], '/');
        }

        $config = array_merge($config, $options);

        return self::create($config);
    }

    /**
     * 验证配置参数
     *
     * @param array $config 配置数组
     * @throws InvalidArgumentException 当配置无效时抛出
     */
    private static function validateConfig(array $config): void
    {
        // 检查是否提供了至少一种连接方式
        if (!isset($config['host']) && !isset($config['cluster']) && !isset($config['sentinel']) && !isset($config['unix_socket'])) {
            throw new \InvalidArgumentException('Either host, cluster, sentinel or unix_socket must be provided');
        }

        // 检查连接池大小配置
        if (isset($config['pool_size']) && (!is_int($config['pool_size']) || $config['pool_size'] <= 0)) {
            throw new \InvalidArgumentException('pool_size must be a positive integer');
        }

        if (isset($config['min_connections']) && (!is_int($config['min_connections']) || $config['min_connections'] < 0)) {
            throw new \InvalidArgumentException('min_connections must be a non-negative integer');
        }

        if (isset($config['max_connections']) && (!is_int($config['max_connections']) || $config['max_connections'] <= 0)) {
            throw new \InvalidArgumentException('max_connections must be a positive integer');
        }

        // 检查超时配置
        if (isset($config['timeout']) && (!is_float($config['timeout']) || $config['timeout'] <= 0)) {
            throw new \InvalidArgumentException('timeout must be a positive float');
        }

        if (isset($config['connection_timeout']) && (!is_float($config['connection_timeout']) || $config['connection_timeout'] <= 0)) {
            throw new \InvalidArgumentException('connection_timeout must be a positive float');
        }

        // 检查集群配置
        if (isset($config['cluster'])) {
            if (!is_array($config['cluster']) || empty($config['cluster'])) {
                throw new \InvalidArgumentException('cluster must be a non-empty array');
            }

            foreach ($config['cluster'] as $node) {
                if (!is_array($node) || !isset($node['host']) || !isset($node['port'])) {
                    throw new \InvalidArgumentException('Each cluster node must be an array with host and port');
                }
            }
        }

        // 检查哨兵配置
        if (isset($config['sentinel'])) {
            if (!is_array($config['sentinel']) || !isset($config['sentinel']['nodes']) || !isset($config['sentinel']['master'])) {
                throw new \InvalidArgumentException('sentinel must be an array with nodes and master');
            }

            if (!is_array($config['sentinel']['nodes']) || empty($config['sentinel']['nodes'])) {
                throw new \InvalidArgumentException('sentinel nodes must be a non-empty array');
            }

            foreach ($config['sentinel']['nodes'] as $node) {
                if (!is_array($node) || !isset($node['host']) || !isset($node['port'])) {
                    throw new \InvalidArgumentException('Each sentinel node must be an array with host and port');
                }
            }
        }
    }
}