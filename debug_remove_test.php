<?php

require 'vendor/autoload.php';

use Rediphp\RedissonClient;

// 创建客户端
$client = new RedissonClient();
$sortedSet = $client->getSortedSet('test-remove-debug-sortedset');

// 清理数据
$sortedSet->clear();

echo "=== 重现testRemoveOperations测试问题 ===\n\n";

// 步骤1：添加初始4个元素
echo "步骤1: 添加初始4个元素\n";
$sortedSet->add('to-keep', 10.0);
$sortedSet->add('to-remove1', 20.0);
$sortedSet->add('to-remove2', 30.0);
$sortedSet->add('to-remove3', 40.0);
echo "集合大小: " . $sortedSet->size() . "\n";
echo "所有元素: ";
var_dump($sortedSet->readAll());
echo "\n";

// 步骤2：删除单个元素
echo "步骤2: 删除单个元素 to-remove1\n";
$sortedSet->remove('to-remove1');
echo "集合大小: " . $sortedSet->size() . "\n";
echo "所有元素: ";
var_dump($sortedSet->readAll());
echo "\n";

// 步骤3：批量删除2个元素
echo "步骤3: 批量删除 to-remove2, to-remove3\n";
$removedCount = $sortedSet->removeBatch(['to-remove2', 'to-remove3']);
echo "删除数量: " . $removedCount . "\n";
echo "集合大小: " . $sortedSet->size() . "\n";
echo "所有元素: ";
var_dump($sortedSet->readAll());
echo "\n";

// 步骤4：添加3个range元素
echo "步骤4: 添加3个range元素\n";
$sortedSet->add('range1', 5.0);
$sortedSet->add('range2', 15.0);
$sortedSet->add('range3', 25.0);
echo "集合大小: " . $sortedSet->size() . "\n";
echo "所有元素（带分数）: ";
var_dump($sortedSet->readAllWithScores());
echo "\n";

// 步骤5：按分数范围删除 (10.0, 20.0)
echo "步骤5: 按分数范围删除 10.0 - 20.0\n";
echo "预期删除: range2 (分数15.0)\n";
$removedByRange = $sortedSet->removeRangeByScore(10.0, 20.0);
echo "实际删除数量: " . $removedByRange . "\n";
echo "删除后集合大小: " . $sortedSet->size() . "\n";
echo "删除后所有元素（带分数）: ";
var_dump($sortedSet->readAllWithScores());
echo "\n";

// 步骤6：验证删除结果
echo "步骤6: 验证删除结果\n";
echo "是否还包含 to-keep: " . ($sortedSet->contains('to-keep') ? '是' : '否') . "\n";
echo "是否还包含 range1: " . ($sortedSet->contains('range1') ? '是' : '否') . "\n";
echo "是否还包含 range2: " . ($sortedSet->contains('range2') ? '是' : '否') . "\n";
echo "是否还包含 range3: " . ($sortedSet->contains('range3') ? '是' : '否') . "\n";

// 清理
$sortedSet->clear();
echo "\n测试完成，数据已清理。\n";