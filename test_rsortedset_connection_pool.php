<?php

require_once __DIR__ . '/vendor/autoload.php';

use Rediphp\RedissonClient;

// 设置测试环境
putenv('REDIS_HOST=127.0.0.1');
putenv('REDIS_PORT=6379');
putenv('REDIS_PASSWORD=');
putenv('REDIS_DATABASE=0');

// 创建RedissonClient配置为使用连接池
$config = [
    'use_pool' => true,
    'database' => 0
];

// 创建RedissonClient实例
$client = new RedissonClient($config);

// 创建测试用的RSortedSet
$sortedSet = $client->getSortedSet('test:sortedset');

echo "开始 RSortedSet 连接池测试...\n";

// 清理测试数据
$sortedSet->clear();

try {
    // 测试添加元素
    echo "测试添加元素...\n";
    $result1 = $sortedSet->add('apple', 10.5);
    $result2 = $sortedSet->add('banana', 20.3);
    $result3 = $sortedSet->add('cherry', 5.8);
    echo "添加结果: $result1, $result2, $result3\n";

    // 测试查看大小
    echo "查看大小: " . $sortedSet->size() . "\n";

    // 测试分数查询
    echo "apple 的分数: " . $sortedSet->score('apple') . "\n";

    // 测试排名
    echo "apple 的排名: " . $sortedSet->rank('apple') . "\n";
    echo "cherry 的排名: " . $sortedSet->rank('cherry') . "\n";

    // 测试反向排名
    echo "apple 的反向排名: " . $sortedSet->revRank('apple') . "\n";

    // 测试范围查询
    echo "全部元素 (范围): " . json_encode($sortedSet->range(0, -1)) . "\n";
    echo "全部元素 (分数范围): " . json_encode($sortedSet->rangeByScore(0, 30)) . "\n";

    // 测试反向范围查询
    echo "反向范围: " . json_encode($sortedSet->revRange(0, -1)) . "\n";

    // 测试分数递增
    echo "increments apple 分数: " . $sortedSet->incrementScore('apple', 5.2) . "\n";
    echo "新的 apple 分数: " . $sortedSet->score('apple') . "\n";

    // 测试包含检查
    echo "包含 apple: " . ($sortedSet->contains('apple') ? '是' : '否') . "\n";
    echo "包含 grape: " . ($sortedSet->contains('grape') ? '是' : '否') . "\n";

    // 测试批量添加
    echo "批量添加元素...\n";
    $batchAdd = $sortedSet->addAll([
        'date' => 15.7,
        'elderberry' => 8.9,
        ['fig', 12.3]
    ]);
    echo "批量添加结果: $batchAdd\n";
    echo "新大小: " . $sortedSet->size() . "\n";

    // 测试读所有元素
    echo "所有元素: " . json_encode($sortedSet->readAll()) . "\n";
    echo "所有元素(含分数): " . json_encode($sortedSet->readAllWithScores()) . "\n";

    // 测试按排名范围删除
    echo "删除排名 0-1 的元素: " . $sortedSet->removeRange(0, 1) . " 个\n";
    echo "删除后大小: " . $sortedSet->size() . "\n";

    // 测试按分数范围删除
    echo "按分数范围删除 (5-12): " . $sortedSet->removeRangeByScore(5, 12) . " 个\n";
    echo "删除后大小: " . $sortedSet->size() . "\n";

    // 测试存在性
    echo "SortedSet 存在: " . ($sortedSet->exists() ? '是' : '否') . "\n";

    // 测试删除
    echo "删除 apple: " . ($sortedSet->remove('apple') ? '成功' : '失败') . "\n";
    echo "最终大小: " . $sortedSet->size() . "\n";

    echo "RSortedSet 连接池测试完成\n";

} catch (Exception $e) {
    echo "测试失败: " . $e->getMessage() . "\n";
    echo "错误堆栈: " . $e->getTraceAsString() . "\n";
}