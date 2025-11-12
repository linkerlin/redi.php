<?php

namespace Rediphp\Tests;

class RReadWriteLockTest extends RedissonTestCase
{
    /**
     * 测试读写锁的基本获取和释放
     */
    public function testBasicLockAcquisitionAndRelease()
    {
        $lock = $this->client->getReadWriteLock('test-lock');
        
        // 获取读锁
        $readLock = $lock->readLock();
        $this->assertTrue($readLock->tryLock());
        
        // 验证读锁已获取
        $this->assertTrue($readLock->isLocked());
        $this->assertTrue($readLock->isHeldByCurrentThread());
        
        // 释放读锁
        $readLock->unlock();
        
        // 验证读锁已释放
        $this->assertFalse($readLock->isLocked());
        
        // 获取写锁
        $writeLock = $lock->writeLock();
        $this->assertTrue($writeLock->tryLock());
        
        // 验证写锁已获取
        $this->assertTrue($writeLock->isLocked());
        $this->assertTrue($writeLock->isHeldByCurrentThread());
        
        // 释放写锁
        $writeLock->unlock();
        
        // 验证写锁已释放
        $this->assertFalse($writeLock->isLocked());
    }
    
    /**
     * 测试读写锁的互斥性
     */
    public function testReadWriteLockMutualExclusion()
    {
        $lock = $this->client->getReadWriteLock('test-mutex-lock');
        
        // 获取写锁
        $writeLock = $lock->writeLock();
        $this->assertTrue($writeLock->tryLock());
        
        // 尝试获取读锁（应该失败，因为写锁已获取）
        $readLock = $lock->readLock();
        $this->assertFalse($readLock->tryLock());
        
        // 释放写锁
        $writeLock->unlock();
        
        // 现在应该可以获取读锁
        $this->assertTrue($readLock->tryLock());
        
        // 尝试获取写锁（应该失败，因为读锁已获取）
        $writeLock2 = $lock->writeLock();
        $this->assertFalse($writeLock2->tryLock());
        
        // 释放读锁
        $readLock->unlock();
    }
    
    /**
     * 测试多个读锁的共享性
     */
    public function testMultipleReadLocksSharing()
    {
        $lock = $this->client->getReadWriteLock('test-shared-lock');
        
        // 获取第一个读锁
        $readLock1 = $lock->readLock();
        $this->assertTrue($readLock1->tryLock());
        
        // 获取第二个读锁（应该成功，因为读锁可以共享）
        $readLock2 = $lock->readLock();
        $this->assertTrue($readLock2->tryLock());
        
        // 获取第三个读锁
        $readLock3 = $lock->readLock();
        $this->assertTrue($readLock3->tryLock());
        
        // 验证所有读锁都已获取
        $this->assertTrue($readLock1->isLocked());
        $this->assertTrue($readLock2->isLocked());
        $this->assertTrue($readLock3->isLocked());
        
        // 释放所有读锁
        $readLock1->unlock();
        $readLock2->unlock();
        $readLock3->unlock();
        
        // 验证所有读锁已释放
        $this->assertFalse($readLock1->isLocked());
        $this->assertFalse($readLock2->isLocked());
        $this->assertFalse($readLock3->isLocked());
    }
    
    /**
     * 测试带超时的锁获取
     */
    public function testLockWithTimeout()
    {
        $lock = $this->client->getReadWriteLock('test-timeout-lock');
        
        // 获取写锁
        $writeLock = $lock->writeLock();
        $this->assertTrue($writeLock->tryLock(5, 10000)); // 等待5秒，租期10秒（10000毫秒）
        
        // 尝试获取读锁（带超时）
        $readLock = $lock->readLock();
        $startTime = microtime(true);
        $result = $readLock->tryLock(2, 5); // 等待2秒
        $endTime = microtime(true);
        
        // 验证获取失败且超时时间正确
        $this->assertFalse($result);
        $this->assertGreaterThanOrEqual(2, $endTime - $startTime);
        
        // 释放写锁
        $writeLock->unlock();
        
        // 现在应该可以获取读锁
        $this->assertTrue($readLock->tryLock(1, 5));
        $readLock->unlock();
    }
    
    /**
     * 测试锁的租期和自动释放
     */
    public function testLockLeaseAndAutoRelease()
    {
        $lock = $this->client->getReadWriteLock('test-lease-lock');
        
        // 获取写锁，设置短租期
        $writeLock = $lock->writeLock();
        $this->assertTrue($writeLock->tryLock(5, 2000)); // 租期2秒（2000毫秒）
        
        // 等待租期过期
        sleep(3);
        
        // 锁应该已自动释放
        $this->assertFalse($writeLock->isLocked());
        
        // 现在应该可以获取读锁
        $readLock = $lock->readLock();
        $this->assertTrue($readLock->tryLock());
        $readLock->unlock();
    }
    
