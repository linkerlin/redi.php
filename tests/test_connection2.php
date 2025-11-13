<?php

require_once 'vendor/autoload.php';

use Rediphp\RedissonClient;

// 测试连接
$client = new RedissonClient([
    'host' => '127.0.0.1',
    'port' => 6379,
]);

try {
    if ($client->connect()) {
        echo "连接成功！\n";
        
        // 测试基本操作
        $bucket = $client->getBucket('test:connection');
        $bucket->set('test_value');
        $value = $bucket->get();
        echo "测试值: $value\n";
        $bucket->delete();
        
        echo "基本操作测试通过！\n";
    } else {
        echo "连接失败！\n";
    }
} catch (Exception $e) {
    echo "连接异常: " . $e->getMessage() . "\n";
}

$client->shutdown();