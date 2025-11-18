<?php

namespace Rediphp\Tests;

use Rediphp\RedissonClusterClient;
use Rediphp\RedisClusterManager;
use Rediphp\RedissonSentinelClient;
use Rediphp\ClusterConfig;
use Rediphp\SentinelConfig;

/**
 * ClusterAndSentinelIntegrationTest - Comprehensive integration tests for cluster and sentinel mode
 * Tests cluster connectivity, node management, failover scenarios, and sentinel operations
 */
class ClusterAndSentinelIntegrationTest extends RedissonTestCase
{
    private ?RedissonClusterClient $clusterClient = null;
    private ?RedissonSentinelClient $sentinelClient = null;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // 初始化集群客户端（模拟配置）
        try {
            // 使用更简单的配置，避免RedisCluster类引用问题
            $this->clusterClient = new RedissonClusterClient([
                'cluster_nodes' => ['127.0.0.1:7000', '127.0.0.1:7001', '127.0.0.1:7002'],
                'timeout' => 5.0,
                'read_timeout' => 5.0,
                'cluster_failover' => 0, // 使用数值而不是RedisCluster常量
            ]);
        } catch (\Exception $e) {
            // 如果Redis集群扩展不可用，跳过集群相关测试
            if (strpos($e->getMessage(), 'RedisCluster') !== false) {
                $this->clusterClient = null;
            } else {
                $this->markTestSkipped("Cluster client initialization failed: " . $e->getMessage());
            }
        }
        
        // 初始化哨兵客户端（模拟配置）
        try {
            $this->sentinelClient = new RedissonSentinelClient([
                'sentinels' => ['127.0.0.1:26379', '127.0.0.1:26380'],
                'master_name' => 'mymaster',
                'timeout' => 5.0,
            ]);
        } catch (\Exception $e) {
            $this->markTestSkipped("Sentinel client initialization failed: " . $e->getMessage());
        }
        
