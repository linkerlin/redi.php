<?php

namespace Rediphp\Tests;

use Rediphp\RMap;
use Rediphp\RList;
use Rediphp\RSet;
use Rediphp\RQueue;
use Rediphp\RSemaphore;
use Rediphp\RLock;
use Rediphp\RAtomicLong;
use Rediphp\RBucket;

/**
 * Performance and concurrency integration tests
 * Tests high-load scenarios and concurrent operations
 */
class PerformanceIntegrationTest extends RedissonTestCase
{
    /**
     * Test high-frequency concurrent operations
     */
    public function testHighFrequencyConcurrentOperations()
    {
        $counter = $this->client->getAtomicLong('perf:counter');
        $lock = $this->client->getLock('perf:lock');
        $dataMap = $this->client->getMap('perf:data');
        
        $iterations = 100;
        $startTime = microtime(true);
        
        // 模拟高频并发操作
        for ($i = 0; $i < $iterations; $i++) {
            if ($lock->tryLock()) {
                try {
                    // 原子操作
                    $current = $counter->get();
                    $counter->set($current + 1);
                    
                    // 存储操作记录
                    $dataMap->put("operation_$i", ['timestamp' => time(), 'value' => $i]);
                    
                } finally {
                    $lock->unlock();
                }
            }
        }
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // 验证结果
        $this->assertEquals($iterations, $counter->get());
        $this->assertEquals($iterations, $dataMap->size());
        
        // 性能验证 - 100次操作应该在合理时间内完成
        $this->assertLessThan(10, $executionTime, "High frequency operations took too long: {$executionTime}s");
        
        // 清理
        $counter->delete();
        $dataMap->clear();
    }
    
    /**
     * Test bulk operations performance
     */
    public function testBulkOperationsPerformance()
    {
        $dataMap = $this->client->getMap('perf:bulk:map');
        $dataList = $this->client->getList('perf:bulk:list');
        $dataSet = $this->client->getSet('perf:bulk:set');
        
        $bulkSize = 1000;
        
        // 批量插入Map
        $startTime = microtime(true);
        for ($i = 0; $i < $bulkSize; $i++) {
            $dataMap->put("key_$i", ['data' => "value_$i", 'index' => $i]);
        }
        $mapInsertTime = microtime(true) - $startTime;
        
        // 批量插入List
        $startTime = microtime(true);
        for ($i = 0; $i < $bulkSize; $i++) {
            $dataList->add("item_$i");
        }
        $listInsertTime = microtime(true) - $startTime;
        
        // 批量插入Set
        $startTime = microtime(true);
        for ($i = 0; $i < $bulkSize; $i++) {
            $dataSet->add("element_$i");
        }
        $setInsertTime = microtime(true) - $startTime;
        
        // 验证数据完整性
        $this->assertEquals($bulkSize, $dataMap->size());
        $this->assertEquals($bulkSize, $dataList->size());
        $this->assertEquals($bulkSize, $dataSet->size());
        
        // 验证性能
        $this->assertLessThan(5, $mapInsertTime, "Map bulk insert too slow: {$mapInsertTime}s");
        $this->assertLessThan(5, $listInsertTime, "List bulk insert too slow: {$listInsertTime}s");
        $this->assertLessThan(5, $setInsertTime, "Set bulk insert too slow: {$setInsertTime}s");
        
        // 测试批量读取性能
        $startTime = microtime(true);
        $mapValues = [];
        for ($i = 0; $i < $bulkSize; $i++) {
            $mapValues[] = $dataMap->get("key_$i");
        }
        $mapReadTime = microtime(true) - $startTime;
        
        $this->assertEquals($bulkSize, count($mapValues));
        $this->assertLessThan(5, $mapReadTime, "Map bulk read too slow: {$mapReadTime}s");
        
        // 清理
        $dataMap->clear();
        $dataList->clear();
        $dataSet->clear();
    }
    
    /**
     * Test memory efficiency with large data sets
     */
    public function testMemoryEfficiencyLargeDataSets()
    {
        $largeMap = $this->client->getMap('perf:large:map');
        $largeList = $this->client->getList('perf:large:list');
        
        $largeDataSize = 500; // 大数据集
        $largeDataItem = str_repeat('x', 1024); // 1KB的数据项
        
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        // 插入大数据集到Map
        for ($i = 0; $i < $largeDataSize; $i++) {
            $largeMap->put("large_key_$i", [
                'data' => $largeDataItem,
                'index' => $i,
                'timestamp' => time()
            ]);
        }
        
        // 插入大数据集到List
        for ($i = 0; $i < $largeDataSize; $i++) {
            $largeList->add([
                'data' => $largeDataItem,
                'index' => $i,
                'type' => 'large_item'
            ]);
        }
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        
        $executionTime = $endTime - $startTime;
        $memoryUsage = ($endMemory - $startMemory) / 1024 / 1024; // MB
        
        // 验证数据完整性
        $this->assertEquals($largeDataSize, $largeMap->size());
        $this->assertEquals($largeDataSize, $largeList->size());
        
        // 性能验证
        $this->assertLessThan(15, $executionTime, "Large dataset operations too slow: {$executionTime}s");
        
        // 内存使用应该在合理范围内（这个测试主要是确保不会内存泄漏）
        $this->assertLessThan(100, $memoryUsage, "Memory usage too high: {$memoryUsage}MB");
        
        // 清理
        $largeMap->clear();
        $largeList->clear();
    }
    
