<?php

require_once 'vendor/autoload.php';

use Rediphp\RedissonClient;

// 创建客户端
$client = new RedissonClient();

// 获取Redis连接
$redis = $client->getRedis();

try {
    // 创建几个HyperLogLog
    $hll1 = new Rediphp\RHyperLogLog($redis, 'test:hll:merge1');
    $hll2 = new Rediphp\RHyperLogLog($redis, 'test:hll:merge2');
    
    // 添加一些元素
    $hll1->add('item1');
    $hll2->add('item2');
    
    // 测试merge方法
    $result = $hll1->merge(['test:hll:merge2']);
    
    echo "Merge result: ";
    var_dump($result);
    
    // 测试直接调用Redis pfMerge
    $redis->del('test:hll:direct1', 'test:hll:direct2', 'test:hll:direct_merged');
    $redis->pfAdd('test:hll:direct1', ['item1']);
    $redis->pfAdd('test:hll:direct2', ['item2']);
    
    $directResult = $redis->pfMerge('test:hll:direct_merged', ['test:hll:direct1', 'test:hll:direct2']);
    
    echo "Direct pfMerge result: ";
    var_dump($directResult);
    
    // 清理
    $hll1->clear();
    $hll2->clear();
    $redis->del('test:hll:direct1', 'test:hll:direct2', 'test:hll:direct_merged');
    
} finally {
    $client->returnRedis($redis);
}

?>