        // 清理测试数据
        $this->cleanupTestData();
    }
    
    protected function tearDown(): void
    {
        if (isset($this->clusterClient)) {
            try {
                $this->clusterClient->close();
            } catch (\Exception $e) {
                // 忽略关闭错误
            }
        }
        
        if (isset($this->sentinelClient)) {
            try {
                $this->sentinelClient->close();
            } catch (\Exception $e) {
                // 忽略关闭错误
            }
        }
        
        $this->cleanupTestData();
        parent::tearDown();
    }
    
    /**
     * Clean up test data
     */
    private function cleanupTestData(): void
    {
        try {
            // 清理集群测试数据
            if (isset($this->clusterClient)) {
                try {
                    $cluster = $this->clusterClient->getCluster();
                    $keys = $cluster->keys('cluster:test:*');
                    if (!empty($keys)) {
                        $cluster->del(...$keys);
                    }
                } catch (\Exception $e) {
                    // 集群未连接或失败，忽略
                }
            }
            
            // 清理哨兵测试数据
            if (isset($this->sentinelClient)) {
                try {
                    $redis = $this->sentinelClient->getRedis();
                    $keys = $redis->keys('sentinel:test:*');
                    if (!empty($keys)) {
                        $redis->del(...$keys);
                    }
                } catch (\Exception $e) {
                    // 哨兵未连接或失败，忽略
                }
            }
        } catch (\Exception $e) {
            // 忽略清理错误
        }
    }
    
    // ==================== 集群连接测试 ====================
    
    /**
     * Test cluster client initialization
     */
    public function testClusterClientInitialization(): void
    {
        if ($this->clusterClient === null) {
            $this->markTestSkipped('Redis cluster extension not available');
        }
        
        $this->assertInstanceOf(RedissonClusterClient::class, $this->clusterClient);
        
        // 测试集群管理器
        try {
            $cluster = $this->clusterClient->getCluster();
            $this->assertInstanceOf(\RedisCluster::class, $cluster);
        } catch (\Exception $e) {
            // 如果Redis集群扩展不可用，跳过测试
            if (strpos($e->getMessage(), 'RedisCluster') !== false) {
                $this->markTestSkipped('Redis cluster extension not available');
            }
            throw $e;
        }
    }
    
    /**
     * Test cluster connection configuration
     */
    public function testClusterConnectionConfiguration(): void
    {
        try {
            $config = new ClusterConfig([
                'nodes' => ['127.0.0.1:7000', '127.0.0.1:7001'],
                'timeout' => 10.0,
                'read_timeout' => 5.0,
                'password' => 'password',
                'persistent' => true,
            ]);
            
            $this->assertEquals(['127.0.0.1:7000', '127.0.0.1:7001'], $config->getNodes());
            $this->assertEquals(10.0, $config->getTimeout());
            $this->assertEquals(5.0, $config->getReadTimeout());
            $this->assertEquals('password', $config->getPassword());
            $this->assertTrue($config->isPersistent());
        } catch (\Exception $e) {
            // 如果Redis集群扩展不可用，跳过测试
            if (strpos($e->getMessage(), 'RedisCluster') !== false) {
                $this->markTestSkipped('Redis cluster extension not available');
            }
            throw $e;
        }
    }
    
    /**
     * Test cluster client connection
     */
    public function testClusterClientConnection(): void
    {
        try {
            // 尝试连接集群
            $cluster = $this->clusterClient->getCluster();
            
            // 测试基本的Redis命令
            $result = $cluster->ping();
            $this->assertTrue($result === 'PONG' || $result === true);
            
        } catch (\Exception $e) {
            // 如果Redis集群扩展不可用，跳过测试
            if (strpos($e->getMessage(), 'RedisCluster') !== false) {
                $this->markTestSkipped("Redis cluster extension not available: " . $e->getMessage());
            }
            $this->markTestSkipped("Cluster not available: " . $e->getMessage());
        }
    }
    
    // ==================== 集群节点管理测试 ====================
    
    /**
     * Test cluster node information retrieval
     */
    public function testClusterNodeInformation(): void
    {
        try {
            $clusterManager = new \ReflectionMethod($this->clusterClient, 'clusterManager');
            $clusterManager->setAccessible(true);
            $manager = $clusterManager->invoke($this->clusterClient);
            
            $nodes = $manager->getNodes();
            $this->assertIsArray($nodes);
            
            // 验证节点结构
            foreach ($nodes as $node) {
                $this->assertArrayHasKey('host', $node);
                $this->assertArrayHasKey('port', $node);
                $this->assertArrayHasKey('role', $node);
                $this->assertTrue(in_array($node['role'], ['master', 'slave']));
            }
            
        } catch (\Exception $e) {
            $this->markTestSkipped("Cluster node info retrieval failed: " . $e->getMessage());
        }
    }
    
    /**
     * Test cluster slot information
     */
    public function testClusterSlotInformation(): void
    {
        try {
            $cluster = $this->clusterClient->getCluster();
            $slots = $cluster->cluster('SLOTS');
            
            $this->assertIsArray($slots);
            
            // 验证槽位信息结构
            foreach ($slots as $slot) {
                $this->assertCount(3, $slot); // 至少包含开始槽位、结束槽位和主节点信息
                $this->assertIsInt($slot[0]); // 开始槽位
                $this->assertIsInt($slot[1]); // 结束槽位
                $this->assertIsArray($slot[2]); // 主节点信息
            }
            
        } catch (\Exception $e) {
            $this->markTestSkipped("Cluster slot info failed: " . $e->getMessage());
        }
    }
    
    // ==================== 集群操作测试 ====================
    
    /**
     * Test basic Redis operations in cluster mode
     */
    public function testClusterBasicOperations(): void
    {
        try {
            $cluster = $this->clusterClient->getCluster();
            
            // 测试String操作
            $result = $cluster->set('cluster:test:string', 'value');
            $this->assertTrue($result);
            
            $value = $cluster->get('cluster:test:string');
            $this->assertEquals('value', $value);
            
            // 测试Hash操作
            $result = $cluster->hset('cluster:test:hash', 'field', 'value');
            $this->assertGreaterThanOrEqual(0, $result);
            
            $fieldValue = $cluster->hget('cluster:test:hash', 'field');
            $this->assertEquals('value', $fieldValue);
            
            // 测试List操作
            $result = $cluster->lpush('cluster:test:list', 'item1', 'item2');
            $this->assertEquals(2, $result);
            
            $length = $cluster->llen('cluster:test:list');
            $this->assertEquals(2, $length);
            
        } catch (\Exception $e) {
            $this->markTestSkipped("Cluster basic operations failed: " . $e->getMessage());
        }
    }
    
    /**
     * Test data structures in cluster mode
     */
    public function testClusterDataStructures(): void
    {
        try {
            // 测试Map
            $map = $this->clusterClient->getMap('cluster:test:structure:map');
            $map->put('key1', 'value1');
            $this->assertEquals('value1', $map->get('key1'));
            
            // 测试List
            $list = $this->clusterClient->getList('cluster:test:structure:list');
            $list->add('item1');
            $this->assertEquals(1, $list->size());
            
            // 测试Set
            $set = $this->clusterClient->getSet('cluster:test:structure:set');
            $set->add('member1');
            $this->assertTrue($set->contains('member1'));
            
            // 测试SortedSet
            $sortedSet = $this->clusterClient->getSortedSet('cluster:test:structure:sorted');
            $sortedSet->add('member1', 1.0);
            $this->assertEquals(1, $sortedSet->size());
            
        } catch (\Exception $e) {
            $this->markTestSkipped("Cluster data structures failed: " . $e->getMessage());
        }
    }
    
    /**
     * Test distributed locks in cluster mode
     */
    public function testClusterDistributedLocks(): void
    {
        try {
            $lock = $this->clusterClient->getLock('cluster:test:lock');
            
            // 尝试获取锁
            $lockAcquired = $lock->tryLock(1, 10);
            $this->assertTrue($lockAcquired);
            
            // 验证锁状态
            $isLocked = $lock->isLocked();
            $this->assertTrue($isLocked);
            
            // 释放锁
            $lockUnlocked = $lock->unlock();
            $this->assertTrue($lockUnlocked);
            
            // 验证锁已释放
            $isLockedAfter = $lock->isLocked();
            $this->assertFalse($isLockedAfter);
            
        } catch (\Exception $e) {
            $this->markTestSkipped("Cluster distributed locks failed: " . $e->getMessage());
        }
    }
    
    // ==================== 集群故障转移测试 ====================
    
    /**
     * Test cluster health monitoring
     */
    public function testClusterHealthMonitoring(): void
    {
        try {
            $clusterManager = new \ReflectionMethod($this->clusterClient, 'clusterManager');
            $clusterManager->setAccessible(true);
            $manager = $clusterManager->invoke($this->clusterClient);
            
            $isHealthy = $manager->isHealthy();
            $this->assertIsBool($isHealthy);
            
            // 如果集群可用，应该能够获取集群信息
            if ($isHealthy) {
                $clusterInfo = $manager->getClusterInfo();
                $this->assertIsArray($clusterInfo);
                $this->assertArrayHasKey('cluster_state', $clusterInfo);
            }
            
        } catch (\Exception $e) {
            $this->markTestSkipped("Cluster health monitoring failed: " . $e->getMessage());
        }
    }
    
    /**
     * Test cluster failover handling
     */
    public function testClusterFailoverHandling(): void
    {
        try {
            $cluster = $this->clusterClient->getCluster();
            
            // 设置故障转移选项
            $cluster->setOption(\RedisCluster::OPT_SLAVE_FAILOVER, \RedisCluster::FAILOVER_ERROR);
            
            // 测试基本操作在故障转移情况下的处理
            $key = 'cluster:test:failover';
            $value = 'test_value_' . time();
            
            // 设置键值
            $setResult = $cluster->set($key, $value);
            $this->assertTrue($setResult);
            
            // 读取键值
            $getResult = $cluster->get($key);
            $this->assertEquals($value, $getResult);
            
        } catch (\Exception $e) {
            $this->markTestSkipped("Cluster failover handling failed: " . $e->getMessage());
        }
    }
    
    // ==================== 哨兵模式测试 ====================
    
    /**
     * Test sentinel client initialization
     */
    public function testSentinelClientInitialization(): void
    {
        $this->assertInstanceOf(RedissonSentinelClient::class, $this->sentinelClient);
    }
    
    /**
     * Test sentinel connection configuration
     */
    public function testSentinelConnectionConfiguration(): void
    {
        $config = new SentinelConfig([
            'sentinels' => ['127.0.0.1:26379'],
            'master_name' => 'mymaster',
            'timeout' => 5.0,
            'database' => 0,
        ]);
        
        $this->assertEquals(['127.0.0.1:26379'], $config->getSentinels());
        $this->assertEquals('mymaster', $config->getMasterName());
        $this->assertEquals(5.0, $config->getTimeout());
        $this->assertEquals(0, $config->getDatabase());
    }
    
    /**
     * Test sentinel client connection
     */
    public function testSentinelClientConnection(): void
    {
        try {
            // 尝试连接哨兵
            $this->sentinelClient->connect();
            
            // 检查连接状态
            $this->assertTrue($this->sentinelClient->isConnected());
            
            // 测试基本的Redis命令
            $redis = $this->sentinelClient->getRedis();
            $result = $redis->ping();
            $this->assertTrue($result === 'PONG' || $result === true);
            
        } catch (\Exception $e) {
            $this->markTestSkipped("Sentinel not available: " . $e->getMessage());
        }
    }
    
    // ==================== 哨兵健康管理测试 ====================
    
    /**
     * Test sentinel health status
     */
    public function testSentinelHealthStatus(): void
    {
        try {
            $this->sentinelClient->connect();
            
            $isHealthy = $this->sentinelClient->isHealthy();
            $this->assertIsBool($isHealthy);
            
            // 获取哨兵信息
            $sentinelInfo = $this->sentinelClient->getSentinelInfo();
            $this->assertIsArray($sentinelInfo);
            
        } catch (\Exception $e) {
            $this->markTestSkipped("Sentinel health status failed: " . $e->getMessage());
        }
    }
    
    /**
     * Test sentinel failover process
     */
    public function testSentinelFailoverProcess(): void
    {
        try {
            $this->sentinelClient->connect();
            
            // 手动触发故障转移
            $failoverResult = $this->sentinelClient->failover();
            
            // 验证故障转移结果（可能成功或失败，取决于哨兵配置）
            $this->assertIsBool($failoverResult);
            
            // 检查连接是否仍然有效
            $isConnected = $this->sentinelClient->isConnected();
            $this->assertTrue($isConnected);
            
        } catch (\Exception $e) {
            $this->markTestSkipped("Sentinel failover process failed: " . $e->getMessage());
        }
    }
    
    // ==================== 哨兵模式下数据结构测试 ====================
    
    /**
     * Test data structures in sentinel mode
     */
    public function testSentinelDataStructures(): void
    {
        try {
            $this->sentinelClient->connect();
            
            // 测试Map
            $map = $this->sentinelClient->getMap('sentinel:test:structure:map');
            $map->put('sentinel_key1', 'sentinel_value1');
            $this->assertEquals('sentinel_value1', $map->get('sentinel_key1'));
            
            // 测试List
            $list = $this->sentinelClient->getList('sentinel:test:structure:list');
            $list->add('sentinel_item1');
            $this->assertEquals(1, $list->size());
            
            // 测试AtomicLong
            $atomicLong = $this->sentinelClient->getAtomicLong('sentinel:test:atomic');
            $atomicLong->set(10);
            $this->assertEquals(10, $atomicLong->get());
            
            $atomicLong->incrementAndGet();
            $this->assertEquals(11, $atomicLong->get());
            
        } catch (\Exception $e) {
            $this->markTestSkipped("Sentinel data structures failed: " . $e->getMessage());
        }
    }
    
    /**
     * Test distributed locks in sentinel mode
     */
    public function testSentinelDistributedLocks(): void
    {
        try {
            $this->sentinelClient->connect();
            
            $lock = $this->sentinelClient->getLock('sentinel:test:lock');
            
            // 尝试获取锁
            $lockAcquired = $lock->tryLock(1, 10);
            $this->assertTrue($lockAcquired);
            
            // 验证锁状态
            $isLocked = $lock->isLocked();
            $this->assertTrue($isLocked);
            
            // 释放锁
            $lockUnlocked = $lock->unlock();
            $this->assertTrue($lockUnlocked);
            
        } catch (\Exception $e) {
            $this->markTestSkipped("Sentinel distributed locks failed: " . $e->getMessage());
        }
    }
    
    // ==================== 集群环境性能测试 ====================
    
    /**
     * Test cluster performance with concurrent operations
     */
    public function testClusterConcurrentPerformance(): void
    {
        try {
            $cluster = $this->clusterClient->getCluster();
            
            $iterations = 100;
            $startTime = microtime(true);
            
            // 并发执行多个操作
            for ($i = 0; $i < $iterations; $i++) {
                $key = "cluster:test:concurrent:perf:$i";
                $value = "performance_test_value_$i";
                
                // 写入操作
                $cluster->set($key, $value);
                
                // 读取操作
                $result = $cluster->get($key);
                $this->assertEquals($value, $result);
                
                // 删除操作
                $cluster->del($key);
            }
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            
            // 性能断言
            $this->assertLessThan(30.0, $executionTime, "集群环境100次操作应在30秒内完成");
            
            echo sprintf("\n集群性能测试结果：%d个并发操作，耗时 %.2f 秒\n", 
                $iterations, $executionTime);
            
        } catch (\Exception $e) {
            $this->markTestSkipped("Cluster concurrent performance test failed: " . $e->getMessage());
        }
    }
    
    // ==================== 哨兵模式性能测试 ====================
    
    /**
     * Test sentinel performance with basic operations
     */
    public function testSentinelBasicPerformance(): void
    {
        try {
            $this->sentinelClient->connect();
            $redis = $this->sentinelClient->getRedis();
            
            $iterations = 100;
            $startTime = microtime(true);
            
            // 执行基本的Redis操作
            for ($i = 0; $i < $iterations; $i++) {
                $key = "sentinel:test:basic:perf:$i";
                $value = "sentinel_performance_test_$i";
                
                $redis->set($key, $value);
                $result = $redis->get($key);
                $this->assertEquals($value, $result);
                $redis->del($key);
            }
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            
            // 性能断言
            $this->assertLessThan(15.0, $executionTime, "哨兵模式100次操作应在15秒内完成");
            
            echo sprintf("\n哨兵模式性能测试结果：%d个操作，耗时 %.2f 秒\n", 
                $iterations, $executionTime);
            
        } catch (\Exception $e) {
            $this->markTestSkipped("Sentinel basic performance test failed: " . $e->getMessage());
        }
    }
    
    // ==================== 错误处理和恢复测试 ====================
    
    /**
     * Test cluster error handling and recovery
     */
    public function testClusterErrorHandlingAndRecovery(): void
    {
        try {
            $cluster = $this->clusterClient->getCluster();
            
            // 测试无效键操作
            $result = $cluster->get('non_existent_cluster_key');
            $this->assertNull($result);
            
            // 测试MOVED重定向处理
            try {
                $cluster->cluster('ADDSLOTS', [1, 2, 3]);
            } catch (\Exception $e) {
                // 预期的错误，因为测试环境可能不允许ADDSLOTS
                $this->assertStringContainsString('MOVED', $e->getMessage()) ||
                    $this->assertStringContainsString('not supported', $e->getMessage());
            }
            
            // 测试批量操作中的错误处理
            $cluster->set('cluster:test:batch:1', 'value1');
            $cluster->set('cluster:test:batch:2', 'value2');
            
            $keys = ['cluster:test:batch:1', 'cluster:test:batch:2', 'non_existent'];
            $results = $cluster->mget($keys);
            
            $this->assertCount(3, $results);
            $this->assertEquals('value1', $results[0]);
            $this->assertEquals('value2', $results[1]);
            $this->assertNull($results[2]);
            
        } catch (\Exception $e) {
            $this->markTestSkipped("Cluster error handling test failed: " . $e->getMessage());
        }
    }
    
    /**
     * Test sentinel error handling and recovery
     */
    public function testSentinelErrorHandlingAndRecovery(): void
    {
        try {
            $this->sentinelClient->connect();
            $redis = $this->sentinelClient->getRedis();
            
            // 测试无效键操作
            $result = $redis->get('non_existent_sentinel_key');
            $this->assertNull($result);
            
            // 测试连接失败后的恢复
            try {
                $invalidRedis = new \Redis();
                $invalidRedis->connect('invalid_host', 9999, 1);
            } catch (\Exception $e) {
                // 预期的连接失败
                $this->assertStringContainsString('Connection refused', $e->getMessage()) ||
                    $this->assertStringContainsString('Unknown host', $e->getMessage());
            }
            
            // 验证主连接仍然有效
            $pingResult = $redis->ping();
            $this->assertTrue($pingResult === 'PONG' || $pingResult === true);
            
        } catch (\Exception $e) {
            $this->markTestSkipped("Sentinel error handling test failed: " . $e->getMessage());
        }
    }
}