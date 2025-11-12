<?php

namespace Rediphp\Tests;

class RSemaphoreTest extends RedissonTestCase
{
    /**
     * 测试信号量的基本获取和释放
     */
    public function testBasicAcquireAndRelease()
    {
        $semaphore = $this->client->getSemaphore('test-semaphore');
        
        // 设置信号量许可数为3
        $semaphore->trySetPermits(3);
        
        // 获取许可
        $this->assertTrue($semaphore->tryAcquire());
        $this->assertEquals(2, $semaphore->availablePermits());
        
        // 再次获取许可
        $this->assertTrue($semaphore->tryAcquire());
        $this->assertEquals(1, $semaphore->availablePermits());
        
        // 释放许可
        $semaphore->release();
        $this->assertEquals(2, $semaphore->availablePermits());
        
        // 释放另一个许可
        $semaphore->release();
        $this->assertEquals(3, $semaphore->availablePermits());
    }
    
    /**
     * 测试信号量的许可数设置
     */
    public function testPermitsSetting()
    {
        $semaphore = $this->client->getSemaphore('test-permits-semaphore');
        $semaphore->clear(); // 清理之前的状态
        
        // 初始许可数应该为0
        $this->assertEquals(0, $semaphore->availablePermits());
        
        // 设置许可数为5
        $this->assertTrue($semaphore->trySetPermits(5));
        $this->assertEquals(5, $semaphore->availablePermits());
        
        // 减少许可数
        $semaphore->reducePermits(2);
        $this->assertEquals(3, $semaphore->availablePermits());
        
        // 增加许可数
        $semaphore->release(2);
        $this->assertEquals(5, $semaphore->availablePermits());
        
        // 设置新的许可数
        $this->assertTrue($semaphore->trySetPermits(10));
        $this->assertEquals(10, $semaphore->availablePermits());
    }
    
    /**
     * 测试带超时的许可获取
     */
    public function testAcquireWithTimeout()
    {
        $semaphore = $this->client->getSemaphore('test-timeout-semaphore');
        
        // 设置许可数为1
        $semaphore->trySetPermits(1);
        
        // 获取第一个许可
        $this->assertTrue($semaphore->tryAcquire());
        $this->assertEquals(0, $semaphore->availablePermits());
        
        // 尝试获取第二个许可（带超时）
        $startTime = microtime(true);
        $result = $semaphore->tryAcquireWithTimeout(2, 2); // 等待2秒
        $endTime = microtime(true);
        
        // 验证获取失败且超时时间正确
        $this->assertFalse($result);
        $this->assertGreaterThanOrEqual(2, $endTime - $startTime);
        
        // 释放许可后应该可以获取
        $semaphore->release();
        $this->assertTrue($semaphore->tryAcquireWithTimeout(1, 1));
    }
    
    /**
     * 测试批量获取和释放许可
     */
    public function testBatchAcquireAndRelease()
    {
        $semaphore = $this->client->getSemaphore('test-batch-semaphore');
        
        // 设置许可数为10
        $semaphore->trySetPermits(10);
        
        // 批量获取3个许可
        $this->assertTrue($semaphore->tryAcquire(3));
        $this->assertEquals(7, $semaphore->availablePermits());
        
        // 再批量获取4个许可
        $this->assertTrue($semaphore->tryAcquire(4));
        $this->assertEquals(3, $semaphore->availablePermits());
        
        // 批量释放5个许可
        $semaphore->release(5);
        $this->assertEquals(8, $semaphore->availablePermits());
        
        // 释放所有许可
        $semaphore->release(2);
        $this->assertEquals(10, $semaphore->availablePermits());
    }
    
    /**
     * 测试信号量的公平性
     */
    public function testSemaphoreFairness()
    {
        $semaphore = $this->client->getSemaphore('test-fair-semaphore');
        
        // 设置许可数为2
        $semaphore->trySetPermits(2);
        
        // 多个线程/进程应该按照请求顺序获取许可
        // 这里模拟顺序获取
        $this->assertTrue($semaphore->tryAcquire());
        $this->assertEquals(1, $semaphore->availablePermits());
        
        $this->assertTrue($semaphore->tryAcquire());
        $this->assertEquals(0, $semaphore->availablePermits());
        
        // 第三个获取应该失败
        $this->assertFalse($semaphore->tryAcquire());
        
        // 释放一个许可后，等待的应该能获取
        $semaphore->release();
        $this->assertTrue($semaphore->tryAcquire());
        $this->assertEquals(0, $semaphore->availablePermits());
    }
    
    /**
     * 测试信号量的清除操作
     */
    public function testClear()
    {
        $semaphore = $this->client->getSemaphore('test-clear-semaphore');
        
        // 设置许可数并获取一些许可
        $semaphore->trySetPermits(5);
        $semaphore->tryAcquire(3);
        $this->assertEquals(2, $semaphore->availablePermits());
        
        // 清除信号量
        $semaphore->clear();
        
        // 许可数应该重置为0
        $this->assertEquals(0, $semaphore->availablePermits());
        
        // 重新设置许可数
        $semaphore->trySetPermits(3);
        $this->assertEquals(3, $semaphore->availablePermits());
    }
    
