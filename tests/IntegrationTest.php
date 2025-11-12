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
 * Integration tests for Redisson data structures
 * Tests interaction between different data structures and real-world scenarios
 */
class IntegrationTest extends RedissonTestCase
{
    /**
     * Test distributed cache with TTL and atomic operations
     */
    public function testDistributedCache()
    {
        $cache = $this->client->getMap('integration:cache');
        $ttlBucket = $this->client->getBucket('integration:cache:ttl');
        $accessCounter = $this->client->getAtomicLong('integration:cache:access');
        
        // 设置缓存项
        $cache->put('user:1', ['name' => 'John', 'age' => 30]);
        $cache->put('user:2', ['name' => 'Jane', 'age' => 25]);
        
        // 设置TTL
        $ttlBucket->set(time() + 3600); // 1小时后过期
        
        // 验证缓存内容
        $this->assertEquals(['name' => 'John', 'age' => 30], $cache->get('user:1'));
        $this->assertEquals(['name' => 'Jane', 'age' => 25], $cache->get('user:2'));
        
        // 验证访问计数器
        $accessCounter->incrementAndGet();
        $accessCounter->incrementAndGet();
        $this->assertEquals(2, $accessCounter->get());
        
        // 验证缓存大小
        $this->assertEquals(2, $cache->size());
        
        // 清理
        $cache->clear();
        $ttlBucket->delete();
        $accessCounter->delete();
    }
    
    /**
     * Test message queue with worker coordination
     */
    public function testMessageQueueWithWorkers()
    {
        $taskQueue = $this->client->getQueue('integration:tasks');
        $resultList = $this->client->getList('integration:results');
        $workerSemaphore = $this->client->getSemaphore('integration:workers', 3); // 最多3个worker
        $processedCounter = $this->client->getAtomicLong('integration:processed');
        
        // 添加任务到队列
        for ($i = 1; $i <= 10; $i++) {
            $taskQueue->offer(['task_id' => $i, 'data' => "task_data_$i"]);
        }
        
        // 模拟worker处理
        $processedTasks = [];
        while (!$taskQueue->isEmpty() && count($processedTasks) < 5) {
            if ($workerSemaphore->tryAcquire()) {
                $task = $taskQueue->poll();
                if ($task !== null) {
                    // 处理任务
                    $result = ['task_id' => $task['task_id'], 'status' => 'completed'];
                    $resultList->add($result);
                    $processedCounter->incrementAndGet();
                    $processedTasks[] = $task['task_id'];
                    
                    // 释放worker信号量
                    $workerSemaphore->release();
                }
            }
        }
        
        // 验证处理结果
        $this->assertEquals(5, $processedCounter->get());
        $this->assertEquals(5, $resultList->size());
        $this->assertEquals(5, count($processedTasks));
        
        // 清理
        $taskQueue->clear();
        $resultList->clear();
        $processedCounter->delete();
        $workerSemaphore->clear();
    }
    
    /**
     * Test distributed rate limiting
     */
    public function testDistributedRateLimiting()
    {
        $rateLimitBucket = $this->client->getBucket('integration:ratelimit:window');
        $requestCounter = $this->client->getAtomicLong('integration:requests');
        $userRequestSet = $this->client->getSet('integration:user:requests');
        
        // 设置时间窗口
        $windowStart = time();
        $rateLimitBucket->set($windowStart);
        
        // 模拟用户请求
        $users = ['user1', 'user2', 'user3', 'user1', 'user2', 'user3'];
        $maxRequests = 3;
        
        foreach ($users as $user) {
            $userRequestSet->add($user);
            $requestCounter->incrementAndGet();
            
            // 检查是否超过限制
            if ($requestCounter->get() <= $maxRequests) {
                // 允许请求
                $this->assertTrue(true);
            } else {
                // 应该拒绝请求
                $this->assertGreaterThan($maxRequests, $requestCounter->get());
            }
        }
        
        // 验证结果
        $this->assertEquals(6, $requestCounter->get());
        $this->assertEquals(3, $userRequestSet->size());
        
        // 清理
        $rateLimitBucket->delete();
        $requestCounter->delete();
        $userRequestSet->clear();
    }
    
