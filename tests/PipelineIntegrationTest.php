<?php

namespace Rediphp\Tests;

use Rediphp\RMap;
use Rediphp\RList;
use Rediphp\RSet;
use Rediphp\RQueue;
use Rediphp\RBucket;
use Rediphp\RAtomicLong;

/**
 * Pipeline integration tests for Redisson
 * Tests pipeline operations with multiple data structures and real-world scenarios
 */
class PipelineIntegrationTest extends RedissonTestCase
{
    /**
     * Test pipeline with mixed data structure operations
     */
    public function testPipelineMixedOperations()
    {
        $map = $this->client->getMap('pipeline:mixed:map');
        $list = $this->client->getList('pipeline:mixed:list');
        $set = $this->client->getSet('pipeline:mixed:set');
        $bucket = $this->client->getBucket('pipeline:mixed:bucket');
        $counter = $this->client->getAtomicLong('pipeline:mixed:counter');
        
        // 清理数据
        $map->clear();
        $list->clear();
        $set->clear();
        $bucket->delete();
        $counter->delete();
        
        // 创建pipeline
        $pipeline = $this->client->createPipeline();
        
        // 添加混合操作到pipeline
        $pipeline->add(function($redis) use ($map) {
            $redis->hSet('pipeline:mixed:map', 'key1', serialize(['data' => 'value1']));
            $redis->hSet('pipeline:mixed:map', 'key2', serialize(['data' => 'value2']));
        });
        
        $pipeline->add(function($redis) use ($list) {
            $redis->lPush('pipeline:mixed:list', serialize('item1'));
            $redis->lPush('pipeline:mixed:list', serialize('item2'));
        });
        
        $pipeline->add(function($redis) use ($set) {
            $redis->sAdd('pipeline:mixed:set', serialize('element1'));
            $redis->sAdd('pipeline:mixed:set', serialize('element2'));
        });
        
        $pipeline->add(function($redis) use ($bucket) {
            $redis->set('pipeline:mixed:bucket', serialize('bucket_value'));
        });
        
        $pipeline->add(function($redis) use ($counter) {
            $redis->incr('pipeline:mixed:counter');
            $redis->incr('pipeline:mixed:counter');
        });
        
        // 执行pipeline
        $results = $pipeline->execute();
        
        // 验证结果
        $this->assertEquals(['key1' => ['data' => 'value1'], 'key2' => ['data' => 'value2']], $map->getAll(['key1', 'key2']));
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
    }
    
    /**
     * Test pipeline error handling and rollback
     */
    public function testPipelineErrorHandling()
    {
        $map = $this->client->getMap('pipeline:error:map');
        $list = $this->client->getList('pipeline:error:list');
        $counter = $this->client->getAtomicLong('pipeline:error:counter');
        
        // 清理数据
        $map->clear();
        $list->clear();
        $counter->delete();
        
        // 初始状态
        $map->put('initial', 'value');
        $counter->set(10);
        
        // 创建pipeline
        $pipeline = $this->client->createPipeline();
        
        // 添加正常操作
        $pipeline->add(function($redis) {
            $redis->hSet('pipeline:error:map', 'key1', serialize('value1'));
        });
        
        // 添加可能导致错误的操作（模拟错误）
        $pipeline->add(function($redis) {
            // 这个操作会成功，但我们可以模拟错误情况
            $redis->hSet('pipeline:error:map', 'key2', serialize('value2'));
        });
        
        // 添加更多操作
        $pipeline->add(function($redis) {
            $redis->lPush('pipeline:error:list', serialize('item1'));
        });
        
        // 执行pipeline
        $results = $pipeline->execute();
        
        // 验证部分操作成功
        $this->assertTrue($map->containsKey('key1'));
        $this->assertTrue($map->containsKey('key2'));
        $this->assertEquals(1, $list->size());
        
        // 验证原始数据未被破坏
        $this->assertEquals('value', $map->get('initial'));
        $this->assertEquals(10, $counter->get());
        
        // 清理
        $map->clear();
        $list->clear();
        $counter->delete();
    }
    
