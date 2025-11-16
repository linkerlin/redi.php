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
use Rediphp\RReadWriteLock;
use Rediphp\RCountDownLatch;
use Rediphp\RBitSet;
use Rediphp\RBloomFilter;
use Rediphp\RTopic;
use Rediphp\RHyperLogLog;
use Rediphp\RGeo;
use Rediphp\RStream;
use Rediphp\RTimeSeries;

/**
 * 性能集成测试 - 性能基准和压力测试
 * 测试redi.php在不同负载下的性能表现
 */
class PerformanceIntegrationTest extends RedissonTestCase
{
    private const PERFORMANCE_THRESHOLD_MS = 1000; // 1秒性能阈值
    
    /**
     * 测试基本操作的性能基准
     */
    public function testBasicOperationsPerformance()
    {
        $map = $this->client->getMap('perf:basic:map');
        $list = $this->client->getList('perf:basic:list');
        $set = $this->client->getSet('perf:basic:set');
        $counter = $this->client->getAtomicLong('perf:basic:counter');
        
        $startTime = microtime(true);
        
        // Map操作性能测试
        for ($i = 0; $i < 100; $i++) {
            $map->put("key:$i", "value:$i");
        }
        
        for ($i = 0; $i < 100; $i++) {
            $map->get("key:$i");
        }
        
        // List操作性能测试
        for ($i = 0; $i < 100; $i++) {
            $list->add("item:$i");
        }
        
        for ($i = 0; $i < 50; $i++) {
            $list->get($i);
        }
        
        // Set操作性能测试
        for ($i = 0; $i < 100; $i++) {
            $set->add("member:$i");
        }
        
        for ($i = 0; $i < 100; $i++) {
            $set->contains("member:$i");
        }
        
        // 原子操作性能测试
        for ($i = 0; $i < 100; $i++) {
            $counter->incrementAndGet();
        }
        
        $endTime = microtime(true);
        $durationMs = ($endTime - $startTime) * 1000;
        
        // 验证性能在可接受范围内
        $this->assertLessThan(self::PERFORMANCE_THRESHOLD_MS, $durationMs);
        
        // 验证数据完整性
        $this->assertEquals(100, $map->size());
        $this->assertEquals(100, $list->size());
        $this->assertEquals(100, $set->size());
        $this->assertEquals(100, $counter->get());
        
        // 清理
        $map->clear();
        $list->clear();
        $set->clear();
        $counter->delete();
    }
    
    /**
     * 测试并发操作的性能
     */
    public function testConcurrentOperationsPerformance()
    {
        $concurrentMap = $this->client->getMap('perf:concurrent:map');
        $semaphore = $this->client->getSemaphore('perf:concurrent:semaphore', 10);
        $performanceCounter = $this->client->getAtomicLong('perf:concurrent:counter');
        
        $startTime = microtime(true);
        $concurrentOperations = 50;
        
        // 模拟并发操作
        for ($i = 0; $i < $concurrentOperations; $i++) {
            if ($semaphore->tryAcquire()) {
                try {
                    $concurrentMap->put("concurrent:key:$i", "concurrent:value:$i");
                    $performanceCounter->incrementAndGet();
                } finally {
                    $semaphore->release();
                }
            }
        }
        
        $endTime = microtime(true);
        $durationMs = ($endTime - $startTime) * 1000;
        
        // 验证并发性能
        $this->assertLessThan(self::PERFORMANCE_THRESHOLD_MS, $durationMs);
        $this->assertEquals($concurrentOperations, $concurrentMap->size());
        $this->assertEquals($concurrentOperations, $performanceCounter->get());
        
        // 清理
        $concurrentMap->clear();
        $performanceCounter->delete();
        $semaphore->clear();
    }
    
    /**
     * 测试管道操作的性能优势
     */
    public function testPipelinePerformance()
    {
        $pipelineMap = $this->client->getMap('perf:pipeline:map');
        $pipelineList = $this->client->getList('perf:pipeline:list');
        
        // 非管道操作基准
        $startTime = microtime(true);
        for ($i = 0; $i < 50; $i++) {
            $pipelineMap->put("nonpipe:key:$i", "nonpipe:value:$i");
        }
        $nonPipelineDuration = (microtime(true) - $startTime) * 1000;
        
        // 清理
        $pipelineMap->clear();
        
        // 管道操作测试
        $startTime = microtime(true);
        
        // 使用管道批量操作
        $pipeline = $this->client->getRedis()->pipeline();
        for ($i = 0; $i < 50; $i++) {
            $pipelineMap->put("pipe:key:$i", "pipe:value:$i");
        }
        $pipelineResults = $pipeline->execute();
        
        $pipelineDuration = (microtime(true) - $startTime) * 1000;
        
        // 验证管道性能优势
        $this->assertLessThan($nonPipelineDuration, $pipelineDuration);
        $this->assertEquals(50, $pipelineMap->size());
        
        // 清理
        $pipelineMap->clear();
    }
    
