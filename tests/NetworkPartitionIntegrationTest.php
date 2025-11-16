<?php

namespace Rediphp\Tests;

use Rediphp\RMap;
use Rediphp\RList;
use Rediphp\RSet;
use Rediphp\RLock;
use Rediphp\RAtomicLong;
use Rediphp\RBucket;

/**
 * 网络分区和连接故障集成测试
 * 测试网络中断、连接超时、Redis服务器故障等异常情况下的系统表现
 */
class NetworkPartitionIntegrationTest extends RedissonTestCase
{
    /**
     * 测试网络连接中断的恢复能力
     */
    public function testNetworkConnectionRecovery()
    {
        $recoveryMap = $this->client->getMap('network:recovery:map');
        $recoveryCounter = $this->client->getAtomicLong('network:recovery:counter');
        
        // 初始写入数据
        for ($i = 0; $i < 20; $i++) {
            $recoveryMap->put("recovery:key:$i", "recovery:value:$i");
        }
        $recoveryCounter->set(20);
        
        // 验证初始状态
        $this->assertEquals(20, $recoveryMap->size());
        $this->assertEquals(20, $recoveryCounter->get());
        
        // 模拟网络连接中断（通过重启客户端）
        try {
            $this->client->shutdown();
            // 尝试连接应该抛出异常
            $redis = $this->client->getRedis();
            $redis->ping();
            $this->fail("连接中断后应该无法执行命令");
        } catch (\Exception $e) {
            $this->assertNotNull($e);
        }
        
        // 重新连接
        $this->client->connect();
        
        // 验证数据持久性
        $recoveredMap = $this->client->getMap('network:recovery:map');
        $recoveredCounter = $this->client->getAtomicLong('network:recovery:counter');
        
        $this->assertEquals(20, $recoveredMap->size());
        $this->assertEquals(20, $recoveredCounter->get());
        
        // 验证数据完整性
        for ($i = 0; $i < 20; $i++) {
            $value = $recoveredMap->get("recovery:key:$i");
            $this->assertEquals("recovery:value:$i", $value);
        }
        
        // 验证系统继续正常工作
        $recoveredMap->put("new:key", "new:value");
        $recoveredCounter->incrementAndGet();
        
        $this->assertEquals(21, $recoveredMap->size());
        $this->assertEquals(21, $recoveredCounter->get());
        
        // 清理
        $recoveredMap->clear();
        $recoveredCounter->delete();
    }
    
    /**
     * 测试连接池在网络故障时的行为
     */
    public function testConnectionPoolNetworkFailure()
    {
        $poolMap = $this->client->getMap('network:pool:map');
        $poolCounter = $this->client->getAtomicLong('network:pool:counter');
        
        // 测试连接池的基本操作
        for ($i = 0; $i < 10; $i++) {
            $poolMap->put("pool:key:$i", "pool:value:$i");
        }
        $poolCounter->set(10);
        
        // 模拟网络故障
        $this->client->shutdown();
        
        // 尝试操作应该失败
        try {
            $poolMap->get("pool:key:0");
            $this->fail("网络故障时操作应该失败");
        } catch (\Exception $e) {
            $this->assertNotNull($e);
        }
        
        // 重新连接并验证数据恢复
        $this->client->connect();
        
        $recoveredPoolMap = $this->client->getMap('network:pool:map');
        $recoveredPoolCounter = $this->client->getAtomicLong('network:pool:counter');
        
        $this->assertEquals(10, $recoveredPoolMap->size());
        $this->assertEquals(10, $recoveredPoolCounter->get());
        
        // 测试连接池恢复正常工作
        $recoveredPoolMap->put("post:recovery:key", "post:recovery:value");
        $recoveredPoolCounter->incrementAndGet();
        
        $this->assertEquals(11, $recoveredPoolMap->size());
        $this->assertEquals(11, $recoveredPoolCounter->get());
        
        // 清理
        $recoveredPoolMap->clear();
        $recoveredPoolCounter->delete();
    }
    
