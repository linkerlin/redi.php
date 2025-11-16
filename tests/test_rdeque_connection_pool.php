<?php

require_once __DIR__ . '/vendor/autoload.php';

use Rediphp\RedissonClient;
use Rediphp\Config;

// 创建客户端（使用配置类）
$client = Config::createClient([
    'min_connections' => 2,
    'max_connections' => 10,
    'connection_timeout' => 5.0,
    'retry_attempts' => 3,
    'retry_delay' => 0.1
]);

// 获取双端队列实例
$deque = $client->getDeque('test_deque');

echo "=== RDeque 连接池测试 ===\n";

// 测试1: 在头部添加元素
echo "1. 在头部添加元素...\n";
$result1 = $deque->addFirst('元素A');
$result2 = $deque->addFirst('元素B');
echo "头部添加结果: " . ($result1 && $result2 ? "成功" : "失败") . "\n";

// 测试2: 在尾部添加元素
echo "\n2. 在尾部添加元素...\n";
$result3 = $deque->addLast('元素C');
$result4 = $deque->addLast('元素D');
echo "尾部添加结果: " . ($result3 && $result4 ? "成功" : "失败") . "\n";

// 测试3: 查看队列大小
echo "\n3. 查看队列大小...\n";
$size = $deque->size();
echo "队列大小: $size\n";

// 测试4: 查看头部元素（不移除）
echo "\n4. 查看头部元素（不移除）...\n";
$first = $deque->peekFirst();
echo "头部元素: " . json_encode($first) . "\n";

// 测试5: 查看尾部元素（不移除）
echo "\n5. 查看尾部元素（不移除）...\n";
$last = $deque->peekLast();
echo "尾部元素: " . json_encode($last) . "\n";

// 测试6: 移除并获取头部元素
echo "\n6. 移除并获取头部元素...\n";
$removedFirst = $deque->removeFirst();
echo "移除的头部元素: " . json_encode($removedFirst) . "\n";
$sizeAfterRemoveFirst = $deque->size();
echo "移除后队列大小: $sizeAfterRemoveFirst\n";

// 测试7: 移除并获取尾部元素
echo "\n7. 移除并获取尾部元素...\n";
$removedLast = $deque->removeLast();
echo "移除的尾部元素: " . json_encode($removedLast) . "\n";
$sizeAfterRemoveLast = $deque->size();
echo "移除后队列大小: $sizeAfterRemoveLast\n";

// 测试8: 检查元素是否存在
echo "\n8. 检查元素是否存在...\n";
$contains = $deque->contains('元素C');
echo "队列是否包含'元素C': " . ($contains ? "是" : "否") . "\n";

// 测试9: 转换为数组
echo "\n9. 转换为数组...\n";
$array = $deque->toArray();
echo "队列内容: " . json_encode($array) . "\n";

// 测试10: 检查队列是否为空
echo "\n10. 检查队列是否为空...\n";
$isEmpty = $deque->isEmpty();
echo "队列是否为空: " . ($isEmpty ? "是" : "否") . "\n";

// 测试11: 清空队列
echo "\n11. 清空队列...\n";
$deque->clear();
$sizeAfterClear = $deque->size();
echo "清空后队列大小: $sizeAfterClear\n";
$isEmptyAfterClear = $deque->isEmpty();
echo "清空后队列是否为空: " . ($isEmptyAfterClear ? "是" : "否") . "\n";

echo "\n=== RDeque 连接池测试完成 ===\n";