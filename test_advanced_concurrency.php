#!/usr/bin/env php
<?php

require_once 'vendor/autoload.php';

use Rediphp\RedissonClient;
use Rediphp\RMap;
use Rediphp\RList;
use Rediphp\RSet;
use Rediphp\RAtomicLong;
use Rediphp\RLock;
use Rediphp\RSemaphore;
use Rediphp\RReadWriteLock;

echo "=== 高级并发测试脚本 ===\n";
echo "开始时间: " . date('H:i:s') . "\n";

try {
    $client = new RedissonClient();
    echo "✓ RedissonClient 创建成功\n";

    // 测试1: 简单的并发写冲突测试
    echo "\n--- 测试1: 简单并发写冲突 ---";
    testSimpleConcurrentWriteConflicts($client);

    echo "\n✓ 测试1 完成\n";

    // 测试2: 读写锁简单场景
    echo "\n--- 测试2: 读写锁简单场景 ---";
    testSimpleReadWriteLockScenario($client);

    echo "\n✓ 测试2 完成\n";

    echo "\n=== 所有测试完成 ===";
    echo "结束时间: " . date('H:i:s') . "\n";

} catch (Exception $e) {
    echo "\n❌ 测试失败: " . $e->getMessage() . "\n";
    echo "错误位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

function testSimpleConcurrentWriteConflicts($client)
{
    echo "\n测试开始时间: " . date('H:i:s') . "\n";
    
    $conflictMap = $client->getMap('test:simple:conflict');
    $conflictCounter = $client->getAtomicLong('test:simple:conflict:counter');
    $conflictLock = $client->getLock('test:simple:conflict:lock');
    
    // 初始化测试数据
    $initialData = [
        'base_value' => 100,
        'version' => 1,
        'timestamp' => time()
    ];
    $conflictMap->put('test:item', $initialData);
    $conflictCounter->set(0);
    
    echo "✓ 初始化完成\n";
    
    // 模拟简单的并发写入
    $operations = 20;
    $threads = 5;
    $operationsPerThread = $operations / $threads;
    
    echo "开始 {$threads} 个线程，每个执行 {$operationsPerThread} 次操作\n";
    
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
                        $currentData = $conflictMap->get('test:item');
                        if (!$currentData) {
                            $currentData = $initialData;
                        }
                        
                        // 模拟业务逻辑
                        $newData = $currentData;
                        $newData['base_value'] += 1;
                        $newData['version'] += 1;
                        $newData['last_modified_by'] = $threadId;
                        $newData['operation_id'] = $operationId;
                        $newData['modified_at'] = time();
                        
                        // 写回数据
                        $conflictMap->put('test:item', $newData);
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
        
        echo "✓ 线程 {$threadId} 完成\n";
    }
    
    // 验证并发操作结果
    $totalSuccess = array_sum(array_column($results, 'success_count'));
    $totalConflicts = array_sum(array_column($results, 'conflict_count'));
    $totalRetries = array_sum(array_column($results, 'retry_count'));
    
    echo "总成功操作: {$totalSuccess}\n";
    echo "总冲突操作: {$totalConflicts}\n";
    echo "总重试操作: {$totalRetries}\n";
    
    // 验证最终数据状态
    $finalData = $conflictMap->get('test:item');
    echo "最终值: " . $finalData['base_value'] . "\n";
    echo "期望值: " . ($initialData['base_value'] + $operations) . "\n";
    
    if ($finalData['base_value'] == $initialData['base_value'] + $operations) {
        echo "✓ 数据一致性验证通过\n";
    } else {
        echo "❌ 数据一致性验证失败\n";
    }
    
    if ($totalRetries > 0) {
        echo "✓ 重试机制验证通过\n";
    } else {
        echo "❌ 重试机制验证失败 (没有重试操作)\n";
    }
    
    // 清理
    $conflictMap->clear();
    $conflictCounter->delete();
    
    echo "✓ 清理完成\n";
    echo "测试结束时间: " . date('H:i:s') . "\n";
}

function testSimpleReadWriteLockScenario($client)
{
    echo "\n测试开始时间: " . date('H:i:s') . "\n";
    
    $rwLockMap = $client->getMap('test:simple:rwlock:map');
    $readLock = $client->getReadWriteLock('test:simple:rwlock');
    $readCounter = $client->getAtomicLong('test:simple:read:counter');
    $writeCounter = $client->getAtomicLong('test:simple:write:counter');
    
    // 初始化数据
    $initialSize = 10;
    for ($i = 0; $i < $initialSize; $i++) {
        $rwLockMap->put("item:$i", [
            'value' => $i * 10,
            'read_count' => 0,
            'write_count' => 0
        ]);
    }
    
    echo "✓ 初始化 {$initialSize} 个数据项\n";
    
    $operations = 20;
    $threads = 4;
    $operationsPerThread = $operations / $threads;
    
    echo "开始 {$threads} 个线程，每个执行 {$operationsPerThread} 次操作\n";
    
    // 模拟简单的读写操作
    for ($threadId = 0; $threadId < $threads; $threadId++) {
        for ($op = 0; $op < $operationsPerThread; $op++) {
            $operationType = $threadId % 3; // 0: 读取, 1: 写入, 2: 读取
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
                                    $data['value'] += 5;
                                    $data['write_count']++;
                                    $rwLockMap->put($itemKey, $data);
                                    $writeCounter->incrementAndGet();
                                }
                            } finally {
                                $readLock->writeLock()->unlock();
                            }
                        }
                        break;
                        
                    case 2: // 读取操作
                        if ($readLock->readLock()->tryLock(1000)) {
                            try {
                                $data = $rwLockMap->get($itemKey);
                                if ($data) {
                                    $data['read_count']++;
                                    $readCounter->incrementAndGet();
                                }
                            } finally {
                                $readLock->readLock()->unlock();
                            }
                        }
                        break;
                }
            } catch (\Exception $e) {
                echo "❌ 线程 {$threadId} 操作失败: " . $e->getMessage() . "\n";
            }
        }
        
        echo "✓ 线程 {$threadId} 完成\n";
    }
    
    // 验证读写锁操作结果
    echo "读取计数器: " . $readCounter->get() . "\n";
    echo "写入计数器: " . $writeCounter->get() . "\n";
    
    if ($readCounter->get() > 0) {
        echo "✓ 读取操作验证通过\n";
    } else {
        echo "❌ 读取操作验证失败\n";
    }
    
    if ($writeCounter->get() > 0) {
        echo "✓ 写入操作验证通过\n";
    } else {
        echo "❌ 写入操作验证失败\n";
    }
    
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
    
    echo "总价值: {$totalValue}\n";
    echo "总读取次数: {$totalReadCount}\n";
    echo "总写入次数: {$totalWriteCount}\n";
    
    if ($totalValue > 0 && $totalReadCount > 0 && $totalWriteCount > 0) {
        echo "✓ 数据一致性验证通过\n";
    } else {
        echo "❌ 数据一致性验证失败\n";
    }
    
    // 清理
    $rwLockMap->clear();
    
    echo "✓ 清理完成\n";
    echo "测试结束时间: " . date('H:i:s') . "\n";
}