    /**
     * 测试分布式锁在网络故障时的行为
     */
    public function testDistributedLockNetworkFailure()
    {
        $lockName = 'network:lock:test';
        $lockCounter = $this->client->getAtomicLong('network:lock:counter');
        $lock = $this->client->getLock($lockName);
        
        // 测试锁的基本操作
        $this->assertTrue($lock->tryLock());
        $lockCounter->set(1);
        $this->assertTrue($lock->isLocked());
        $lock->unlock();
        
        $this->assertFalse($lock->isLocked());
        
        // 模拟网络故障
        $this->client->shutdown();
        
        // 网络故障时tryLock应该返回false而不是抛出异常
        $canLock = $lock->tryLock();
        $this->assertFalse($canLock, "网络故障时获取锁应该失败");
        
        // 重新连接
        $this->client->connect();
        
        $recoveredLock = $this->client->getLock($lockName);
        $recoveredCounter = $this->client->getAtomicLong('network:lock:counter');
        
        // 验证锁状态应该已释放
        $this->assertFalse($recoveredLock->isLocked());
        
        // 验证其他数据仍然存在
        $this->assertEquals(1, $recoveredCounter->get());
        
        // 测试锁恢复正常
        $this->assertTrue($recoveredLock->tryLock());
        $recoveredCounter->incrementAndGet();
        $this->assertTrue($recoveredLock->isLocked());
        $recoveredLock->unlock();
        
        $this->assertEquals(2, $recoveredCounter->get());
        $this->assertFalse($recoveredLock->isLocked());
        
        // 清理
        $recoveredCounter->delete();
    }
    
    /**
     * 测试超时连接的处理
     */
    public function testConnectionTimeoutHandling()
    {
        $timeoutMap = $this->client->getMap('network:timeout:map');
        $timeoutList = $this->client->getList('network:timeout:list');
        
        // 测试正常操作
        for ($i = 0; $i < 5; $i++) {
            $timeoutMap->put("timeout:key:$i", "timeout:value:$i");
            $timeoutList->add("timeout:item:$i");
        }
        
        $this->assertEquals(5, $timeoutMap->size());
        $this->assertEquals(5, $timeoutList->size());
        
        // 模拟长时间阻塞操作（通过占用锁）
        $blockingLock = $this->client->getLock('network:block:lock');
        
        $this->assertTrue($blockingLock->tryLock());
        
        // 尝试获取已锁定的资源
        $startTime = microtime(true);
        $acquired = $blockingLock->tryLock(1); // 1秒超时
        $endTime = microtime(true);
        
        $duration = ($endTime - $startTime) * 1000;
        
        // 应该超时返回false
        $this->assertFalse($acquired);
        $this->assertGreaterThan(800, $duration); // 至少800ms，给个容差
        
        $blockingLock->unlock();
        
        // 验证其他数据结构正常
        $this->assertEquals(5, $timeoutMap->size());
        $this->assertEquals(5, $timeoutList->size());
        
        // 清理
        $timeoutMap->clear();
        $timeoutList->clear();
    }
    
    /**
     * 测试并发网络中断的情况
     */
    public function testConcurrentNetworkDisruption()
    {
        $concurrentMap = $this->client->getMap('network:concurrent:map');
        $concurrentLock = $this->client->getLock('network:concurrent:lock');
        $concurrentCounter = $this->client->getAtomicLong('network:concurrent:counter');
        
        $operations = 15;
        
        for ($i = 0; $i < $operations; $i++) {
            if ($concurrentLock->tryLock()) {
                try {
                    $concurrentMap->put("concurrent:key:$i", "concurrent:value:$i");
                    $concurrentCounter->incrementAndGet();
                    
                    // 模拟随机网络中断
                    if ($i % 5 == 0 && $i > 0) {
                        // 轻微的网络延迟
                        usleep(1000);
                    }
                } finally {
                    $concurrentLock->unlock();
                }
            }
        }
        
        // 验证操作结果
        $this->assertEquals($operations, $concurrentMap->size());
        $this->assertEquals($operations, $concurrentCounter->get());
        
        // 模拟网络中断和恢复
        $this->client->shutdown();
        $this->client->connect();
        
        // 验证数据持久性
        $recoveredMap = $this->client->getMap('network:concurrent:map');
        $recoveredCounter = $this->client->getAtomicLong('network:concurrent:counter');
        
        $this->assertEquals($operations, $recoveredMap->size());
        $this->assertEquals($operations, $recoveredCounter->get());
        
        // 测试恢复后的继续操作
        $recoveredMap->put("post:disruption:key", "post:disruption:value");
        $recoveredCounter->incrementAndGet();
        
        $this->assertEquals($operations + 1, $recoveredMap->size());
        $this->assertEquals($operations + 1, $recoveredCounter->get());
        
        // 清理
        $recoveredMap->clear();
        $recoveredCounter->delete();
    }
    