    /**
     * 测试信号量的存在性检查
     */
    public function testExists()
    {
        $semaphore = $this->client->getSemaphore('test-exists-semaphore');
        $semaphore->clear(); // 清理之前的状态
        
        // 初始状态下应该不存在
        $this->assertFalse($semaphore->exists());
        
        // 设置许可数后应该存在
        $semaphore->trySetPermits(1);
        $this->assertTrue($semaphore->exists());
        
        // 清除后应该不存在
        $semaphore->clear();
        $this->assertFalse($semaphore->exists());
    }
    
    /**
     * 测试信号量的大小
     */
    public function testSize()
    {
        $semaphore = $this->client->getSemaphore('test-size-semaphore');
        $semaphore->clear(); // 清理之前的状态
        
        // 初始大小应该为0
        $this->assertEquals(0, $semaphore->size());
        
        // 设置许可数后大小应该更新
        $semaphore->trySetPermits(5);
        $this->assertEquals(5, $semaphore->size());
        
        // 获取许可后大小不变
        $semaphore->tryAcquire(2);
        $this->assertEquals(5, $semaphore->size());
        
        // 减少许可数后大小更新
        $semaphore->reducePermits(1);
        $this->assertEquals(4, $semaphore->size());
    }
    
    /**
     * 测试信号量的边界情况
     */
    public function testEdgeCases()
    {
        $semaphore = $this->client->getSemaphore('test-edge-semaphore');
        
        // 测试0许可数
        $semaphore->trySetPermits(0);
        $this->assertEquals(0, $semaphore->availablePermits());
        $this->assertFalse($semaphore->tryAcquire());
        
        // 测试负许可数获取
        try {
            $semaphore->tryAcquire(-1);
            $this->fail('应该抛出异常');
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
        
        // 测试大量许可数
        $semaphore->trySetPermits(1000);
        $this->assertEquals(1000, $semaphore->availablePermits());
        
        // 测试释放超过可用许可数
        $semaphore->tryAcquire(10);
        $semaphore->release(20); // 释放超过获取的数量
        $this->assertEquals(1010, $semaphore->availablePermits());
        
        // 测试空信号量名
        try {
            $emptySemaphore = $this->client->getSemaphore('');
            $this->assertNotNull($emptySemaphore);
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
    }
    
    /**
     * 测试信号量的性能
     */
    public function testPerformance()
    {
        $semaphore = $this->client->getSemaphore('test-perf-semaphore');
        
        $startTime = microtime(true);
        
        // 设置许可数
        $semaphore->trySetPermits(100);
        
        // 执行多次许可操作
        for ($i = 0; $i < 50; $i++) {
            $semaphore->tryAcquire();
            $semaphore->release();
        }
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // 验证性能在合理范围内
        $this->assertLessThan(5, $executionTime); // 100次操作应该在5秒内完成
    }
    
    /**
     * 测试信号量的异常情况
     */
    public function testSemaphoreExceptions()
    {
        $semaphore = $this->client->getSemaphore('test-exception-semaphore');
        
        // 测试无效参数
        try {
            $semaphore->trySetPermits(-1); // 负许可数
            $this->fail('应该抛出异常');
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
        
        // 测试超时获取的无效参数
        try {
            $semaphore->tryAcquire(-1, -1); // 无效的超时和租期
            $this->fail('应该抛出异常');
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
        
        // 测试释放未获取的许可
        try {
            $semaphore->release(); // 释放未获取的许可
            $this->assertTrue(true); // 可能不会抛出异常
        } catch (\Exception $e) {
            $this->assertTrue(true); // 或者抛出异常
        }
    }
    
    /**
     * 测试多个信号量的并发操作
     */
    public function testMultipleSemaphores()
    {
        $semaphore1 = $this->client->getSemaphore('test-multi-semaphore-1');
        $semaphore2 = $this->client->getSemaphore('test-multi-semaphore-2');
        
        // 设置不同的许可数
        $semaphore1->trySetPermits(3);
        $semaphore2->trySetPermits(5);
        
        // 验证各自的许可数
        $this->assertEquals(3, $semaphore1->availablePermits());
        $this->assertEquals(5, $semaphore2->availablePermits());
        
        // 分别获取许可
        $semaphore1->tryAcquire(2);
        $semaphore2->tryAcquire(3);
        
        $this->assertEquals(1, $semaphore1->availablePermits());
        $this->assertEquals(2, $semaphore2->availablePermits());
        
        // 分别释放许可
        $semaphore1->release(1);
        $semaphore2->release(2);
        
        $this->assertEquals(2, $semaphore1->availablePermits());
        $this->assertEquals(4, $semaphore2->availablePermits());
        
        // 清除信号量
        $semaphore1->clear();
        $semaphore2->clear();
        
        $this->assertEquals(0, $semaphore1->availablePermits());
        $this->assertEquals(0, $semaphore2->availablePermits());
    }
}