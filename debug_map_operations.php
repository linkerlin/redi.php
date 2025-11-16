<?php

require_once __DIR__ . '/vendor/autoload.php';

use Rediphp\RedissonClient;

echo "=== 测试Map操作连接池问题 ===\n";

try {
    // 创建客户端（启用连接池）
    $config = [
        'host' => '127.0.0.1',
        'port' => 6379,
        'timeout' => 5.0,
        'database' => 0,
        'password' => null,
        'use_pool' => true,
        'pool_config' => [
            'min_connections' => 2,
            'max_connections' => 10,
            'idle_timeout' => 3600,
            'max_lifetime' => 7200,
        ]
    ];
    
    $client = new RedissonClient($config);
    $client->connect();
    
    echo "✅ 客户端初始化成功\n";
    
    // 创建Map
    $mapName = 'debug_test_map';
    $map = $client->getMap($mapName);
    
    echo "✅ Map创建成功\n";
    
    // 检查Map状态
    $size = $map->size();
    echo "📊 Map初始大小: $size\n";
    
    // 添加一些数据
    for ($i = 0; $i < 3; $i++) {
        $key = "key_$i";
        $value = "value_$i";
        echo "🔧 添加: $key => $value\n";
        $map->put($key, $value);
    }
    
    // 再次检查大小
    $sizeAfter = $map->size();
    echo "📊 Map添加后大小: $sizeAfter\n";
    
    // 获取所有键
    $keys = $map->keySet();
    echo "🔑 Map中的所有键: " . json_encode($keys) . "\n";
    
    // 获取所有值
    $values = $map->values();
    echo "📝 Map中的所有值: " . json_encode($values) . "\n";
    
    // 获取所有条目
    $entries = $map->entrySet();
    echo "📋 Map中的所有条目: " . json_encode($entries) . "\n";
    
    echo "✅ Map操作测试完成\n";
    
    // 清理
    $map->clear();
    echo "🧹 数据清理完成\n";
    
    $client->disconnect();
    echo "✅ 客户端断开连接\n";
    
} catch (Exception $e) {
    echo "❌ 测试失败: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}

echo "=== 测试完成 ===\n";