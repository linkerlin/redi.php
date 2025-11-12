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
 * Distributed scenarios and cluster behavior integration tests
 * Tests multi-node scenarios, consistency, and distributed coordination
 */
class DistributedIntegrationTest extends RedissonTestCase
{
    /**
     * Test distributed leader election
     */
    public function testDistributedLeaderElection()
    {
        $electionLock = $this->client->getLock('distributed:leader:election');
        $leaderBucket = $this->client->getBucket('distributed:current:leader');
        $termCounter = $this->client->getAtomicLong('distributed:leader:term');
        $candidateCounter = $this->client->getAtomicLong('distributed:candidate:count');
        
        // 模拟多个候选者
        $candidates = ['node1', 'node2', 'node3'];
        $successfulElections = 0;
        $leaders = [];
        
        foreach ($candidates as $candidate) {
            $candidateCounter->incrementAndGet();
            
            // 尝试成为领导者
            if ($electionLock->tryLock(1)) { // 1秒超时
                try {
                    // 检查当前是否有领导者
                    $currentLeader = $leaderBucket->get();
                    
                    if (empty($currentLeader) || $currentLeader === $candidate) {
                        // 成为新的领导者
                        $leaderBucket->set($candidate);
                        $termCounter->incrementAndGet();
                        $leaders[] = $candidate;
                        $successfulElections++;
                        
                        // 模拟领导者工作
                        usleep(100000); // 100ms
                        
                        // 领导者卸任
                        $leaderBucket->delete();
                    }
                    
                } finally {
                    $electionLock->unlock();
                }
            }
        }
        
        // 验证选举结果
        $this->assertGreaterThan(0, $successfulElections);
        $this->assertEquals($successfulElections, count($leaders));
        $this->assertEquals($successfulElections, $termCounter->get());
        
        // 清理
        $leaderBucket->delete();
        $termCounter->delete();
        $candidateCounter->delete();
    }
    
    /**
     * Test distributed configuration management
     */
    public function testDistributedConfigurationManagement()
    {
        $configMap = $this->client->getMap('distributed:config:map');
        $configVersion = $this->client->getAtomicLong('distributed:config:version');
        $configLock = $this->client->getLock('distributed:config:lock');
        $configListeners = $this->client->getList('distributed:config:listeners');
        
        // 初始配置
        $initialConfig = [
            'database.host' => 'localhost',
            'database.port' => 5432,
            'cache.ttl' => 3600,
            'feature.flags' => ['new_ui', 'beta_feature'],
            'max.connections' => 100
        ];
        
        // 设置初始配置
        foreach ($initialConfig as $key => $value) {
            $configMap->put($key, $value);
        }
        $configVersion->set(1);
        
        // 模拟配置更新
        $configUpdates = [
            ['database.host', 'newhost.example.com'],
            ['cache.ttl', 7200],
            ['feature.flags', ['new_ui', 'stable_feature']],
            ['max.connections', 200]
        ];
        
        $successfulUpdates = 0;
        foreach ($configUpdates as [$key, $newValue]) {
            if ($configLock->tryLock(1)) {
                try {
                    // 更新配置
                    $configMap->put($key, $newValue);
                    
                    // 增加版本号
                    $currentVersion = $configVersion->incrementAndGet();
                    
                    // 通知监听器
                    $configListeners->add("config_updated:$key:$currentVersion");
                    
                    $successfulUpdates++;
                    
                } finally {
                    $configLock->unlock();
                }
            }
        }
        
        // 验证配置更新
        $this->assertGreaterThan(0, $successfulUpdates);
        $this->assertEquals('newhost.example.com', $configMap->get('database.host'));
        $this->assertEquals(7200, $configMap->get('cache.ttl'));
        $this->assertEquals(200, $configMap->get('max.connections'));
        $this->assertEquals($successfulUpdates + 1, $configVersion->get());
        
        // 验证监听器通知
        $this->assertEquals($successfulUpdates, $configListeners->size());
        
        // 清理
        $configMap->clear();
        $configVersion->delete();
        $configListeners->clear();
    }
    
