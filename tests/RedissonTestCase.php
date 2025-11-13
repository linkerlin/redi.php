<?php

namespace Rediphp\Tests;

use PHPUnit\Framework\TestCase;
use Rediphp\RedissonClient;

/**
 * Base test case for Redisson data structure tests
 * Eliminates repetitive client initialization code
 */
abstract class RedissonTestCase extends TestCase
{
    protected RedissonClient $client;
    
    /**
     * Set up the test environment
     * Creates and connects the RedissonClient
     */
    protected function setUp(): void
    {
        $host = getenv('REDIS_HOST') ?: '127.0.0.1';
        $port = (int)(getenv('REDIS_PORT') ?: 6379);
        
        $this->client = new RedissonClient([
            'host' => $host,
            'port' => $port,
        ]);
        
        try {
            $this->client->connect();
            
            // 清理integration相关的测试数据
            $redis = $this->client->getRedis();
            $keys = $redis->keys('integration:*');
            if (!empty($keys)) {
                $redis->del(...$keys);
            }
        } catch (\Exception $e) {
            // 如果连接失败，尝试其他连接方式
            $this->tryAlternativeConnections($host, $port);
        }
    }
    
    /**
     * Try alternative Redis connection methods
     */
    protected function tryAlternativeConnections(string $host, int $port): void
    {
        $connectionMethods = [
            ['host' => '127.0.0.1', 'port' => 6379],
            ['host' => 'localhost', 'port' => 6379],
            ['host' => '0.0.0.0', 'port' => 6379],
        ];
        
        $lastError = null;
        
        foreach ($connectionMethods as $method) {
            try {
                $this->client = new RedissonClient($method);
                $this->client->connect();
                return; // 连接成功，返回
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                continue;
            }
        }
        
        // 所有连接方法都失败
        $this->markTestSkipped('Redis server not available: ' . $lastError);
    }
    
    /**
     * Clean up after tests
     * Shuts down the client connection
     */
    protected function tearDown(): void
    {
        if (isset($this->client)) {
            $this->client->shutdown();
        }
    }
}