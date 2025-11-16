<?php

namespace Rediphp\Tests;

use Rediphp\RMap;
use Rediphp\RList;
use Rediphp\RLock;
use Rediphp\RAtomicLong;
use Rediphp\RBucket;
use Rediphp\RSemaphore;

/**
 * Redis集群故障切换集成测试
 * 测试Redis集群环境下的故障切换、节点故障和数据迁移场景
 */
class RedisClusterFailoverIntegrationTest extends RedissonTestCase
{
    /**
     * 测试Redis节点故障切换时的数据一致性
     */
    public function testNodeFailoverDataConsistency()
    {
        $failoverMap = $this->client->getMap('cluster:failover:map');
        $failoverCounter = $this->client->getAtomicLong('cluster:failover:counter');
        $failoverLock = $this->client->getLock('cluster:failover:lock');
        
        // 初始化测试数据
        $dataSize = 30;
        for ($i = 0; $i < $dataSize; $i++) {
            $data = [
                'id' => $i,
                'status' => 'active',
                'cluster_node' => 'primary',
                'timestamp' => time()
            ];
            $failoverMap->put("item:$i", $data);
        }
        $failoverCounter->set($dataSize);
        
        // 验证初始状态
        $this->assertEquals($dataSize, $failoverMap->size());
        $this->assertEquals($dataSize, $failoverCounter->get());
        
        // 模拟主节点故障（通过重启客户端）
        $this->client->shutdown();
        
        // 模拟故障恢复时间
        usleep(100000); // 100ms
        
        $this->client->connect();
        
        // 验证故障恢复后的数据一致性
        $recoveredMap = $this->client->getMap('cluster:failover:map');
        $recoveredCounter = $this->client->getAtomicLong('cluster:failover:counter');
        
        $this->assertEquals($dataSize, $recoveredMap->size());
        $this->assertEquals($dataSize, $recoveredCounter->get());
        
        // 验证数据完整性
        for ($i = 0; $i < $dataSize; $i++) {
            $data = $recoveredMap->get("item:$i");
            $this->assertNotNull($data);
            $this->assertEquals('active', $data['status']);
        }
        
        // 测试故障恢复后的继续操作
        $this->assertTrue($failoverLock->tryLock());
        $recoveredCounter->incrementAndGet();
        $failoverLock->unlock();
        
        // 添加新的数据项
        $newData = [
            'id' => $dataSize,
            'status' => 'active',
            'cluster_node' => 'recovered',
            'timestamp' => time()
        ];
        $recoveredMap->put("item:$dataSize", $newData);
        
        $this->assertEquals($dataSize + 1, $recoveredMap->size());
        $this->assertEquals($dataSize + 1, $recoveredCounter->get());
        
        // 清理
        $recoveredMap->clear();
        $recoveredCounter->delete();
    }
    
    /**
     * 测试多节点负载均衡下的操作一致性
     */
    public function testMultiNodeLoadBalancing()
    {
        $loadBalancedMap = $this->client->getMap('cluster:load:map');
        $loadBalancedSet = $this->client->getSet('cluster:load:set');
        $loadBalancedCounter = $this->client->getAtomicLong('cluster:load:counter');
        
        // 模拟分布式写入
        $nodeOperations = 20;
        for ($i = 0; $i < $nodeOperations; $i++) {
            // 模拟不同节点的操作
            $nodeId = $i % 3; // 模拟3个节点
            $data = [
                'item_id' => $i,
                'node_id' => $nodeId,
                'operation' => 'write',
                'timestamp' => time()
            ];
            
            $loadBalancedMap->put("distributed:item:$i", $data);
            $loadBalancedSet->add("node:$nodeId:item:$i");
            $loadBalancedCounter->incrementAndGet();
        }
        
        // 验证分布式操作结果
        $this->assertEquals($nodeOperations, $loadBalancedMap->size());
        $this->assertEquals($nodeOperations, $loadBalancedSet->size());
        $this->assertEquals($nodeOperations, $loadBalancedCounter->get());
        
        // 验证节点分布
        $nodeCounts = [];
        for ($i = 0; $i < 3; $i++) {
            $nodeCounts[$i] = $loadBalancedSet->count("node:$i:*") ?? 0;
        }
        
        // 验证每个节点都有操作
        foreach ($nodeCounts as $count) {
            $this->assertGreaterThan(0, $count);
        }
        
        // 模拟负载均衡故障
        $this->client->shutdown();
        $this->client->connect();
        
        // 验证故障恢复后的一致性
        $recoveredMap = $this->client->getMap('cluster:load:map');
        $recoveredSet = $this->client->getSet('cluster:load:set');
        $recoveredCounter = $this->client->getAtomicLong('cluster:load:counter');
        
        $this->assertEquals($nodeOperations, $recoveredMap->size());
        $this->assertEquals($nodeOperations, $recoveredSet->size());
        $this->assertEquals($nodeOperations, $recoveredCounter->get());
        
        // 验证数据完整性
        $validOperations = 0;
        for ($i = 0; $i < $nodeOperations; $i++) {
            $data = $recoveredMap->get("distributed:item:$i");
            if ($data && $data['operation'] === 'write') {
                $validOperations++;
            }
        }
        
        $this->assertEquals($nodeOperations, $validOperations);
        
        // 清理
        $recoveredMap->clear();
        $recoveredSet->clear();
        $recoveredCounter->delete();
    }
    
