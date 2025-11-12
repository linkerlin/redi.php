<?php

namespace Rediphp\Tests;

class RQueueTest extends RedissonTestCase
{
    
    public function testOfferAndPoll(): void
    {
        $queue = $this->client->getQueue('test:queue:basic');
        $queue->clear();
        
        // Offer items to queue
        $this->assertTrue($queue->offer('item1'));
        $this->assertTrue($queue->offer('item2'));
        $this->assertTrue($queue->offer('item3'));
        
        $this->assertEquals(3, $queue->size());
        
        // Poll items (FIFO order)
        $this->assertEquals('item1', $queue->poll());
        $this->assertEquals('item2', $queue->poll());
        $this->assertEquals('item3', $queue->poll());
        $this->assertNull($queue->poll()); // Empty queue
    }
    
    public function testPeek(): void
    {
        $queue = $this->client->getQueue('test:queue:peek');
        $queue->clear();
        
        // Peek empty queue
        $this->assertNull($queue->peek());
        
        // Add items and peek
        $queue->offer('first');
        $queue->offer('second');
        
        $this->assertEquals('first', $queue->peek());
        $this->assertEquals(2, $queue->size()); // Peek doesn't remove
        
        // Poll and peek again
        $queue->poll();
        $this->assertEquals('second', $queue->peek());
    }
    
    public function testSizeAndClear(): void
    {
        $queue = $this->client->getQueue('test:queue:size');
        $queue->clear();
        
        // Test initial state
        $this->assertEquals(0, $queue->size());
        $this->assertTrue($queue->isEmpty());
        
        // Add items and test
        $queue->offer('item1');
        $queue->offer('item2');
        
        $this->assertEquals(2, $queue->size());
        $this->assertFalse($queue->isEmpty());
        
        // Clear and test
        $queue->clear();
        $this->assertEquals(0, $queue->size());
        $this->assertTrue($queue->isEmpty());
    }
    
    public function testToArray(): void
    {
        $queue = $this->client->getQueue('test:queue:toarray');
        $queue->clear();
        
        // Add items in order
        $queue->offer('first');
        $queue->offer('second');
        $queue->offer('third');
        
        // Convert to array (FIFO order)
        $array = $queue->toArray();
        $this->assertEquals(['first', 'second', 'third'], $array);
    }
    
    public function testContains(): void
    {
        $queue = $this->client->getQueue('test:queue:contains');
        $queue->clear();
        
        $queue->offer('apple');
        $queue->offer('banana');
        $queue->offer('cherry');
        
        $this->assertTrue($queue->contains('apple'));
        $this->assertTrue($queue->contains('banana'));
        $this->assertTrue($queue->contains('cherry'));
        $this->assertFalse($queue->contains('orange'));
    }
    
    public function testRemove(): void
    {
        $queue = $this->client->getQueue('test:queue:remove');
        $queue->clear();
        
        $queue->offer('first');
        $queue->offer('second');
        $queue->offer('third');
        
        // Remove specific element
        $this->assertTrue($queue->remove('second'));
        $this->assertEquals(2, $queue->size());
        $this->assertFalse($queue->contains('second'));
        
        // Try to remove non-existent element
        $this->assertFalse($queue->remove('nonexistent'));
        $this->assertEquals(2, $queue->size());
        
        // Poll remaining items
        $this->assertEquals('first', $queue->poll());
        $this->assertEquals('third', $queue->poll());
    }
    
    public function testRemoveAll(): void
    {
        $queue = $this->client->getQueue('test:queue:removeall');
        $queue->clear();
        
        $queue->offer('apple');
        $queue->offer('banana');
        $queue->offer('cherry');
        $queue->offer('date');
        
        // Remove multiple elements
        $removed = $queue->removeAll(['banana', 'cherry', 'nonexistent']);
        $this->assertEquals(2, $removed); // Only banana and cherry exist
        $this->assertEquals(2, $queue->size());
        $this->assertTrue($queue->contains('apple'));
        $this->assertTrue($queue->contains('date'));
        $this->assertFalse($queue->contains('banana'));
        $this->assertFalse($queue->contains('cherry'));
    }
    
    public function testExists(): void
    {
        $queue = $this->client->getQueue('test:queue:exists');
        $queue->clear();
        
        // Test non-existent
        $this->assertFalse($queue->exists());
        
        // Test exists after offer
        $queue->offer('item');
        $this->assertTrue($queue->exists());
        
        // Test exists after clear
        $queue->clear();
        $this->assertFalse($queue->exists());
    }
}