<?php

require __DIR__ . '/../vendor/autoload.php';

use Rediphp\RedissonClient;

// 创建客户端并连接
$client = new RedissonClient([
    'host' => '127.0.0.1',
    'port' => 6379,
]);
$client->connect();

echo "=== RLock 示例 ===\n";

// 获取锁
$lock = $client->getLock('myLock');

echo "尝试获取锁...\n";
if ($lock->tryLock(1000, 5000)) { // 等待1秒，租期5秒
    echo "成功获取锁！\n";
    
    try {
        // 执行需要同步的代码
        echo "执行关键代码...\n";
        sleep(2);
        echo "关键代码执行完成！\n";
    } finally {
        // 确保释放锁
        $lock->unlock();
        echo "锁已释放！\n";
    }
} else {
    echo "无法获取锁！\n";
}

echo "\n=== RReadWriteLock 示例 ===\n";

$rwLock = $client->getReadWriteLock('myRWLock');
$readLock = $rwLock->readLock();
$writeLock = $rwLock->writeLock();

// 读锁示例
echo "获取读锁...\n";
$readLock->lock();
try {
    echo "执行读操作...\n";
    sleep(1);
} finally {
    $readLock->unlock();
    echo "读锁已释放！\n";
}

// 写锁示例
echo "获取写锁...\n";
$writeLock->lock();
try {
    echo "执行写操作...\n";
    sleep(1);
} finally {
    $writeLock->unlock();
    echo "写锁已释放！\n";
}

echo "\n=== RSemaphore 示例 ===\n";

$semaphore = $client->getSemaphore('mySemaphore');
$semaphore->trySetPermits(3); // 设置3个许可

echo "可用许可数: " . $semaphore->availablePermits() . "\n";

echo "获取许可...\n";
if ($semaphore->tryAcquire()) {
    echo "成功获取许可！\n";
    echo "可用许可数: " . $semaphore->availablePermits() . "\n";
    
    // 执行操作
    sleep(1);
    
    // 释放许可
    $semaphore->release();
    echo "许可已释放！\n";
    echo "可用许可数: " . $semaphore->availablePermits() . "\n";
}

echo "\n=== RCountDownLatch 示例 ===\n";

$latch = $client->getCountDownLatch('myLatch');
$latch->trySetCount(3); // 设置计数为3

echo "初始计数: " . $latch->getCount() . "\n";

// 模拟倒计时
for ($i = 1; $i <= 3; $i++) {
    echo "倒计时 {$i}...\n";
    $latch->countDown();
    echo "当前计数: " . $latch->getCount() . "\n";
    sleep(1);
}

// 等待计数归零
echo "等待计数归零...\n";
if ($latch->await(5000)) {
    echo "计数已归零！\n";
} else {
    echo "等待超时！\n";
}

echo "\n=== RAtomicLong 示例 ===\n";

$atomicLong = $client->getAtomicLong('myAtomicLong');
$atomicLong->set(0);

echo "初始值: " . $atomicLong->get() . "\n";

// 自增
$newValue = $atomicLong->incrementAndGet();
echo "自增后: " . $newValue . "\n";

// 加法
$newValue = $atomicLong->addAndGet(10);
echo "加10后: " . $newValue . "\n";

// 比较并设置
if ($atomicLong->compareAndSet(11, 100)) {
    echo "比较并设置成功，新值: " . $atomicLong->get() . "\n";
} else {
    echo "比较并设置失败！\n";
}

echo "\n=== RAtomicDouble 示例 ===\n";

$atomicDouble = $client->getAtomicDouble('myAtomicDouble');
$atomicDouble->set(0.0);

echo "初始值: " . $atomicDouble->get() . "\n";

// 加法
$newValue = $atomicDouble->addAndGet(3.14);
echo "加3.14后: " . $newValue . "\n";

$newValue = $atomicDouble->addAndGet(2.86);
echo "再加2.86后: " . $newValue . "\n";

// 关闭连接
$client->shutdown();

echo "\n所有并发示例执行完成！\n";
