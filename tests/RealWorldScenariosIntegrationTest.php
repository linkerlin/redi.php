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
 * 真实世界场景集成测试
 * 测试redi.php在实际应用场景中的使用情况
 */
class RealWorldScenariosIntegrationTest extends RedissonTestCase
{
    /**
     * 测试电商购物车场景
     */
    public function testEcommerceShoppingCart()
    {
        // 用户购物车
        $cart = $this->client->getMap('cart:user:12345');
        // 商品库存
        $inventory = $this->client->getMap('inventory:products');
        // 用户会话
        $session = $this->client->getBucket('session:user:12345');
        // 购物车计数器
        $cartCounter = $this->client->getAtomicLong('counter:cart:items');
        
        // 初始化库存
        $inventory->put('product:1', ['name' => 'iPhone 15', 'price' => 999.99, 'stock' => 100]);
        $inventory->put('product:2', ['name' => 'MacBook Pro', 'price' => 1999.99, 'stock' => 50]);
        
        // 用户添加商品到购物车
        $cart->put('product:1', ['quantity' => 1, 'added_at' => time()]);
        $cartCounter->incrementAndGet();
        
        $cart->put('product:2', ['quantity' => 1, 'added_at' => time()]);
        $cartCounter->incrementAndGet();
        
        // 更新用户会话
        $session->set([
            'user_id' => 12345,
            'last_activity' => time(),
            'cart_items' => $cart->size()
        ]);
        
        // 验证购物车内容
        $this->assertEquals(2, $cart->size());
        $this->assertEquals(2, $cartCounter->get());
        $this->assertEquals(12345, $session->get()['user_id']);
        
        // 模拟用户修改购物车
        $cart->put('product:1', ['quantity' => 2, 'added_at' => time()]);
        
        // 验证修改后的购物车
        $product1 = $cart->get('product:1');
        $this->assertEquals(2, $product1['quantity']);
        
        // 清理
        $cart->clear();
        $session->delete();
        $cartCounter->delete();
    }
    
    /**
     * 测试分布式任务队列场景
     */
    public function testDistributedTaskQueue()
    {
        // 任务队列
        $taskQueue = $this->client->getQueue('tasks:processing');
        // 失败任务队列
        $failedQueue = $this->client->getQueue('tasks:failed');
        // 完成任务队列
        $completedQueue = $this->client->getQueue('tasks:completed');
        // 工作进程信号量
        $workerSemaphore = $this->client->getSemaphore('workers:available', 3);
        // 任务计数器
        $taskCounter = $this->client->getAtomicLong('counter:tasks:total');
        
        // 添加任务到队列
        $tasks = [
            ['id' => 1, 'type' => 'email', 'data' => ['to' => 'user@example.com', 'subject' => 'Welcome']],
            ['id' => 2, 'type' => 'image_processing', 'data' => ['image_id' => 12345, 'size' => 'large']],
            ['id' => 3, 'type' => 'report_generation', 'data' => ['report_id' => 67890, 'format' => 'pdf']]
        ];
        
        foreach ($tasks as $task) {
            $taskQueue->add($task);
            $taskCounter->incrementAndGet();
        }
        
        // 验证任务已添加
        $this->assertEquals(3, $taskQueue->size());
        $this->assertEquals(3, $taskCounter->get());
        
        // 模拟工作进程处理任务
        $processedTasks = 0;
        while (!$taskQueue->isEmpty()) {
            // 获取工作许可
            if ($workerSemaphore->tryAcquire()) {
                try {
                    $task = $taskQueue->poll();
                    
                    // 模拟任务处理
                    if ($task['type'] === 'email') {
                        // 模拟邮件发送成功
                        $completedQueue->add($task);
                        $processedTasks++;
                    } elseif ($task['type'] === 'image_processing') {
                        // 模拟图像处理失败
                        $failedQueue->add($task);
                    } else {
                        // 其他任务成功
                        $completedQueue->add($task);
                        $processedTasks++;
                    }
                } finally {
                    $workerSemaphore->release();
                }
            }
        }
        
        // 验证处理结果
        $this->assertEquals(0, $taskQueue->size());
        $this->assertEquals(2, $completedQueue->size());
        $this->assertEquals(1, $failedQueue->size());
        $this->assertEquals(2, $processedTasks);
        
        // 清理
        $taskQueue->clear();
        $failedQueue->clear();
        $completedQueue->clear();
        $taskCounter->delete();
    }
    
