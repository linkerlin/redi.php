<?php

require_once __DIR__ . '/vendor/autoload.php';

use Rediphp\RedissonClient;

// 模拟子进程的行为
echo "=== 模拟子进程 Map 写入 ===\n";

try {
    // 模拟子进程的连接配置（从concurrency_helper.php复制）
    $config = [
        'host' => '127.0.0.1',
        'port' => 6379,
        'timeout' => 5.0,
        'database' => 0,  // 注意：这里应该是数据库0
        'password' => null,
        'use_pool' => true,  // 启用连接池
        'pool_config' => [
            'min_connections' => 2,
            'max_connections' => 10,
            'idle_timeout' => 3600,
            'max_lifetime' => 7200,
        ]
    ];
    
    $client = new RedissonClient($config);
    $client->connect();
    
    echo "✅ 子进程客户端初始化成功\n";
    
    $mapName = 'test_map_debug';
    
    // 模拟 map_write 操作
    $map = $client->getMap($mapName);
    
    // 清空测试
    $map->clear();
    echo "🧹 清空测试Map\n";
    
    echo "🔧 开始写入数据...\n";
    
    $processId = 0;
    $iterations = 3;
    
    for ($i = 0; $i < $iterations; $i++) {
        $key = "process_{$processId}_key_{$i}";
        $value = "value_from_process_{$processId}_iteration_{$i}";
        echo "  写入: $key => $value\n";
        $map->put($key, $value);
    }
    
    echo "📊 子进程完成写入后Map大小: " . $map->size() . "\n";
    
    // 检查写入的数据
    $keys = $map->keySet();
    echo "🔑 子进程写入的键: " . json_encode($keys) . "\n";
    
    $entries = $map->entrySet();
    echo "📋 子进程写入的条目: " . json_encode($entries) . "\n";
    
    echo "✅ 子进程操作完成\n";
    
    // 不清理，等待主进程验证
    
} catch (Exception $e) {
    echo "❌ 子进程操作失败: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}

echo "=== 子进程模拟完成 ===\n";