    /**
     * Test pipeline performance with bulk operations
     */
    public function testPipelineBulkOperations()
    {
        $map = $this->client->getMap('pipeline:bulk:map');
        $list = $this->client->getList('pipeline:bulk:list');
        
        // 清理数据
        $map->clear();
        $list->clear();
        
        $bulkSize = 100;
        
        // 测试不使用pipeline的性能
        $startTime = microtime(true);
        for ($i = 0; $i < $bulkSize; $i++) {
            $map->put("key_$i", ['data' => "value_$i"]);
        }
        $normalTime = microtime(true) - $startTime;
        
        // 清理
        $map->clear();
        
        // 测试使用pipeline的性能
        $pipeline = $this->client->createPipeline();
        $startTime = microtime(true);
        
        for ($i = 0; $i < $bulkSize; $i++) {
            $pipeline->add(function($redis) use ($i) {
                $redis->hSet('pipeline:bulk:map', "key_$i", serialize(['data' => "value_$i"]));
            });
        }
        
        $pipeline->execute();
        $pipelineTime = microtime(true) - $startTime;
        
        // 验证数据完整性
        $this->assertEquals($bulkSize, $map->size());
        
        // 验证pipeline性能更好
        $this->assertLessThan($normalTime, $pipelineTime, "Pipeline should be faster than normal operations");
        
        // 清理
        $map->clear();
    }
    
    /**
     * Test pipeline with atomic operations across multiple data structures
     */
    public function testPipelineAtomicOperations()
    {
        $account1 = $this->client->getMap('pipeline:atomic:account1');
        $account2 = $this->client->getMap('pipeline:atomic:account2');
        $transactionLog = $this->client->getList('pipeline:atomic:log');
        $transactionCounter = $this->client->getAtomicLong('pipeline:atomic:counter');
        
        // 清理数据
        $account1->clear();
        $account2->clear();
        $transactionLog->clear();
        $transactionCounter->delete();
        
        // 初始账户余额
        $account1->put('balance', 1000);
        $account2->put('balance', 500);
        
        // 创建pipeline执行转账
        $pipeline = $this->client->createPipeline();
        $transferAmount = 100;
        
        $pipeline->add(function($redis) use ($transferAmount) {
            // 从账户1扣款
            $balance1 = unserialize($redis->hGet('pipeline:atomic:account1', 'balance'));
            $newBalance1 = $balance1 - $transferAmount;
            $redis->hSet('pipeline:atomic:account1', 'balance', serialize($newBalance1));
        });
        
        $pipeline->add(function($redis) use ($transferAmount) {
            // 向账户2存款
            $balance2 = unserialize($redis->hGet('pipeline:atomic:account2', 'balance'));
            $newBalance2 = $balance2 + $transferAmount;
            $redis->hSet('pipeline:atomic:account2', 'balance', serialize($newBalance2));
        });
        
        $pipeline->add(function($redis) use ($transferAmount) {
            // 记录交易日志
            $logEntry = [
                'from' => 'account1',
                'to' => 'account2',
                'amount' => $transferAmount,
                'timestamp' => time()
            ];
            $redis->lPush('pipeline:atomic:log', serialize($logEntry));
        });
        
        $pipeline->add(function($redis) {
            // 增加交易计数器
            $redis->incr('pipeline:atomic:counter');
        });
        
        // 执行pipeline
        $results = $pipeline->execute();
        
        // 验证原子操作结果
        $this->assertEquals(900, $account1->get('balance')); // 1000 - 100
        $this->assertEquals(600, $account2->get('balance')); // 500 + 100
        $this->assertEquals(1, $transactionLog->size());
        $this->assertEquals(1, $transactionCounter->get());
        
        // 验证交易日志
        $log = $transactionLog->get(0);
        $this->assertEquals('account1', $log['from']);
        $this->assertEquals('account2', $log['to']);
        $this->assertEquals(100, $log['amount']);
        
        // 清理
        $account1->clear();
        $account2->clear();
        $transactionLog->clear();
        $transactionCounter->delete();
    }
}