    /**
     * 测试Redis集群迁移时的数据同步
     */
    public function testClusterMigrationDataSync()
    {
        $migrationMap = $this->client->getMap('cluster:migration:map');
        $migrationCounter = $this->client->getAtomicLong('cluster:migration:counter');
        
        // 模拟初始数据分布
        $migrationData = [];
        for ($i = 0; $i < 25; $i++) {
            $item = [
                'id' => $i,
                'slot' => $i % 16, // 模拟16个哈希槽
                'migrated' => false,
                'created_at' => time()
            ];
            
            $migrationMap->put("migration:item:$i", $item);
            $migrationData[] = $item;
        }
        $migrationCounter->set(25);
        
        // 验证初始分布
        $this->assertEquals(25, $migrationMap->size());
        $this->assertEquals(25, $migrationCounter->get());
        
        // 模拟数据迁移过程
        $migratedCount = 0;
        for ($i = 0; $i < 25; $i++) {
            $item = $migrationMap->get("migration:item:$i");
            if ($item) {
                // 模拟迁移操作
                $item['migrated'] = true;
                $item['migrated_at'] = time();
                $migrationMap->put("migration:item:$i", $item);
                $migratedCount++;
            }
        }
        
        // 验证迁移过程
        $this->assertEquals(25, $migratedCount);
        
        // 模拟集群故障恢复
        $this->client->shutdown();
        $this->client->connect();
        
        // 验证迁移后的数据状态
        $recoveredMap = $this->client->getMap('cluster:migration:map');
        $recoveredCounter = $this->client->getAtomicLong('cluster:migration:counter');
        
        $this->assertEquals(25, $recoveredMap->size());
        $this->assertEquals(25, $recoveredCounter->get());
        
        // 验证迁移状态保持
        $migratedItems = 0;
        for ($i = 0; $i < 25; $i++) {
            $item = $recoveredMap->get("migration:item:$i");
            if ($item && $item['migrated']) {
                $migratedItems++;
            }
        }
        
        $this->assertEquals(25, $migratedItems);
        
        // 验证数据完整性
        for ($i = 0; $i < 25; $i++) {
            $item = $recoveredMap->get("migration:item:$i");
            $this->assertNotNull($item);
            $this->assertEquals($i, $item['id']);
            $this->assertTrue($item['migrated']);
        }
        
        // 清理
        $recoveredMap->clear();
        $recoveredCounter->delete();
    }
    
    /**
     * 测试集群模式下的分布式锁故障恢复
     */
    public function testClusterDistributedLockFailover()
    {
        $clusterLock = $this->client->getLock('cluster:lock');
        $lockCounter = $this->client->getAtomicLong('cluster:lock:counter');
        $lockQueue = $this->client->getQueue('cluster:lock:queue');
        
        // 测试锁的正常获取和释放
        $this->assertTrue($clusterLock->tryLock());
        $lockCounter->set(1);
        $this->assertTrue($clusterLock->isLocked());
        
        $lockQueue->add("lock:acquired");
        $clusterLock->unlock();
        
        $this->assertFalse($clusterLock->isLocked());
        $this->assertEquals(1, $lockQueue->size());
        
        // 模拟集群节点故障
        $this->client->shutdown();
        
        // 尝试获取锁应该失败
        try {
            $clusterLock->tryLock();
            $this->fail("故障时获取锁应该失败");
        } catch (\Exception $e) {
            $this->assertNotNull($e);
        }
        
        // 模拟故障恢复
        $this->client->connect();
        
        $recoveredLock = $this->client->getLock('cluster:lock');
        $recoveredCounter = $this->client->getAtomicLong('cluster:lock:counter');
        $recoveredQueue = $this->client->getQueue('cluster:lock:queue');
        
        // 验证锁状态已重置
        $this->assertFalse($recoveredLock->isLocked());
        
        // 验证队列数据保持
        $this->assertEquals(1, $recoveredQueue->size());
        $this->assertEquals("lock:acquired", $recoveredQueue->poll());
        
        // 验证锁恢复正常工作
        $this->assertTrue($recoveredLock->tryLock());
        $recoveredCounter->incrementAndGet();
        $this->assertTrue($recoveredLock->isLocked());
        $recoveredLock->unlock();
        
        $this->assertEquals(2, $recoveredCounter->get());
        $this->assertFalse($recoveredLock->isLocked());
        
        // 清理
        $recoveredCounter->delete();
        $recoveredQueue->clear();
    }
    