    /**
     * 测试用户会话管理场景
     */
    public function testUserSessionManagement()
    {
        // 活跃用户集合
        $activeUsers = $this->client->getSet('users:active');
        // 用户会话映射
        $userSessions = $this->client->getMap('sessions:users');
        // 会话超时队列
        $sessionTimeoutQueue = $this->client->getQueue('sessions:timeout');
        // 在线用户计数器
        $onlineCounter = $this->client->getAtomicLong('counter:users:online');
        // 会话读写锁
        $sessionLock = $this->client->getReadWriteLock('lock:sessions');
        
        // 模拟用户登录
        $users = [
            ['id' => 1001, 'name' => 'Alice', 'login_time' => time()],
            ['id' => 1002, 'name' => 'Bob', 'login_time' => time()],
            ['id' => 1003, 'name' => 'Charlie', 'login_time' => time()]
        ];
        
        foreach ($users as $user) {
            $sessionLock->writeLock()->lock();
            try {
                // 添加到活跃用户集合
                $activeUsers->add($user['id']);
                
                // 创建用户会话
                $userSessions->put($user['id'], [
                    'user_id' => $user['id'],
                    'name' => $user['name'],
                    'login_time' => $user['login_time'],
                    'last_activity' => $user['login_time'],
                    'session_id' => uniqid('session_', true)
                ]);
                
                // 增加在线用户计数
                $onlineCounter->incrementAndGet();
                
                // 添加到会话超时队列（30分钟后超时）
                $sessionTimeoutQueue->add([
                    'user_id' => $user['id'],
                    'timeout_at' => $user['login_time'] + 1800 // 30分钟
                ]);
            } finally {
                $sessionLock->writeLock()->unlock();
            }
        }
        
        // 验证用户登录状态
        $this->assertEquals(3, $activeUsers->size());
        $this->assertEquals(3, $userSessions->size());
        $this->assertEquals(3, $onlineCounter->get());
        $this->assertEquals(3, $sessionTimeoutQueue->size());
        
        // 模拟用户活动
        $sessionLock->readLock()->lock();
        try {
            $aliceSession = $userSessions->get(1001);
            $aliceSession['last_activity'] = time();
            $userSessions->put(1001, $aliceSession);
        } finally {
            $sessionLock->readLock()->unlock();
        }
        
        // 验证用户活动更新
        $updatedAliceSession = $userSessions->get(1001);
        $this->assertGreaterThan($updatedAliceSession['login_time'], $updatedAliceSession['last_activity']);
        
        // 清理
        $activeUsers->clear();
        $userSessions->clear();
        $sessionTimeoutQueue->clear();
        $onlineCounter->delete();
    }
    
    /**
     * 测试实时通知系统场景
     */
    public function testRealTimeNotificationSystem()
    {
        // 通知主题
        $notificationTopic = $this->client->getTopic('notifications');
        // 用户订阅映射
        $userSubscriptions = $this->client->getMap('subscriptions:users');
        // 通知历史
        $notificationHistory = $this->client->getList('notifications:history');
        // 未读通知计数
        $unreadCounters = $this->client->getMap('counters:unread');
        
        // 设置用户订阅
        $userSubscriptions->put(2001, ['email' => true, 'push' => true, 'sms' => false]);
        $userSubscriptions->put(2002, ['email' => false, 'push' => true, 'sms' => true]);
        $userSubscriptions->put(2003, ['email' => true, 'push' => false, 'sms' => false]);
        
        // 初始化未读计数器
        $unreadCounters->put(2001, 0);
        $unreadCounters->put(2002, 0);
        $unreadCounters->put(2003, 0);
        
        // 模拟发送通知
        $notifications = [
            ['id' => uniqid(), 'user_id' => 2001, 'type' => 'message', 'content' => 'You have a new message', 'timestamp' => time()],
            ['id' => uniqid(), 'user_id' => 2002, 'type' => 'alert', 'content' => 'System maintenance scheduled', 'timestamp' => time()],
            ['id' => uniqid(), 'user_id' => 2001, 'type' => 'update', 'content' => 'Your profile was updated', 'timestamp' => time()]
        ];
        
        foreach ($notifications as $notification) {
            // 添加到通知历史
            $notificationHistory->add($notification);
            
            // 发布通知
            $notificationTopic->publish($notification);
            
            // 增加未读计数
            $currentCount = $unreadCounters->get($notification['user_id']);
            $unreadCounters->put($notification['user_id'], $currentCount + 1);
        }
        
        // 验证通知处理
        $this->assertEquals(3, $notificationHistory->size());
        $this->assertEquals(2, $unreadCounters->get(2001));
        $this->assertEquals(1, $unreadCounters->get(2002));
        $this->assertEquals(0, $unreadCounters->get(2003));
        
        // 模拟用户读取通知
        $userId = 2001;
        $unreadCounters->put($userId, 0);
        
        // 验证通知已读
        $this->assertEquals(0, $unreadCounters->get($userId));
        
        // 清理
        $notificationHistory->clear();
        $userSubscriptions->clear();
        $unreadCounters->clear();
    }
    
