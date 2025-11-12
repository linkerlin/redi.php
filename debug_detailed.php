<?php

require_once 'vendor/autoload.php';

use Rediphp\Tests\RedissonTestCase;

// 创建测试用例
$testCase = new class extends RedissonTestCase {
    public function runDebug() {
        $redis = $this->client->getRedis();
        
        // 清理之前的测试数据
        $redis->del('test-detailed-lock:read');
        $redis->del('test-detailed-lock:write');
        
        // 获取读写锁
        $lock = $this->client->getReadWriteLock('test-detailed-lock');
        $writeLock = $lock->writeLock();
        $readLock = $lock->readLock();
        
        echo "=== 初始状态 ===\n";
        echo "Redis中所有键: ";
        var_dump($redis->keys('*test-detailed-lock*'));
        
        echo "\n=== 获取写锁 ===\n";
        $result = $writeLock->tryLock(5, 10);
        echo "写锁获取结果: " . ($result ? "成功" : "失败") . "\n";
        echo "写锁是否锁定: " . ($writeLock->isLocked() ? "是" : "否") . "\n";
        echo "Redis中所有键: ";
        var_dump($redis->keys('*test-detailed-lock*'));
        
        $writeLockName = $writeLock->getName();
        $readLockName = $readLock->getName();
        echo "写锁名称: $writeLockName\n";
        echo "读锁名称: $readLockName\n";
        
        // 手动检查写锁存在性
        echo "手动检查写锁存在性:\n";
        echo "redis->exists('$writeLockName'): " . $redis->exists($writeLockName) . "\n";
        echo "redis->exists('" . str_replace(':read', ':write', $readLockName) . "'): " . $redis->exists(str_replace(':read', ':write', $readLockName)) . "\n";
        
        // 检查写锁内容
        echo "写锁内容:\n";
        var_dump($redis->hGetAll($writeLockName));
        
        echo "\n=== 尝试获取读锁 ===\n";
        $startTime = microtime(true);
        $result = $readLock->tryLock(2, 5);
        $endTime = microtime(true);
        
        $duration = $endTime - $startTime;
        echo "读锁获取结果: " . ($result ? "成功" : "失败") . "\n";
        echo "耗时: " . $duration . " 秒\n";
        
        echo "\n=== 清理 ===\n";
        $writeLock->unlock();
        $redis->del($readLockName);
        echo "清理完成\n";
    }
};

$testCase->runDebug();