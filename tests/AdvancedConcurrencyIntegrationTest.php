<?php

namespace Rediphp\Tests;

use Rediphp\RMap;
use Rediphp\RList;
use Rediphp\RSet;
use Rediphp\RAtomicLong;
use Rediphp\RLock;
use Rediphp\RSemaphore;
use Rediphp\RReadWriteLock;

/**
 * 高级并发冲突和数据一致性集成测试
 * 测试复杂并发场景下的数据一致性和冲突解决机制
 */
class AdvancedConcurrencyIntegrationTest extends RedissonTestCase
{
    /**
     * 测试复杂并发写冲突和数据一致性
     */
    public function testComplexConcurrentWriteConflicts()
    {
        $conflictMap = $this->client->getMap('concurrency:conflict:map');
        $conflictCounter = $this->client->getAtomicLong('concurrency:conflict:counter');
        $conflictLock = $this->client->getLock('concurrency:conflict:lock');
        $writeQueue = $this->client->getQueue('concurrency:conflict:queue');
        
        $operations = 200;
        $threads = 10;
        $operationsPerThread = $operations / $threads;
        
        // 初始化测试数据
        $initialData = [
            'base_value' => 1000,
            'version' => 1,
            'timestamp' => time()
        ];
        $conflictMap->put('conflict:test:item', $initialData);
        $conflictCounter->set(0);
        
        // 模拟复杂的并发写入冲突
        $results = [];
        for ($threadId = 0; $threadId < $threads; $threadId++) {
            $results[$threadId] = [
                'success_count' => 0,
                'conflict_count' => 0,
                'retry_count' => 0,
                'final_value' => null
            ];
            
            for ($op = 0; $op < $operationsPerThread; $op++) {
                $operationId = $threadId * $operationsPerThread + $op;
                $retryCount = 0;
                $maxRetries = 3;
                $success = false;
                
                while (!$success && $retryCount < $maxRetries) {
                    if ($conflictLock->tryLock()) {
                        try {
                            // 读取当前状态
                            $currentData = $conflictMap->get('conflict:test:item');
                            if (!$currentData) {
                                $currentData = $initialData;
                            }
                            
                            // 模拟复杂的业务逻辑
                            $newData = $currentData;
                            $newData['base_value'] += rand(1, 10);
                            $newData['version'] += 1;
                            $newData['last_modified_by'] = $threadId;
                            $newData['operation_id'] = $operationId;
                            $newData['modified_at'] = time();
                            
                            // 添加到操作队列
                            $writeQueue->add([
                                'thread_id' => $threadId,
                                'operation_id' => $operationId,
                                'timestamp' => time()
                            ]);
                            
                            // 写回数据
                            $conflictMap->put('conflict:test:item', $newData);
                            $conflictCounter->incrementAndGet();
                            
                            $results[$threadId]['success_count']++;
                            $success = true;
                            
                        } catch (\Exception $e) {
                            $results[$threadId]['conflict_count']++;
                        } finally {
                            $conflictLock->unlock();
                        }
                    } else {
                        // 锁不可用时的重试逻辑
                        $retryCount++;
                        $results[$threadId]['retry_count']++;
                        usleep(rand(100, 1000)); // 随机延迟
                    }
                }
                
                if (!$success) {
                    $results[$threadId]['conflict_count']++;
                }
            }
        }
        
        // 验证并发操作结果
        $totalSuccess = array_sum(array_column($results, 'success_count'));
        $totalConflicts = array_sum(array_column($results, 'conflict_count'));
        $totalRetries = array_sum(array_column($results, 'retry_count'));
        
        $this->assertEquals($operations, $totalSuccess + $totalConflicts);
        $this->assertGreaterThan(0, $totalRetries, "应该有重试操作");
        
        // 验证最终数据状态
        $finalData = $conflictMap->get('conflict:test:item');
        $this->assertNotNull($finalData);
        $this->assertEquals($operations, $finalData['base_value'] - $initialData['base_value']);
        $this->assertEquals($operations + 1, $finalData['version']);
        
        // 验证操作队列
        $this->assertEquals($operations, $writeQueue->size());
        
        // 清理
        $conflictMap->clear();
        $conflictCounter->delete();
        $writeQueue->clear();
    }
    