    /**
     * 测试数据完整性在网络故障后的验证
     */
    public function testDataIntegrityAfterNetworkFailure()
    {
        $integrityMap = $this->client->getMap('network:integrity:map');
        $integritySet = $this->client->getSet('network:integrity:set');
        $integrityCounter = $this->client->getAtomicLong('network:integrity:counter');
        
        // 创建复杂的关联数据
        $testData = [];
        for ($i = 0; $i < 25; $i++) {
            $data = [
                'id' => $i,
                'name' => "user_$i",
                'email' => "user$i@example.com",
                'timestamp' => time(),
                'metadata' => json_encode(['level' => $i % 5, 'active' => true])
            ];
            
            $integrityMap->put("user:$i", $data);
            $integritySet->add("user:$i");
            $testData[] = $data;
        }
        $integrityCounter->set(25);
        
        // 验证初始状态
        $this->assertEquals(25, $integrityMap->size());
        $this->assertEquals(25, $integritySet->size());
        $this->assertEquals(25, $integrityCounter->get());
        
        // 模拟网络故障
        $this->client->shutdown();
        $this->client->connect();
        
        // 验证数据完整性
        $recoveredMap = $this->client->getMap('network:integrity:map');
        $recoveredSet = $this->client->getSet('network:integrity:set');
        $recoveredCounter = $this->client->getAtomicLong('network:integrity:counter');
        
        $this->assertEquals(25, $recoveredMap->size());
        $this->assertEquals(25, $recoveredSet->size());
        $this->assertEquals(25, $recoveredCounter->get());
        
        // 验证每个数据项的完整性
        foreach ($testData as $expectedData) {
            $userId = $expectedData['id'];
            $actualData = $recoveredMap->get("user:$userId");
            
            $this->assertNotNull($actualData);
            $this->assertEquals($expectedData['name'], $actualData['name']);
            $this->assertEquals($expectedData['email'], $actualData['email']);
            $metadata = json_decode($actualData['metadata'], true);
            $this->assertTrue($metadata['active']);
            
            // 验证集合中也存在
            $this->assertTrue($recoveredSet->contains("user:$userId"));
        }
        
        // 测试数据验证函数
        $allUsers = $recoveredMap->keySet();
        $validUsers = 0;
        
        foreach ($allUsers as $userKey) {
            $userData = $recoveredMap->get($userKey);
            if ($userData && isset($userData['email']) && strpos($userData['email'], '@') !== false) {
                $validUsers++;
            }
        }
        
        $this->assertEquals(25, $validUsers);
        
        // 清理
        $recoveredMap->clear();
        $recoveredSet->clear();
        $recoveredCounter->delete();
    }
    
    /**
     * 测试网络重连后的会话恢复
     */
    public function testSessionRecoveryAfterReconnection()
    {
        $sessionMap = $this->client->getMap('network:session:map');
        $sessionLock = $this->client->getLock('network:session:lock');
        $sessionCounter = $this->client->getAtomicLong('network:session:counter');
        
        // 创建会话状态
        $sessionId = uniqid('session_');
        $sessionData = [
            'user_id' => 12345,
            'login_time' => time(),
            'last_activity' => time(),
            'permissions' => ['read', 'write', 'admin']
        ];
        
        $sessionMap->put("session:$sessionId", $sessionData);
        $sessionCounter->set(1);
        
        // 获取会话锁
        $this->assertTrue($sessionLock->tryLock());
        $sessionCounter->incrementAndGet();
        $sessionLock->unlock();
        
        // 验证会话创建
        $this->assertTrue($sessionMap->containsKey("session:$sessionId"));
        $this->assertEquals(1, $sessionCounter->get());
        
        // 模拟网络中断
        $this->client->shutdown();
        $this->client->connect();
        
        // 验证会话恢复
        $recoveredSession = $this->client->getMap('network:session:map');
        $recoveredLock = $this->client->getLock('network:session:lock');
        $recoveredCounter = $this->client->getAtomicLong('network:session:counter');
        
        $this->assertTrue($recoveredSession->containsKey("session:$sessionId"));
        $this->assertEquals(1, $recoveredCounter->get());
        
        // 验证会话数据完整性
        $recoveredData = $recoveredSession->get("session:$sessionId");
        $this->assertEquals(12345, $recoveredData['user_id']);
        $this->assertEquals(['read', 'write', 'admin'], $recoveredData['permissions']);
        
        // 测试会话继续使用
        $recoveredData['last_activity'] = time();
        $recoveredSession->put("session:$sessionId", $recoveredData);
        $recoveredCounter->incrementAndGet();
        
        $this->assertEquals(2, $recoveredCounter->get());
        
        // 验证锁机制仍然正常工作
        $this->assertTrue($recoveredLock->tryLock());
        $recoveredLock->unlock();
        
        // 清理
        $recoveredSession->clear();
        $recoveredCounter->delete();
    }
}