<?php

namespace Rediphp\Tests;

class RSetTest extends RedissonTestCase
{
    
    public function testAddAndContains(): void
    {
        $set = $this->client->getSet('test:set:basic');
        $set->clear();
        
        // Test initial state
        $this->assertFalse($set->contains('item1'));
        $this->assertFalse($set->contains('item2'));
        
        // Add items and test
        $this->assertTrue($set->add('item1'));
        $this->assertTrue($set->add('item2'));
        
        $this->assertTrue($set->contains('item1'));
        $this->assertTrue($set->contains('item2'));
        $this->assertFalse($set->contains('item3'));
        
        // Add duplicate (should return false)
        $this->assertFalse($set->add('item1'));
    }
    
    public function testRemove(): void
    {
        $set = $this->client->getSet('test:set:remove');
        $set->clear();
        
        $set->add('apple');
        $set->add('banana');
        $set->add('cherry');
        
        // Remove existing item
        $this->assertTrue($set->remove('banana'));
        $this->assertFalse($set->contains('banana'));
        $this->assertTrue($set->contains('apple'));
        $this->assertTrue($set->contains('cherry'));
        
        // Remove non-existent item
        $this->assertFalse($set->remove('orange'));
    }
    
    public function testSizeAndClear(): void
    {
        $set = $this->client->getSet('test:set:size');
        $set->clear();
        
        // Test initial state
        $this->assertEquals(0, $set->size());
        $this->assertTrue($set->isEmpty());
        
        // Add items and test
        $set->add('item1');
        $set->add('item2');
        
        $this->assertEquals(2, $set->size());
        $this->assertFalse($set->isEmpty());
        
        // Clear and test
        $set->clear();
        $this->assertEquals(0, $set->size());
        $this->assertTrue($set->isEmpty());
    }
    
    public function testToArray(): void
    {
        $set = $this->client->getSet('test:set:toarray');
        $set->clear();
        
        // Add items (order doesn't matter in set)
        $set->add('apple');
        $set->add('banana');
        $set->add('cherry');
        
        // Convert to array
        $array = $set->toArray();
        $this->assertCount(3, $array);
        $this->assertContains('apple', $array);
        $this->assertContains('banana', $array);
        $this->assertContains('cherry', $array);
    }
    
    public function testSetOperations(): void
    {
        $set1 = $this->client->getSet('test:set:op1');
        $set2 = $this->client->getSet('test:set:op2');
        
        $set1->clear();
        $set2->clear();
        
        // Set1: {1, 2, 3, 4}
        $set1->add(1);
        $set1->add(2);
        $set1->add(3);
        $set1->add(4);
        
        // Set2: {3, 4, 5, 6}
        $set2->add(3);
        $set2->add(4);
        $set2->add(5);
        $set2->add(6);
        
        // Test union
        $union = $set1->union($set2);
        $this->assertTrue($union->contains(1));
        $this->assertTrue($union->contains(2));
        $this->assertTrue($union->contains(3));
        $this->assertTrue($union->contains(4));
        $this->assertTrue($union->contains(5));
        $this->assertTrue($union->contains(6));
        $this->assertEquals(6, $union->size());
        
        // Test intersection
        $intersection = $set1->intersection($set2);
        $this->assertFalse($intersection->contains(1));
        $this->assertFalse($intersection->contains(2));
        $this->assertTrue($intersection->contains(3));
        $this->assertTrue($intersection->contains(4));
        $this->assertFalse($intersection->contains(5));
        $this->assertFalse($intersection->contains(6));
        $this->assertEquals(2, $intersection->size());
        
        // Test difference
        $difference = $set1->difference($set2);
        $this->assertTrue($difference->contains(1));
        $this->assertTrue($difference->contains(2));
        $this->assertFalse($difference->contains(3));
        $this->assertFalse($difference->contains(4));
        $this->assertFalse($difference->contains(5));
        $this->assertFalse($difference->contains(6));
        $this->assertEquals(2, $difference->size());
    }
    
    public function testAddAll(): void
    {
        $set = $this->client->getSet('test:set:addall');
        $set->clear();
        
        // Add multiple items at once
        $items = ['apple', 'banana', 'cherry', 'apple']; // Duplicate
        $added = $set->addAll($items);
        
        $this->assertEquals(3, $added); // Only 3 unique items added
        $this->assertEquals(3, $set->size());
        $this->assertTrue($set->contains('apple'));
        $this->assertTrue($set->contains('banana'));
        $this->assertTrue($set->contains('cherry'));
    }
    
    public function testRemoveAll(): void
    {
        $set = $this->client->getSet('test:set:removeall');
        $set->clear();
        
        $set->add('apple');
        $set->add('banana');
        $set->add('cherry');
        $set->add('date');
        
        // Remove multiple elements
        $removed = $set->removeAll(['banana', 'cherry', 'nonexistent']);
        $this->assertEquals(2, $removed); // Only banana and cherry exist
        $this->assertEquals(2, $set->size());
        $this->assertTrue($set->contains('apple'));
        $this->assertTrue($set->contains('date'));
        $this->assertFalse($set->contains('banana'));
        $this->assertFalse($set->contains('cherry'));
    }
    
    public function testExists(): void
    {
        $set = $this->client->getSet('test:set:exists');
        $set->clear();
        
        // Test non-existent
        $this->assertFalse($set->exists());
        
        // Test exists after add
        $set->add('item');
        $this->assertTrue($set->exists());
        
        // Test exists after clear
        $set->clear();
        $this->assertFalse($set->exists());
    }
}