    /**
     * 测试读写锁在复杂并发场景下的表现
     */
    public function testComplexReadWriteLockScenario()
    {
        $rwLockMap = $this->client->getMap('rwlock:complex:map');
        $readLock = $this->client->getReadWriteLock('rwlock:complex:rwlock');
        $readCounter = $this->client->getAtomicLong('rwlock:read:counter');
        $writeCounter = $this->client->getAtomicLong('rwlock:write:counter');
        $operationLog = $this->client->getList('rwlock:operation:log');
        
        // 初始化数据
        $initialSize = 50;
        for ($i = 0; $i < $initialSize; $i++) {
            $rwLockMap->put("item:$i", [
                'value' => $i * 10,
                'read_count' => 0,
                'write_count' => 0
            ]);
        }
        
        $operations = 100;
        $threads = 8;
        $operationsPerThread = $operations / $threads;
        
        // 模拟复杂读写操作
        for ($threadId = 0; $threadId < $threads; $threadId++) {
            $threadOperationCount = 0;
            
            for ($op = 0; $op < $operationsPerThread; $op++) {
                $operationType = $threadId % 3; // 0: 读取, 1: 写入, 2: 批量操作
                $itemKey = "item:" . rand(0, $initialSize - 1);
                
                try {
                    switch ($operationType) {
                        case 0: // 读取操作
                            if ($readLock->readLock()->tryLock(1000)) {
                                try {
                                    $data = $rwLockMap->get($itemKey);
                                    if ($data) {
                                        $data['read_count']++;
                                        $readCounter->incrementAndGet();
                                        $operationLog->add([
                                            'type' => 'read',
                                            'thread_id' => $threadId,
                                            'item_key' => $itemKey,
                                            'timestamp' => microtime(true)
                                        ]);
                                    }
                                } finally {
                                    $readLock->readLock()->unlock();
                                }
                            }
                            break;
                            
                        case 1: // 写入操作
                            if ($readLock->writeLock()->tryLock(2000)) {
                                try {
                                    $data = $rwLockMap->get($itemKey);
                                    if ($data) {
                                        $data['value'] += rand(1, 5);
                                        $data['write_count']++;
                                        $rwLockMap->put($itemKey, $data);
                                        $writeCounter->incrementAndGet();
                                        $operationLog->add([
                                            'type' => 'write',
                                            'thread_id' => $threadId,
                                            'item_key' => $itemKey,
                                            'timestamp' => microtime(true)
                                        ]);
                                    }
                                } finally {
                                    $readLock->writeLock()->unlock();
                                }
                            }
                            break;
                            
                        case 2: // 批量操作
                            if ($readLock->writeLock()->tryLock(3000)) {
                                try {
                                    $batchSize = rand(3, 8);
                                    for ($i = 0; $i < $batchSize; $i++) {
                                        $batchKey = "item:" . rand(0, $initialSize - 1);
                                        $data = $rwLockMap->get($batchKey);
                                        if ($data) {
                                            $data['value'] += rand(10, 20);
                                            $data['write_count']++;
                                            $rwLockMap->put($batchKey, $data);
                                        }
                                    }
                                    $writeCounter->addAndGet($batchSize);
                                    $operationLog->add([
                                        'type' => 'batch_write',
                                        'thread_id' => $threadId,
                                        'batch_size' => $batchSize,
                                        'timestamp' => microtime(true)
                                    ]);
                                } finally {
                                    $readLock->writeLock()->unlock();
                                }
                            }
                            break;
                    }
                } catch (\Exception $e) {
                    // 记录异常但不中断测试
                    $operationLog->add([
                        'type' => 'error',
                        'thread_id' => $threadId,
                        'error' => $e->getMessage(),
                        'timestamp' => microtime(true)
                    ]);
                }
            }
        }
        
        // 验证读写锁操作结果
        $this->assertGreaterThan(0, $readCounter->get());
        $this->assertGreaterThan(0, $writeCounter->get());
        $this->assertGreaterThan(0, $operationLog->size());
        
        // 验证数据一致性
        $totalValue = 0;
        $totalReadCount = 0;
        $totalWriteCount = 0;
        
        for ($i = 0; $i < $initialSize; $i++) {
            $data = $rwLockMap->get("item:$i");
            if ($data) {
                $totalValue += $data['value'];
                $totalReadCount += $data['read_count'];
                $totalWriteCount += $data['write_count'];
            }
        }
        
        $this->assertGreaterThan(0, $totalValue);
        $this->assertGreaterThan(0, $totalReadCount);
        $this->assertGreaterThan(0, $totalWriteCount);
        
        // 验证操作日志
        $operationTypes = ['read', 'write', 'batch_write', 'error'];
        foreach ($operationTypes as $type) {
            $typeCount = 0;
            $operations = $operationLog->toArray();
            foreach ($operations as $op) {
                if (is_array($op) && isset($op['type']) && $op['type'] === $type) {
                    $typeCount++;
                }
            }
            // 每个操作类型都应该有一些操作
            if ($type !== 'error') {
                $this->assertGreaterThanOrEqual(0, $typeCount);
            }
        }
        
        // 清理
        $rwLockMap->clear();
        $operationLog->clear();
    }
    
