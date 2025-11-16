<?php

require_once __DIR__ . '/vendor/autoload.php';

use Rediphp\RedissonClient;

// 测试连接池功能
echo "=== 测试连接池功能 ===\n";

// 1. 测试直接连接（向后兼容）
echo "\n1. 测试直接连接模式：\n";
$client1 = new RedissonClient([
    'host' => '127.0.0.1',
    'port' => 6379,
    'use_pool' => false
]);

$map1 = $client1->getMap('test_map_direct');
$map1->put('key1', 'value1');
$value1 = $map1->get('key1');
echo "直接连接模式测试结果：" . $value1 . "\n";

// 2. 测试连接池模式
echo "\n2. 测试连接池模式：\n";
$client2 = new RedissonClient([
    'host' => '127.0.0.1',
    'port' => 6379,
    'use_pool' => true,
    'pool_config' => [
        'max_connections' => 5,
        'min_connections' => 2,
        'connection_timeout' => 5.0
    ]
]);

$map2 = $client2->getMap('test_map_pool');
$map2->put('key2', 'value2');
$value2 = $map2->get('key2');
echo "连接池模式测试结果：" . $value2 . "\n";

// 3. 测试连接池性能
echo "\n3. 测试连接池性能（并发操作）：\n";
$start = microtime(true);

$promises = [];
for ($i = 0; $i < 10; $i++) {
    $promises[] = function() use ($client2, $i) {
        $map = $client2->getMap('test_map_pool');
        $map->put('concurrent_key_' . $i, 'value_' . $i);
        return $map->get('concurrent_key_' . $i);
    };
}

// 模拟并发执行
$results = [];
foreach ($promises as $promise) {
    $results[] = $promise();
}

$end = microtime(true);
echo "并发操作耗时：" . round(($end - $start) * 1000, 2) . "ms\n";
echo "并发操作结果数量：" . count($results) . "\n";

// 4. 测试数据结构是否支持连接池
echo "\n4. 测试各种数据结构是否支持连接池：\n";
$testStructures = [
    'set' => $client2->getSet('test_set'),
    'list' => $client2->getList('test_list'),
    'sorted_set' => $client2->getSortedSet('test_sorted_set'),
    'bucket' => $client2->getBucket('test_bucket')
];

foreach ($testStructures as $type => $structure) {
    try {
        if (method_exists($structure, 'add') || method_exists($structure, 'put')) {
            if (method_exists($structure, 'add')) {
                $structure->add('test_value');
            } else {
                $structure->put('test_key', 'test_value');
            }
            echo "✓ {$type} 支持连接池\n";
        } else {
            echo "- {$type} 无需测试添加操作\n";
        }
    } catch (Exception $e) {
        echo "✗ {$type} 连接池测试失败：" . $e->getMessage() . "\n";
    }
}

// 5. 清理测试数据
echo "\n5. 清理测试数据...\n";
$client1->getRedis()->del('test_map_direct');
$client2->getRedis()->del('test_map_pool');
$client2->getRedis()->del('test_set');
$client2->getRedis()->del('test_list');
$client2->getRedis()->del('test_sorted_set');
$client2->getRedis()->del('test_bucket');

echo "\n=== 连接池功能测试完成 ===\n";

// 6. 测试连接池统计信息
echo "\n6. 连接池统计信息：\n";
if ($client2->isUsingPool()) {
    echo "客户端正在使用连接池模式\n";
    // 这里可以添加连接池统计信息的获取
} else {
    echo "客户端使用直接连接模式\n";
}

// 7. 测试连接池关闭
echo "\n7. 测试连接池关闭...\n";
$client2->shutdown();
echo "连接池已关闭\n";

echo "\n=== 所有测试完成 ===\n";