<?php

namespace Rediphp\Tests;

use Rediphp\RMap;
use Rediphp\RList;
use Rediphp\RSet;
use Rediphp\RQueue;
use Rediphp\RBucket;
use Rediphp\RAtomicLong;
use Rediphp\RedissonPool;

/**
 * Connection pool integration tests for Redisson
 * Tests connection pool operations with multiple data structures and real-world scenarios
 */
class ConnectionPoolIntegrationTest extends RedissonTestCase
{
    /**
     * Test connection pool with mixed data structure operations
     */
    public function testConnectionPoolMixedOperations()
    {
        $pool = new RedissonPool([
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => (int)(getenv('REDIS_PORT') ?: 6379),
            'max_connections' => 5,
            'min_connections' => 2,
        ]);
        
        $map = $pool->getMap('pool:mixed:map');
        $list = $pool->getList('pool:mixed:list');
        $set = $pool->getSet('pool:mixed:set');
        $bucket = $pool->getBucket('pool:mixed:bucket');
        $counter = $pool->getAtomicLong('pool:mixed:counter');
        
        // 清理数据
        $map->clear();
        $list->clear();
        $set->clear();
        $bucket->delete();
        $counter->delete();
        
        // 执行混合操作
        $map->put('key1', ['data' => 'value1']);
        $map->put('key2', ['data' => 'value2']);
        
        $list->add('item1');
        $list->add('item2');
        
        $set->add('element1');
        $set->add('element2');
        
        $bucket->set('bucket_value');
        
        $counter->incrementAndGet();
        $counter->incrementAndGet();
        
        // 验证结果
        $this->assertEquals(['data' => 'value1'], $map->get('key1'));
        $this->assertEquals(2, $list->size());
        $this->assertEquals(2, $set->size());
        $this->assertEquals('bucket_value', $bucket->get());
        $this->assertEquals(2, $counter->get());
        
        // 清理
        $map->clear();
        $list->clear();
        $set->clear();
        $bucket->delete();
        $counter->delete();
        $pool->shutdown();
    }
    
    /**
     * Test connection pool under high concurrency
     */
    public function testConnectionPoolHighConcurrency()
    {
        $pool = new RedissonPool([
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => (int)(getenv('REDIS_PORT') ?: 6379),
            'max_connections' => 3,
            'min_connections' => 1,
        ]);
        
        $counter = $pool->getAtomicLong('pool:concurrent:counter');
        $map = $pool->getMap('pool:concurrent:map');
        
        // 清理数据
        $counter->delete();
        $map->clear();
        
        $concurrentOperations = 20;
        $threads = [];
        
        // 模拟并发操作
        for ($i = 0; $i < $concurrentOperations; $i++) {
            $threads[] = $this->simulateConcurrentOperation($pool, $i);
        }
        
        // 等待所有操作完成
        foreach ($threads as $thread) {
            $thread();
        }
        
        // 验证结果
        $this->assertEquals($concurrentOperations, $counter->get());
        $this->assertEquals($concurrentOperations, $map->size());
        
        // 清理
        $counter->delete();
        $map->clear();
        $pool->shutdown();
    }
    
    /**
     * Simulate a concurrent operation
     */
    private function simulateConcurrentOperation($pool, $index)
    {
        return function() use ($pool, $index) {
            $counter = $pool->getAtomicLong('pool:concurrent:counter');
            $map = $pool->getMap('pool:concurrent:map');
            
            $counter->incrementAndGet();
            $map->put("key_$index", ['data' => "value_$index"]);
        };
    }
    
    /**
     * Test connection pool connection reuse
     */
    public function testConnectionPoolReuse()
    {
        $pool = new RedissonPool([
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => (int)(getenv('REDIS_PORT') ?: 6379),
            'max_connections' => 2,
            'min_connections' => 1,
        ]);
        
        $counter = $pool->getAtomicLong('pool:reuse:counter');
        $counter->delete();
        
        // 执行多次操作，测试连接重用
        $operations = 10;
        for ($i = 0; $i < $operations; $i++) {
            $counter->incrementAndGet();
        }
        
        // 验证结果
        $this->assertEquals($operations, $counter->get());
        
        // 验证连接池统计
        $stats = $pool->getStats();
        $this->assertGreaterThan(0, $stats['total_connections']);
        $this->assertLessThanOrEqual(2, $stats['active_connections']);
        
        // 清理
        $counter->delete();
        $pool->shutdown();
    }
    
    /**
     * Test connection pool error handling and recovery
     */
    public function testConnectionPoolErrorHandling()
    {
        $pool = new RedissonPool([
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => (int)(getenv('REDIS_PORT') ?: 6379),
            'max_connections' => 3,
            'min_connections' => 1,
            'connection_timeout' => 1,
            'retry_attempts' => 2,
        ]);
        
        $map = $pool->getMap('pool:error:map');
        $counter = $pool->getAtomicLong('pool:error:counter');
        
        // 清理数据
        $map->clear();
        $counter->delete();
        
        // 正常操作
        $map->put('key1', 'value1');
        $counter->incrementAndGet();
        
        // 验证结果
        $this->assertEquals('value1', $map->get('key1'));
        $this->assertEquals(1, $counter->get());
        
        // 模拟错误情况（通过无效操作）
        try {
            // 尝试无效操作
            $invalidResult = $pool->getRedis()->executeRaw(['INVALID', 'COMMAND']);
        } catch (\Exception $e) {
            // 应该捕获异常
            $this->assertStringContainsString('ERR', $e->getMessage());
        }
        
        // 验证连接池仍然可用
        $map->put('key2', 'value2');
        $counter->incrementAndGet();
        
        $this->assertEquals('value2', $map->get('key2'));
        $this->assertEquals(2, $counter->get());
        
        // 清理
        $map->clear();
        $counter->delete();
        $pool->shutdown();
    }
    
    /**
     * Test connection pool performance comparison
     */
    public function testConnectionPoolPerformance()
    {
        // 测试不使用连接池的性能
        $startTime = microtime(true);
        $counter1 = $this->client->getAtomicLong('pool:perf:normal:counter');
        $counter1->delete();
        
        $operations = 50;
        for ($i = 0; $i < $operations; $i++) {
            $counter1->incrementAndGet();
        }
        $normalTime = microtime(true) - $startTime;
        
        // 测试使用连接池的性能
        $pool = new RedissonPool([
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => (int)(getenv('REDIS_PORT') ?: 6379),
            'max_connections' => 5,
            'min_connections' => 2,
        ]);
        
        $startTime = microtime(true);
        $counter2 = $pool->getAtomicLong('pool:perf:pool:counter');
        $counter2->delete();
        
        for ($i = 0; $i < $operations; $i++) {
            $counter2->incrementAndGet();
        }
        $poolTime = microtime(true) - $startTime;
        
        // 验证结果
        $this->assertEquals($operations, $counter1->get());
        $this->assertEquals($operations, $counter2->get());
        
        // 连接池性能应该不差于普通连接（在大量操作时应该更好）
        $this->assertLessThan($normalTime * 1.5, $poolTime, "Connection pool should not be much slower than normal connection");
        
        // 清理
        $counter1->delete();
        $counter2->delete();
        $pool->shutdown();
    }
}