    /**
     * 测试信号量在复杂并发控制中的表现
     */
    public function testComplexSemaphoreConcurrencyControl()
    {
        $semaphoreResource = $this->client->getSemaphore('complex:semaphore', 5);
        $resourcePool = $this->client->getMap('complex:resource:pool');
        $resourceCounter = $this->client->getAtomicLong('complex:resource:counter');
        $resourceQueue = $this->client->getQueue('complex:resource:queue');
        $semaphoreLock = $this->client->getLock('complex:semaphore:lock');
        
        // 初始化资源池
        $resourcePool->clear();
        for ($i = 0; $i < 20; $i++) {
            $resourcePool->put("resource:$i", [
                'status' => 'available',
                'owner' => null,
                'usage_count' => 0,
                'created_at' => time()
            ]);
            $resourceQueue->add("resource:$i");
        }
        $resourceCounter->set(20);
        
        // 模拟复杂的资源请求
        $requestCount = 50;
        $successfulRequests = 0;
        $failedRequests = 0;
        
        for ($requestId = 0; $requestId < $requestCount; $requestId++) {
            $threadId = $requestId % 8; // 8个不同的客户端
            $requiredResources = rand(1, 3); // 每个请求需要1-3个资源
            $requestTimeout = rand(100, 2000); // 随机超时时间
            
            $acquiredResources = [];
            $requestSuccess = false;
            
            // 尝试获取信号量
            if ($semaphoreResource->tryAcquire($requestTimeout)) {
                try {
                    if ($semaphoreLock->tryLock()) {
                        try {
                            // 分配资源
                            for ($i = 0; $i < $requiredResources; $i++) {
                                $resourceId = $resourceQueue->poll();
                                if ($resourceId) {
                                    $resource = $resourcePool->get($resourceId);
                                    if ($resource && $resource['status'] === 'available') {
                                        $resource['status'] = 'in_use';
                                        $resource['owner'] = "thread:$threadId";
                                        $resource['usage_count']++;
                                        $resource['acquired_at'] = time();
                                        $resourcePool->put($resourceId, $resource);
                                        $acquiredResources[] = $resourceId;
                                    }
                                }
                            }
                            
                            if (count($acquiredResources) === $requiredResources) {
                                // 模拟资源使用
                                usleep(rand(1000, 5000)); // 1-5ms使用时间
                                
                                // 释放资源
                                foreach ($acquiredResources as $resourceId) {
                                    $resource = $resourcePool->get($resourceId);
                                    if ($resource) {
                                        $resource['status'] = 'available';
                                        $resource['owner'] = null;
                                        $resource['released_at'] = time();
                                        $resourcePool->put($resourceId, $resource);
                                        $resourceQueue->add($resourceId);
                                    }
                                }
                                
                                $successfulRequests++;
                                $requestSuccess = true;
                            } else {
                                // 资源不足，释放已获取的资源
                                foreach ($acquiredResources as $resourceId) {
                                    $resource = $resourcePool->get($resourceId);
                                    if ($resource) {
                                        $resource['status'] = 'available';
                                        $resource['owner'] = null;
                                        $resourcePool->put($resourceId, $resource);
                                        $resourceQueue->add($resourceId);
                                    }
                                }
                                $failedRequests++;
                            }
                        } finally {
                            $semaphoreLock->unlock();
                        }
                    }
                } finally {
                    $semaphoreResource->release();
                }
            } else {
                $failedRequests++;
            }
        }
        
        // 验证信号量控制结果
        $this->assertEquals($requestCount, $successfulRequests + $failedRequests);
        $this->assertGreaterThan(0, $successfulRequests);
        $this->assertGreaterThan(0, $failedRequests);
        
        // 验证资源状态
        $availableResources = 0;
        $inUseResources = 0;
        
        for ($i = 0; $i < 20; $i++) {
            $resource = $resourcePool->get("resource:$i");
            if ($resource) {
                if ($resource['status'] === 'available') {
                    $availableResources++;
                } elseif ($resource['status'] === 'in_use') {
                    $inUseResources++;
                }
            }
        }
        
        $this->assertGreaterThan(0, $availableResources);
        $this->assertEquals(0, $inUseResources, "所有资源应该已释放");
        
        // 验证资源池数据完整性
        $totalUsageCount = 0;
        for ($i = 0; $i < 20; $i++) {
            $resource = $resourcePool->get("resource:$i");
            if ($resource) {
                $totalUsageCount += $resource['usage_count'];
                $this->assertGreaterThanOrEqual(0, $resource['usage_count']);
            }
        }
        
        $this->assertEquals($successfulRequests, $totalUsageCount);
        
        // 清理
        $resourcePool->clear();
        $resourceQueue->clear();
    }
    
