<?php

require_once __DIR__ . '/../vendor/autoload.php';

use RediPhp\RedisPool;
use RediPhp\RedissonPool;

/**
 * 连接池使用示例
 */

// 示例1: 使用RedisPool
function redisPoolExample()
{
    echo "=== RedisPool示例 ===\n";
    
    // 创建连接池
    $pool = new RedisPool([
        'min_size' => 2,
        'max_size' => 10,
        'host' => '127.0.0.1',
        'port' => 6379,
        'password' => null,
        'database' => 0,
        'prefix' => 'example:',
    ]);
    
    // 获取连接
    $redis = $pool->getConnection();
    
    // 使用连接
    $redis->set('key1', 'value1');
    echo "设置 key1 = value1\n";
    
    $value = $redis->get('key1');
    echo "获取 key1 = {$value}\n";
    
    // 归还连接
    $pool->returnConnection($redis);
    
    // 显示连接池状态
    $stats = $pool->getStats();
    echo "连接池状态:\n";
    foreach ($stats as $key => $value) {
        echo "  {$key}: {$value}\n";
    }
    
    // 关闭连接池
    $pool->close();
    echo "连接池已关闭\n\n";
}

// 示例2: 使用RedissonPool
function redissonPoolExample()
{
    echo "=== RedissonPool示例 ===\n";
    
    // 创建连接池
    $pool = new RedissonPool([
        'min_size' => 2,
        'max_size' => 10,
        'host' => '127.0.0.1',
        'port' => 6379,
        'password' => null,
        'database' => 0,
        'prefix' => 'redisson:',
    ]);
    
    // 获取连接
    $redis = $pool->getConnection();
    
    // 使用连接
    $redis->set('key2', 'value2');
    echo "设置 key2 = value2\n";
    
    $value = $redis->get('key2');
    echo "获取 key2 = {$value}\n";
    
    // 归还连接
    $pool->returnConnection($redis);
    
    // 显示连接池状态
    $stats = $pool->getStats();
    echo "连接池状态:\n";
    foreach ($stats as $key => $value) {
        echo "  {$key}: {$value}\n";
    }
    
    // 关闭连接池
    $pool->close();
    echo "连接池已关闭\n\n";
}

// 示例3: 并发测试
function concurrentTest()
{
    echo "=== 并发测试示例 ===\n";
    
    $pool = new RedisPool([
        'min_size' => 2,
        'max_size' => 5,
        'host' => '127.0.0.1',
        'port' => 6379,
    ]);
    
    // 模拟并发获取连接
    $connections = [];
    for ($i = 0; $i < 5; $i++) {
        try {
            $redis = $pool->getConnection();
            $connections[] = $redis;
            echo "成功获取连接 {$i}\n";
        } catch (Exception $e) {
            echo "获取连接 {$i} 失败: " . $e->getMessage() . "\n";
        }
    }
    
    // 归还所有连接
    foreach ($connections as $i => $redis) {
        $pool->returnConnection($redis);
        echo "归还连接 {$i}\n";
    }
    
    // 显示连接池状态
    $stats = $pool->getStats();
    echo "连接池状态:\n";
    foreach ($stats as $key => $value) {
        echo "  {$key}: {$value}\n";
    }
    
    $pool->close();
    echo "连接池已关闭\n\n";
}

// 示例4: 连接池预热
function warmUpExample()
{
    echo "=== 连接池预热示例 ===\n";
    
    $pool = new RedisPool([
        'min_size' => 5,
        'max_size' => 10,
        'host' => '127.0.0.1',
        'port' => 6379,
    ]);
    
    echo "预热前空闲连接数: " . $pool->getIdleConnections() . "\n";
    
    // 预热连接池
    $pool->warmUp();
    
    echo "预热后空闲连接数: " . $pool->getIdleConnections() . "\n";
    
    $pool->close();
    echo "连接池已关闭\n\n";
}

// 运行示例
if (php_sapi_name() === 'cli') {
    redisPoolExample();
    redissonPoolExample();
    concurrentTest();
    warmUpExample();
} else {
    echo "请在命令行中运行此示例\n";
}