    /**
     * Test distributed rate limiting
     */
    public function testDistributedRateLimiting()
    {
        $rateLimitBucket = $this->client->getBucket('distributed:rate:limit:bucket');
        $rateLimitCounter = $this->client->getAtomicLong('distributed:rate:limit:counter');
        $rateLimitWindow = $this->client->getMap('distributed:rate:limit:window');
        $rateLimitLock = $this->client->getLock('distributed:rate:limit:lock');
        
        // 设置速率限制参数
        $maxRequests = 5;
        $windowSize = 60; // 60秒窗口
        $currentWindow = floor(time() / $windowSize);
        
        $rateLimitWindow->put('max_requests', $maxRequests);
        $rateLimitWindow->put('window_size', $windowSize);
        $rateLimitWindow->put('current_window', $currentWindow);
        
        // 模拟请求
        $totalRequests = 15;
        $allowedRequests = 0;
        $rejectedRequests = 0;
        
        for ($i = 0; $i < $totalRequests; $i++) {
            if ($rateLimitLock->tryLock()) {
                try {
                    $newWindow = floor(time() / $windowSize);
                    $storedWindow = $rateLimitWindow->get('current_window');
                    
                    // 检查是否需要重置窗口
                    if ($newWindow != $storedWindow) {
                        $rateLimitWindow->put('current_window', $newWindow);
                        $rateLimitCounter->set(0);
                    }
                    
                    $currentCount = $rateLimitCounter->get();
                    
                    // 检查是否允许请求
                    if ($currentCount < $maxRequests) {
                        $rateLimitCounter->incrementAndGet();
                        $allowedRequests++;
                        $rateLimitBucket->set("request_$i:allowed");
                    } else {
                        $rejectedRequests++;
                        $rateLimitBucket->set("request_$i:rejected");
                    }
                    
                } finally {
                    $rateLimitLock->unlock();
                }
            }
        }
        
        // 验证速率限制
        $this->assertEquals($maxRequests, $allowedRequests);
        $this->assertEquals($totalRequests - $maxRequests, $rejectedRequests);
        $this->assertEquals($allowedRequests, $rateLimitCounter->get());
        
        // 清理
        $rateLimitBucket->delete();
        $rateLimitCounter->delete();
        $rateLimitWindow->clear();
    }
    
    /**
     * Test distributed session management
     */
    public function testDistributedSessionManagement()
    {
        $sessionMap = $this->client->getMap('distributed:session:map');
        $sessionList = $this->client->getList('distributed:session:list');
        $sessionCounter = $this->client->getAtomicLong('distributed:session:counter');
        $sessionLock = $this->client->getLock('distributed:session:lock');
        
        // 模拟用户会话
        $sessions = [
            'user123' => [
                'session_id' => 'sess_' . uniqid(),
                'user_id' => 'user123',
                'login_time' => time(),
                'last_activity' => time(),
                'ip_address' => '192.168.1.1',
                'user_agent' => 'Mozilla/5.0'
            ],
            'user456' => [
                'session_id' => 'sess_' . uniqid(),
                'user_id' => 'user456',
                'login_time' => time(),
                'last_activity' => time(),
                'ip_address' => '192.168.1.2',
                'user_agent' => 'Chrome/91.0'
            ]
        ];
        
        // 创建会话
        foreach ($sessions as $userId => $sessionData) {
            if ($sessionLock->tryLock(1)) {
                try {
                    $sessionMap->put($userId, $sessionData);
                    $sessionList->add($sessionData['session_id']);
                    $sessionCounter->incrementAndGet();
                } finally {
                    $sessionLock->unlock();
                }
            }
        }
        
        // 验证会话创建
        $this->assertEquals(2, $sessionMap->size());
        $this->assertEquals(2, $sessionList->size());
        $this->assertEquals(2, $sessionCounter->get());
        
        // 模拟会话更新
        foreach ($sessions as $userId => $sessionData) {
            if ($sessionLock->tryLock(1)) {
                try {
                    $sessionData['last_activity'] = time() + 300; // 5分钟后
                    $sessionMap->put($userId, $sessionData);
                } finally {
                    $sessionLock->unlock();
                }
            }
        }
        
        // 模拟会话过期检查
        $expiredSessions = 0;
        $currentTime = time();
        foreach ($sessions as $userId => $sessionData) {
            $storedSession = $sessionMap->get($userId);
            if ($storedSession && ($currentTime - $storedSession['last_activity']) > 3600) {
                $expiredSessions++;
            }
        }
        
        $this->assertEquals(0, $expiredSessions); // 会话应该还没过期
        
        // 清理
        $sessionMap->clear();
        $sessionList->clear();
        $sessionCounter->delete();
    }
    
