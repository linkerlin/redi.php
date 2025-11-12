<?php

namespace Rediphp\Tests;

class RCountDownLatchTest extends RedissonTestCase
{
    
    public function testCountDownAndGetCount(): void
    {
        $latch = $this->client->getCountDownLatch('test:latch:count');
        $latch->delete();
        
        // Initialize with count
        $latch->trySetCount(5);
        $this->assertEquals(5, $latch->getCount());
        
        // Count down
        $latch->countDown();
        $this->assertEquals(4, $latch->getCount());
        
        $latch->countDown();
        $this->assertEquals(3, $latch->getCount());
        
        // Count down multiple times (call countDown multiple times)
        $latch->countDown();
        $latch->countDown();
        $this->assertEquals(1, $latch->getCount());
        
        // Final count down
        $latch->countDown();
        $this->assertEquals(0, $latch->getCount());
    }
    
    public function testTrySetCount(): void
    {
        $latch = $this->client->getCountDownLatch('test:latch:tryset');
        $latch->delete();
        
        // Set count on empty latch
        $this->assertTrue($latch->trySetCount(3));
        $this->assertEquals(3, $latch->getCount());
        
        // Try to set count again (should fail)
        $this->assertFalse($latch->trySetCount(5));
        $this->assertEquals(3, $latch->getCount());
        
        // Delete and try again
        $latch->delete();
        $this->assertTrue($latch->trySetCount(10));
        $this->assertEquals(10, $latch->getCount());
    }
    
    public function testAwait(): void
    {
        $latch = $this->client->getCountDownLatch('test:latch:await');
        $latch->delete();
        
        // Set count to 1
        $latch->trySetCount(1);
        
        // Count down to zero
        $latch->countDown();
        
        // Await should return immediately since count is zero
        $this->assertTrue($latch->await(1)); // 1 second timeout
    }
    
    public function testSizeAndClear(): void
    {
        $latch = $this->client->getCountDownLatch('test:latch:size');
        $latch->delete();
        
        // Test initial state
        $this->assertEquals(0, $latch->getCount());
        
        // Set count and test
        $latch->trySetCount(5);
        $this->assertEquals(5, $latch->getCount());
        
        // Delete and test
        $latch->delete();
        $this->assertEquals(0, $latch->getCount());
    }
    
    public function testExists(): void
    {
        $latch = $this->client->getCountDownLatch('test:latch:exists');
        $latch->delete();
        
        // Test non-existent
        $this->assertEquals(0, $latch->getCount());
        
        // Test exists after set count
        $latch->trySetCount(3);
        $this->assertEquals(3, $latch->getCount());
        
        // Test exists after delete
        $latch->delete();
        $this->assertEquals(0, $latch->getCount());
    }
    
    public function testMultipleLatchOperations(): void
    {
        $latch1 = $this->client->getCountDownLatch('test:latch:multi1');
        $latch2 = $this->client->getCountDownLatch('test:latch:multi2');
        
        $latch1->delete();
        $latch2->delete();
        
        // Set different counts
        $latch1->trySetCount(3);
        $latch2->trySetCount(2);
        
        $this->assertEquals(3, $latch1->getCount());
        $this->assertEquals(2, $latch2->getCount());
        
        // Count down latch1
        $latch1->countDown();
        $this->assertEquals(2, $latch1->getCount());
        $this->assertEquals(2, $latch2->getCount());
        
        // Count down latch2 to zero (call countDown twice)
        $latch2->countDown();
        $latch2->countDown();
        $this->assertEquals(2, $latch1->getCount());
        $this->assertEquals(0, $latch2->getCount());
        
        // Test await on latch2 (should succeed immediately)
        $this->assertTrue($latch2->await(1));
    }
}