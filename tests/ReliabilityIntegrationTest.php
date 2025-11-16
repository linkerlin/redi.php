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
 * 可靠性集成测试 - 测试系统的稳定性和容错能力
 * 测试redi.php在异常情况下的表现和恢复能力
 */
class ReliabilityIntegrationTest extends RedissonTestCase
{
    /**
     * 测试数据持久性
     */
    public function testDataPersistence()
    {
        $persistenceMap = $this->client->getMap('reliability:persistence:map');
        $persistenceList = $this->client->getList('reliability:persistence:list');
        $persistenceCounter = $this->client->getAtomicLong('reliability:persistence:counter');
        
        // 写入数据
        $testData = [];
        for ($i = 0; $i < 50; $i++) {
            $data = [
                'id' => $i,
                'value' => "persistent_value_$i",
                'timestamp' => time()
            ];
            $persistenceMap->put("persistent:key:$i", $data);
            $persistenceList->add("persistent_item_$i");
            $testData[] = $data;
        }
        
        $persistenceCounter->set(50);
        
        // 验证数据持久性
        $this->assertEquals(50, $persistenceMap->size());
        $this->assertEquals(50, $persistenceList->size());
        $this->assertEquals(50, $persistenceCounter->get());
        
        // 重新连接后验证数据仍然存在
        $this->client->shutdown();
        $this->client->connect();
        
        $newMap = $this->client->getMap('reliability:persistence:map');
        $newList = $this->client->getList('reliability:persistence:list');
        $newCounter = $this->client->getAtomicLong('reliability:persistence:counter');
        
        $this->assertEquals(50, $newMap->size());
        $this->assertEquals(50, $newList->size());
        $this->assertEquals(50, $newCounter->get());
        
        // 验证数据完整性
        foreach ($testData as $i => $expectedData) {
            $actualData = $newMap->get("persistent:key:$i");
            $this->assertEquals($expectedData, $actualData);
        }
        
        // 清理
        $newMap->clear();
        $newList->clear();
        $newCounter->delete();
    }
    
    /**
     * 测试锁的可靠性
     */
    public function testLockReliability()
    {
        $reliabilityLock = $this->client->getLock('reliability:lock');
        $lockCounter = $this->client->getAtomicLong('reliability:lock:counter');
        
        // 测试锁的获取和释放
        for ($i = 0; $i < 20; $i++) {
            $this->assertTrue($reliabilityLock->tryLock());
            $this->assertTrue($reliabilityLock->isLocked());
            
            $lockCounter->incrementAndGet();
            
            $this->assertTrue($reliabilityLock->unlock());
            $this->assertFalse($reliabilityLock->isLocked());
        }
        
        // 验证锁计数器
        $this->assertEquals(20, $lockCounter->get());
        
        // 测试锁的强制解锁
        $this->assertTrue($reliabilityLock->tryLock());
        $this->assertTrue($reliabilityLock->isLocked());
        
        // 强制解锁
        $this->assertTrue($reliabilityLock->forceUnlock());
        $this->assertFalse($reliabilityLock->isLocked());
        
        // 可以重新获取锁
        $this->assertTrue($reliabilityLock->tryLock());
        $this->assertTrue($reliabilityLock->unlock());
        
        // 清理
        $lockCounter->delete();
    }
    
    /**
     * 测试信号量的可靠性
     */
    public function testSemaphoreReliability()
    {
        $reliabilitySemaphore = $this->client->getSemaphore('reliability:semaphore', 5);
        
        // 初始化信号量
        $this->assertTrue($reliabilitySemaphore->trySetPermits(5));
        
        // 测试信号量的获取和释放
        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue($reliabilitySemaphore->tryAcquire());
            $this->assertEquals(4 - $i, $reliabilitySemaphore->availablePermits());
        }
        
        // 信号量应该已经耗尽
        $this->assertEquals(0, $reliabilitySemaphore->availablePermits());
        $this->assertFalse($reliabilitySemaphore->tryAcquire());
        
        // 释放信号量
        for ($i = 0; $i < 5; $i++) {
            $reliabilitySemaphore->release();
            $this->assertEquals($i + 1, $reliabilitySemaphore->availablePermits());
        }
        