    /**
     * 测试集群模式下信号量的故障切换
     */
    public function testClusterSemaphoreFailover()
    {
        $clusterSemaphore = $this->client->getSemaphore('cluster:semaphore', 3);
        $semaphoreCounter = $this->client->getAtomicLong('cluster:semaphore:counter');
        
        // 初始化信号量
        $clusterSemaphore->clear();
        $this->assertTrue($clusterSemaphore->trySetPermits(3));
        $this->assertEquals(3, $clusterSemaphore->availablePermits());
        
        // 获取信号量许可
        $acquiredPermits = 0;
        for ($i = 0; $i < 3; $i++) {
            if ($clusterSemaphore->tryAcquire()) {
                $acquiredPermits++;
                $semaphoreCounter->incrementAndGet();
            }
        }
        
        $this->assertEquals(3, $acquiredPermits);
        $this->assertEquals(0, $clusterSemaphore->availablePermits());
        
        // 模拟集群故障
        $this->client->shutdown();
        
        try {
            $clusterSemaphore->tryAcquire();
            $this->fail("故障时获取信号量应该失败");
        } catch (\Exception $e) {
            $this->assertNotNull($e);
        }
        
        // 故障恢复
        $this->client->connect();
        
        $recoveredSemaphore = $this->client->getSemaphore('cluster:semaphore', 3);
        $recoveredCounter = $this->client->getAtomicLong('cluster:semaphore:counter');
        
        // 验证信号量状态重置
        $this->assertEquals(3, $recoveredSemaphore->availablePermits());
        
        // 验证计数器数据保持
        $this->assertEquals(3, $recoveredCounter->get());
        
        // 测试信号量恢复正常
        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue($recoveredSemaphore->tryAcquire());
            $recoveredCounter->incrementAndGet();
        }
        
        $this->assertEquals(6, $recoveredCounter->get());
        
        // 释放许可
        for ($i = 0; $i < 3; $i++) {
            $recoveredSemaphore->release();
        }
        
        $this->assertEquals(3, $recoveredSemaphore->availablePermits());
        
