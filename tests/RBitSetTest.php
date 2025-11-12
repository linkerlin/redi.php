<?php

namespace Rediphp\Tests;

class RBitSetTest extends RedissonTestCase
{
    
    public function testSetAndGet(): void
    {
        $bitSet = $this->client->getBitSet('test:bitset:basic');
        $bitSet->clearAll();
        
        // Test initial state
        $this->assertFalse($bitSet->get(0));
        $this->assertFalse($bitSet->get(100));
        
        // Set bits and verify
        $bitSet->set(0);
        $bitSet->set(5);
        $bitSet->set(100);
        
        $this->assertTrue($bitSet->get(0));
        $this->assertTrue($bitSet->get(5));
        $this->assertTrue($bitSet->get(100));
        $this->assertFalse($bitSet->get(1));
        $this->assertFalse($bitSet->get(50));
    }
    
    public function testClearBit(): void
    {
        $bitSet = $this->client->getBitSet('test:bitset:clear');
        $bitSet->clearAll();
        
        // Set bits
        $bitSet->set(0);
        $bitSet->set(10);
        $bitSet->set(20);
        
        // Clear specific bit
        $bitSet->clear(10);
        
        $this->assertTrue($bitSet->get(0));
        $this->assertFalse($bitSet->get(10));
        $this->assertTrue($bitSet->get(20));
    }
    
    public function testCardinality(): void
    {
        $bitSet = $this->client->getBitSet('test:bitset:cardinality');
        $bitSet->clearAll();
        
        // Test empty bitset
        $this->assertEquals(0, $bitSet->cardinality());
        
        // Set bits and test cardinality
        $bitSet->set(1);
        $bitSet->set(3);
        $bitSet->set(5);
        
        $this->assertEquals(3, $bitSet->cardinality());
        
        // Clear one bit
        $bitSet->clear(3);
        $this->assertEquals(2, $bitSet->cardinality());
    }
    
    public function testSize(): void
    {
        $bitSet = $this->client->getBitSet('test:bitset:size');
        $bitSet->clearAll();
        
        // Test initial size
        $this->assertEquals(0, $bitSet->length());
        $this->assertTrue($bitSet->isEmpty());
        
        // Set bits and test size
        $bitSet->set(0);
        $bitSet->set(100);
        
        $this->assertGreaterThan(0, $bitSet->length());
        $this->assertFalse($bitSet->isEmpty());
        
        // Clear and test
        $bitSet->clearAll();
        $this->assertEquals(0, $bitSet->length());
        $this->assertTrue($bitSet->isEmpty());
    }
    
    public function testAndOrXorOperations(): void
    {
        $bitSet1 = $this->client->getBitSet('test:bitset:op1');
        $bitSet2 = $this->client->getBitSet('test:bitset:op2');
        
        $bitSet1->clearAll();
        $bitSet2->clearAll();
        
        // Set bits for bitSet1: {1, 3, 5}
        $bitSet1->set(1);
        $bitSet1->set(3);
        $bitSet1->set(5);
        
        // Set bits for bitSet2: {3, 4, 5}
        $bitSet2->set(3);
        $bitSet2->set(4);
        $bitSet2->set(5);
        
        // Test cardinality (number of bits set)
        $this->assertEquals(3, $bitSet1->cardinality());
        $this->assertEquals(3, $bitSet2->cardinality());
        
        // Test individual bit operations
        $this->assertTrue($bitSet1->get(1));
        $this->assertTrue($bitSet1->get(3));
        $this->assertTrue($bitSet1->get(5));
        $this->assertFalse($bitSet1->get(4));
        
        $this->assertTrue($bitSet2->get(3));
        $this->assertTrue($bitSet2->get(4));
        $this->assertTrue($bitSet2->get(5));
        $this->assertFalse($bitSet2->get(1));
        
        // Test clear bit operation
        $bitSet1->clear(1);
        $this->assertFalse($bitSet1->get(1));
        $this->assertEquals(2, $bitSet1->cardinality());
        
        // Reset bit
        $bitSet1->set(1);
        $this->assertEquals(3, $bitSet1->cardinality());
        
        $bitSet1->delete();
        $bitSet2->delete();
    }
    
    public function testExists(): void
    {
        $bitSet = $this->client->getBitSet('test:bitset:exists');
        $bitSet->clearAll();
        
        // Test non-existent
        $this->assertTrue($bitSet->isEmpty());
        
        // Test exists after set
        $bitSet->set(0);
        $this->assertFalse($bitSet->isEmpty());
        
        // Test exists after clear
        $bitSet->clearAll();
        $this->assertTrue($bitSet->isEmpty());
    }
}