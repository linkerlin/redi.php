<?php

require_once 'vendor/autoload.php';

use Rediphp\RedissonClient;

class DebugAdvancedTest
{
    private $client;
    
    public function __construct()
    {
        $this->client = new RedissonClient([
            'use_pool' => true,
            'database' => 15
        ]);
    }
    
    public function testSimpleConcurrencyIssue()
    {
        echo "=== 开始调试并发问题 ===\n";
        echo "时间: " . date('H:i:s') . "\n";
        
        // 1. 简单的Redis连接测试
        echo "1. 测试Redis连接...\n";
        try {
            $redis = $this->client->getRedis();
            $result = $redis->ping();
            echo "✓ Redis连接正常: {$result}\n";
        } catch (Exception $e) {
            echo "❌ Redis连接失败: " . $e->getMessage() . "\n";
            return;
        }
        
        // 2. 测试锁的创建和释放
        echo "2. 测试锁的创建和释放...\n";
        $lockName = "debug:test:lock";
        try {
            $lock = $this->client->getLock($lockName);
            echo "✓ 锁创建成功\n";
            
            // 释放锁
            $lock->unlock();
            echo "✓ 锁释放成功\n";
        } catch (Exception $e) {
            echo "❌ 锁操作失败: " . $e->getMessage() . "\n";
            return;
        }
        
        // 3. 测试并发锁获取（短时间）
        echo "3. 测试并发锁获取（短时间）...\n";
        $this->testConcurrentLocks($lockName, 2, 5);
        
        // 4. 测试读写锁
        echo "4. 测试读写锁...\n";
        $this->testReadWriteLocks();
        
        // 5. 尝试模拟原始测试的复杂场景
        echo "5. 模拟复杂并发场景...\n";
        $this->testComplexScenario();
        
        echo "=== 调试完成 ===\n";
    }
    
    private function testConcurrentLocks($lockName, $threadCount, $operationsPerThread)
    {
        echo "启动 {$threadCount} 个线程，每个执行 {$operationsPerThread} 次锁操作\n";
        
        $successCount = 0;
        $failCount = 0;
        $threads = [];
        
        for ($i = 0; $i < $threadCount; $i++) {
            $threads[$i] = new class($i, $lockName, $operationsPerThread, $this->client) {
                private $threadId;
                private $lockName;
                private $operations;
                private $client;
                
                public function __construct($threadId, $lockName, $operations, $client)
                {
                    $this->threadId = $threadId;
                    $this->lockName = $lockName;
                    $this->operations = $operations;
                    $this->client = $client;
                }
                
                public function run()
                {
                    global $successCount, $failCount;
                    
                    for ($j = 0; $j < $this->operations; $j++) {
                        try {
                            $lock = $this->client->getLock($this->lockName . ":" . $this->threadId);
                            
                            // 短暂持有锁
                            if ($lock->tryLock(100)) {
                                usleep(10000); // 10ms
                                $lock->unlock();
                                
                                $successCount++;
                            } else {
                                $failCount++;
                            }
                        } catch (Exception $e) {
                            $failCount++;
                        }
                        
                        // 检查是否超时
                        if (($j + 1) % 10 == 0) {
                            echo "线程 {$this->threadId} 完成 {$j}/{$this->operations} 操作\n";
                        }
                    }
                    
                    echo "线程 {$this->threadId} 完成\n";
                }
            };
        }
        
        // 启动线程
        foreach ($threads as $thread) {
            $thread->run();
        }
        
        echo "并发测试结果 - 成功: {$successCount}, 失败: {$failCount}\n";
    }
    
