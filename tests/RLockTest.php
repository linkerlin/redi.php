<?php

namespace Rediphp\Tests;

use PHPUnit\Framework\TestCase;
use Rediphp\RedissonClient;

class RLockTest extends TestCase
{
    private RedissonClient $client;
    
    protected function setUp(): void
    {
        $this->client = new RedissonClient([
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => (int)(getenv('REDIS_PORT') ?: 6379),
        ]);
        
        if (!@$this->client->connect()) {
            $this->markTestSkipped('Redis server not available');
        }
    }
    
    protected function tearDown(): void
    {
        if (isset($this->client)) {
            $this->client->shutdown();
        }
    }
    
    public function testLockAndUnlock(): void
    {
        $lock = $this->client->getLock('test:lock:basic');
        $lock->forceUnlock(); // Clean up
        
        $result = $lock->tryLock(0, 5000);
        $this->assertTrue($result);
        $this->assertTrue($lock->isLocked());
        $this->assertTrue($lock->isHeldByCurrentThread());
        
        $unlocked = $lock->unlock();
        $this->assertTrue($unlocked);
        $this->assertFalse($lock->isLocked());
    }
    
    public function testTryLockFails(): void
    {
        $lock1 = $this->client->getLock('test:lock:conflict');
        $lock1->forceUnlock(); // Clean up
        
        // First lock acquires
        $result1 = $lock1->tryLock(0, 5000);
        $this->assertTrue($result1);
        
        // Second lock should fail immediately
        $lock2 = $this->client->getLock('test:lock:conflict');
        $result2 = $lock2->tryLock(0, 5000);
        $this->assertFalse($result2);
        
        // Clean up
        $lock1->unlock();
    }
    
    public function testForceUnlock(): void
    {
        $lock = $this->client->getLock('test:lock:force');
        $lock->forceUnlock(); // Clean up
        
        $lock->tryLock(0, 5000);
        $this->assertTrue($lock->isLocked());
        
        $result = $lock->forceUnlock();
        $this->assertTrue($result);
        $this->assertFalse($lock->isLocked());
    }
}
