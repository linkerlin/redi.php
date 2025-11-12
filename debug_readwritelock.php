<?php

require_once __DIR__ . '/vendor/autoload.php';

use Rediphp\RedissonClient;
use Rediphp\RReadWriteLock;

// 创建客户端
$client = new RedissonClient([
    'host' => '127.0.0.1',
    'port' => 6379,
    'database' => 0
]);

// 获取读写锁
$lock = $client->getReadWriteLock('test-debug-lock');

// 获取写锁
$writeLock = $lock->writeLock();
echo "写锁名称: " . $writeLock->getName() . "\n";

// 尝试获取写锁
$result = $writeLock->tryLock(5, 10);
echo "写锁获取结果: " . ($result ? '成功' : '失败') . "\n";
echo "写锁是否锁定: " . ($writeLock->isLocked() ? '是' : '否') . "\n";

// 获取读锁
$readLock = $lock->readLock();
echo "读锁名称: " . $readLock->getName() . "\n";

// 检查写锁是否存在
$writeLockName = str_replace(':read', ':write', $readLock->getName());
echo "写锁检查名称: " . $writeLockName . "\n";
echo "写锁是否存在: " . ($client->getRedis()->exists($writeLockName) ? '是' : '否') . "\n";

// 检查写锁的具体内容
echo "写锁内容: ";
var_dump($client->getRedis()->hGetAll($writeLockName));

// 尝试获取读锁（带超时）
$startTime = microtime(true);
$result = $readLock->tryLock(2, 5);
$endTime = microtime(true);

echo "读锁获取结果: " . ($result ? '成功' : '失败') . "\n";
echo "耗时: " . ($endTime - $startTime) . " 秒\n";

// 释放写锁
$writeLock->unlock();
echo "写锁已释放\n";

// 再次尝试获取读锁
$result = $readLock->tryLock(1, 5);
echo "读锁再次获取结果: " . ($result ? '成功' : '失败') . "\n";

$readLock->unlock();
echo "读锁已释放\n";