    private function testReadWriteLocks()
    {
        echo "开始读写锁测试...\n";
        
        $rwLockName = "debug:test:rwlock";
        try {
            $rwLock = $this->client->getReadWriteLock($rwLockName);
            echo "✓ 读写锁创建成功\n";
            
            // 测试读锁
            $readLock = $rwLock->readLock();
            if ($readLock->tryLock(1000)) {
                echo "✓ 读锁获取成功\n";
                usleep(5000); // 5ms
                $readLock->unlock();
                echo "✓ 读锁释放成功\n";
            }
            
            // 测试写锁
            $writeLock = $rwLock->writeLock();
            if ($writeLock->tryLock(1000)) {
                echo "✓ 写锁获取成功\n";
                usleep(5000); // 5ms
                $writeLock->unlock();
                echo "✓ 写锁释放成功\n";
            }
            
        } catch (Exception $e) {
            echo "❌ 读写锁测试失败: " . $e->getMessage() . "\n";
        }
    }
    
    private function testComplexScenario()
    {
        echo "开始复杂场景测试...\n";
        
        $key = "debug:test:counter";
        $lockName = "debug:test:complex:lock";
        
        try {
            // 初始化计数器
            $redis = $this->client->getRedis();
            $redis->del($key);
            $redis->set($key, 0);
            $this->client->returnRedis($redis);
            echo "✓ 计数器初始化完成\n";
            
            // 模拟5个线程的并发操作
            $threadCount = 5;
            $operationsPerThread = 10;
            
            for ($i = 0; $i < $threadCount; $i++) {
                echo "启动线程 {$i}...\n";
                
                for ($j = 0; $j < $operationsPerThread; $j++) {
                    $success = false;
                    $attempts = 0;
                    $maxAttempts = 3;
                    
                    while (!$success && $attempts < $maxAttempts) {
                        try {
                            $lock = $this->client->getLock($lockName);
                            
                            if ($lock->tryLock(2000)) { // 2秒超时
                                try {
                                    $redis = $this->client->getRedis();
                                    $currentValue = $redis->get($key);
                                    $newValue = $currentValue + 1;
                                    $redis->set($key, $newValue);
                                    $this->client->returnRedis($redis);
                                    
                                    // 模拟一些处理时间
                                    usleep(rand(1000, 5000));
                                    
                                    $success = true;
                                    echo "线程 {$i} 操作 {$j} 完成，值: {$newValue}\n";
                                } finally {
                                    $lock->unlock();
                                }
                            } else {
                                $attempts++;
                                if ($attempts < $maxAttempts) {
                                    echo "线程 {$i} 操作 {$j} 尝试 {$attempts}/{$maxAttempts} 失败，等待重试\n";
                                    usleep(100000); // 100ms
                                }
                            }
                        } catch (Exception $e) {
                            echo "线程 {$i} 操作 {$j} 异常: " . $e->getMessage() . "\n";
                            $attempts++;
                        }
                    }
                    
                    if (!$success) {
                        echo "线程 {$i} 操作 {$j} 最终失败\n";
                        break;
                    }
                }
                
                // 检查是否卡住
                if (($i + 1) % 2 == 0) {
                    $redis = $this->client->getRedis();
                    $finalValue = $redis->get($key);
                    $this->client->returnRedis($redis);
                    echo "中间检查 - 当前值: {$finalValue}, 期望值: " . (($i + 1) * $operationsPerThread) . "\n";
                }
            }
            
            $redis = $this->client->getRedis();
            $finalValue = $redis->get($key);
            $this->client->returnRedis($redis);
            $expectedValue = $threadCount * $operationsPerThread;
            
            echo "最终结果 - 实际值: {$finalValue}, 期望值: {$expectedValue}\n";
            
            if ($finalValue == $expectedValue) {
                echo "✓ 复杂场景测试通过\n";
            } else {
                echo "❌ 复杂场景测试失败 - 数据不一致\n";
            }
            
        } catch (Exception $e) {
            echo "❌ 复杂场景测试失败: " . $e->getMessage() . "\n";
        }
    }
    
    public function cleanup()
    {
        try {
            // 没有专门的close方法，使用连接池清理
            echo "✓ 清理完成\n";
        } catch (Exception $e) {
            echo "清理时出错: " . $e->getMessage() . "\n";
        }
    }
}

// 运行调试测试
$test = new DebugAdvancedTest();
$test->testSimpleConcurrencyIssue();
$test->cleanup();

echo "调试测试结束时间: " . date('H:i:s') . "\n";