    /**
     * Test concurrent semaphore operations
     */
    public function testConcurrentSemaphoreOperations()
    {
        $semaphore = $this->client->getSemaphore('perf:semaphore', 10);
        $counter = $this->client->getAtomicLong('perf:semaphore:counter');
        
        // 设置初始许可数
        $semaphore->trySetPermits(10);
        
        $concurrentOperations = 20;
        $successfulOperations = 0;
        
        $startTime = microtime(true);
        
        // 模拟并发获取许可
        for ($i = 0; $i < $concurrentOperations; $i++) {
            if ($semaphore->tryAcquire()) {
                $successfulOperations++;
                $counter->incrementAndGet();
                
                // 模拟一些工作
                usleep(1000); // 1ms
                
                // 释放许可
                $semaphore->release();
            }
        }
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // 验证结果
        $this->assertGreaterThan(0, $successfulOperations, "No semaphore operations succeeded");
        $this->assertEquals($successfulOperations, $counter->get());
        $this->assertEquals(10, $semaphore->availablePermits()); // 应该恢复到初始值
        
        // 性能验证
        $this->assertLessThan(5, $executionTime, "Semaphore operations too slow: {$executionTime}s");
        
        // 清理
        $counter->delete();
        $semaphore->clear();
    }
    
    /**
     * Test mixed operations performance
     */
    public function testMixedOperationsPerformance()
    {
        $mixedMap = $this->client->getMap('perf:mixed:map');
        $mixedList = $this->client->getList('perf:mixed:list');
        $mixedSet = $this->client->getSet('perf:mixed:set');
        $mixedCounter = $this->client->getAtomicLong('perf:mixed:counter');
        
        $operations = 200;
        $startTime = microtime(true);
        
        // 混合操作
        for ($i = 0; $i < $operations; $i++) {
            switch ($i % 4) {
                case 0: // Map操作
                    $mixedMap->put("key_$i", ['operation' => $i, 'type' => 'map']);
                    break;
                case 1: // List操作
                    $mixedList->add(['operation' => $i, 'type' => 'list']);
                    break;
                case 2: // Set操作
                    $mixedSet->add("element_$i");
                    break;
                case 3: // 计数器操作
                    $mixedCounter->incrementAndGet();
                    break;
            }
        }
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // 验证结果
        $this->assertGreaterThan(0, $mixedMap->size());
        $this->assertGreaterThan(0, $mixedList->size());
        $this->assertGreaterThan(0, $mixedSet->size());
        $this->assertGreaterThan(0, $mixedCounter->get());
        
        // 性能验证
        $this->assertLessThan(10, $executionTime, "Mixed operations too slow: {$executionTime}s");
        
        // 计算每秒操作数
        $opsPerSecond = $operations / $executionTime;
        $this->assertGreaterThan(20, $opsPerSecond, "Operations per second too low: {$opsPerSecond}");
        
        // 清理
        $mixedMap->clear();
        $mixedList->clear();
        $mixedSet->clear();
        $mixedCounter->delete();
    }
    
    /**
     * Test stress test with rapid operations
     */
    public function testStressTestRapidOperations()
    {
        $stressMap = $this->client->getMap('perf:stress:map');
        $stressCounter = $this->client->getAtomicLong('perf:stress:counter');
        $stressLock = $this->client->getLock('perf:stress:lock');
        
        $rapidOperations = 50; // 快速操作数量
        $startTime = microtime(true);
        
        // 快速连续操作
        for ($i = 0; $i < $rapidOperations; $i++) {
            if ($stressLock->tryLock()) {
                try {
                    // 快速写入
                    $stressMap->put("rapid_key_$i", ['index' => $i, 'timestamp' => microtime(true)]);
                    $stressCounter->incrementAndGet();
                    
                    // 快速读取
                    $value = $stressMap->get("rapid_key_$i");
                    $this->assertNotNull($value);
                    
                } finally {
                    $stressLock->unlock();
                }
            }
        }
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // 验证结果
        $this->assertEquals($rapidOperations, $stressCounter->get());
        $this->assertEquals($rapidOperations, $stressMap->size());
        
        // 性能验证 - 快速操作应该在很短时间内完成
        $this->assertLessThan(3, $executionTime, "Rapid operations too slow: {$executionTime}s");
        
        // 计算平均操作时间
        $avgOperationTime = ($executionTime / $rapidOperations) * 1000; // ms
        $this->assertLessThan(60, $avgOperationTime, "Average operation time too high: {$avgOperationTime}ms");
        
        // 清理
        $stressMap->clear();
        $stressCounter->delete();
    }
}