<?php

namespace Rediphp\Tests;

class RBucketTest extends RedissonTestCase
{
    
    public function testSetAndGet(): void
    {
        $bucket = $this->client->getBucket('test:bucket:basic');
        $bucket->delete();
        
        // Test initial state
        $this->assertNull($bucket->get());
        
        // Test string value
        $bucket->set('hello world');
        $this->assertEquals('hello world', $bucket->get());
        
        // Test numeric value
        $bucket->set(42);
        $this->assertEquals(42, $bucket->get());
        
        // Test float value
        $bucket->set(3.14159);
        $this->assertEquals(3.14159, $bucket->get());
        
        // Test boolean value
        $bucket->set(true);
        $this->assertTrue($bucket->get());
        
        // Test array value
        $bucket->set(['key' => 'value', 'number' => 123]);
        $this->assertEquals(['key' => 'value', 'number' => 123], $bucket->get());
    }
    
    public function testSetIfAbsent(): void
    {
        $bucket = $this->client->getBucket('test:bucket:setifabsent');
        $bucket->delete();
        
        // Set if absent on empty bucket
        $this->assertTrue($bucket->trySet('first value'));
        $this->assertEquals('first value', $bucket->get());
        
        // Try to set if absent on non-empty bucket
        $this->assertFalse($bucket->trySet('second value'));
        $this->assertEquals('first value', $bucket->get());
        
        // Delete and test again
        $bucket->delete();
        $this->assertTrue($bucket->trySet('new value'));
        $this->assertEquals('new value', $bucket->get());
    }
    
    public function testCompareAndSet(): void
    {
        $bucket = $this->client->getBucket('test:bucket:cas');
        $bucket->delete();
        
        $bucket->set('initial value');
        
        // Successful CAS
        $this->assertTrue($bucket->compareAndSet('initial value', 'new value'));
        $this->assertEquals('new value', $bucket->get());
        
        // Failed CAS
        $this->assertFalse($bucket->compareAndSet('initial value', 'another value'));
        $this->assertEquals('new value', $bucket->get());
        
        // CAS with empty bucket (null value)
        $bucket->delete();
        $this->assertTrue($bucket->compareAndSet(null, 'from null'));
        $this->assertEquals('from null', $bucket->get());
    }
    
    public function testGetAndSet(): void
    {
        $bucket = $this->client->getBucket('test:bucket:getandset');
        $bucket->delete();
        
        // Get and set on empty bucket
        $oldValue = $bucket->getAndSet('first');
        $this->assertNull($oldValue);
        $this->assertEquals('first', $bucket->get());
        
        // Get and set on non-empty bucket
        $oldValue = $bucket->getAndSet('second');
        $this->assertEquals('first', $oldValue);
        $this->assertEquals('second', $bucket->get());
    }
    
    public function testSizeAndClear(): void
    {
        $bucket = $this->client->getBucket('test:bucket:size');
        $bucket->delete();
        
        // Test initial state
        $this->assertFalse($bucket->isExists());
        
        // Set value and test
        $bucket->set('test value');
        $this->assertTrue($bucket->isExists());
        
        // Delete and test
        $bucket->delete();
        $this->assertFalse($bucket->isExists());
    }
    
    public function testExists(): void
    {
        $bucket = $this->client->getBucket('test:bucket:exists');
        $bucket->delete();
        
        // Test non-existent
        $this->assertFalse($bucket->isExists());
        
        // Test exists after set
        $bucket->set('test value');
        $this->assertTrue($bucket->isExists());
        
        // Test exists after delete
        $bucket->delete();
        $this->assertFalse($bucket->isExists());
    }
    
    public function testTtlOperations(): void
    {
        $bucket = $this->client->getBucket('test:bucket:ttl');
        $bucket->delete();
        
        // Set with TTL
        $bucket->set('value with ttl', 5); // 5 seconds TTL
        $this->assertEquals('value with ttl', $bucket->get());
        
        // Test that value exists immediately after set
        $this->assertTrue($bucket->isExists());
        
        // Test getAndDelete with TTL
        $value = $bucket->getAndDelete();
        $this->assertEquals('value with ttl', $value);
        $this->assertFalse($bucket->isExists());
        
        // Set TTL on existing value
        $bucket->setWithTTL('value with new ttl', 10); // 10 seconds TTL
        $this->assertEquals('value with new ttl', $bucket->get());
        $this->assertTrue($bucket->isExists());
    }
    
    public function testDelete(): void
    {
        $bucket = $this->client->getBucket('test:bucket:delete');
        $bucket->delete();
        
        $bucket->set('value to delete');
        $this->assertTrue($bucket->isExists());
        
        // Delete the value
        $deleted = $bucket->delete();
        $this->assertTrue($deleted);
        $this->assertFalse($bucket->isExists());
        $this->assertNull($bucket->get());
        
        // Try to delete non-existent bucket
        $deleted = $bucket->delete();
        $this->assertFalse($deleted);
    }
}