<?php

namespace Rediphp\Tests;

use PHPUnit\Framework\TestCase;
use Rediphp\RedissonClient;

class RAtomicLongTest extends TestCase
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
    
    public function testSetAndGet(): void
    {
        $atomic = $this->client->getAtomicLong('test:atomic:setget');
        $atomic->delete();
        
        $atomic->set(100);
        $this->assertEquals(100, $atomic->get());
    }
    
    public function testIncrementAndGet(): void
    {
        $atomic = $this->client->getAtomicLong('test:atomic:incr');
        $atomic->set(0);
        
        $result = $atomic->incrementAndGet();
        $this->assertEquals(1, $result);
        $this->assertEquals(1, $atomic->get());
    }
    
    public function testDecrementAndGet(): void
    {
        $atomic = $this->client->getAtomicLong('test:atomic:decr');
        $atomic->set(10);
        
        $result = $atomic->decrementAndGet();
        $this->assertEquals(9, $result);
        $this->assertEquals(9, $atomic->get());
    }
    
    public function testAddAndGet(): void
    {
        $atomic = $this->client->getAtomicLong('test:atomic:add');
        $atomic->set(5);
        
        $result = $atomic->addAndGet(10);
        $this->assertEquals(15, $result);
        $this->assertEquals(15, $atomic->get());
    }
    
    public function testGetAndAdd(): void
    {
        $atomic = $this->client->getAtomicLong('test:atomic:getadd');
        $atomic->set(5);
        
        $result = $atomic->getAndAdd(10);
        $this->assertEquals(5, $result);
        $this->assertEquals(15, $atomic->get());
    }
    
    public function testCompareAndSet(): void
    {
        $atomic = $this->client->getAtomicLong('test:atomic:cas');
        $atomic->set(100);
        
        // Should succeed
        $result = $atomic->compareAndSet(100, 200);
        $this->assertTrue($result);
        $this->assertEquals(200, $atomic->get());
        
        // Should fail
        $result = $atomic->compareAndSet(100, 300);
        $this->assertFalse($result);
        $this->assertEquals(200, $atomic->get());
    }
}
