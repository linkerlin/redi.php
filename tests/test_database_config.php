<?php

require 'vendor/autoload.php';

use Rediphp\RedissonClient;

echo "=== Redis数据库配置测试 ===\n";

// 显示当前环境变量
echo "当前环境变量:\n";
echo "  REDIS_HOST: " . (getenv('REDIS_HOST') ?: '未设置 (使用默认值)') . "\n";
echo "  REDIS_PORT: " . (getenv('REDIS_PORT') ?: '未设置 (使用默认值: 6379)') . "\n";
echo "  REDIS_DB: " . (getenv('REDIS_DB') ?: '未设置 (使用默认值: 0)') . "\n";
echo "  REDIS_DATABASE: " . (getenv('REDIS_DATABASE') ?: '未设置 (使用默认值: 0)') . "\n";
echo "\n";

function testDatabase($dbNumber, $testName) {
    echo "测试 {$dbNumber}: {$testName}\n";
    try {
        $config = ['database' => $dbNumber];
        $client = new RedissonClient($config);
        $client->connect();
        
        // 测试数据操作
        $testKey = "test_db_{$dbNumber}_" . \time();
        $map = $client->getMap('database_test_map');
        $map->put($testKey, "value_from_db_{$dbNumber}");
        $retrievedValue = $map->get($testKey);
        
        // 清理测试数据
        $map->remove($testKey);
        
        echo "  ✅ 数据库{$dbNumber}连接和数据操作成功\n";
        echo "  📝 测试值: {$retrievedValue}\n";
        
        $client->shutdown();
        return true;
    } catch (\Exception $e) {
        echo "  ❌ 数据库{$dbNumber}测试失败: " . $e->getMessage() . "\n";
        return false;
    }
}

// 测试1: 使用默认数据库 (db=0)
echo "=== 测试1: 默认数据库配置 ===\n";
testDatabase(0, "默认配置");

echo "\n=== 测试2: 环境变量配置 ===\n";
// 测试2: 如果设置了REDIS_DB环境变量
$redisDb = getenv('REDIS_DB');
if ($redisDb !== false) {
    echo "检测到 REDIS_DB={$redisDb} 环境变量\n";
    testDatabase((int)$redisDb, "环境变量配置 (REDIS_DB={$redisDb})");
} else {
    echo "未设置 REDIS_DB 环境变量，跳过此测试\n";
}

echo "\n=== 测试3: 不同数据库编号测试 ===\n";
// 测试3: 测试不同的数据库编号
for ($db = 1; $db <= 3; $db++) {
    testDatabase($db, "数据库编号 {$db}");
}

echo "\n=== 测试4: 自定义配置测试 ===\n";
// 测试4: 使用自定义配置
try {
    $customConfig = [
        'host' => '127.0.0.1',
        'port' => 6379,
        'database' => 5,
        'timeout' => 1.0
    ];
    echo "使用自定义配置: " . json_encode($customConfig) . "\n";
    
    $client = new RedissonClient($customConfig);
    $client->connect();
    echo "  ✅ 自定义配置连接成功 (db=5)\n";
    
    // 测试数据库隔离
    $map = $client->getMap('custom_db_test');
    $map->put('isolation_test', 'database_5_data');
    echo "  📝 数据库隔离测试: " . $map->get('isolation_test') . "\n";
    
    $client->shutdown();
} catch (\Exception $e) {
    echo "  ❌ 自定义配置测试失败: " . $e->getMessage() . "\n";
}

echo "\n=== 测试5: 数据库范围验证 ===\n";
// 测试5: 验证Redis数据库范围 (0-15)
echo "Redis支持数据库编号范围: 0-15\n";
echo "测试数据库编号边界值:\n";

// 测试边界值
$boundaryTests = [0, 15];
foreach ($boundaryTests as $db) {
    try {
        $client = new RedissonClient(['database' => $db]);
        $client->connect();
        echo "  ✅ 数据库{$db} (边界值) 连接成功\n";
        $client->shutdown();
    } catch (\Exception $e) {
        echo "  ❌ 数据库{$db} (边界值) 连接失败: " . $e->getMessage() . "\n";
    }
}

echo "\n=== 数据库配置测试完成 ===\n";
echo "\n使用建议:\n";
echo "1. 开发环境: export REDIS_DB=0\n";
echo "2. 测试环境: export REDIS_DB=1\n";
echo "3. 生产环境: export REDIS_DB=2\n";
echo "4. 缓存环境: export REDIS_DB=15\n";
echo "5. 或在.env文件中设置: REDIS_DB=5\n";