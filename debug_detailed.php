<?php

require 'vendor/autoload.php';

use Rediphp\RedissonClient;

// 创建客户端
$client = new RedissonClient([
    'host' => '127.0.0.1',
    'port' => 6379,
    'password' => null,
    'database' => 0
]);

// 测试1：检查基本删除操作
echo "=== 测试1：基本删除操作 ===\n";
try {
    $sortedSet = $client->getSortedSet('test-detailed-sortedset');
    
    // 清理
    $sortedSet->clear();
    
    // 添加元素
    $sortedSet->add('to-keep', 10.0);
    $sortedSet->add('range1', 5.0);
    $sortedSet->add('range2', 15.0);
    $sortedSet->add('range3', 25.0);
    
    echo "初始集合大小: " . $sortedSet->size() . "\n";
    
    // 按分数范围删除 (10.0, 20.0]
    $removedCount = $sortedSet->removeRangeByScore(10.0, 20.0);
    
    echo "删除的元素数量: " . $removedCount . "\n";
    echo "删除后集合大小: " . $sortedSet->size() . "\n";
    echo "删除后的元素: ";
    $allElements = $sortedSet->readAllWithScores();
    foreach ($allElements as $element => $score) {
        echo "$element:$score ";
    }
    echo "\n";
    
    // 验证预期结果
    if ($sortedSet->size() == 2 && 
        $sortedSet->contains('to-keep') && 
        !$sortedSet->contains('range2')) {
        echo "测试1通过！\n\n";
    } else {
        echo "测试1失败！\n\n";
    }
} catch (Exception $e) {
    echo "测试1出错: " . $e->getMessage() . "\n\n";
}

// 测试2：检查Redis原始命令行为
echo "=== 测试2：Redis原始命令行为 ===\n";
try {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    
    // 清理
    $redis->del('test-raw-sortedset');
    
    // 添加元素
    $redis->zAdd('test-raw-sortedset', 5.0, 'range1');
    $redis->zAdd('test-raw-sortedset', 10.0, 'to-keep');
    $redis->zAdd('test-raw-sortedset', 15.0, 'range2');
    $redis->zAdd('test-raw-sortedset', 25.0, 'range3');
    
    echo "初始集合: ";
    $elements = $redis->zRange('test-raw-sortedset', 0, -1, true);
    foreach ($elements as $member => $score) {
        echo "$member:$score ";
    }
    echo "\n";
    
    // 尝试排他性下边界, 包含性上边界: (10.0, 20.0]
    $removedCount = $redis->zRemRangeByScore('test-raw-sortedset', '(10.0', '20.0');
    
    echo "删除的元素数量: " . $removedCount . "\n";
    echo "删除后的元素: ";
    $remaining = $redis->zRange('test-raw-sortedset', 0, -1, true);
    foreach ($remaining as $member => $score) {
        echo "$member:$score ";
    }
    echo "\n";
    
    // 再试试只使用排他性下边界: (10.0, 15.0)
    $redis->del('test-raw-sortedset2');
    $redis->zAdd('test-raw-sortedset2', 5.0, 'range1');
    $redis->zAdd('test-raw-sortedset2', 10.0, 'to-keep');
    $redis->zAdd('test-raw-sortedset2', 15.0, 'range2');
    $redis->zAdd('test-raw-sortedset2', 25.0, 'range3');
    
    echo "\n第二次测试 (15.0, 15.0): ";
    $removedCount2 = $redis->zRemRangeByScore('test-raw-sortedset2', '(15.0', '15.0');
    echo "删除的元素数量: " . $removedCount2 . "\n";
    echo "删除后的元素: ";
    $remaining2 = $redis->zRange('test-raw-sortedset2', 0, -1, true);
    foreach ($remaining2 as $member => $score) {
        echo "$member:$score ";
    }
    echo "\n";
    
    echo "测试2完成\n\n";
} catch (Exception $e) {
    echo "测试2出错: " . $e->getMessage() . "\n\n";
}

// 清理测试数据
try {
    $redis->del('test-detailed-sortedset');
    $redis->del('test-raw-sortedset');
    $redis->del('test-raw-sortedset2');
} catch (Exception $e) {
    echo "清理测试数据出错: " . $e->getMessage() . "\n";
}

echo "测试完成\n";