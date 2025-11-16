<?php

namespace Rediphp;

use Redis;
use RuntimeException;

/**
 * Redis Sentinel管理器
 * 提供Redis Sentinel模式的支持和管理
 */
class RedisSentinelManager
{
    private SentinelConfig $config;
    private ?Redis $redis = null;
    private bool $connected = false;
    private array $sentinelConnections = [];

    /**
     * 创建Redis Sentinel管理器
     *
     * @param SentinelConfig $config Sentinel配置
     */
    public function __construct(SentinelConfig $config)
    {
        $this->config = $config;
    }

    /**
     * 连接到Redis主节点（通过Sentinel发现）
     */
    public function connect(): void
    {
        if ($this->connected) {
            return;
        }

        try {
            // 通过Sentinel发现主节点
            $masterInfo = $this->discoverMaster();
            
            // 连接到主节点
            $this->redis = new Redis();
            $connected = $this->redis->connect(
                $masterInfo['ip'],
                $masterInfo['port'],
                $this->config->getTimeout()
            );

            if (!$connected) {
                $error = $this->redis->getLastError();
                throw new RuntimeException("Redis connection failed: " . ($error ?: 'Unknown error'));
            }

            // 认证和选择数据库
            if ($this->config->getPassword() !== null) {
                $this->redis->auth($this->config->getPassword());
            }
            $this->redis->select($this->config->getDatabase());

            $this->connected = true;
        } catch (\Exception $e) {
            throw new RuntimeException("Redis Sentinel connection failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 通过Sentinel发现主节点
     */
    private function discoverMaster(): array
    {
        $sentinels = $this->config->getSentinels();
        $masterName = $this->config->getMasterName();
        $sentinelPassword = $this->config->getSentinelPassword();

        foreach ($sentinels as $sentinel) {
            list($host, $port) = explode(':', $sentinel);
            
            try {
                $sentinelRedis = new Redis();
                $connected = $sentinelRedis->connect($host, (int)$port, $this->config->getTimeout());
                
                if (!$connected) {
                    continue; // 尝试下一个Sentinel
                }

                // Sentinel认证（如果需要）
                if ($sentinelPassword !== null) {
                    $sentinelRedis->auth($sentinelPassword);
                }

                // 获取主节点信息
                $masterInfo = $sentinelRedis->rawCommand('SENTINEL', 'get-master-addr-by-name', $masterName);
                
                if ($masterInfo && is_array($masterInfo) && count($masterInfo) >= 2) {
                    $this->sentinelConnections[] = $sentinelRedis; // 保留连接用于监控
                    return [
                        'ip' => $masterInfo[0],
                        'port' => (int)$masterInfo[1],
                        'sentinel' => $sentinel,
                    ];
                }
                
                $sentinelRedis->close();
            } catch (\Exception $e) {
                // 继续尝试下一个Sentinel
                continue;
            }
        }

        throw new RuntimeException("Failed to discover master node through any sentinel");
    }

    /**
     * 获取Redis实例
     */
    public function getRedis(): Redis
    {
        if (!$this->connected) {
            $this->connect();
        }
        return $this->redis;
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
        $redis = $this->getRedis();
        
        try {
            return call_user_func_array([$redis, $command], $arguments);
        } catch (\Exception $e) {
            // 如果连接失败，尝试重新连接
            if (strpos($e->getMessage(), 'connection') !== false) {
                $this->reconnect();
                $redis = $this->getRedis();
                return call_user_func_array([$redis, $command], $arguments);
            }
            throw new RuntimeException("Redis Sentinel command failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 重新连接（故障转移处理）
     */
    private function reconnect(): void
    {
        $this->close();
        $this->connected = false;
        $this->connect();
    }

    /**
     * 获取Sentinel信息
     */
    public function getSentinelInfo(): array
    {
        $masterName = $this->config->getMasterName();
        $info = [];

        foreach ($this->sentinelConnections as $sentinel) {
            try {
                // 获取主节点信息
                $masterInfo = $sentinel->rawCommand('SENTINEL', 'master', $masterName);
                
                // 获取从节点信息
                $slavesInfo = $sentinel->rawCommand('SENTINEL', 'slaves', $masterName);
                
                // 获取Sentinel信息
                $sentinelsInfo = $sentinel->rawCommand('SENTINEL', 'sentinels', $masterName);

                $info[] = [
                    'master' => $masterInfo,
                    'slaves' => $slavesInfo,
                    'sentinels' => $sentinelsInfo,
                ];
            } catch (\Exception $e) {
                // 跳过失败的Sentinel
                continue;
            }
        }

        return $info;
    }

    /**
     * 检查主节点健康状态
     */
    public function isHealthy(): bool
    {
        try {
            $redis = $this->getRedis();
            $result = $redis->ping();
            return $result === 'PONG' || $result === true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 手动触发故障转移
     */
    public function failover(): bool
    {
        $masterName = $this->config->getMasterName();
        
        foreach ($this->sentinelConnections as $sentinel) {
            try {
                $result = $sentinel->rawCommand('SENTINEL', 'failover', $masterName);
                if ($result === 'OK') {
                    // 故障转移后重新连接
                    $this->reconnect();
                    return true;
                }
            } catch (\Exception $e) {
                // 继续尝试下一个Sentinel
                continue;
            }
        }

        return false;
    }

    /**
     * 关闭连接
     */
    public function close(): void
    {
        if ($this->connected && $this->redis) {
            $this->redis->close();
            $this->connected = false;
            $this->redis = null;
        }

        // 关闭Sentinel连接
        foreach ($this->sentinelConnections as $sentinel) {
            try {
                $sentinel->close();
            } catch (\Exception $e) {
                // 忽略关闭错误
            }
        }
        $this->sentinelConnections = [];
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
        return $this->connected;
    }

    /**
     * 获取配置信息
     */
    public function getConfig(): SentinelConfig
    {
        return $this->config;
    }
}