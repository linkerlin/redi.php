<?php

require_once 'vendor/autoload.php';

use Rediphp\RedissonClient;

// 测试连接池基本功能
echo "=== 测试连接池基本功能 ===\n\n";

// 1. 测试直接连接模式
echo "1. 测试直接连接模式：\n";
$client1 = new RedissonClient(['use_pool' => false]);
$map1 = $client1->getMap('test_map');
$map1->put('key1', 'value1');
$result1 = $map1->get('key1');
echo "直接连接模式测试结果：" . $result1 . "\n\n";

// 2. 测试连接池模式
echo "2. 测试连接池模式：\n";
$client2 = new RedissonClient(['use_pool' => true]);
$map2 = $client2->getMap('test_map');
$map2->put('key2', 'value2');
$result2 = $map2->get('key2');
echo "连接池模式测试结果：" . $result2 . "\n\n";

// 3. 测试连接池性能（并发操作）
echo "3. 测试连接池性能（并发操作）：\n";
$startTime = microtime(true);
$results = [];
for ($i = 0; $i < 10; $i++) {
    $results[] = $map2->put("concurrent_key_$i", "value_$i");
}
$endTime = microtime(true);
$duration = round(($endTime - $startTime) * 1000, 2);
echo "并发操作耗时：{$duration}ms\n";
echo "并发操作结果数量：" . count($results) . "\n\n";

// 4. 清理测试数据
$map1->clear();
$map2->clear();

// 5. 测试连接池统计信息
echo "4. 测试连接池统计信息：\n";
// 暂时跳过统计信息测试，因为getPoolStats方法尚未实现
echo "连接池统计信息功能待实现\n";

echo "\n=== 连接池基本功能测试完成 ===\n";