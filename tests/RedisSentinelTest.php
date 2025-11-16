<?php

namespace Rediphp\Tests;

use Rediphp\Config;
use Rediphp\RedissonSentinelClient;
use Rediphp\SentinelConfig;
use Rediphp\RedisSentinelManager;
use PHPUnit\Framework\TestCase;

/**
 * Redis Sentinel模式测试类
 * 测试Sentinel模式的功能和可靠性
 */
class RedisSentinelTest extends TestCase
{
    private ?RedissonSentinelClient $client = null;
    private ?RedisSentinelManager $sentinelManager = null;

    protected function setUp(): void
    {
        // 检查是否配置了Sentinel环境变量
        $sentinels = getenv('REDIS_SENTINELS');
        if (empty($sentinels)) {
            $this->markTestSkipped('Redis Sentinel environment not configured');
        }

        // 创建Sentinel客户端
        $this->client = new RedissonSentinelClient([
            'sentinels' => array_map('trim', explode(',', $sentinels)),
            'master_name' => getenv('REDIS_SENTINEL_MASTER') ?: 'mymaster',
            'timeout' => 5.0,
            'read_timeout' => 5.0,
            'password' => getenv('REDIS_PASSWORD') ?: null,
            'database' => 0,
        ]);

        $this->sentinelManager = $this->client->getSentinelManager();
    }

    protected function tearDown(): void
    {
        if ($this->client) {
            $this->client->close();
            $this->client = null;
        }
        $this->sentinelManager = null;
    }

    /**
     * 测试Sentinel连接
     */
    public function testSentinelConnection(): void
    {
        $this->client->connect();
        $this->assertTrue($this->client->isConnected());
        $this->assertTrue($this->client->isHealthy());

        // 测试基本操作
        $result = $this->client->set('sentinel_test_key', 'sentinel_test_value');
        $this->assertTrue($result);

        $value = $this->client->get('sentinel_test_key');
        $this->assertEquals('sentinel_test_value', $value);

        // 清理测试数据
        $this->client->del('sentinel_test_key');
    }

    /**
     * 测试Sentinel配置
     */
    public function testSentinelConfig(): void
    {
        $config = new SentinelConfig([
            'sentinels' => ['127.0.0.1:26379', '127.0.0.1:26380'],
            'master_name' => 'mymaster',
            'timeout' => 5.0,
            'read_timeout' => 5.0,
            'password' => 'test_password',
            'database' => 1,
            'retry_interval' => 100,
            'sentinel_password' => 'sentinel_password',
        ]);

        $this->assertEquals(['127.0.0.1:26379', '127.0.0.1:26380'], $config->getSentinels());
        $this->assertEquals('mymaster', $config->getMasterName());
        $this->assertEquals(5.0, $config->getTimeout());
        $this->assertEquals(5.0, $config->getReadTimeout());
        $this->assertEquals('test_password', $config->getPassword());
        $this->assertEquals(1, $config->getDatabase());
        $this->assertEquals(100, $config->getRetryInterval());
        $this->assertEquals('sentinel_password', $config->getSentinelPassword());
    }

    /**
     * 测试Sentinel管理器
     */
    public function testSentinelManager(): void
    {
        $this->sentinelManager->connect();
        $this->assertTrue($this->sentinelManager->isConnected());

        // 测试Redis实例获取
        $redis = $this->sentinelManager->getRedis();
        $this->assertInstanceOf(\Redis::class, $redis);

        // 测试命令执行
        $result = $this->sentinelManager->execute('ping');
        $this->assertEquals('PONG', $result);

        // 测试健康检查
        $this->assertTrue($this->sentinelManager->isHealthy());
    }

    /**
     * 测试Sentinel信息获取
     */
    public function testSentinelInfo(): void
    {
        $sentinelInfo = $this->client->getSentinelInfo();
        $this->assertIsArray($sentinelInfo);

        // 至少应该有一个Sentinel的信息
        $this->assertNotEmpty($sentinelInfo);

        // 检查信息结构
        foreach ($sentinelInfo as $info) {
            $this->assertArrayHasKey('master', $info);
            $this->assertArrayHasKey('slaves', $info);
            $this->assertArrayHasKey('sentinels', $info);
        }
    }

    /**
     * 测试分布式数据结构
     */
    public function testDistributedDataStructures(): void
    {
        $this->client->connect();

        // 测试Map
        $map = $this->client->getMap('sentinel_test_map');
        $map->put('key1', 'value1');
        $this->assertEquals('value1', $map->get('key1'));

        // 测试List
        $list = $this->client->getList('sentinel_test_list');
        $list->add('item1');
        $this->assertEquals('item1', $list->get(0));

        // 测试Set
        $set = $this->client->getSet('sentinel_test_set');
        $set->add('member1');
        $this->assertTrue($set->contains('member1'));

        // 测试Lock
        $lock = $this->client->getLock('sentinel_test_lock');
        $this->assertTrue($lock->tryLock());
        $lock->unlock();

        // 清理测试数据
        $this->client->del('sentinel_test_map');
        $this->client->del('sentinel_test_list');
        $this->client->del('sentinel_test_set');
    }

    /**
     * 测试配置类
     */
    public function testConfig(): void
    {
        // 测试通过Config类创建Sentinel客户端
        $client = Config::createSentinelClient();
        $this->assertInstanceOf(RedissonSentinelClient::class, $client);

        // 测试自动模式选择
        $client = Config::createClient();
        $this->assertInstanceOf(RedissonSentinelClient::class, $client);

        $client->close();
    }

    /**
     * 测试无效配置
     */
    public function testInvalidConfig(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        new SentinelConfig([
            'sentinels' => [], // 空Sentinel节点
            'master_name' => 'mymaster',
        ]);
    }

    /**
     * 测试连接池不支持
     */
    public function testSentinelNoPooling(): void
    {
        $this->client->connect();
        
        // Sentinel模式不支持连接池，应该正常工作
        $this->assertTrue($this->client->isConnected());
        
        // 测试基本操作
        $result = $this->client->set('pool_test', 'value');
        $this->assertTrue($result);
        
        $value = $this->client->get('pool_test');
        $this->assertEquals('value', $value);
        
        // 清理测试数据
        $this->client->del('pool_test');
    }

    /**
     * 测试故障转移恢复
     */
    public function testFailoverRecovery(): void
    {
        $this->client->connect();
        
        // 写入测试数据
        $this->client->set('failover_test', 'before_failover');
        
        // 模拟连接中断（通过关闭连接）
        $this->client->close();
        
        // 重新连接（应该自动发现新的主节点）
        $this->client->connect();
        
        // 验证数据仍然可访问
        $value = $this->client->get('failover_test');
        $this->assertEquals('before_failover', $value);
        
        // 清理测试数据
        $this->client->del('failover_test');
    }

    /**
     * 测试环境变量配置
     */
    public function testEnvironmentConfig(): void
    {
        $config = SentinelConfig::fromEnvironment();
        $this->assertInstanceOf(SentinelConfig::class, $config);
        
        // 验证配置不为空
        $this->assertNotEmpty($config->getSentinels());
        $this->assertNotEmpty($config->getMasterName());
    }
}