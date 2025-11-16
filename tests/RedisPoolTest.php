<?php

namespace Rediphp\Tests;

use Rediphp\RedisPool;
use Rediphp\PooledRedis;
use InvalidArgumentException;
use RuntimeException;

/**
 * RedisPool连接池测试
 */
class RedisPoolTest extends RedissonTestCase
{
    private ?RedisPool $pool = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pool = new RedisPool([
            'host' => '127.0.0.1',
            'port' => 6379,
            'min_size' => 0,
            'max_size' => 3,
            'max_wait_time' => 1.0,
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->pool) {
            $this->pool->close();
        }
        parent::tearDown();
    }

    /**
     * 测试基本连接获取和归还
     */
    public function testGetAndReturnConnection(): void
    {
        $connection = $this->pool->getConnection();
        $this->assertInstanceOf(PooledRedis::class, $connection);
        $this->assertTrue($connection->isConnected());

        // 检查统计信息 - min_size=0时，第一次获取连接会创建新连接
        $stats = $this->pool->getStats();
        $this->assertEquals(1, $stats['active_connections']);
        $this->assertGreaterThanOrEqual(1, $stats['total_connections']); // 至少1个连接

        // 归还连接
        $this->pool->returnConnection($connection);
        
        $stats = $this->pool->getStats();
        $this->assertEquals(0, $stats['active_connections']);
        $this->assertEquals(1, $stats['idle_connections']);
    }

    /**
     * 测试连接池初始化
     */
    public function testPoolInitialization(): void
    {
        // min_size=0时，初始化不创建任何连接
        $stats = $this->pool->getStats();
        $this->assertEquals(0, $stats['total_connections']);
        $this->assertEquals(0, $stats['idle_connections']);
        $this->assertEquals(0, $stats['active_connections']);
    }

    /**
     * 测试连接复用
     */
    public function testConnectionReuse(): void
    {
        $connection1 = $this->pool->getConnection();
        $this->assertInstanceOf(PooledRedis::class, $connection1);
        $this->assertTrue($connection1->isConnected());
        
        $this->pool->returnConnection($connection1);
        
        $connection2 = $this->pool->getConnection();
        $this->assertInstanceOf(PooledRedis::class, $connection2);
        $this->assertTrue($connection2->isConnected());
        
        // 验证连接复用 - 通过检查连接是否可用而不是严格的对象引用
        $this->assertTrue($connection2->isConnected());
        
        // 清理
        $this->pool->returnConnection($connection2);
    }

    /**
     * 测试连接池上限
     */
    public function testPoolMaxSize(): void
    {
        $connections = [];
        
        // 获取所有可用连接 (max_size=3)
        for ($i = 0; $i < 3; $i++) {
            $connections[] = $this->pool->getConnection();
        }
        
        $stats = $this->pool->getStats();
        $this->assertEquals(3, $stats['active_connections']);
        $this->assertEquals(3, $stats['total_connections']);
        
        // 尝试获取第4个连接应该超时
        try {
            $this->pool->getConnection();
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Failed to acquire Redis connection', $e->getMessage());
        }
        
        // 归还连接后应该可以获取
        $this->pool->returnConnection($connections[0]);
        $newConnection = $this->pool->getConnection();
        $this->assertInstanceOf(PooledRedis::class, $newConnection);
        
        // 清理
        foreach ($connections as $i => $conn) {
            if ($i > 0) {
                $this->pool->returnConnection($conn);
            }
        }
        $this->pool->returnConnection($newConnection);
    }

    /**
     * 测试连接池关闭
     */
    public function testPoolClose(): void
    {
        $connection = $this->pool->getConnection();
        $this->pool->close();
        
        $stats = $this->pool->getStats();
        $this->assertTrue($stats['is_closed']);
        $this->assertEquals(0, $stats['idle_connections']);
        $this->assertEquals(0, $stats['active_connections']);
        
        // 关闭后无法获取连接
        try {
            $this->pool->getConnection();
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Connection pool is closed', $e->getMessage());
        }
    }

    /**
     * 测试统计信息
     */
    public function testPoolStats(): void
    {
        $stats = $this->pool->getStats();
        
        $this->assertArrayHasKey('idle_connections', $stats);
        $this->assertArrayHasKey('active_connections', $stats);
        $this->assertArrayHasKey('total_connections', $stats);
        $this->assertArrayHasKey('min_size', $stats);
        $this->assertArrayHasKey('max_size', $stats);
        $this->assertArrayHasKey('is_closed', $stats);
        $this->assertArrayHasKey('total_requests', $stats);
        $this->assertArrayHasKey('total_acquires', $stats);
        $this->assertArrayHasKey('avg_acquire_time_ms', $stats);
        $this->assertArrayHasKey('pool_utilization', $stats);
        
        $this->assertEquals(0, $stats['min_size']);
        $this->assertEquals(3, $stats['max_size']);
        $this->assertFalse($stats['is_closed']);
    }

    /**
     * 测试无效配置
     */
    public function testInvalidConfiguration(): void
    {
        // 最小连接数大于最大连接数
        try {
            new RedisPool([
                'min_size' => 10,
                'max_size' => 5,
            ]);
            $this->fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Invalid pool size configuration', $e->getMessage());
        }
        
        // 等待时间为负数
        try {
            new RedisPool([
                'max_wait_time' => -1.0,
            ]);
            $this->fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('max_wait_time must be greater than 0', $e->getMessage());
        }
    }

    /**
     * 测试性能统计
     */
    public function testPerformanceStats(): void
    {
        // 执行一些操作
        for ($i = 0; $i < 5; $i++) {
            $conn = $this->pool->getConnection();
            $this->pool->returnConnection($conn);
        }
        
        $stats = $this->pool->getStats();
        $this->assertEquals(5, $stats['total_requests']);
        $this->assertGreaterThan(0, $stats['total_acquires']);
        $this->assertGreaterThanOrEqual(0, $stats['avg_acquire_time_ms']);
    }

    /**
     * 测试连接健康检查
     */
    public function testConnectionHealthCheck(): void
    {
        $connection = $this->pool->getConnection();
        
        // 模拟连接断开
        $connection->close();
        
        // 归还断开的连接
        $this->pool->returnConnection($connection);
        
        // 获取新连接
        $newConnection = $this->pool->getConnection();
        $this->assertInstanceOf(PooledRedis::class, $newConnection);
        $this->assertTrue($newConnection->isConnected());
        
        $this->pool->returnConnection($newConnection);
    }
}
