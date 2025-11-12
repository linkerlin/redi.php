<?php

require_once 'vendor/autoload.php';

use Rediphp\RedissonClient;

// 创建Redis客户端
$client = new RedissonClient([
    'host' => '127.0.0.1',
    'port' => 6379,
]);
$client->connect();
$redis = $client->getRedis();

// 清理之前的测试数据
$redis->del('test-debug-lock:read');
$redis->del('test-debug-lock:write');

// 获取读写锁
$lock = $client->getReadWriteLock('test-debug-lock');
$writeLock = $lock->writeLock();
$readLock = $lock->readLock();

echo "=== 获取写锁 ===\n";
$result = $writeLock->tryLock(5, 10); // 5秒等待，10秒租期
echo "写锁获取结果: " . ($result ? "成功" : "失败") . "\n";

$writeLockName = $writeLock->getName();
$readLockName = $readLock->getName();
echo "写锁名称: $writeLockName\n";
echo "读锁名称: $readLockName\n";

// 手动检查写锁存在性
echo "写锁存在性检查:\n";
echo "redis->exists('$writeLockName'): " . $redis->exists($writeLockName) . "\n";

// 模拟测试场景：写锁存在时尝试获取读锁
echo "\n=== 模拟测试场景 ===\n";
echo "当前时间: " . date('H:i:s') . "\n";

// 创建自定义ReadLock类来调试
class DebugReadLock extends \Rediphp\ReadLock {
    public function tryLock(int $waitTime = 0, int $leaseTime = -1): bool
    {
        echo "DebugReadLock::tryLock called with waitTime=$waitTime, leaseTime=$leaseTime\n";
        
        if ($leaseTime < 0) {
            $leaseTime = 30000;
        }

        if ($this->lockId === null) {
            $this->lockId = uniqid(gethostname() . '_', true);
        }
        
        $ttl = (int)ceil($leaseTime / 1000);
        $waitUntil = microtime(true) + $waitTime;
        
        echo "开始时间: " . date('H:i:s') . ", 等待直到: " . date('H:i:s', $waitUntil) . "\n";

        $writeLockName = str_replace(':read', ':write', $this->name);
        echo "写锁名称: $writeLockName\n";
        
        $iteration = 0;
        while (microtime(true) < $waitUntil) {
            $iteration++;
            $currentTime = microtime(true);
            $writeLockExists = $this->redis->exists($writeLockName);
            echo "第{$iteration}次检查 (时间: " . date('H:i:s', $currentTime) . "): 写锁存在=" . ($writeLockExists ? "是" : "否") . "\n";
            
            if ($writeLockExists === 0) {
                echo "写锁不存在，获取读锁\n";
                $this->redis->hIncrBy($this->name, $this->lockId, 1);
                $this->redis->expire($this->name, $ttl);
                return true;
            }
            
            echo "写锁存在，继续等待\n";
            usleep(100000); // 100ms
        }
        
        echo "超时，返回false\n";
        return false;
    }
}

// 使用调试版本的ReadLock
$debugReadLock = new DebugReadLock($redis, $readLockName);

$startTime = microtime(true);
$result = $debugReadLock->tryLock(2, 5); // 2秒等待，5秒租期
$endTime = microtime(true);

$duration = $endTime - $startTime;
echo "\n=== 结果 ===\n";
echo "读锁获取结果: " . ($result ? "成功" : "失败") . "\n";
echo "耗时: " . $duration . " 秒\n";

// 清理
echo "\n=== 清理 ===\n";
$writeLock->unlock();
$redis->del($readLockName);