    /**
     * 测试分布式限流场景
     */
    public function testDistributedRateLimiting()
    {
        // 用户限流计数器
        $userCounters = $this->client->getMap('rate_limit:users');
        // 全局限流计数器
        $globalCounter = $this->client->getAtomicLong('rate_limit:global');
        // 限流配置
        $rateLimitConfig = $this->client->getBucket('rate_limit:config');
        // 被限流的用户集合
        $throttledUsers = $this->client->getSet('rate_limit:throttled');
        
        // 设置限流配置
        $rateLimitConfig->set([
            'user_limit' => 100,  // 每用户每分钟100次请求
            'global_limit' => 10000,  // 全局每分钟10000次请求
            'window' => 60  // 时间窗口60秒
        ]);
        
        // 模拟用户请求
        $requests = [];
        for ($i = 0; $i < 250; $i++) {
            $userId = ($i % 5) + 3001; // 5个用户
            $requests[] = ['user_id' => $userId, 'timestamp' => time(), 'request_id' => uniqid()];
        }
        
        $config = $rateLimitConfig->get();
        $allowedRequests = 0;
        $blockedRequests = 0;
        
        foreach ($requests as $request) {
            $userId = $request['user_id'];
            
            // 检查用户是否已被限流
            if ($throttledUsers->contains($userId)) {
                $blockedRequests++;
                continue;
            }
            
            // 获取当前用户计数
            $userCount = $userCounters->get($userId) ?: ['count' => 0, 'window_start' => time()];
            
            // 检查时间窗口
            if (time() - $userCount['window_start'] > $config['window']) {
                $userCount = ['count' => 0, 'window_start' => time()];
            }
            
            // 检查用户限流
            if ($userCount['count'] >= $config['user_limit']) {
                $throttledUsers->add($userId);
                $blockedRequests++;
                continue;
            }
            
            // 检查全局限流
            if ($globalCounter->get() >= $config['global_limit']) {
                $blockedRequests++;
                continue;
            }
            
            // 允许请求
            $userCount['count']++;
            $userCounters->put($userId, $userCount);
            $globalCounter->incrementAndGet();
            $allowedRequests++;
        }
        
        // 验证限流结果
        $this->assertGreaterThan(0, $allowedRequests);
        $this->assertGreaterThan(0, $blockedRequests);
        $this->assertEquals(5, $throttledUsers->size()); // 所有5个用户都应被限流
        
        // 清理
        $userCounters->clear();
        $globalCounter->delete();
        $rateLimitConfig->delete();
        $throttledUsers->clear();
    }
    
    /**
     * 测试分布式缓存场景
     */
    public function testDistributedCache()
    {
        // 缓存存储
        $cache = $this->client->getMap('cache:data');
        // 缓存元数据
        $cacheMetadata = $this->client->getMap('cache:metadata');
        // 缓存统计
        $cacheStats = $this->client->getAtomicLong('cache:stats:hits');
        $cacheMisses = $this->client->getAtomicLong('cache:stats:misses');
        // 布隆过滤器用于快速判断缓存是否存在
        $cacheBloomFilter = $this->client->getBloomFilter('cache:bloom', 1000000, 0.01);
        
        // 初始化布隆过滤器
        $cacheBloomFilter->clear();
        
        // 模拟缓存数据
        $data = [
            'user:1001' => ['id' => 1001, 'name' => 'Alice', 'email' => 'alice@example.com'],
            'user:1002' => ['id' => 1002, 'name' => 'Bob', 'email' => 'bob@example.com'],
            'product:2001' => ['id' => 2001, 'name' => 'Laptop', 'price' => 999.99],
            'product:2002' => ['id' => 2002, 'name' => 'Phone', 'price' => 699.99]
        ];
        
        // 添加数据到缓存
        foreach ($data as $key => $value) {
            $cache->put($key, $value);
            $cacheMetadata->put($key, [
                'created_at' => time(),
                'ttl' => 3600, // 1小时
                'access_count' => 0
            ]);
            $cacheBloomFilter->add($key);
        }
        
        // 模拟缓存访问
        $accessKeys = ['user:1001', 'user:1002', 'product:2001', 'product:2002', 'user:1003'];
        
        foreach ($accessKeys as $key) {
            // 使用布隆过滤器快速检查
            if (!$cacheBloomFilter->contains($key)) {
                $cacheMisses->incrementAndGet();
                continue;
            }
            
            // 检查缓存中是否存在
            if ($cache->containsKey($key)) {
                $value = $cache->get($key);
                $metadata = $cacheMetadata->get($key);
                $metadata['access_count']++;
                $cacheMetadata->put($key, $metadata);
                $cacheStats->incrementAndGet();
            } else {
                $cacheMisses->incrementAndGet();
            }
        }
        
        // 验证缓存统计
        $this->assertEquals(4, $cacheStats->get());
        $this->assertEquals(1, $cacheMisses->get());
        
        // 验证缓存元数据
        $user1001Metadata = $cacheMetadata->get('user:1001');
        $this->assertEquals(1, $user1001Metadata['access_count']);
        
        // 清理
        $cache->clear();
        $cacheMetadata->clear();
        $cacheStats->delete();
        $cacheMisses->delete();
        $cacheBloomFilter->delete();
    }
}