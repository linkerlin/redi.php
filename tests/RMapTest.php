<?php

namespace Rediphp\Tests;

use PHPUnit\Framework\TestCase;
use Rediphp\RedissonClient;

class RMapTest extends TestCase
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
    
    public function testPutAndGet(): void
    {
        $map = $this->client->getMap('test:map:putget');
        $map->clear();
        
        $map->put('key1', 'value1');
        $this->assertEquals('value1', $map->get('key1'));
        
        $map->put('key2', ['nested' => 'data']);
        $this->assertEquals(['nested' => 'data'], $map->get('key2'));
    }
    
    public function testSize(): void
    {
        $map = $this->client->getMap('test:map:size');
        $map->clear();
        
        $this->assertEquals(0, $map->size());
        $this->assertTrue($map->isEmpty());
        
        $map->put('k1', 'v1');
        $map->put('k2', 'v2');
        
        $this->assertEquals(2, $map->size());
        $this->assertFalse($map->isEmpty());
    }
    
    public function testContainsKey(): void
    {
        $map = $this->client->getMap('test:map:contains');
        $map->clear();
        
        $map->put('exists', 'yes');
        
        $this->assertTrue($map->containsKey('exists'));
        $this->assertFalse($map->containsKey('notexists'));
    }
    
    public function testRemove(): void
    {
        $map = $this->client->getMap('test:map:remove');
        $map->clear();
        
        $map->put('toRemove', 'value');
        $this->assertTrue($map->containsKey('toRemove'));
        
        $removed = $map->remove('toRemove');
        $this->assertEquals('value', $removed);
        $this->assertFalse($map->containsKey('toRemove'));
    }
    
    public function testPutAll(): void
    {
        $map = $this->client->getMap('test:map:putall');
        $map->clear();
        
        $data = [
            'k1' => 'v1',
            'k2' => 'v2',
            'k3' => 'v3',
        ];
        
        $map->putAll($data);
        
        $this->assertEquals(3, $map->size());
        $this->assertEquals('v1', $map->get('k1'));
        $this->assertEquals('v2', $map->get('k2'));
        $this->assertEquals('v3', $map->get('k3'));
    }
    
    public function testEntrySet(): void
    {
        $map = $this->client->getMap('test:map:entryset');
        $map->clear();
        
        $map->put('a', 1);
        $map->put('b', 2);
        
        $entries = $map->entrySet();
        $this->assertCount(2, $entries);
        $this->assertEquals(1, $entries['a']);
        $this->assertEquals(2, $entries['b']);
    }
    
    public function testKeySet(): void
    {
        $map = $this->client->getMap('test:map:keyset');
        $map->clear();
        
        $map->put('key1', 'v1');
        $map->put('key2', 'v2');
        
        $keys = $map->keySet();
        $this->assertCount(2, $keys);
        $this->assertContains('key1', $keys);
        $this->assertContains('key2', $keys);
    }
    
    public function testValues(): void
    {
        $map = $this->client->getMap('test:map:values');
        $map->clear();
        
        $map->put('k1', 'value1');
        $map->put('k2', 'value2');
        
        $values = $map->values();
        $this->assertCount(2, $values);
        $this->assertContains('value1', $values);
        $this->assertContains('value2', $values);
    }
}