        // 验证信号量恢复
        $this->assertEquals(5, $reliabilitySemaphore->availablePermits());
        
        // 清理
        $reliabilitySemaphore->clear();
    }
    
    /**
     * 测试计数器的可靠性
     */
    public function testCounterReliability()
    {
        $reliabilityCounter = $this->client->getAtomicLong('reliability:counter');
        
        // 测试计数器的基本操作
        $reliabilityCounter->set(0);
        
        for ($i = 1; $i <= 100; $i++) {
            $this->assertEquals($i, $reliabilityCounter->incrementAndGet());
        }
        
        for ($i = 99; $i >= 0; $i--) {
            $this->assertEquals($i, $reliabilityCounter->decrementAndGet());
        }
        
        // 测试原子性
        $reliabilityCounter->set(50);
        $this->assertEquals(50, $reliabilityCounter->get());
        
        $oldValue = $reliabilityCounter->getAndSet(100);
        $this->assertEquals(50, $oldValue);
        $this->assertEquals(100, $reliabilityCounter->get());
        
        // 测试比较并设置
        $this->assertTrue($reliabilityCounter->compareAndSet(100, 200));
        $this->assertEquals(200, $reliabilityCounter->get());
        
        $this->assertFalse($reliabilityCounter->compareAndSet(100, 300)); // 应该失败
        $this->assertEquals(200, $reliabilityCounter->get()); // 值应该不变
        
        // 清理
        $reliabilityCounter->delete();
    }
    
    /**
     * 测试数据结构的容错能力
     */
    public function testDataStructureFaultTolerance()
    {
        $faultMap = $this->client->getMap('reliability:fault:map');
        $faultList = $this->client->getList('reliability:fault:list');
        $faultSet = $this->client->getSet('reliability:fault:set');
        
        // 测试空操作
        $this->assertNull($faultMap->get('nonexistent:key'));
        $this->assertNull($faultList->get(999));
        $this->assertFalse($faultSet->contains('nonexistent:member'));
        
        // 测试边界条件
        $faultList->add('item');
        $this->assertNull($faultList->get(-1)); // 负索引
        $this->assertNull($faultList->get(1000)); // 超出范围
        
        // 测试重复操作
        $this->assertTrue($faultSet->add('unique:member'));
        $this->assertFalse($faultSet->add('unique:member')); // 重复添加应该失败
        
        // 测试删除不存在的元素
        $this->assertFalse($faultMap->remove('nonexistent:key'));
        $this->assertFalse($faultList->remove('nonexistent:item'));
        $this->assertFalse($faultSet->remove('nonexistent:member'));
        
        // 清理
        $faultMap->clear();
        $faultList->clear();
        $faultSet->clear();
    }
    
    /**
     * 测试并发操作的可靠性
     */
    public function testConcurrentReliability()
    {
        $concurrentMap = $this->client->getMap('reliability:concurrent:map');
        $concurrentLock = $this->client->getLock('reliability:concurrent:lock');
        $concurrentCounter = $this->client->getAtomicLong('reliability:concurrent:counter');
        
        // 模拟并发场景
        $concurrentOperations = 30;
        
        for ($i = 0; $i < $concurrentOperations; $i++) {
            if ($concurrentLock->tryLock()) {
                try {
                    // 原子操作
                    $currentValue = $concurrentCounter->get();
                    $concurrentMap->put("concurrent:key:$i", $currentValue);
                    $concurrentCounter->incrementAndGet();
                } finally {
                    $concurrentLock->unlock();
                }
            }
        }
        
        // 验证并发操作的可靠性
        $this->assertEquals($concurrentOperations, $concurrentCounter->get());
        $this->assertEquals($concurrentOperations, $concurrentMap->size());
        
        // 验证数据一致性
        $values = $concurrentMap->values();
        sort($values);
        
        for ($i = 0; $i < $concurrentOperations; $i++) {
            $this->assertEquals($i, $values[$i]);
        }
        
        // 清理
        $concurrentMap->clear();
        $concurrentCounter->delete();
    }
    
    /**
     * 测试数据恢复的可靠性
     */
    public function testDataRecovery()
    {
        $recoveryMap = $this->client->getMap('reliability:recovery:map');
        $recoveryList = $this->client->getList('reliability:recovery:list');
        
        // 准备恢复测试数据
        $testData = [];
        for ($i = 0; $i < 30; $i++) {
            $data = [
                'id' => $i,
                'value' => "recovery_value_$i",
                'timestamp' => time()
            ];
            $recoveryMap->put("recovery:key:$i", $data);
            $recoveryList->add("recovery_item_$i");
            $testData[] = $data;
        }
        
        // 模拟部分数据丢失
        $recoveryMap->remove('recovery:key:5');
        $recoveryMap->remove('recovery:key:10');
        $recoveryMap->remove('recovery:key:15');
        
        $recoveryList->remove('recovery_item_3');
        $recoveryList->remove('recovery_item_7');
        
        // 验证数据恢复能力
        $this->assertEquals(27, $recoveryMap->size()); // 30 - 3 = 27
        $this->assertEquals(28, $recoveryList->size()); // 30 - 2 = 28
        
        // 验证剩余数据的完整性
        $remainingKeys = $recoveryMap->keySet();
        $this->assertNotContains('recovery:key:5', $remainingKeys);
        $this->assertNotContains('recovery:key:10', $remainingKeys);
        $this->assertNotContains('recovery:key:15', $remainingKeys);
        
        // 验证未删除的数据仍然存在
        $this->assertTrue($recoveryMap->containsKey('recovery:key:0'));
        $this->assertTrue($recoveryMap->containsKey('recovery:key:29'));
        
        // 清理
        $recoveryMap->clear();
        $recoveryList->clear();
    }
    
    /**
     * 测试布隆过滤器的可靠性
     */
    public function testBloomFilterReliability()
    {
        $reliabilityBloom = $this->client->getBloomFilter('reliability:bloom', 1000, 0.01);
        
        // 初始化布隆过滤器
        $reliabilityBloom->clear();
        
        // 添加元素
        $testElements = [];
        for ($i = 0; $i < 100; $i++) {
            $element = "bloom_element_$i";
            $reliabilityBloom->add($element);
            $testElements[] = $element;
        }
        
        // 验证已添加元素的存在性
        foreach ($testElements as $element) {
            $this->assertTrue($reliabilityBloom->contains($element));
        }
        
        // 验证未添加元素的不存在性（可能有误报，但概率很低）
        $falsePositives = 0;
        for ($i = 100; $i < 200; $i++) {
            $element = "bloom_element_$i";
            if ($reliabilityBloom->contains($element)) {
                $falsePositives++;
            }
        }
        
        // 误报率应该在可接受范围内
        $falsePositiveRate = $falsePositives / 100;
        $this->assertLessThan(0.05, $falsePositiveRate); // 误报率应该小于5%
        
        // 清理
        $reliabilityBloom->delete();
    }
    
    /**
     * 测试长时间运行的可靠性
     */
    public function testLongRunningReliability()
    {
        $longRunningMap = $this->client->getMap('reliability:longrunning:map');
        $longRunningCounter = $this->client->getAtomicLong('reliability:longrunning:counter');
        
        $iterations = 200;
        
        // 长时间运行测试
        for ($i = 0; $i < $iterations; $i++) {
            $longRunningMap->put("long:key:$i", "long:value:$i");
            $longRunningCounter->incrementAndGet();
            
            // 定期验证
            if ($i % 50 == 0) {
                $this->assertEquals($i + 1, $longRunningMap->size());
                $this->assertEquals($i + 1, $longRunningCounter->get());
            }
        }
        
        // 最终验证
        $this->assertEquals($iterations, $longRunningMap->size());
        $this->assertEquals($iterations, $longRunningCounter->get());
        
        // 验证数据完整性
        for ($i = 0; $i < $iterations; $i++) {
            $value = $longRunningMap->get("long:key:$i");
            $this->assertEquals("long:value:$i", $value);
        }
        
        // 清理
        $longRunningMap->clear();
        $longRunningCounter->delete();
    }
}