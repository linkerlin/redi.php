<?php

require_once __DIR__ . '/vendor/autoload.php';

use Rediphp\Config;
use Rediphp\RedissonClient;

echo "=== RSet 连接池测试 ===\n";

// 使用连接池配置创建客户端
$client = Config::createClient([
    'host' => '127.0.0.1',
    'port' => 6379,
    'database' => 0,
    'pool' => [
        'enabled' => true,
        'min_connections' => 2,
        'max_connections' => 10,
        'connection_timeout' => 5,
        'idle_timeout' => 30,
    ]
]);

// 获取 RSet 实例
$set1 = $client->getSet('test_set1');
$set2 = $client->getSet('test_set2');

// 测试1: 添加元素
echo "1. 添加元素...\n";
$result1 = $set1->add('apple');
$result2 = $set1->add('banana');
$result3 = $set1->add('orange');
echo "添加 apple: " . ($result1 ? '成功' : '失败') . "\n";
echo "添加 banana: " . ($result2 ? '成功' : '失败') . "\n";
echo "添加 orange: " . ($result3 ? '成功' : '失败') . "\n\n";

// 测试2: 批量添加元素
echo "2. 批量添加元素...\n";
$batchResult = $set1->addAll(['grape', 'pear', 'watermelon']);
echo "批量添加结果: " . ($batchResult ? '成功' : '失败') . "\n\n";

// 测试3: 检查元素是否存在
echo "3. 检查元素是否存在...\n";
$hasApple = $set1->contains('apple');
$hasMango = $set1->contains('mango');
echo "是否包含 apple: " . ($hasApple ? '是' : '否') . "\n";
echo "是否包含 mango: " . ($hasMango ? '是' : '否') . "\n\n";

// 测试4: 查看Set大小
echo "4. 查看Set大小...\n";
$size = $set1->size();
echo "Set1 大小: " . $size . "\n\n";

// 测试5: 获取所有元素
echo "5. 获取所有元素...\n";
$elements = $set1->toArray();
echo "Set1 所有元素: " . json_encode($elements, JSON_UNESCAPED_UNICODE) . "\n\n";

// 测试6: 获取随机元素
echo "6. 获取随机元素...\n";
$randomElement = $set1->random();
echo "随机元素: " . json_encode($randomElement, JSON_UNESCAPED_UNICODE) . "\n\n";

// 测试7: 移除元素
echo "7. 移除元素...\n";
$removeResult = $set1->remove('pear');
echo "移除 pear: " . ($removeResult ? '成功' : '失败（不存在）') . "\n";
$sizeAfterRemove = $set1->size();
echo "移除后大小: " . $sizeAfterRemove . "\n\n";

// 测试8: 移除随机元素
echo "8. 移除随机元素...\n";
$removedRandom = $set1->removeRandom();
echo "移除的随机元素: " . json_encode($removedRandom, JSON_UNESCAPED_UNICODE) . "\n";
$sizeAfterRandomRemove = $set1->size();
echo "移除随机元素后大小: " . $sizeAfterRandomRemove . "\n\n";

// 测试9: 批量移除元素
echo "9. 批量移除元素...\n";
$removedCount = $set1->removeAll(['grape', 'watermelon']);
echo "批量移除数量: " . $removedCount . "\n";
$sizeAfterBatchRemove = $set1->size();
echo "批量移除后大小: " . $sizeAfterBatchRemove . "\n\n";

// 测试10: 检查Set是否存在
echo "10. 检查Set是否存在...\n";
$exists = $set1->exists();
echo "Set1 是否存在: " . ($exists ? '是' : '否') . "\n\n";

// 测试11: 检查是否为空
echo "11. 检查是否为空...\n";
$isEmpty = $set1->isEmpty();
echo "是否为空: " . ($isEmpty ? '是' : '否') . "\n\n";

// 为集合操作测试准备数据
echo "12. 准备集合操作测试数据...\n";
$set2->add('banana');
$set2->add('orange');
$set2->add('kiwi');
$set2->add('pineapple');
echo "Set2 元素: " . json_encode($set2->toArray(), JSON_UNESCAPED_UNICODE) . "\n\n";

// 测试13: 并集操作
echo "13. 并集操作...\n";
$unionSet = $set1->union($set2);
echo "并集结果: " . json_encode($unionSet->toArray(), JSON_UNESCAPED_UNICODE) . "\n";
$unionSize = $unionSet->size();
echo "并集大小: " . $unionSize . "\n\n";

// 测试14: 交集操作
echo "14. 交集操作...\n";
$intersectionSet = $set1->intersection($set2);
echo "交集结果: " . json_encode($intersectionSet->toArray(), JSON_UNESCAPED_UNICODE) . "\n";
$intersectionSize = $intersectionSet->size();
echo "交集大小: " . $intersectionSize . "\n\n";

// 测试15: 差集操作
echo "15. 差集操作...\n";
$differenceSet = $set1->difference($set2);
echo "差集结果 (Set1 - Set2): " . json_encode($differenceSet->toArray(), JSON_UNESCAPED_UNICODE) . "\n";
$differenceSize = $differenceSet->size();
echo "差集大小: " . $differenceSize . "\n\n";

// 测试16: 清空Set
echo "16. 清空Set...\n";
$set1->clear();
$sizeAfterClear = $set1->size();
echo "清空后大小: " . $sizeAfterClear . "\n";
$isEmptyAfterClear = $set1->isEmpty();
echo "清空后是否为空: " . ($isEmptyAfterClear ? '是' : '否') . "\n\n";

// 清理
$set2->clear();
if (isset($unionSet)) {
    $unionSet->clear();
}
if (isset($intersectionSet)) {
    $intersectionSet->clear();
}
if (isset($differenceSet)) {
    $differenceSet->clear();
}

echo "=== RSet 连接池测试完成 ===\n";