    /**
     * 测试大数据量操作的性能
     */
    public function testLargeDataPerformance()
    {
        $largeMap = $this->client->getMap('perf:large:map');
        $largeList = $this->client->getList('perf:large:list');
        
        $dataSize = 1000;
        $startTime = microtime(true);
        
        // 大数据量插入
        for ($i = 0; $i < $dataSize; $i++) {
            $largeMap->put("large:key:$i", str_repeat("large_value_$i", 10));
        }
        
        for ($i = 0; $i < $dataSize; $i++) {
            $largeList->add(str_repeat("large_item_$i", 10));
        }
        
        $insertDuration = (microtime(true) - $startTime) * 1000;
        
        // 大数据量查询
        $startTime = microtime(true);
        
        for ($i = 0; $i < 100; $i++) {
            $randomKey = rand(0, $dataSize - 1);
            $largeMap->get("large:key:$randomKey");
        }
        
        for ($i = 0; $i < 100; $i++) {
            $randomIndex = rand(0, 99);
            $largeList->get($randomIndex);
        }
        
        $queryDuration = (microtime(true) - $startTime) * 1000;
        
        // 验证性能和数据完整性
        $this->assertLessThan(5000, $insertDuration); // 插入应该在5秒内完成
        $this->assertLessThan(1000, $queryDuration); // 查询应该在1秒内完成
        $this->assertEquals($dataSize, $largeMap->size());
        $this->assertEquals($dataSize, $largeList->size());
        
        // 清理
        $largeMap->clear();
        $largeList->clear();
    }
    
    /**
     * 测试锁操作的性能影响
     */
    public function testLockPerformance()
    {
        $lockedMap = $this->client->getMap('perf:lock:map');
        $performanceLock = $this->client->getLock('perf:lock:lock');
        $lockCounter = $this->client->getAtomicLong('perf:lock:counter');
        
        $startTime = microtime(true);
        $lockOperations = 20;
        
        // 带锁的操作
        for ($i = 0; $i < $lockOperations; $i++) {
            if ($performanceLock->tryLock()) {
                try {
                    $lockedMap->put("locked:key:$i", "locked:value:$i");
                    $lockCounter->incrementAndGet();
                } finally {
                    $performanceLock->unlock();
                }
            }
        }
        
        $lockDuration = (microtime(true) - $startTime) * 1000;
        
        // 验证锁性能
        $this->assertLessThan(self::PERFORMANCE_THRESHOLD_MS, $lockDuration);
        $this->assertEquals($lockOperations, $lockedMap->size());
        $this->assertEquals($lockOperations, $lockCounter->get());
        
        // 清理
        $lockedMap->clear();
        $lockCounter->delete();
    }
    
    /**
     * 测试内存使用效率
     */
    public function testMemoryEfficiency()
    {
        $memoryMap = $this->client->getMap('perf:memory:map');
        $memoryList = $this->client->getList('perf:memory:list');
        
        // 测试不同大小数据的内存效率
        $testSizes = [10, 50, 100];
        
        foreach ($testSizes as $size) {
            $startTime = microtime(true);
            
            // 插入数据
            for ($i = 0; $i < $size; $i++) {
                $value = str_repeat("x", $size * 10); // 可变大小数据
                $memoryMap->put("mem:key:$i:$size", $value);
                $memoryList->add($value);
            }
            
            $duration = (microtime(true) - $startTime) * 1000;
            
            // 验证性能随数据大小的变化
            $this->assertLessThan(self::PERFORMANCE_THRESHOLD_MS, $duration);
            
            // 清理当前批次
            $memoryMap->clear();
            $memoryList->clear();
        }
    }
    
    /**
     * 测试长时间运行的稳定性
     */
    public function testLongRunningStability()
    {
        $stabilityMap = $this->client->getMap('perf:stability:map');
        $stabilityCounter = $this->client->getAtomicLong('perf:stability:counter');
        
        $iterations = 100;
        $startTime = microtime(true);
        
        // 长时间运行测试
        for ($i = 0; $i < $iterations; $i++) {
            $stabilityMap->put("stable:key:$i", "stable:value:$i");
            $stabilityCounter->incrementAndGet();
            
            // 每10次迭代验证一次
            if ($i % 10 == 0) {
                $this->assertEquals($i + 1, $stabilityMap->size());
                $this->assertEquals($i + 1, $stabilityCounter->get());
            }
        }
        
        $duration = (microtime(true) - $startTime) * 1000;
        
        // 验证稳定性
        $this->assertLessThan(self::PERFORMANCE_THRESHOLD_MS * 2, $duration);
        $this->assertEquals($iterations, $stabilityMap->size());
        $this->assertEquals($iterations, $stabilityCounter->get());
        
        // 清理
        $stabilityMap->clear();
        $stabilityCounter->delete();
    }
}