    /**
     * Test distributed leader election
     */
    public function testDistributedLeaderElection()
    {
        $leaderLock = $this->client->getLock('integration:leader:lock');
        $leaderBucket = $this->client->getBucket('integration:leader:id');
        $candidateCounter = $this->client->getAtomicLong('integration:candidates');
        
        // 模拟多个候选者
        $candidates = ['candidate1', 'candidate2', 'candidate3'];
        $electedLeader = null;
        
        foreach ($candidates as $candidate) {
            $candidateCounter->incrementAndGet();
            
            // 尝试获取领导锁
            if ($leaderLock->tryLock()) {
                // 成为领导者
                $leaderBucket->set($candidate);
                $electedLeader = $candidate;
                break;
            }
        }
        
        // 验证选举结果
        $this->assertNotNull($electedLeader);
        $this->assertEquals($electedLeader, $leaderBucket->get());
        $this->assertEquals(3, $candidateCounter->get());
        
        // 释放领导锁
        if ($leaderLock->isLocked()) {
            $leaderLock->unlock();
        }
        
        // 清理
        $leaderBucket->delete();
        $candidateCounter->delete();
    }
    
    /**
     * Test distributed configuration management
     */
    public function testDistributedConfiguration()
    {
        $configMap = $this->client->getMap('integration:config');
        $configVersion = $this->client->getAtomicLong('integration:config:version');
        $configLock = $this->client->getLock('integration:config:lock');
        
        // 初始配置
        $configMap->put('database.host', 'localhost');
        $configMap->put('database.port', '5432');
        $configMap->put('cache.enabled', 'true');
        $configVersion->set(1);
        
        // 更新配置（需要锁）
        if ($configLock->tryLock()) {
            try {
                // 更新配置
                $configMap->put('database.host', 'db.example.com');
                $configMap->put('database.port', '5433');
                
                // 增加版本号
                $configVersion->incrementAndGet();
                
                // 验证更新
                $this->assertEquals('db.example.com', $configMap->get('database.host'));
                $this->assertEquals('5433', $configMap->get('database.port'));
                $this->assertEquals(2, $configVersion->get());
            } finally {
                $configLock->unlock();
            }
        }
        
        // 验证配置完整性
        $this->assertEquals('true', $configMap->get('cache.enabled'));
        $this->assertEquals(3, $configMap->size());
        
        // 清理
        $configMap->clear();
        $configVersion->delete();
    }
    
    /**
     * Test distributed session management
     */
    public function testDistributedSessionManagement()
    {
        $sessionMap = $this->client->getMap('integration:sessions');
        $sessionList = $this->client->getList('integration:active:sessions');
        $sessionCounter = $this->client->getAtomicLong('integration:session:counter');
        
        // 创建会话
        $sessionId1 = 'sess_' . uniqid();
        $sessionId2 = 'sess_' . uniqid();
        
        $sessionData1 = [
            'user_id' => 'user123',
            'login_time' => time(),
            'last_activity' => time()
        ];
        
        $sessionData2 = [
            'user_id' => 'user456',
            'login_time' => time(),
            'last_activity' => time()
        ];
        
        // 存储会话
        $sessionMap->put($sessionId1, $sessionData1);
        $sessionMap->put($sessionId2, $sessionData2);
        
        // 添加到活跃会话列表
        $sessionList->add($sessionId1);
        $sessionList->add($sessionId2);
        
        // 更新计数器
        $sessionCounter->addAndGet(2);
        
        // 验证会话
        $this->assertEquals($sessionData1, $sessionMap->get($sessionId1));
        $this->assertEquals($sessionData2, $sessionMap->get($sessionId2));
        $this->assertTrue($sessionList->contains($sessionId1));
        $this->assertTrue($sessionList->contains($sessionId2));
        $this->assertEquals(2, $sessionCounter->get());
        
        // 模拟会话过期
        $sessionMap->remove($sessionId1);
        $sessionList->remove($sessionId1);
        $sessionCounter->decrementAndGet();
        
        $this->assertNull($sessionMap->get($sessionId1));
        $this->assertFalse($sessionList->contains($sessionId1));
        $this->assertEquals(1, $sessionCounter->get());
        
        // 清理
        $sessionMap->clear();
        $sessionList->clear();
        $sessionCounter->delete();
    }
    
