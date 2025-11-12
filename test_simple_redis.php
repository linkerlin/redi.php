<?php

// 简单的Redis连接测试
require_once 'vendor/autoload.php';

echo "=== 简单Redis连接测试 ===\n";

// 测试1: 直接Redis连接
echo "\n=== 测试1: 直接Redis连接 ===\n";
$redis = new Redis();

try {
    echo "尝试连接 127.0.0.1:6379...\n";
    $connected = $redis->connect('127.0.0.1', 6379, 2);
    
    if ($connected) {
        echo "✓ 连接成功！\n";
        echo "Ping响应: " . $redis->ping() . "\n";
        
        // 测试基本操作
        $redis->set('test_key', 'test_value');
        echo "设置键值: test_key = test_value\n";
        
        $value = $redis->get('test_key');
        echo "获取键值: $value\n";
        
        $redis->del('test_key');
        echo "删除键值: test_key\n";
        
        $redis->close();
        echo "✓ 所有测试通过！\n";
    } else {
        echo "✗ 连接失败！\n";
        echo "最后错误: " . $redis->getLastError() . "\n";
    }
} catch (Exception $e) {
    echo "✗ 连接异常: " . $e->getMessage() . "\n";
}

// 测试2: 检查Redis服务器信息
echo "\n=== 测试2: Redis服务器信息 ===\n";
$redis2 = new Redis();
try {
    if ($redis2->connect('127.0.0.1', 6379, 2)) {
        $info = $redis2->info();
        echo "Redis版本: " . $info['redis_version'] . "\n";
        echo "运行模式: " . $info['redis_mode'] . "\n";
        echo "连接数: " . $info['connected_clients'] . "\n";
        echo "内存使用: " . $info['used_memory_human'] . "\n";
        $redis2->close();
    }
} catch (Exception $e) {
    echo "获取服务器信息失败: " . $e->getMessage() . "\n";
}

echo "\n=== 环境信息 ===\n";
echo "PHP版本: " . PHP_VERSION . "\n";
echo "Redis扩展版本: " . phpversion('redis') . "\n";
echo "当前用户: " . get_current_user() . "\n";