    /**
     * Test distributed work queue coordination
     */
    public function testDistributedWorkQueue()
    {
        $workQueue = $this->client->getQueue('distributed:work:queue');
        $workCounter = $this->client->getAtomicLong('distributed:work:counter');
        $workerStatus = $this->client->getMap('distributed:worker:status');
        $workLock = $this->client->getLock('distributed:work:lock');
        
        // 添加工作任务
        $workItems = [
            ['id' => 'task1', 'type' => 'email', 'priority' => 1, 'data' => 'send_welcome_email'],
            ['id' => 'task2', 'type' => 'report', 'priority' => 2, 'data' => 'generate_monthly_report'],
            ['id' => 'task3', 'type' => 'cleanup', 'priority' => 3, 'data' => 'cleanup_old_files'],
            ['id' => 'task4', 'type' => 'backup', 'priority' => 1, 'data' => 'database_backup'],
            ['id' => 'task5', 'type' => 'sync', 'priority' => 2, 'data' => 'sync_user_data']
        ];
        
        foreach ($workItems as $item) {
            $workQueue->add($item);
        }
        
        // 模拟工作处理
        $processedTasks = 0;
        $workerId = 'worker_' . uniqid();
        
        while (!$workQueue->isEmpty() && $processedTasks < 3) {
            if ($workLock->tryLock(1)) {
                try {
                    $task = $workQueue->poll();
                    if ($task) {
                        // 更新工人状态
                        $workerStatus->put($workerId, [
                            'status' => 'processing',
                            'current_task' => $task['id'],
                            'started_at' => time()
                        ]);
                        
                        // 模拟工作处理
                        usleep(50000); // 50ms
                        
                        // 标记任务完成
                        $workCounter->incrementAndGet();
                        $processedTasks++;
                        
                        // 更新工人状态
                        $workerStatus->put($workerId, [
                            'status' => 'idle',
                            'last_task' => $task['id'],
                            'completed_at' => time()
                        ]);
                    }
                } finally {
                    $workLock->unlock();
                }
            }
        }
        
        // 验证工作队列处理
        $this->assertGreaterThan(0, $processedTasks);
        $this->assertEquals($processedTasks, $workCounter->get());
        $this->assertLessThanOrEqual(2, $workQueue->size()); // 应该还剩下一些任务
        
        // 验证工人状态
        $workerInfo = $workerStatus->get($workerId);
        $this->assertNotNull($workerInfo);
        $this->assertEquals('idle', $workerInfo['status']);
        
        // 清理
        $workQueue->clear();
        $workCounter->delete();
        $workerStatus->clear();
    }
    
