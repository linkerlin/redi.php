<?php

namespace Rediphp\Tests;

class RLockTest extends RedissonTestCase
{
    
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
