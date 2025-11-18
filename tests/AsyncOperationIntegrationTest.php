<?php

namespace Rediphp\Tests;

use Rediphp\AsyncRedissonClient;
use Rediphp\RedisPromise;

/**
 * AsyncOperationIntegrationTest - Comprehensive integration tests for asynchronous operations
 * Tests async client functionality, promise handling, concurrent operations, and error scenarios
 */
class AsyncOperationIntegrationTest extends RedissonTestCase
{
    private AsyncRedissonClient $asyncClient;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->asyncClient = new AsyncRedissonClient();
        
        // 清理所有异步测试相关数据
        $this->cleanupAsyncTestData();
    }
    
    protected function tearDown(): void
    {
        if (isset($this->asyncClient)) {
            try {
                $this->asyncClient->disconnect()->wait(5);
            } catch (\Exception $e) {
                // 忽略断开连接时的错误
            }
        }
        $this->cleanupAsyncTestData();
        parent::tearDown();
    }
    
    /**
     * Clean up async test data
     */
    private function cleanupAsyncTestData(): void
    {
        try {
            $redis = $this->client->getRedis();
            $keys = $redis->keys('async:test:*');
            if (!empty($keys)) {
                $redis->del(...$keys);
            }
        } catch (\Exception $e) {
            // 忽略清理错误
        }
    }
    
    // ==================== 异步连接测试 ====================
    
    /**
     * Test async client connection
     */
    public function testAsyncClientConnection(): void
    {
        $connectionPromise = $this->asyncClient->connect();
        $this->assertInstanceOf(RedisPromise::class, $connectionPromise);
        
        $result = $connectionPromise->wait(10);
        $this->assertSame($this->asyncClient, $result);
        $this->assertTrue($this->asyncClient->isConnected());
    }
    
    /**
     * Test async client disconnection
     */
    public function testAsyncClientDisconnection(): void
    {
        // 先连接
        $this->asyncClient->connect()->wait(10);
        $this->assertTrue($this->asyncClient->isConnected());
        
        // 断开连接
        $disconnectPromise = $this->asyncClient->disconnect();
        $result = $disconnectPromise->wait(5);
        $this->assertTrue($result);
    }
    
    /**
     * Test connection failure handling
     */
    public function testAsyncConnectionFailureHandling(): void
    {
        $invalidClient = new AsyncRedissonClient([
            'host' => 'invalid-host',
            'port' => 9999
        ]);
        
        $this->expectException(\Exception::class);
        $invalidClient->connect()->wait(5);
    }
    
    // ==================== Promise链式操作测试 ====================
    
    /**
     * Test promise chaining with list operations
     */
    public function testPromiseChainingWithListOperations(): void
    {
        $this->asyncClient->connect()->wait(10);
        
        $list = $this->asyncClient->getList('async:test:chaining');
        
        // Promise链：添加元素 -> 获取大小 -> 获取元素
        $result = $list->add('first')
            ->then(function() use ($list) {
                return $list->add('second');
            })
            ->then(function() use ($list) {
                return $list->add('third');
            })
            ->then(function() use ($list) {
                return $list->size();
            })
            ->then(function($size) use ($list) {
                $this->assertEquals(3, $size);
                return $list->get(0);
            })
            ->then(function($firstElement) {
                $this->assertEquals('first', $firstElement);
                return 'success';
            })
            ->wait(10);
        
        $this->assertEquals('success', $result);
    }
    
    /**
     * Test promise chaining with error propagation
     */
    public function testPromiseChainingErrorPropagation(): void
    {
        $this->asyncClient->connect()->wait(10);
        $list = $this->asyncClient->getList('async:test:error-chain');
        
        $errorCaught = false;
        
        // 创建一个会失败的promise链
        $result = $list->add('value')
            ->then(function() {
                throw new \Exception('Test error in promise chain');
            })
            ->then(function() {
                return 'This should not be reached';
            })
            ->catch(function(\Exception $e) use (&$errorCaught) {
                $errorCaught = true;
                $this->assertEquals('Test error in promise chain', $e->getMessage());
                return 'error handled';
            })
            ->wait(10);
        
        $this->assertTrue($errorCaught);
        $this->assertEquals('error handled', $result);
    }
    
    /**
     * Test complex promise chaining with multiple data structures
     */
    public function testComplexPromiseChaining(): void
    {
        $this->asyncClient->connect()->wait(10);
        
        $list = $this->asyncClient->getList('async:test:complex:list');
        $map = $this->asyncClient->getMap('async:test:complex:map');
        $set = $this->asyncClient->getSet('async:test:complex:set');
        
        $result = $list->addAll(['a', 'b', 'c'])
            ->then(function() use ($map) {
                return $map->put('key1', 'value1');
            })
            ->then(function() use ($set) {
                return $set->addAll(['x', 'y', 'z']);
            })
            ->then(function() use ($list, $map, $set) {
                return RedisPromise::all([
                    $list->size(),
                    $map->size(),
                    $set->size()
                ]);
            })
            ->then(function($sizes) {
                $this->assertEquals([3, 1, 3], $sizes);
                return 'complex operations completed';
            })
            ->wait(10);
        
        $this->assertEquals('complex operations completed', $result);
    }
    
    // ==================== 并发异步操作测试 ====================
    
    /**
     * Test concurrent async operations
     */
    public function testConcurrentAsyncOperations(): void
    {
        $this->asyncClient->connect()->wait(10);
        $list = $this->asyncClient->getList('async:test:concurrent');
        
        // 并发添加多个元素
        $promises = [];
        for ($i = 0; $i < 10; $i++) {
            $promises[] = $list->add("element_$i");
        }
        
        // 等待所有并发操作完成
        $results = RedisPromise::all($promises)->wait(10);
        
        // 验证所有操作都成功
        $this->assertCount(10, $results);
        foreach ($results as $result) {
            $this->assertTrue($result);
        }
        
        // 验证最终列表大小
        $finalSize = $list->size()->wait(5);
        $this->assertEquals(10, $finalSize);
    }
    
    /**
     * Test concurrent reads and writes
     */
    public function testConcurrentReadsAndWrites(): void
    {
        $this->asyncClient->connect()->wait(10);
        $map = $this->asyncClient->getMap('async:test:concurrent:rw');
        
        // 预填充一些数据
        for ($i = 0; $i < 5; $i++) {
            $map->put("key$i", "value$i")->wait(5);
        }
        
        // 并发读写操作
        $promises = [];
        
        // 5个写入操作
        for ($i = 5; $i < 10; $i++) {
            $promises[] = $map->put("key$i", "value$i");
        }
        
        // 5个读取操作
        for ($i = 0; $i < 5; $i++) {
            $promises[] = $map->get("key$i");
        }
        
        // 等待所有操作完成
        $results = RedisPromise::all($promises)->wait(10);
        
        // 验证写入操作结果
        $writeResults = array_slice($results, 0, 5);
        foreach ($writeResults as $result) {
            $this->assertTrue($result);
        }
        
        // 验证读取操作结果
        $readResults = array_slice($results, 5, 5);
        $expectedValues = ['value0', 'value1', 'value2', 'value3', 'value4'];
        $this->assertEquals($expectedValues, $readResults);
    }
    
    /**
     * Test high concurrency with different data structures
     */
    public function testHighConcurrencyWithDifferentStructures(): void
    {
        $this->asyncClient->connect()->wait(10);
        
        $list = $this->asyncClient->getList('async:test:high:concurrent:list');
        $map = $this->asyncClient->getMap('async:test:high:concurrent:map');
        $set = $this->asyncClient->getSet('async:test:high:concurrent:set');
        $sortedSet = $this->asyncClient->getSortedSet('async:test:high:concurrent:sorted');
        
        // 并发操作不同数据结构
        $promises = [];
        
        // List operations
        for ($i = 0; $i < 20; $i++) {
            $promises[] = $list->add("list_item_$i");
        }
        
        // Map operations
        for ($i = 0; $i < 15; $i++) {
            $promises[] = $map->put("map_key_$i", "map_value_$i");
        }
        
        // Set operations
        for ($i = 0; $i < 18; $i++) {
            $promises[] = $set->add("set_item_$i");
        }
        
        // Sorted set operations
        for ($i = 0; $i < 12; $i++) {
            $promises[] = $sortedSet->add("sorted_item_$i", $i);
        }
        
        // 等待所有操作完成
        $startTime = microtime(true);
        $results = RedisPromise::all($promises)->wait(30);
        $endTime = microtime(true);
        
        // 验证所有操作完成
        $this->assertCount(65, $results); // 20 + 15 + 18 + 12
        
        // 验证各个数据结构的最终状态
        $this->assertEquals(20, $list->size()->wait(5));
        $this->assertEquals(15, $map->size()->wait(5));
        $this->assertEquals(18, $set->size()->wait(5));
        $this->assertEquals(12, $sortedSet->size()->wait(5));
        
        // 记录性能指标
        $executionTime = $endTime - $startTime;
        $this->assertLessThan(25.0, $executionTime, "并发操作执行时间应在25秒内完成");
        
        echo sprintf("\n高并发测试完成：65个操作，耗时 %.2f 秒\n", $executionTime);
    }
    
    // ==================== 异步操作超时测试 ====================
    
    /**
     * Test promise timeout handling
     */
    public function testPromiseTimeoutHandling(): void
    {
        $this->asyncClient->connect()->wait(10);
        $list = $this->asyncClient->getList('async:test:timeout');
        
        // 创建一个快速完成的操作
        $fastPromise = $list->add('fast_value')->wait(1);
        $this->assertTrue($fastPromise);
        
        // 模拟一个可能超时的操作（这里用合理的超时时间）
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Promise wait timeout');
        
        // 创建一个长时间运行的操作（在实际场景中这可能是网络延迟）
        $longOperation = new \Rediphp\RedisPromise(function($resolve) {
            sleep(3); // 模拟长时间操作
            $resolve('completed');
        });
        
        $longOperation->wait(1); // 设置1秒超时，应该抛出异常
    }
    
    // ==================== 异步与同步操作混合测试 ====================
    
    /**
     * Test mixing async and sync operations
     */
    public function testMixedAsyncAndSyncOperations(): void
    {
        $this->asyncClient->connect()->wait(10);
        
        // 同步操作 - 直接使用底层客户端
        $syncMap = $this->client->getMap('sync:test:map');
        $syncMap->put('sync_key', 'sync_value');
        
        // 异步操作
        $asyncList = $this->asyncClient->getList('async:test:mixed');
        $asyncList->add('async_value')->wait(5);
        
        // 混合操作：异步读取，同步写入
        $value = $asyncList->get(0)->wait(5);
        $this->assertEquals('async_value', $value);
        
        $syncMap->put('mixed_key', $value);
        
        // 验证结果
        $this->assertEquals('sync_value', $syncMap->get('sync_key'));
        $this->assertEquals('async_value', $syncMap->get('mixed_key'));
        $this->assertEquals(1, $asyncList->size()->wait(5));
    }
    
    // ==================== 各种数据结构的异步操作测试 ====================
    
    /**
     * Test async operations on different data structures
     */
    public function testAsyncOperationsOnDifferentStructures(): void
    {
        $this->asyncClient->connect()->wait(10);
        
        // List operations
        $list = $this->asyncClient->getList('async:test:structures:list');
        $this->assertTrue($list->add('list_item')->wait(5));
        $this->assertEquals('list_item', $list->get(0)->wait(5));
        $this->assertEquals(1, $list->size()->wait(5));
        
        // Map operations
        $map = $this->asyncClient->getMap('async:test:structures:map');
        $this->assertTrue($map->put('map_key', 'map_value')->wait(5));
        $this->assertEquals('map_value', $map->get('map_key')->wait(5));
        $this->assertEquals(1, $map->size()->wait(5));
        
        // Set operations
        $set = $this->asyncClient->getSet('async:test:structures:set');
        $this->assertTrue($set->add('set_item')->wait(5));
        $this->assertTrue($set->contains('set_item')->wait(5));
        $this->assertEquals(1, $set->size()->wait(5));
        
        // SortedSet operations
        $sortedSet = $this->asyncClient->getSortedSet('async:test:structures:sorted');
        $this->assertTrue($sortedSet->add('sorted_item', 1.0)->wait(5));
        $this->assertEquals(1, $sortedSet->size()->wait(5));
        $score = $sortedSet->score('sorted_item')->wait(5);
        $this->assertEquals(1.0, $score);
        
        // Bucket operations
        $bucket = $this->asyncClient->getBucket('async:test:structures:bucket');
        $this->assertTrue($bucket->set('bucket_value')->wait(5));
        $this->assertEquals('bucket_value', $bucket->get()->wait(5));
    }
    
    /**
     * Test async atomic operations
     */
    public function testAsyncAtomicOperations(): void
    {
        $this->asyncClient->connect()->wait(10);
        
        // AtomicLong
        $atomicLong = $this->asyncClient->getAtomicLong('async:test:atomic:long');
        
        // 初始化值
        $atomicLong->set(0)->wait(5);
        
        // 并发递增操作
        $promises = [];
        for ($i = 0; $i < 10; $i++) {
            $promises[] = $atomicLong->incrementAndGet();
        }
        
        RedisPromise::all($promises)->wait(10);
        
        // 验证最终值
        $finalValue = $atomicLong->get()->wait(5);
        $this->assertEquals(10, $finalValue);
        
        // AtomicDouble
        $atomicDouble = $this->asyncClient->getAtomicDouble('async:test:atomic:double');
        $atomicDouble->set(0.0)->wait(5);
        
        $doublePromises = [];
        for ($i = 0; $i < 5; $i++) {
            $doublePromises[] = $atomicDouble->addAndGet(0.5);
        }
        
        RedisPromise::all($doublePromises)->wait(10);
        
        $finalDoubleValue = $atomicDouble->get()->wait(5);
        $this->assertEquals(2.5, $finalDoubleValue, '', 0.001);
    }
    
    /**
     * Test async distributed locks
     */
    public function testAsyncDistributedLocks(): void
    {
        $this->asyncClient->connect()->wait(10);
        $lock = $this->asyncClient->getLock('async:test:lock');
        
        // 尝试获取锁
        $lockAcquired = $lock->tryLock(1, 10)->wait(5);
        $this->assertTrue($lockAcquired);
        
        // 验证锁状态
        $isLocked = $lock->isLocked()->wait(5);
        $this->assertTrue($isLocked);
        
        // 释放锁
        $lockUnlocked = $lock->unlock()->wait(5);
        $this->assertTrue($lockUnlocked);
        
        // 验证锁已释放
        $isLockedAfter = $lock->isLocked()->wait(5);
        $this->assertFalse($isLockedAfter);
    }
    
    /**
     * Test async topic operations
     */
    public function testAsyncTopicOperations(): void
    {
        $this->asyncClient->connect()->wait(10);
        $topic = $this->asyncClient->getTopic('async:test:topic');
        
        // 发布消息
        $publishResult = $topic->publish('test message')->wait(5);
        $this->assertGreaterThanOrEqual(0, $publishResult);
        
        // 发布多条消息
        $messages = ['message1', 'message2', 'message3'];
        $publishPromises = [];
        foreach ($messages as $message) {
            $publishPromises[] = $topic->publish($message);
        }
        
        $results = RedisPromise::all($publishPromises)->wait(10);
        foreach ($results as $result) {
            $this->assertGreaterThanOrEqual(0, $result);
        }
    }
    
    // ==================== 性能基准测试 ====================
    
    /**
     * Test async operation performance benchmark
     */
    public function testAsyncOperationPerformance(): void
    {
        $this->asyncClient->connect()->wait(10);
        $list = $this->asyncClient->getList('async:test:performance');
        
        $iterations = 100;
        $startTime = microtime(true);
        
        // 并发执行批量操作
        $promises = [];
        for ($i = 0; $i < $iterations; $i++) {
            $promises[] = $list->add("perf_item_$i");
        }
        
        RedisPromise::all($promises)->wait(30);
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // 性能断言：100个并发操作应在合理时间内完成
        $this->assertLessThan(20.0, $executionTime, "100个并发操作应在20秒内完成");
        
        // 验证最终结果
        $finalSize = $list->size()->wait(5);
        $this->assertEquals($iterations, $finalSize);
        
        echo sprintf("\n性能测试结果：%d个操作，耗时 %.2f 秒，平均 %.4f 秒/操作\n", 
            $iterations, $executionTime, $executionTime / $iterations);
    }
    
    /**
     * Test memory usage during async operations
     */
    public function testAsyncMemoryUsage(): void
    {
        $this->asyncClient->connect()->wait(10);
        $list = $this->asyncClient->getList('async:test:memory');
        
        $initialMemory = memory_get_usage();
        
        // 执行大量异步操作
        $promises = [];
        for ($i = 0; $i < 1000; $i++) {
            $promises[] = $list->add("memory_test_item_$i");
        }
        
        RedisPromise::all($promises)->wait(60);
        
        $peakMemory = memory_get_peak_usage();
        $memoryIncrease = $peakMemory - $initialMemory;
        
        // 内存增长应在合理范围内（这里设置一个宽松的限制）
        $this->assertLessThan(100 * 1024 * 1024, $memoryIncrease, 
            "内存增长应小于100MB");
        
        echo sprintf("\n内存使用测试：初始 %.2f MB，峰值 %.2f MB，增长 %.2f MB\n",
            $initialMemory / 1024 / 1024,
            $peakMemory / 1024 / 1024,
            $memoryIncrease / 1024 / 1024);
    }
}