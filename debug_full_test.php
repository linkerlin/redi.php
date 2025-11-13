<?php

require_once 'vendor/autoload.php';

use Rediphp\RedissonClient;

// 创建Redis客户端
$client = new RedissonClient();

// 获取有序集合
$sortedSet = $client->getSortedSet('debug-full-test');

// 清空集合
$sortedSet->clear();

echo "=== 模拟完整测试流程 ===\n";

// 添加元素
echo "1. 添加初始元素\n";
$sortedSet->add('to-keep', 10.0);
$sortedSet->add('to-remove1', 20.0);
$sortedSet->add('to-remove2', 30.0);
$sortedSet->add('to-remove3', 40.0);
echo "集合大小: " . $sortedSet->size() . "\n";

// 显示所有元素
$elements = $sortedSet->range(0, -1);
echo "元素列表:\n";
foreach ($elements as $element) {
    $score = $sortedSet->score($element);
    echo "  $element: $score\n";
}

// 删除单个元素
echo "\n2. 删除单个元素 to-remove1\n";
$sortedSet->remove('to-remove1');
echo "集合大小: " . $sortedSet->size() . "\n";
echo "包含 to-remove1: " . ($sortedSet->contains('to-remove1') ? 'true' : 'false') . "\n";

// 删除不存在的元素
echo "\n3. 删除不存在的元素\n";
echo "删除 nonexistent: " . ($sortedSet->remove('nonexistent') ? 'true' : 'false') . "\n";

// 批量删除
echo "\n4. 批量删除 to-remove2, to-remove3\n";
$removedCount = $sortedSet->removeBatch(['to-remove2', 'to-remove3']);
echo "删除的元素数量: $removedCount\n";
echo "集合大小: " . $sortedSet->size() . "\n";
echo "包含 to-keep: " . ($sortedSet->contains('to-keep') ? 'true' : 'false') . "\n";

// 显示当前元素
$elements = $sortedSet->range(0, -1);
echo "当前元素列表:\n";
foreach ($elements as $element) {
    $score = $sortedSet->score($element);
    echo "  $element: $score\n";
}

// 按分数范围删除
echo "\n5. 添加范围测试元素\n";
$sortedSet->add('range1', 5.0);
$sortedSet->add('range2', 15.0);
$sortedSet->add('range3', 25.0);
echo "集合大小: " . $sortedSet->size() . "\n";

// 显示所有元素
$elements = $sortedSet->range(0, -1);
echo "元素列表:\n";
foreach ($elements as $element) {
    $score = $sortedSet->score($element);
    echo "  $element: $score\n";
}

echo "\n6. 执行 removeRangeByScore(10.0, 20.0)\n";
$removedByRange = $sortedSet->removeRangeByScore(10.0, 20.0);
echo "删除的元素数量: $removedByRange\n";
echo "集合大小: " . $sortedSet->size() . "\n";

// 显示剩余元素
$remainingElements = $sortedSet->range(0, -1);
echo "剩余元素列表:\n";
foreach ($remainingElements as $element) {
    $score = $sortedSet->score($element);
    echo "  $element: $score\n";
}

// 清理
$sortedSet->clear();