    /**
     * 测试强制解锁
     */
    public function testForceUnlock()
    {
        $lock = $this->client->getReadWriteLock('test-force-lock');
        
        // 获取写锁
        $writeLock = $lock->writeLock();
        $this->assertTrue($writeLock->tryLock());
        
        // 强制解锁
        $writeLock->forceUnlock();
        
        // 验证锁已释放
        $this->assertFalse($writeLock->isLocked());
        
        // 现在应该可以获取读锁
        $readLock = $lock->readLock();
        $this->assertTrue($readLock->tryLock());
        $readLock->unlock();
    }
    
    /**
     * 测试锁的重入性
     */
    public function testLockReentrancy()
    {
        $lock = $this->client->getReadWriteLock('test-reentrant-lock');
        
        // 获取读锁多次
        $readLock = $lock->readLock();
        $this->assertTrue($readLock->tryLock());
        $this->assertTrue($readLock->tryLock()); // 重入
        
        // 验证锁状态
        $this->assertTrue($readLock->isLocked());
        
        // 需要释放两次
        $readLock->unlock();
        $this->assertTrue($readLock->isLocked()); // 第一次释放后仍然锁定
        
        $readLock->unlock();
        $this->assertFalse($readLock->isLocked()); // 第二次释放后解锁
        
        // 测试写锁的重入
        $writeLock = $lock->writeLock();
        $this->assertTrue($writeLock->tryLock());
        $this->assertTrue($writeLock->tryLock()); // 重入
        
        $writeLock->unlock();
        $this->assertTrue($writeLock->isLocked());
        
        $writeLock->unlock();
        $this->assertFalse($writeLock->isLocked());
    }
    
    /**
     * 测试锁的公平性
     */
    public function testLockFairness()
    {
        $lock = $this->client->getReadWriteLock('test-fair-lock');
        
        // 获取多个读锁
        $readLock1 = $lock->readLock();
        $readLock2 = $lock->readLock();
        $readLock3 = $lock->readLock();
        
        $this->assertTrue($readLock1->tryLock());
        $this->assertTrue($readLock2->tryLock());
        $this->assertTrue($readLock3->tryLock());
        
        // 释放顺序应该不影响
        $readLock2->unlock();
        $readLock1->unlock();
        $readLock3->unlock();
        
        // 验证所有锁已释放
        $this->assertFalse($readLock1->isLocked());
        $this->assertFalse($readLock2->isLocked());
        $this->assertFalse($readLock3->isLocked());
    }
    
    /**
     * 测试锁的边界情况
     */
    public function testEdgeCases()
    {
        $lock = $this->client->getReadWriteLock('test-edge-lock');
        
        // 测试空锁名
        try {
            $emptyLock = $this->client->getReadWriteLock('');
            $this->assertNotNull($emptyLock);
        } catch (\Exception $e) {
            $this->assertTrue(true); // 可能抛出异常，这是预期的
        }
        
        // 测试非常长的锁名
        $longName = str_repeat('a', 255);
        $longLock = $this->client->getReadWriteLock($longName);
        $this->assertNotNull($longLock);
        
        // 测试特殊字符锁名
        $specialLock = $this->client->getReadWriteLock('test-lock-@#$%');
        $this->assertNotNull($specialLock);
        
        // 测试重复获取和释放
        $readLock = $lock->readLock();
        $readLock->unlock(); // 释放未获取的锁
        $this->assertFalse($readLock->isLocked());
        
        $readLock->tryLock();
        $readLock->unlock();
        $readLock->unlock(); // 重复释放
        $this->assertFalse($readLock->isLocked());
    }
    
    /**
     * 测试锁的性能
     */
    public function testLockPerformance()
    {
        $lock = $this->client->getReadWriteLock('test-perf-lock');
        
        $startTime = microtime(true);
        
        // 执行多次锁操作
        for ($i = 0; $i < 100; $i++) {
            $readLock = $lock->readLock();
            $readLock->tryLock();
            $readLock->unlock();
        }
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // 验证性能在合理范围内
        $this->assertLessThan(10, $executionTime); // 100次操作应该在10秒内完成
    }
    
    /**
     * 测试锁的异常情况
     */
    public function testLockExceptions()
    {
        $lock = $this->client->getReadWriteLock('test-exception-lock');
        
        // 测试无效参数
        try {
            $readLock = $lock->readLock();
            $readLock->tryLock(-1, -1); // 无效的超时和租期
            $this->fail('应该抛出异常');
        } catch (\Exception $e) {
            $this->assertTrue(true); // 预期抛出异常
        }
        
        // 测试在已获取锁的情况下重复获取
        $writeLock = $lock->writeLock();
        $writeLock->tryLock();
        
        try {
            $writeLock->tryLock(); // 重复获取
            $this->fail('应该抛出异常或返回false');
        } catch (\Exception $e) {
            $this->assertTrue(true); // 预期行为
        }
        
        $writeLock->unlock();
    }
}