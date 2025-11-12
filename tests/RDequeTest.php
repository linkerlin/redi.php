<?php

namespace Rediphp\Tests;

class RDequeTest extends RedissonTestCase
{
    
    public function testAddAndRemove(): void
    {
        $deque = $this->client->getDeque('test:deque:basic');
        $deque->clear();
        
        // Add to front and back
        $deque->addFirst('first');
        $deque->addLast('last');
        
        $this->assertEquals(2, $deque->size());
        $this->assertEquals('first', $deque->peekFirst());
        $this->assertEquals('last', $deque->peekLast());
        
        // Remove from front and back
        $first = $deque->removeFirst();
        $last = $deque->removeLast();
        
        $this->assertEquals('first', $first);
        $this->assertEquals('last', $last);
        $this->assertEquals(0, $deque->size());
    }
    
    public function testOfferAndPoll(): void
    {
        $deque = $this->client->getDeque('test:deque:offer');
        $deque->clear();
        
        // Add to front and back
        $this->assertTrue($deque->addFirst('front'));
        $this->assertTrue($deque->addLast('back'));
        
        $this->assertEquals(2, $deque->size());
        
        // Remove from front and back
        $front = $deque->removeFirst();
        $back = $deque->removeLast();
        
        $this->assertEquals('front', $front);
        $this->assertEquals('back', $back);
        $this->assertEquals(0, $deque->size());
        
        // Remove from empty deque
        $this->assertNull($deque->removeFirst());
        $this->assertNull($deque->removeLast());
    }
    
    public function testPeek(): void
    {
        $deque = $this->client->getDeque('test:deque:peek');
        $deque->clear();
        
        // Add items
        $deque->addFirst('first');
        $deque->addLast('last');
        
        // Peek without removing
        $this->assertEquals('first', $deque->peekFirst());
        $this->assertEquals('last', $deque->peekLast());
        $this->assertEquals(2, $deque->size());
        
        // Peek from empty deque
        $deque->clear();
        $this->assertNull($deque->peekFirst());
        $this->assertNull($deque->peekLast());
    }
    
    public function testSizeAndClear(): void
    {
        $deque = $this->client->getDeque('test:deque:size');
        $deque->clear();
        
        // Test initial state
        $this->assertEquals(0, $deque->size());
        $this->assertTrue($deque->isEmpty());
        
        // Add items and test
        $deque->addFirst('item1');
        $deque->addLast('item2');
        
        $this->assertEquals(2, $deque->size());
        $this->assertFalse($deque->isEmpty());
        
        // Clear and test
        $deque->clear();
        $this->assertEquals(0, $deque->size());
        $this->assertTrue($deque->isEmpty());
    }
    
    public function testToArray(): void
    {
        $deque = $this->client->getDeque('test:deque:toarray');
        $deque->clear();
        
        // Add items in order
        $deque->addFirst('first');
        $deque->addLast('middle');
        $deque->addLast('last');
        
        // Convert to array
        $array = $deque->toArray();
        $this->assertEquals(['first', 'middle', 'last'], $array);
    }
    
    public function testContains(): void
    {
        $deque = $this->client->getDeque('test:deque:contains');
        $deque->clear();
        
        $deque->addFirst('apple');
        $deque->addLast('banana');
        $deque->addLast('cherry');
        
        $this->assertTrue($deque->contains('apple'));
        $this->assertTrue($deque->contains('banana'));
        $this->assertTrue($deque->contains('cherry'));
        $this->assertFalse($deque->contains('orange'));
    }
    
    public function testRemove(): void
    {
        $deque = $this->client->getDeque('test:deque:remove');
        $deque->clear();
        
        $deque->addFirst('first');
        $deque->addLast('middle');
        $deque->addLast('last');
        
        // Remove elements using removeFirst and removeLast
        $this->assertEquals('first', $deque->removeFirst());
        $this->assertEquals('last', $deque->removeLast());
        $this->assertEquals(1, $deque->size());
        $this->assertEquals('middle', $deque->peekFirst());
        
        // Remove remaining element
        $this->assertEquals('middle', $deque->removeFirst());
        $this->assertEquals(0, $deque->size());
    }
    
    public function testRemoveAll(): void
    {
        $deque = $this->client->getDeque('test:deque:removeall');
        $deque->clear();
        
        $deque->addFirst('apple');
        $deque->addLast('banana');
        $deque->addLast('cherry');
        $deque->addLast('date');
        
        // Remove all elements using clear
        $this->assertEquals(4, $deque->size());
        $deque->clear();
        $this->assertEquals(0, $deque->size());
        $this->assertTrue($deque->isEmpty());
        
        // Add elements again and remove one by one
        $deque->addFirst('apple');
        $deque->addLast('banana');
        $this->assertEquals(2, $deque->size());
        
        $deque->removeFirst();
        $this->assertEquals(1, $deque->size());
        $this->assertEquals('banana', $deque->peekFirst());
        
        $deque->removeLast();
        $this->assertEquals(0, $deque->size());
        $this->assertTrue($deque->isEmpty());
    }
    
    public function testExists(): void
    {
        $deque = $this->client->getDeque('test:deque:exists');
        $deque->clear();
        
        // Test non-existent
        $this->assertEquals(0, $deque->size());
        $this->assertTrue($deque->isEmpty());
        
        // Test exists after add
        $deque->addFirst('item');
        $this->assertEquals(1, $deque->size());
        $this->assertFalse($deque->isEmpty());
        
        // Test exists after clear
        $deque->clear();
        $this->assertEquals(0, $deque->size());
        $this->assertTrue($deque->isEmpty());
    }
}