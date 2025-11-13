<?php

require 'vendor/autoload.php';

use Rediphp\RedissonClient;

echo "=== Redis连接测试 ===\n";

// 显示当前环境变量配置
echo "当前环境配置:\n";
echo "  REDIS_HOST: " . (getenv('REDIS_HOST') ?: '未设置 (默认: 127.0.0.1)') . "\n";
echo "  REDIS_PORT: " . (getenv('REDIS_PORT') ?: '未设置 (默认: 6379)') . "\n";
echo "  REDIS_DB: " . (getenv('REDIS_DB') ?: '未设置 (默认: 0)') . "\n";
echo "  REDIS_DATABASE: " . (getenv('REDIS_DATABASE') ?: '未设置 (默认: 0)') . "\n\n";

// 测试1: 使用默认配置
echo "测试1: 使用默认配置 (127.0.0.1:6379, db=0)\n";
try {
    $client1 = new RedissonClient();
    $client1->connect();
    echo "✅ 默认配置连接成功\n";
    
    // 简单测试
    $map = $client1->getMap('test_map');
    $map->put('test_key', 'test_value');
    echo "✅ 数据写入测试成功: " . $map->get('test_key') . "\n";
    
    $client1->shutdown();
} catch (Exception $e) {
    echo "❌ 默认配置连接失败: " . $e->getMessage() . "\n";
}

echo "\n测试2: 使用环境变量配置\n";
// 测试2: 如果有环境变量
$env_host = getenv('REDIS_HOST') ?: '未设置';
$env_port = getenv('REDIS_PORT') ?: '未设置';
$env_db = getenv('REDIS_DB') ?: '0';
echo "环境变量 - REDIS_HOST: $env_host, REDIS_PORT: $env_port, REDIS_DB: $env_db\n";

try {
    $client2 = new RedissonClient();
    $client2->connect();
    echo "✅ 环境变量配置连接成功\n";
    $client2->shutdown();
} catch (Exception $e) {
    echo "❌ 环境变量配置连接失败: " . $e->getMessage() . "\n";
}

echo "\n测试3: 自定义配置\n";
try {
    $client3 = new RedissonClient([
        'host' => 'localhost',
        'port' => 6379,
        'database' => 3,
    ]);
    $client3->connect();
    echo "✅ 自定义配置 (localhost:6379, db=3) 连接成功\n";
    $client3->shutdown();
} catch (Exception $e) {
    echo "❌ 自定义配置连接失败: " . $e->getMessage() . "\n";
}

echo "\n=== 测试完成 ===\n";
echo "建议：\n";
echo "1. 开发环境: 设置 REDIS_HOST=localhost\n";
echo "2. 生产环境: 设置 REDIS_HOST=你的实际Redis服务器IP\n";
echo "3. Docker环境: 设置 REDIS_HOST=redis-server\n";
echo "4. 使用 ./configure_redis.sh 脚本快速配置\n";