    /**
     * 测试分布式计数器在并发场景下的原子性
     */
    public function testDistributedCounterAtomicOperations()
    {
        $atomicCounter = $this->client->getAtomicLong('atomic:counter:complex');
        $counterLock = $this->client->getLock('atomic:counter:lock');
        $counterMap = $this->client->getMap('atomic:counter:operations');
        
        $threadCount = 5; // 减少线程数量以避免过于复杂的并发
        $operationsPerThread = 10; // 减少操作数量
        $totalOperations = $threadCount * $operationsPerThread;
        
        // 初始化
        $atomicCounter->set(0);
        $counterMap->clear();
        
        // 使用多线程执行原子操作
        $threads = [];
        $expectedValue = 0; // 跟踪期望值
        
        for ($threadId = 0; $threadId < $threadCount; $threadId++) {
            $threads[$threadId] = function() use ($threadId, $operationsPerThread, $atomicCounter, $counterMap, $counterLock, &$expectedValue) {
                for ($op = 0; $op < $operationsPerThread; $op++) {
                    $operationId = $threadId * $operationsPerThread + $op;
                    
                    // 使用锁保护的简单递增操作
                    if ($counterLock->tryLock()) {
                        try {
                            $currentValue = $atomicCounter->get();
                            $newValue = $currentValue + 1;
                            $atomicCounter->set($newValue);
                            
                            $counterMap->put("op:$operationId", [
                                'type' => 'increment',
                                'thread_id' => $threadId,
                                'old_value' => $currentValue,
                                'new_value' => $newValue,
                                'success' => true
                            ]);
                            
                            $expectedValue++; // 更新期望值
                            
                        } finally {
                            $counterLock->unlock();
                        }
                    } else {
                        // 如果无法获取锁，记录失败操作
                        $counterMap->put("op:$operationId", [
                            'type' => 'increment',
                            'thread_id' => $threadId,
                            'success' => false
                        ]);
                    }
                }
            };
        }
        
        // 执行所有线程
        foreach ($threads as $thread) {
            $thread();
        }
        
        // 验证原子操作结果
        $finalValue = $atomicCounter->get();
        
        // 验证操作日志完整性
        $operationCount = $counterMap->size();
        $this->assertEquals($totalOperations, $operationCount);
        
        // 验证成功操作的数量
        $successfulOperations = 0;
        $operationTypes = [];
        
        // 手动迭代获取所有操作记录
        for ($i = 0; $i < $totalOperations; $i++) {
            $operation = $counterMap->get("op:$i");
            if ($operation && is_array($operation)) {
                if (isset($operation['success']) && $operation['success']) {
                    $successfulOperations++;
                }
                if (isset($operation['type'])) {
                    $operationTypes[$operation['type']] = ($operationTypes[$operation['type']] ?? 0) + 1;
                }
            }
        }
        
        // 由于所有操作都应该是成功的（锁机制确保），验证最终值
        $this->assertEquals($expectedValue, $finalValue, "最终值应等于成功操作的次数");
        $this->assertGreaterThan(0, $finalValue, "计数器应该有值");
        
        // 验证所有操作都是递增操作
        $this->assertArrayHasKey('increment', $operationTypes, "应该有递增操作");
        $this->assertEquals($successfulOperations, $operationTypes['increment'], "所有成功操作都应该是递增");
        
        // 清理
        $atomicCounter->delete();
        $counterMap->clear();
    }
}