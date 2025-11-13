<?php

require 'vendor/autoload.php';

use Rediphp\RedissonClient;

// 创建客户端
$client = new RedissonClient();
$redis = $client->getRedis();

// 清理数据
$redis->del('test-redis-syntax');

echo "=== 测试Redis zRemRangeByScore语法 ===\n\n";

// 添加测试数据
$redis->zadd('test-redis-syntax', 5.0, json_encode('range1'));
$redis->zadd('test-redis-syntax', 10.0, json_encode('to-keep'));
$redis->zadd('test-redis-syntax', 15.0, json_encode('range2'));
$redis->zadd('test-redis-syntax', 25.0, json_encode('range3'));

echo "初始数据: \n";
$all = $redis->zrange('test-redis-syntax', 0, -1, true);
foreach ($all as $member => $score) {
    echo "  " . json_decode($member) . ": " . $score . "\n";
}

echo "\n测试1: zRemRangeByScore with inclusive bounds (10.0, 20.0)\n";
$result = $redis->zRemRangeByScore('test-redis-syntax', 10.0, 20.0);
echo "删除数量: " . $result . "\n";
$remaining = $redis->zrange('test-redis-syntax', 0, -1, true);
echo "剩余元素: ";
foreach ($remaining as $member => $score) {
    echo json_decode($member) . ":" . $score . " ";
}
echo "\n\n";

// 重新添加数据
$redis->zadd('test-redis-syntax', 5.0, json_encode('range1'));
$redis->zadd('test-redis-syntax', 10.0, json_encode('to-keep'));
$redis->zadd('test-redis-syntax', 15.0, json_encode('range2'));
$redis->zadd('test-redis-syntax', 25.0, json_encode('range3'));

echo "测试2: zRemRangeByScore with exclusive bounds (10.0, 20.0)\n";
$result = $redis->zRemRangeByScore('test-redis-syntax', "(10.0", "20.0)");
echo "删除数量: " . $result . "\n";
$remaining = $redis->zrange('test-redis-syntax', 0, -1, true);
echo "剩余元素: ";
foreach ($remaining as $member => $score) {
    echo json_decode($member) . ":" . $score . " ";
}
echo "\n\n";

// 清理
$redis->del('test-redis-syntax');
echo "测试完成，数据已清理。\n";