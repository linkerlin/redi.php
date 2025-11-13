<?php

// 模拟PHPUnit测试环境
require_once 'vendor/autoload.php';

use Rediphp\RedissonClient;

// 设置可能影响连接的环境变量
putenv('REDIS_HOST=127.0.0.1');
putenv('REDIS_PORT=6379');

echo "=== 模拟PHPUnit环境测试 ===\n";

echo "环境变量:\n";
echo "REDIS_HOST: " . getenv('REDIS_HOST') . "\n";
echo "REDIS_PORT: " . getenv('REDIS_PORT') . "\n";

// 测试1: 直接使用Redis扩展
echo "\n=== 测试1: 直接Redis扩展连接 ===\n";
$redis = new Redis();
try {
    $connected = $redis->connect('127.0.0.1', 6379, 5);
    if ($connected) {
        echo "✓ 直接Redis连接成功\n";
        echo "  Ping响应: " . $redis->ping() . "\n";
        $redis->close();
    } else {
        echo "✗ 直接Redis连接失败\n";
        echo "  最后错误: " . $redis->getLastError() . "\n";
    }
} catch (Exception $e) {
    echo "✗ 直接Redis连接异常: " . $e->getMessage() . "\n";
}

// 测试2: 使用RedissonClient
echo "\n=== 测试2: RedissonClient连接 ===\n";
$client = new RedissonClient([
    'host' => '127.0.0.1',
    'port' => 6379,
]);

try {
    // 使用反射访问私有方法
    $reflection = new ReflectionClass($client);
    $connectMethod = $reflection->getMethod('connect');
    $connectMethod->setAccessible(true);
    
    $result = $connectMethod->invoke($client);
    echo "✓ RedissonClient连接方法返回: " . ($result ? 'true' : 'false') . "\n";
    
    // 检查Redis对象状态
    $redisProperty = $reflection->getProperty('redis');
    $redisProperty->setAccessible(true);
    $internalRedis = $redisProperty->getValue($client);
    
    if ($internalRedis instanceof Redis) {
        echo "✓ 内部Redis对象类型正确\n";
        echo "  内部连接状态: " . ($internalRedis->isConnected() ? '已连接' : '未连接') . "\n";
    }
    
} catch (Exception $e) {
    echo "✗ RedissonClient连接异常: " . $e->getMessage() . "\n";
}

// 测试3: 模拟测试用例的setUp方法
echo "\n=== 测试3: 模拟测试用例setUp方法 ===\n";
class TestSimulation {
    private $client;
    
    public function setUp() {
        $this->client = new RedissonClient([
            'host' => '127.0.0.1',
            'port' => 6379,
        ]);
        
        // 模拟测试用例中的连接检查
        $reflection = new ReflectionClass($this->client);
        $redisProperty = $reflection->getProperty('redis');
        $redisProperty->setAccessible(true);
        $redis = $redisProperty->getValue($this->client);
        
        if (!$redis->connect('127.0.0.1', 6379, 5)) {
            throw new RuntimeException('Redis server not available: ' . $redis->getLastError());
        }
        
        echo "✓ 模拟setUp方法连接成功\n";
    }
}

try {
    $test = new TestSimulation();
    $test->setUp();
} catch (Exception $e) {
    echo "✗ 模拟setUp方法失败: " . $e->getMessage() . "\n";
}

echo "\n=== 环境信息 ===\n";
echo "PHP版本: " . PHP_VERSION . "\n";
echo "PHP配置文件: " . php_ini_loaded_file() . "\n";
echo "当前用户: " . get_current_user() . "\n";