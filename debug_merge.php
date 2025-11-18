<?php

require_once 'vendor/autoload.php';

use Rediphp\RedissonClient;

// 创建客户端
$client = new RedissonClient();

// 获取Redis连接
$redis = $client->getRedis();

try {
    echo "=== 测试HyperLogLog merge方法 ===\n";
    
    // 创建几个HyperLogLog
    $hll1 = new Rediphp\RHyperLogLog($redis, 'test:hll:debug1');
    $hll2 = new Rediphp\RHyperLogLog($redis, 'test:hll:debug2');
    
    // 添加一些元素
    echo "添加元素到hll1: ";
    var_dump($hll1->add('item1'));
    
    echo "添加元素到hll2: ";
    var_dump($hll2->add('item2'));
    
    echo "hll1基数: ";
    var_dump($hll1->count());
    
    echo "hll2基数: ";
    var_dump($hll2->count());
    
    // 测试merge方法
    echo "执行merge操作: ";
    $result = $hll1->merge(['test:hll:debug2']);
    var_dump($result);
    
    echo "merge后hll1基数: ";
    var_dump($hll1->count());
    
    // 测试直接调用Redis pfMerge
    echo "\n=== 测试直接Redis pfMerge ===\n";
    $redis->del('test:hll:direct1', 'test:hll:direct2', 'test:hll:direct_merged');
    
    echo "pfAdd到direct1: ";
    var_dump($redis->pfAdd('test:hll:direct1', ['item1']));
    
    echo "pfAdd到direct2: ";
    var_dump($redis->pfAdd('test:hll:direct2', ['item2']));
    
    echo "pfCount direct1: ";
    var_dump($redis->pfCount('test:hll:direct1'));
    
    echo "pfCount direct2: ";
    var_dump($redis->pfCount('test:hll:direct2'));
    
    echo "执行pfMerge: ";
    $directResult = $redis->pfMerge('test:hll:direct_merged', ['test:hll:direct1', 'test:hll:direct2']);
    var_dump($directResult);
    
    echo "pfCount merged: ";
    var_dump($redis->pfCount('test:hll:direct_merged'));
    
    // 清理
    $hll1->clear();
    $hll2->clear();
    $redis->del('test:hll:direct1', 'test:hll:direct2', 'test:hll:direct_merged');
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "堆栈: " . $e->getTraceAsString() . "\n";
} finally {
    $client->returnRedis($redis);
}

?>