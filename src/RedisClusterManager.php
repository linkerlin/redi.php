<?php

namespace Rediphp;

use RedisCluster;
use RuntimeException;

/**
 * Redis集群管理器
 * 提供Redis Cluster模式的支持和管理
 */
class RedisClusterManager
{
    private ?RedisCluster $cluster = null;
    private ClusterConfig $config;
    private bool $connected = false;

    /**
     * 创建Redis集群管理器
     *
     * @param ClusterConfig $config 集群配置
     */
    public function __construct(ClusterConfig $config)
    {
        $this->config = $config;
    }

    /**
     * 连接到Redis集群
     */
    public function connect(): void
    {
        if ($this->connected) {
            return;
        }

        try {
            $this->cluster = new RedisCluster(
                null, // 集群名称，null表示自动发现
                $this->config->getNodes(),
                $this->config->getTimeout(),
                $this->config->getReadTimeout(),
                $this->config->isPersistent(),
                $this->config->getPassword()
            );

            // 设置集群选项
            $this->cluster->setOption(RedisCluster::OPT_SLAVE_FAILOVER, $this->config->getClusterFailover());
            $this->cluster->setOption(RedisCluster::OPT_TCP_KEEPALIVE, 60);
            $this->cluster->setOption(RedisCluster::OPT_READ_TIMEOUT, $this->config->getReadTimeout());

            $this->connected = true;
        } catch (\Exception $e) {
            throw new RuntimeException("Redis cluster connection failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 获取RedisCluster实例
     */
    public function getCluster(): RedisCluster
    {
        if (!$this->connected) {
            $this->connect();
        }
        return $this->cluster;
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
        $cluster = $this->getCluster();
        
        try {
            return call_user_func_array([$cluster, $command], $arguments);
        } catch (\Exception $e) {
            throw new RuntimeException("Redis cluster command failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 获取集群信息
     */
    public function getClusterInfo(): array
    {
        $cluster = $this->getCluster();
        
        try {
            $info = $cluster->info();
            $slots = $cluster->cluster('SLOTS');
            
            return [
                'cluster_state' => $info['cluster_state'] ?? 'unknown',
                'cluster_slots_assigned' => $info['cluster_slots_assigned'] ?? 0,
                'cluster_slots_ok' => $info['cluster_slots_ok'] ?? 0,
                'cluster_slots_pfail' => $info['cluster_slots_pfail'] ?? 0,
                'cluster_slots_fail' => $info['cluster_slots_fail'] ?? 0,
                'cluster_known_nodes' => $info['cluster_known_nodes'] ?? 0,
                'cluster_size' => $info['cluster_size'] ?? 0,
                'cluster_current_epoch' => $info['cluster_current_epoch'] ?? 0,
                'cluster_my_epoch' => $info['cluster_my_epoch'] ?? 0,
                'slots' => $slots,
            ];
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to get cluster info: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 获取集群节点信息
     */
    public function getNodes(): array
    {
        $cluster = $this->getCluster();
        
        try {
            $nodes = [];
            $slots = $cluster->cluster('SLOTS');
            
            foreach ($slots as $slotInfo) {
                $master = $slotInfo[0]; // 主节点信息
                $nodes[] = [
                    'host' => $master[0],
                    'port' => $master[1],
                    'node_id' => $master[2] ?? null,
                    'slots' => [$slotInfo[1], $slotInfo[2]], // 槽位范围
                    'role' => 'master',
                ];
                
                // 如果有从节点
                for ($i = 3; $i < count($slotInfo); $i++) {
                    $slave = $slotInfo[$i];
                    $nodes[] = [
                        'host' => $slave[0],
                        'port' => $slave[1],
                        'node_id' => $slave[2] ?? null,
                        'slots' => [], // 从节点不持有槽位
                        'role' => 'slave',
                    ];
                }
            }
            
            return $nodes;
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to get cluster nodes: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 检查集群健康状态
     */
    public function isHealthy(): bool
    {
        try {
            $info = $this->getClusterInfo();
            return $info['cluster_state'] === 'ok' && 
                   $info['cluster_slots_assigned'] === $info['cluster_slots_ok'];
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 获取键所在的槽位
     */
    public function getSlot(string $key): int
    {
        $cluster = $this->getCluster();
        return $cluster->_masters() ? $cluster->_masters() : 0; // 简化实现，实际应计算CRC16
    }

    /**
     * 关闭集群连接
     */
    public function close(): void
    {
        if ($this->connected && $this->cluster) {
            $this->cluster->close();
            $this->connected = false;
            $this->cluster = null;
        }
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
    public function getConfig(): ClusterConfig
    {
        return $this->config;
    }
}