        // 清理
        $recoveredSemaphore->clear();
        $recoveredCounter->delete();
    }
    
    /**
     * 测试集群模式下的原子操作一致性
     */
    public function testClusterAtomicOperationsConsistency()
    {
        $atomicMap = $this->client->getMap('cluster:atomic:map');
        $atomicCounter = $this->client->getAtomicLong('cluster:atomic:counter');
        $atomicLock = $this->client->getLock('cluster:atomic:lock');
        
        // 模拟分布式原子操作
        $operations = 40;
        for ($i = 0; $i < $operations; $i++) {
            if ($atomicLock->tryLock()) {
                try {
                    $currentValue = $atomicCounter->get();
                    $atomicCounter->set($currentValue + 1);
                    
                    $atomicMap->put("atomic:key:$i", [
                        'operation' => $i,
                        'value' => $currentValue + 1,
                        'timestamp' => time()
                    ]);
                } finally {
                    $atomicLock->unlock();
                }
            }
        }
        
        // 验证原子操作结果
        $this->assertEquals($operations, $atomicCounter->get());
        $this->assertEquals($operations, $atomicMap->size());
        
        // 模拟集群故障
        $this->client->shutdown();
        $this->client->connect();
        
        // 验证故障恢复后的一致性
        $recoveredMap = $this->client->getMap('cluster:atomic:map');
        $recoveredCounter = $this->client->getAtomicLong('cluster:atomic:counter');
        $recoveredLock = $this->client->getLock('cluster:atomic:lock');
        
        $this->assertEquals($operations, $recoveredMap->size());
        $this->assertEquals($operations, $recoveredCounter->get());
        
        // 验证数据完整性
        $validOperations = 0;
        for ($i = 0; $i < $operations; $i++) {
            $data = $recoveredMap->get("atomic:key:$i");
            if ($data && isset($data['operation'])) {
                $validOperations++;
            }
        }
        
        $this->assertEquals($operations, $validOperations);
        
        // 测试故障恢复后的原子操作
        if ($recoveredLock->tryLock()) {
            try {
                $newValue = $recoveredCounter->getAndSet($operations + 10);
                $this->assertEquals($operations, $newValue);
                $this->assertEquals($operations + 10, $recoveredCounter->get());
            } finally {
                $recoveredLock->unlock();
            }
        }
        
        // 清理
        $recoveredMap->clear();
        $recoveredCounter->delete();
    }
    
    /**
     * 测试集群故障恢复后的数据验证机制
     */
    public function testClusterDataValidationAfterFailover()
    {
        $validationMap = $this->client->getMap('cluster:validation:map');
        $validationSet = $this->client->getSet('cluster:validation:set');
        $validationCounter = $this->client->getAtomicLong('cluster:validation:counter');
        $validationBloom = $this->client->getBloomFilter('cluster:validation:bloom', 100, 0.01);
        
        // 创建复杂的验证数据
        $validationData = [];
        for ($i = 0; $i < 50; $i++) {
            $data = [
                'id' => $i,
                'type' => $i % 3 === 0 ? 'user' : ($i % 3 === 1 ? 'session' : 'data'),
                'status' => 'active',
                'checksum' => md5("item:$i"),
                'created_at' => time(),
                'metadata' => [
                    'cluster' => 'primary',
                    'node' => $i % 3,
                    'replicated' => true
                ]
            ];
            
            $validationMap->put("validation:item:$i", $data);
            $validationSet->add("validation:type:{$data['type']}:$i");
            $validationBloom->add("validation:item:$i");
            $validationData[] = $data;
        }
        $validationCounter->set(50);
        
        // 验证初始状态
        $this->assertEquals(50, $validationMap->size());
        $this->assertEquals(50, $validationSet->size());
        $this->assertEquals(50, $validationCounter->get());
        
        // 模拟集群故障
        $this->client->shutdown();
        $this->client->connect();
        
        // 故障恢复后的全面验证
        $recoveredMap = $this->client->getMap('cluster:validation:map');
        $recoveredSet = $this->client->getSet('cluster:validation:set');
        $recoveredCounter = $this->client->getAtomicLong('cluster:validation:counter');
        $recoveredBloom = $this->client->getBloomFilter('cluster:validation:bloom', 100, 0.01);
        
        // 基本状态验证
        $this->assertEquals(50, $recoveredMap->size());
        $this->assertEquals(50, $recoveredSet->size());
        $this->assertEquals(50, $recoveredCounter->get());
        
        // 数据完整性验证
        $integrityErrors = 0;
        $checksumErrors = 0;
        $metadataErrors = 0;
        
        foreach ($validationData as $expectedData) {
            $itemId = $expectedData['id'];
            $actualData = $recoveredMap->get("validation:item:$itemId");
            
            if (!$actualData) {
                $integrityErrors++;
                continue;
            }
            
            // 验证ID
            if ($actualData['id'] !== $expectedData['id']) {
                $integrityErrors++;
            }
            
            // 验证类型
            if ($actualData['type'] !== $expectedData['type']) {
                $integrityErrors++;
            }
            
            // 验证状态
            if ($actualData['status'] !== $expectedData['status']) {
                $integrityErrors++;
            }
            
            // 验证校验和
            if ($actualData['checksum'] !== $expectedData['checksum']) {
                $checksumErrors++;
            }
            
            // 验证元数据
            if (!isset($actualData['metadata']['cluster']) || 
                !isset($actualData['metadata']['replicated'])) {
                $metadataErrors++;
            }
        }
        
        // 验证布隆过滤器
        $bloomMisses = 0;
        for ($i = 0; $i < 50; $i++) {
            if (!$recoveredBloom->contains("validation:item:$i")) {
                $bloomMisses++;
            }
        }
        
        // 验证结果
        $this->assertEquals(0, $integrityErrors, "数据完整性错误应该为0");
        $this->assertEquals(0, $checksumErrors, "校验和错误应该为0");
        $this->assertEquals(0, $metadataErrors, "元数据错误应该为0");
        $this->assertEquals(0, $bloomMisses, "布隆过滤器缺失应该为0");
        
        // 清理
        $recoveredMap->clear();
        $recoveredSet->clear();
        $recoveredCounter->delete();
        $recoveredBloom->delete();
    }
}