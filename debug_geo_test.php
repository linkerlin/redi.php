<?php

require_once __DIR__ . '/vendor/autoload.php';

use Rediphp\RedissonClient;
use Rediphp\RGeo;

try {
    $client = new RedissonClient([
        'scheme' => 'tcp',
        'host' => '127.0.0.1',
        'port' => 6379,
    ]);
    
    $redis = $client->getRedis();
    $geo = new RGeo($redis, 'test:geo:basic_operations');
    
    // 清理之前的数据
    echo "Cleaning up existing data...\n";
    $geo->clear();
    
    echo "Testing RGeo add method step by step...\n";
    
    // 第一次添加 - 应该返回1
    $result1 = $geo->add(116.4074, 39.9042, 'Beijing');
    echo "First add Beijing result: " . var_export($result1, true) . "\n";
    
    // 第二次添加相同的数据 - 应该返回0
    $result2 = $geo->add(116.4074, 39.9042, 'Beijing');
    echo "Second add Beijing result: " . var_export($result2, true) . "\n";
    
    // 添加不同的城市
    $result3 = $geo->add(121.4737, 31.2304, 'Shanghai');
    echo "Add Shanghai result: " . var_export($result3, true) . "\n";
    
    // 检查键是否存在和成员数量
    $exists = $redis->exists('test:geo:basic_operations');
    echo "Key exists: " . var_export($exists, true) . "\n";
    
    $members = $redis->zRange('test:geo:basic_operations', 0, -1);
    echo "Members in geo set: " . var_export($members, true) . "\n";
    
    $client->shutdown();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}