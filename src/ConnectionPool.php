<?php

declare(strict_types=1);

namespace Redi;

use InvalidArgumentException;
use RuntimeException;
use Swoole\Coroutine\Channel;

/**
 * Redis连接池
 * 
 * 提供Redis连接的复用和管理，提高性能
 */
class ConnectionPool
{
    /**
     * 连接池配置
     */
    private array $config;
    
    /**
     * 连接池
     */
    private Channel $pool;
    
    /**
     * 当前连接数
     */
    private int $currentConnections = 0;
    
    /**
     * 连接池是否已初始化
     */
    private bool $initialized = false;
    
    /**
     * 构造函数
     * 
     * @param array $config 连接池配置
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'min_connections' => 5,
            'max_connections' => 20,
            'connect_timeout' => 5.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => 60,
        ], $config);
        
        $this->pool = new Channel($this->config['max_connections']);
    }
    
    /**
     * 初始化连接池
     * 
     * @param array $redisConfig Redis连接配置
     */
    public function init(array $redisConfig): void
    {
        if ($this->initialized) {
            return;
        }
        
        $this->config['redis'] = $redisConfig;
        
        // 创建最小连接数
        for ($i = 0; $i < $this->config['min_connections']; $i++) {
            $connection = $this->createConnection();
            if ($connection) {
                $this->pool->push($connection);
            }
        }
        
        $this->initialized = true;
    }
    
    /**
     * 获取连接
     * 
     * @return Redis|false
     */
    public function getConnection()
    {
        if (!$this->initialized) {
            throw new RuntimeException('Connection pool not initialized');
        }
        
        // 尝试从池中获取连接
        $connection = $this->pool->pop($this->config['wait_timeout']);
        
        if ($connection === false) {
            // 池中没有可用连接，尝试创建新连接
            if ($this->currentConnections < $this->config['max_connections']) {
                $connection = $this->createConnection();
            }
            
            if ($connection === false) {
                throw new RuntimeException('Unable to get connection from pool');
            }
        }
        
        // 检查连接是否有效
        if (!$this->isConnectionValid($connection)) {
            $this->closeConnection($connection);
            $connection = $this->createConnection();
            
            if ($connection === false) {
                throw new RuntimeException('Unable to create valid connection');
            }
        }
        
        return $connection;
    }
    
    /**
     * 归还连接
     * 
     * @param mixed $connection
     */
    public function returnConnection($connection): void
    {
        if (!$connection) {
            return;
        }
        
        // 检查连接是否有效
        if ($this->isConnectionValid($connection)) {
            $this->pool->push($connection);
        } else {
            $this->closeConnection($connection);
            $this->currentConnections--;
        }
    }
    
    /**
     * 关闭连接池
     */
    public function close(): void
    {
        while (!$this->pool->isEmpty()) {
            $connection = $this->pool->pop();
            $this->closeConnection($connection);
        }
        
        $this->currentConnections = 0;
        $this->initialized = false;
    }
    
    /**
     * 获取连接池状态
     * 
     * @return array
     */
    public function getStats(): array
    {
        return [
            'current_connections' => $this->currentConnections,
            'available_connections' => $this->pool->length(),
            'max_connections' => $this->config['max_connections'],
            'min_connections' => $this->config['min_connections'],
        ];
    }
    
    /**
     * 创建新连接
     * 
     * @return Redis|false
     */
    private function createConnection()
    {
        try {
            $redis = new Redis();
            
            $redisConfig = $this->config['redis'];
            $connected = $redis->connect(
                $redisConfig['host'] ?? '127.0.0.1',
                $redisConfig['port'] ?? 6379,
                $this->config['connect_timeout']
            );
            
            if (!$connected) {
                return false;
            }
            
            // 设置密码
            if (!empty($redisConfig['password'])) {
                if (!$redis->auth($redisConfig['password'])) {
                    return false;
                }
            }
            
            // 选择数据库
            if (isset($redisConfig['database'])) {
                $redis->select($redisConfig['database']);
            }
            
            $this->currentConnections++;
            
            return $redis;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * 检查连接是否有效
     * 
     * @param mixed $connection
     * @return bool
     */
    private function isConnectionValid($connection): bool
    {
        if (!$connection) {
            return false;
        }
        
        try {
            $result = $connection->ping();
            return $result === true || $result === '+PONG';
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * 关闭连接
     * 
     * @param mixed $connection
     */
    private function closeConnection($connection): void
    {
        if ($connection) {
            try {
                $connection->close();
            } catch (\Exception $e) {
                // 忽略关闭错误
            }
        }
    }
}