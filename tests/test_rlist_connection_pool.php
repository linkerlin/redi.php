<?php

require_once 'vendor/autoload.php';

use Rediphp\RedissonClient;

// 创建带连接池的RedissonClient
$client = new RedissonClient([
    'host' => '127.0.0.1',
    'port' => 6379,
    'database' => 0,
    'use_pool' => true,
    'pool_config' => [
        'max_connections' => 10,
        'min_connections' => 2,
        'connection_timeout' => 5,
        'command_timeout' => 5,
    ]
]);

echo "开始 RList 连接池测试...\n";

try {
    // 创建RList实例
    $list = $client->getList('test:list');
    
    // 清空测试
    $list->clear();
    
    echo "测试添加元素...\n";
    // 测试添加元素
    $result1 = $list->add("apple");
    $result2 = $list->add("banana");
    $result3 = $list->add("cherry");
    
    echo "添加结果: " . (int)$result1 . ", " . (int)$result2 . ", " . (int)$result3 . "\n";
    
    // 测试大小
    echo "列表大小: " . $list->size() . "\n";
    
    // 测试获取元素
    echo "索引 0 的元素: " . $list->get(0) . "\n";
    echo "索引 1 的元素: " . $list->get(1) . "\n";
    
    // 测试设置元素
    $prev = $list->set(1, "orange");
    echo "设置前索引 1 的元素: " . $prev . "\n";
    echo "设置后索引 1 的元素: " . $list->get(1) . "\n";
    
    // 测试contains
    echo "包含 apple: " . ($list->contains("apple") ? "是" : "否") . "\n";
    echo "包含 banana: " . ($list->contains("banana") ? "是" : "否") . "\n";
    
    // 测试批量添加
    echo "测试批量添加...\n";
    $elements = ["grape", "peach", "melon"];
    $addAllResult = $list->addAll($elements);
    echo "批量添加结果: " . ($addAllResult ? "成功" : "失败") . "\n";
    echo "新大小: " . $list->size() . "\n";
    
    // 测试转换为数组
    $array = $list->toArray();
    echo "列表元素: " . json_encode($array, JSON_UNESCAPED_UNICODE) . "\n";
    
    // 测试范围获取
    $range = $list->range(0, 2);
    echo "前3个元素: " . json_encode($range, JSON_UNESCAPED_UNICODE) . "\n";
    
    // 测试按索引删除
    $removed = $list->removeByIndex(0);
    echo "删除索引 0 的元素: " . $removed . "\n";
    echo "删除后大小: " . $list->size() . "\n";
    
    // 测试按值删除
    $removeResult = $list->remove("orange");
    echo "删除 orange: " . ($removeResult ? "成功" : "失败") . "\n";
    echo "最终大小: " . $list->size() . "\n";
    
    // 测试trim
    $list->trim(0, 2);
    echo "Trim 后大小: " . $list->size() . "\n";
    
    // 测试isEmpty
    $list->clear();
    echo "清空后是否为空: " . ($list->isEmpty() ? "是" : "否") . "\n";
    
    echo "RList 连接池测试完成\n";
    
} catch (Exception $e) {
    echo "测试失败: " . $e->getMessage() . "\n";
    exit(1);
}