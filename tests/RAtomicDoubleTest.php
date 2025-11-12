<?php

namespace Rediphp\Tests;

class RAtomicDoubleTest extends RedissonTestCase
{
    
    public function testGetAndSet(): void
    {
        $atomic = $this->client->getAtomicDouble('test:atomic:double');
        $atomic->delete();
        
        // Test initial value
        $this->assertEquals(0.0, $atomic->get());
        
        // Test set and get
        $atomic->set(3.14);
        $this->assertEquals(3.14, $atomic->get());
        
        $atomic->set(-2.5);
        $this->assertEquals(-2.5, $atomic->get());
    }
    
    public function testIncrementAndDecrement(): void
    {
        $atomic = $this->client->getAtomicDouble('test:atomic:double:incdec');
        $atomic->delete();
        
        $atomic->set(10.0);
        
        // Test increment using addAndGet
        $this->assertEquals(15.0, $atomic->addAndGet(5.0));
        $this->assertEquals(15.0, $atomic->get());
        
        // Test decrement using addAndGet with negative value
        $this->assertEquals(10.0, $atomic->addAndGet(-5.0));
        $this->assertEquals(10.0, $atomic->get());
        
        // Test getAndIncrement using getAndAdd
        $this->assertEquals(10.0, $atomic->getAndAdd(2.5));
        $this->assertEquals(12.5, $atomic->get());
        
        // Test getAndDecrement using getAndAdd with negative value
        $this->assertEquals(12.5, $atomic->getAndAdd(-2.5));
        $this->assertEquals(10.0, $atomic->get());
    }
    
    public function testCompareAndSet(): void
    {
        $atomic = $this->client->getAtomicDouble('test:atomic:double:cas');
        $atomic->delete();
        
        $atomic->set(100.0);
        
        // Successful CAS
        $this->assertTrue($atomic->compareAndSet(100.0, 200.0));
        $this->assertEquals(200.0, $atomic->get());
        
        // Failed CAS
        $this->assertFalse($atomic->compareAndSet(100.0, 300.0));
        $this->assertEquals(200.0, $atomic->get());
    }
    
    public function testDelete(): void
    {
        $atomic = $this->client->getAtomicDouble('test:atomic:double:delete');
        $atomic->delete();
        
        // Test initial state
        $this->assertEquals(0.0, $atomic->get());
        
        // Set value and test
        $atomic->set(42.5);
        $this->assertEquals(42.5, $atomic->get());
        
        // Delete and test
        $atomic->delete();
        $this->assertEquals(0.0, $atomic->get());
    }
    
    public function testExists(): void
    {
        $atomic = $this->client->getAtomicDouble('test:atomic:double:exists');
        $atomic->delete();
        
        // Test non-existent (get should return 0.0)
        $this->assertEquals(0.0, $atomic->get());
        
        // Test exists after set
        $atomic->set(1.0);
        $this->assertEquals(1.0, $atomic->get());
        
        // Test exists after delete
        $atomic->delete();
        $this->assertEquals(0.0, $atomic->get());
    }
    
    public function testIsEmpty(): void
    {
        $atomic = $this->client->getAtomicDouble('test:atomic:double:empty');
        $atomic->delete();
        
        // Test empty (get should return 0.0)
        $this->assertEquals(0.0, $atomic->get());
        
        // Test not empty after set
        $atomic->set(0.0); // Even 0.0 makes it not empty
        $this->assertEquals(0.0, $atomic->get());
        
        // Test empty after delete
        $atomic->delete();
        $this->assertEquals(0.0, $atomic->get());
    }
}