    /**
     * Test distributed state synchronization
     */
    public function testDistributedStateSynchronization()
    {
        $stateMap = $this->client->getMap('distributed:state:map');
        $stateVersion = $this->client->getAtomicLong('distributed:state:version');
        $stateLock = $this->client->getLock('distributed:state:lock');
        $stateHistory = $this->client->getList('distributed:state:history');
        
        // 初始状态
        $initialState = [
            'system_status' => 'online',
            'active_users' => 0,
            'total_requests' => 0,
            'error_count' => 0,
            'last_updated' => time()
        ];
        
        foreach ($initialState as $key => $value) {
            $stateMap->put($key, $value);
        }
        $stateVersion->set(1);
        
        // 模拟状态更新
        $stateUpdates = [
            ['active_users', 5],
            ['total_requests', 100],
            ['error_count', 2],
            ['system_status', 'maintenance']
        ];
        
        $successfulUpdates = 0;
        foreach ($stateUpdates as [$key, $newValue]) {
            if ($stateLock->tryLock(1)) {
                try {
                    // 更新状态
                    $stateMap->put($key, $newValue);
                    
                    // 更新版本
                    $currentVersion = $stateVersion->incrementAndGet();
                    
                    // 记录历史
                    $stateHistory->add("$key:$newValue:$currentVersion");
                    
                    $successfulUpdates++;
                    
                } finally {
                    $stateLock->unlock();
                }
            }
        }
        
        // 验证状态同步
        $this->assertEquals($successfulUpdates, $stateVersion->get() - 1);
        $this->assertEquals(5, $stateMap->get('active_users'));
        $this->assertEquals(100, $stateMap->get('total_requests'));
        $this->assertEquals(2, $stateMap->get('error_count'));
        $this->assertEquals('maintenance', $stateMap->get('system_status'));
        
        // 验证历史记录
        $this->assertEquals($successfulUpdates, $stateHistory->size());
        
        // 清理
        $stateMap->clear();
        $stateVersion->delete();
        $stateHistory->clear();
    }
    
    /**
     * Test distributed cache invalidation
     */
    public function testDistributedCacheInvalidation()
    {
        $cacheMap = $this->client->getMap('distributed:cache:map');
        $cacheVersion = $this->client->getAtomicLong('distributed:cache:version');
        $invalidationQueue = $this->client->getQueue('distributed:cache:invalidation');
        $cacheLock = $this->client->getLock('distributed:cache:lock');
        
        // 填充缓存
        $cacheItems = [
            'user:1' => ['name' => 'Alice', 'email' => 'alice@example.com'],
            'user:2' => ['name' => 'Bob', 'email' => 'bob@example.com'],
            'product:1' => ['name' => 'Widget', 'price' => 29.99],
            'product:2' => ['name' => 'Gadget', 'price' => 49.99]
        ];
        
        foreach ($cacheItems as $key => $value) {
            $cacheMap->put($key, $value);
        }
        $cacheVersion->set(1);
        
        // 模拟缓存失效
        $invalidationKeys = ['user:1', 'product:1'];
        $invalidationCount = 0;
        
        foreach ($invalidationKeys as $key) {
            if ($cacheLock->tryLock(1)) {
                try {
                    // 从缓存中移除
                    $cacheMap->remove($key);
                    
                    // 增加版本号
                    $newVersion = $cacheVersion->incrementAndGet();
                    
                    // 添加到失效队列
                    $invalidationQueue->add([
                        'key' => $key,
                        'version' => $newVersion,
                        'timestamp' => time()
                    ]);
                    
                    $invalidationCount++;
                    
                } finally {
                    $cacheLock->unlock();
                }
            }
        }
        
        // 验证缓存失效
        $this->assertEquals($invalidationCount, $cacheVersion->get() - 1);
        $this->assertFalse($cacheMap->containsKey('user:1'));
        $this->assertFalse($cacheMap->containsKey('product:1'));
        $this->assertTrue($cacheMap->containsKey('user:2'));
        $this->assertTrue($cacheMap->containsKey('product:2'));
        
        // 验证失效队列
        $this->assertEquals($invalidationCount, $invalidationQueue->size());
        
        // 清理
        $cacheMap->clear();
        $cacheVersion->delete();
        $invalidationQueue->clear();
    }
}