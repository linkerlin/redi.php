<?php

require_once 'vendor/autoload.php';

use Rediphp\RedissonClient;

// 创建客户端
$client = new RedissonClient();

// 获取Redis连接
$redis = $client->getRedis();

try {
    echo "=== 模拟testHyperLogLogCardinality测试场景 ===\n";
    
    $hyperLogLog = new Rediphp\RHyperLogLog($redis, 'test:hll:cardinality');
    
    // 测试添加元素
    echo "添加item1: ";
    var_dump($hyperLogLog->add('item1'));
    
    echo "添加item2: ";
    var_dump($hyperLogLog->add('item2'));
    
    echo "添加item3: ";
    var_dump($hyperLogLog->add('item3'));
    
    // 测试基数统计
    $count = $hyperLogLog->count();
    echo "基数统计: ";
    var_dump($count);
    
    // 测试合并功能
    $hll2 = new Rediphp\RHyperLogLog($redis, 'test:hll:integration2');
    
    echo "添加item4到hll2: ";
    var_dump($hll2->add('item4'));
    
    echo "添加item5到hll2: ";
    var_dump($hll2->add('item5'));
    
    echo "hll2基数: ";
    var_dump($hll2->count());
    
    // 这是测试失败的地方
    echo "执行merge操作: ";
    $mergeResult = $hyperLogLog->merge(['test:hll:integration2']);
    var_dump($mergeResult);
    
    echo "merge后基数: ";
    $mergedCount = $hyperLogLog->count();
    var_dump($mergedCount);
    
    echo "原始基数: $count, 合并后基数: $mergedCount\n";
    
    // 验证合并结果
    if ($mergedCount >= $count) {
        echo "✅ 合并成功，基数增加\n";
    } else {
        echo "❌ 合并失败，基数未增加\n";
    }
    
    // 清理测试数据
    $hyperLogLog->clear();
    $hll2->clear();
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "堆栈: " . $e->getTraceAsString() . "\n";
} finally {
    $client->returnRedis($redis);
}

?>