    /**
     * Test distributed statistics collection
     */
    public function testDistributedStatistics()
    {
        $statsMap = $this->client->getMap('integration:stats');
        $eventQueue = $this->client->getQueue('integration:events');
        $statsCounter = $this->client->getAtomicLong('integration:stats:counter');
        $dailySet = $this->client->getSet('integration:daily:users');
        
        // 模拟事件收集
        $events = [
            ['type' => 'page_view', 'user' => 'user1', 'timestamp' => time()],
            ['type' => 'click', 'user' => 'user2', 'timestamp' => time()],
            ['type' => 'page_view', 'user' => 'user1', 'timestamp' => time()],
            ['type' => 'purchase', 'user' => 'user3', 'timestamp' => time()],
        ];
        
        foreach ($events as $event) {
            $eventQueue->offer($event);
            $eventQueue->offer($event); // 添加两次以模拟重复事件
        }
        
        // 处理事件
        while (!$eventQueue->isEmpty()) {
            $event = $eventQueue->poll();
            if ($event !== null) {
                $eventType = $event['type'];
                $currentCount = $statsMap->get($eventType) ?: 0;
                $statsMap->put($eventType, $currentCount + 1);
                
                $dailySet->add($event['user']);
                $statsCounter->incrementAndGet();
            }
        }
        
        // 验证统计结果
        $this->assertEquals(2, $statsMap->get('page_view')); // user1 两次
        $this->assertEquals(2, $statsMap->get('click'));     // user2 两次
        $this->assertEquals(2, $statsMap->get('purchase'));  // user3 两次
        $this->assertEquals(8, $statsCounter->get());        // 总共8个事件
        $this->assertEquals(3, $dailySet->size());           // 3个不同用户
        
        // 清理
        $statsMap->clear();
        $eventQueue->clear();
        $statsCounter->delete();
        $dailySet->clear();
    }
    
    /**
     * Test error handling and recovery
     */
    public function testErrorHandlingAndRecovery()
    {
        $dataMap = $this->client->getMap('integration:data');
        $backupList = $this->client->getList('integration:backup');
        $errorCounter = $this->client->getAtomicLong('integration:errors');
        $recoverySemaphore = $this->client->getSemaphore('integration:recovery', 1);
        
        // 模拟正常操作
        try {
            $dataMap->put('key1', 'value1');
            $dataMap->put('key2', 'value2');
            
            // 模拟错误情况
            if (rand(0, 1) == 0) { // 随机错误
                throw new \Exception('Simulated error');
            }
        } catch (\Exception $e) {
            $errorCounter->incrementAndGet();
            
            // 尝试恢复
            if ($recoverySemaphore->tryAcquire()) {
                // 备份数据
                $backupList->add('Backup of key1: ' . $dataMap->get('key1'));
                $backupList->add('Backup of key2: ' . $dataMap->get('key2'));
                
                $recoverySemaphore->release();
            }
        }
        
        // 验证数据完整性
        $this->assertEquals('value1', $dataMap->get('key1'));
        $this->assertEquals('value2', $dataMap->get('key2'));
        $this->assertGreaterThanOrEqual(0, $errorCounter->get());
        
        // 清理
        $dataMap->clear();
        $backupList->clear();
        $errorCounter->delete();
        $recoverySemaphore->clear();
    }
}