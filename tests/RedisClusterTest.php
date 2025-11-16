<?php

namespace Rediphp\Tests;

use Rediphp\RedissonClusterClient;
use Rediphp\ClusterConfig;
use Rediphp\RedisClusterManager;
use RuntimeException;

/**
 * Redis集群模式测试
 */
class RedisClusterTest extends RedissonTestCase
{
    private ?RedissonClusterClient $clusterClient = null;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 检查是否配置了集群节点
        $clusterNodes = getenv('REDIS_CLUSTER_NODES');
        if (empty($clusterNodes)) {
            $this->markTestSkipped('Redis cluster nodes not configured. Set REDIS_CLUSTER_NODES environment variable.');
        }

        $this->clusterClient = new RedissonClusterClient([
            'cluster_nodes' => array_map('trim', explode(',', $clusterNodes)),
            'timeout' => 5.0,
            'read_timeout' => 5.0,
            'password' => getenv('REDIS_PASSWORD') ?: null,
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->clusterClient) {
            $this->clusterClient->close();
        }
        parent::tearDown();
    }

    /**
     * 测试集群客户端连接
     */
    public function testClusterConnection(): void
    {
        $this->assertTrue($this->clusterClient->isConnected());
        
        // 测试基本操作
        $cluster = $this->clusterClient->getCluster();
        $result = $cluster->ping();
        $this->assertEquals('PONG', $result);
    }

    /**
     * 测试集群信息获取
     */
    public function testClusterInfo(): void
    {
        $clusterInfo = $this->clusterClient->getClusterInfo();
        
        $this->assertArrayHasKey('cluster_state', $clusterInfo);
        $this->assertArrayHasKey('cluster_slots_assigned', $clusterInfo);
        $this->assertArrayHasKey('cluster_known_nodes', $clusterInfo);
        $this->assertArrayHasKey('cluster_size', $clusterInfo);
        
        // 集群应该处于健康状态
        $this->assertTrue($this->clusterClient->isHealthy());
    }

    /**
     * 测试集群节点信息
     */
    public function testClusterNodes(): void
    {
        $nodes = $this->clusterClient->getNodes();
        
        $this->assertIsArray($nodes);
        $this->assertNotEmpty($nodes);
        
        foreach ($nodes as $node) {
            $this->assertArrayHasKey('host', $node);
            $this->assertArrayHasKey('port', $node);
            $this->assertArrayHasKey('role', $node);
            $this->assertContains($node['role'], ['master', 'slave']);
        }
    }

    /**
     * 测试分布式数据结构在集群模式下的操作
     */
    public function testDistributedDataStructures(): void
    {
        // 测试Map
        $map = $this->clusterClient->getMap('test_cluster_map');
        $map->put('key1', 'value1');
        $this->assertEquals('value1', $map->get('key1'));
        $this->assertTrue($map->containsKey('key1'));
        $map->remove('key1');
        $this->assertFalse($map->containsKey('key1'));

        // 测试AtomicLong
        $atomic = $this->clusterClient->getAtomicLong('test_cluster_atomic');
        $atomic->set(100);
        $this->assertEquals(100, $atomic->get());
        $this->assertEquals(101, $atomic->incrementAndGet());

        // 测试Set
        $set = $this->clusterClient->getSet('test_cluster_set');
        $set->add('member1');
        $this->assertTrue($set->contains('member1'));
        $this->assertEquals(1, $set->size());
        $set->remove('member1');
        $this->assertFalse($set->contains('member1'));
    }

    /**
     * 测试集群模式下的锁操作
     */
    public function testClusterLock(): void
    {
        $lock = $this->clusterClient->getLock('test_cluster_lock');
        
        // 获取锁
        $this->assertTrue($lock->tryLock());
        
        // 验证锁状态
        $this->assertTrue($lock->isLocked());
        
        // 释放锁
        $lock->unlock();
        
        // 验证锁已释放
        $this->assertFalse($lock->isLocked());
    }

    /**
     * 测试集群配置类
     */
    public function testClusterConfig(): void
    {
        $config = new ClusterConfig([
            'nodes' => ['127.0.0.1:7000', '127.0.0.1:7001'],
            'timeout' => 3.0,
            'read_timeout' => 2.0,
            'password' => 'testpass',
            'persistent' => true,
            'retry_interval' => 200,
            'cluster_failover' => RedisCluster::FAILOVER_ERROR,
        ]);

        $this->assertEquals(['127.0.0.1:7000', '127.0.0.1:7001'], $config->getNodes());
        $this->assertEquals(3.0, $config->getTimeout());
        $this->assertEquals(2.0, $config->getReadTimeout());
        $this->assertEquals('testpass', $config->getPassword());
        $this->assertTrue($config->isPersistent());
        $this->assertEquals(200, $config->getRetryInterval());
        $this->assertEquals(RedisCluster::FAILOVER_ERROR, $config->getClusterFailover());
    }

    /**
     * 测试集群管理器
     */
    public function testClusterManager(): void
    {
        $clusterManager = $this->clusterClient->getClusterManager();
        
        $this->assertInstanceOf(RedisClusterManager::class, $clusterManager);
        $this->assertTrue($clusterManager->isConnected());
        
        $cluster = $clusterManager->getCluster();
        $this->assertInstanceOf(RedisCluster::class, $cluster);
        
        // 测试执行命令
        $result = $clusterManager->execute('ping');
        $this->assertEquals('PONG', $result);
        
        // 测试获取槽位（简化实现）
        $slot = $clusterManager->getSlot('test_key');
        $this->assertIsInt($slot);
    }

    /**
     * 测试无效集群配置
     */
    public function testInvalidClusterConfig(): void
    {
        // 空节点列表
        try {
            new ClusterConfig(['nodes' => []]);
            $this->fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Cluster nodes cannot be empty', $e->getMessage());
        }

        // 无效节点格式
        try {
            new ClusterConfig(['nodes' => ['invalid']]);
            $this->fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Invalid node format', $e->getMessage());
        }

        // 负超时时间
        try {
            new ClusterConfig(['timeout' => -1.0]);
            $this->fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Timeout must be greater than 0', $e->getMessage());
        }
    }

    /**
     * 测试集群模式不支持连接池
     */
    public function testClusterNoPooling(): void
    {
        try {
            new RedissonClusterClient([
                'cluster_nodes' => ['127.0.0.1:6379'],
                'use_pool' => true,
            ]);
            $this->fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Connection pooling is not supported in cluster mode', $e->getMessage());
        }
    }
}