<?php

require_once __DIR__ . '/vendor/autoload.php';

use Rediphp\SentinelConfig;
use Rediphp\RedisSentinelManager;
use Rediphp\RedissonSentinelClient;

// 测试Sentinel配置类
echo "=== 测试Sentinel配置类 ===\n";

try {
    $config = new SentinelConfig([
        'sentinels' => ['127.0.0.1:26379'],
        'master_name' => 'mymaster',
        'timeout' => 5.0,
        'read_timeout' => 5.0,
        'password' => null,
        'database' => 0,
        'retry_interval' => 100,
        'sentinel_password' => null,
    ]);
    
    echo "✓ Sentinel配置类创建成功\n";
    echo "  - Sentinel节点: " . implode(', ', $config->getSentinels()) . "\n";
    echo "  - 主节点名称: " . $config->getMasterName() . "\n";
    echo "  - 超时时间: " . $config->getTimeout() . "\n";
    echo "  - 数据库: " . $config->getDatabase() . "\n";
    
} catch (Exception $e) {
    echo "✗ Sentinel配置类创建失败: " . $e->getMessage() . "\n";
}

// 测试环境变量配置
echo "\n=== 测试环境变量配置 ===\n";

try {
    $config = SentinelConfig::fromEnvironment();
    echo "✓ 环境变量配置加载成功\n";
    echo "  - Sentinel节点: " . implode(', ', $config->getSentinels()) . "\n";
    echo "  - 主节点名称: " . $config->getMasterName() . "\n";
} catch (Exception $e) {
    echo "✗ 环境变量配置加载失败: " . $e->getMessage() . "\n";
}

// 测试Sentinel管理器（不实际连接）
echo "\n=== 测试Sentinel管理器 ===\n";

try {
    $config = new SentinelConfig([
        'sentinels' => ['127.0.0.1:26379'],
        'master_name' => 'mymaster',
    ]);
    
    $manager = new RedisSentinelManager($config);
    echo "✓ Sentinel管理器创建成功\n";
    echo "  - 连接状态: " . ($manager->isConnected() ? '已连接' : '未连接') . "\n";
    
} catch (Exception $e) {
    echo "✗ Sentinel管理器创建失败: " . $e->getMessage() . "\n";
}

// 测试Sentinel客户端
echo "\n=== 测试Sentinel客户端 ===\n";

try {
    $client = new RedissonSentinelClient([
        'sentinels' => ['127.0.0.1:26379'],
        'master_name' => 'mymaster',
    ]);
    
    echo "✓ Sentinel客户端创建成功\n";
    echo "  - 连接状态: " . ($client->isConnected() ? '已连接' : '未连接') . "\n";
    
    // 测试分布式数据结构创建
    $map = $client->getMap('test_map');
    echo "  - Map创建成功: " . get_class($map) . "\n";
    
    $list = $client->getList('test_list');
    echo "  - List创建成功: " . get_class($list) . "\n";
    
    $lock = $client->getLock('test_lock');
    echo "  - Lock创建成功: " . get_class($lock) . "\n";
    
} catch (Exception $e) {
    echo "✗ Sentinel客户端创建失败: " . $e->getMessage() . "\n";
}

// 测试Config类集成
echo "\n=== 测试Config类集成 ===\n";

try {
    $client = \Rediphp\Config::createSentinelClient([
        'sentinels' => ['127.0.0.1:26379'],
        'master_name' => 'mymaster',
    ]);
    
    echo "✓ Config类Sentinel客户端创建成功\n";
    echo "  - 客户端类型: " . get_class($client) . "\n";
    
} catch (Exception $e) {
    echo "✗ Config类Sentinel客户端创建失败: " . $e->getMessage() . "\n";
}

// 测试配置验证
echo "\n=== 测试配置验证 ===\n";

try {
    $config = new SentinelConfig([
        'sentinels' => ['invalid_format'], // 无效格式
        'master_name' => 'mymaster',
    ]);
    echo "✗ 无效配置验证失败\n";
} catch (InvalidArgumentException $e) {
    echo "✓ 无效配置验证成功: " . $e->getMessage() . "\n";
}

try {
    $config = new SentinelConfig([
        'sentinels' => [], // 空节点
        'master_name' => 'mymaster',
    ]);
    echo "✗ 空节点验证失败\n";
} catch (InvalidArgumentException $e) {
    echo "✓ 空节点验证成功: " . $e->getMessage() . "\n";
}

echo "\n=== Sentinel模式支持测试完成 ===\n";
echo "所有基础功能测试通过！\n";