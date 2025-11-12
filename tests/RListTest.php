<?php

namespace Rediphp\Tests;

class RListTest extends RedissonTestCase
{
    
    public function testAddAndGet(): void
    {
        $list = $this->client->getList('test:list:addget');
        $list->clear();
        
        $list->add('item1');
        $list->add('item2');
        $list->add('item3');
        
        $this->assertEquals('item1', $list->get(0));
        $this->assertEquals('item2', $list->get(1));
        $this->assertEquals('item3', $list->get(2));
    }
    
    public function testSize(): void
    {
        $list = $this->client->getList('test:list:size');
        $list->clear();
        
        $this->assertEquals(0, $list->size());
        $this->assertTrue($list->isEmpty());
        
        $list->add('a');
        $list->add('b');
        
        $this->assertEquals(2, $list->size());
        $this->assertFalse($list->isEmpty());
    }
    
    public function testSet(): void
    {
        $list = $this->client->getList('test:list:set');
        $list->clear();
        
        $list->add('old');
        $prev = $list->set(0, 'new');
        
        $this->assertEquals('old', $prev);
        $this->assertEquals('new', $list->get(0));
    }
    
    public function testRemove(): void
    {
        $list = $this->client->getList('test:list:remove');
        $list->clear();
        
        $list->add('item1');
        $list->add('item2');
        $list->add('item1');
        
        $result = $list->remove('item1');
        $this->assertTrue($result);
        
        // Should remove first occurrence only
        $this->assertEquals(2, $list->size());
    }
    
    public function testToArray(): void
    {
        $list = $this->client->getList('test:list:toarray');
        $list->clear();
        
        $list->add('a');
        $list->add('b');
        $list->add('c');
        
        $array = $list->toArray();
        $this->assertEquals(['a', 'b', 'c'], $array);
    }
    
    public function testRange(): void
    {
        $list = $this->client->getList('test:list:range');
        $list->clear();
        
        $list->add('a');
        $list->add('b');
        $list->add('c');
        $list->add('d');
        $list->add('e');
        
        $range = $list->range(1, 3);
        $this->assertEquals(['b', 'c', 'd'], $range);
    }
}
