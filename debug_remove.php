<?php

require_once 'vendor/autoload.php';

use Rediphp\RedissonClient;

// 创建Redis客户端
$client = new RedissonClient();

// 获取有序集合
$sortedSet = $client->getSortedSet('debug-remove');

// 清空集合
$sortedSet->clear();

// 添加元素
$sortedSet->add('to-keep', 10.0);
$sortedSet->add('range1', 5.0);
$sortedSet->add('range2', 15.0);
$sortedSet->add('range3', 25.0);

echo "初始集合大小: " . $sortedSet->size() . "\n";

// 显示所有元素
$elements = $sortedSet->range(0, -1);
echo "初始元素:\n";
foreach ($elements as $element) {
    $score = $sortedSet->score($element);
    echo "  $element: $score\n";
}

// 执行删除操作
$removedCount = $sortedSet->removeRangeByScore(10.0, 20.0);
echo "删除的元素数量: $removedCount\n";
echo "删除后集合大小: " . $sortedSet->size() . "\n";

// 显示剩余元素
$remainingElements = $sortedSet->range(0, -1);
echo "剩余元素:\n";
foreach ($remainingElements as $element) {
    $score = $sortedSet->score($element);
    echo "  $element: $score\n";
}

// 清理
$sortedSet->clear();

echo "\n直接测试Redis命令:\n";

// 直接使用Redis命令测试
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);
$redis->del('debug-redis');

// 添加元素
$redis->zAdd('debug-redis', 10.0, 'to-keep');
$redis->zAdd('debug-redis', 5.0, 'range1');
$redis->zAdd('debug-redis', 15.0, 'range2');
$redis->zAdd('debug-redis', 25.0, 'range3');

echo "Redis初始集合大小: " . $redis->zCard('debug-redis') . "\n";

// 显示所有元素
$redisElements = $redis->zRange('debug-redis', 0, -1, ['withscores' => true]);
echo "Redis初始元素:\n";
foreach ($redisElements as $element => $score) {
    echo "  $element: $score\n";
}

// 执行删除操作
$redisRemovedCount = $redis->zRemRangeByScore('debug-redis', "(10.0", "20.0");
echo "Redis删除的元素数量: $redisRemovedCount\n";
echo "Redis删除后集合大小: " . $redis->zCard('debug-redis') . "\n";

// 显示剩余元素
$redisRemainingElements = $redis->zRange('debug-redis', 0, -1, ['withscores' => true]);
echo "Redis剩余元素:\n";
foreach ($redisRemainingElements as $element => $score) {
    echo "  $element: $score\n";
}

// 清理
$redis->del('debug-redis');
$redis->close();