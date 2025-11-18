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
    $geo = new RGeo($redis, 'test:geo:debug');
    
    // 清理之前的数据
    $geo->clear();
    
    echo "Testing RGeo add method...\n";
    
    // 添加一个地理位置
    $result = $geo->add(116.4074, 39.9042, 'Beijing');
    echo "Add Beijing result: " . var_export($result, true) . "\n";
    
    // 检查Redis的geoAdd返回值
    $rawResult = $redis->geoAdd('test:geo:debug', 116.4074, 39.9042, 'Beijing');
    echo "Raw Redis geoAdd result: " . var_export($rawResult, true) . "\n";
    
    // 检查键是否存在
    $exists = $redis->exists('test:geo:debug');
    echo "Key exists: " . var_export($exists, true) . "\n";
    
    // 检查成员位置
    $position = $geo->position('Beijing');
    echo "Beijing position: " . var_export($position, true) . "\n";
    
    $client->shutdown();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}