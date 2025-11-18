<?php

require_once __DIR__ . '/vendor/autoload.php';

use Rediphp\RedissonClient;
use Rediphp\RHyperLogLog;

// 创建客户端
$client = new RedissonClient();
$redis = $client->getRedis();

try {
    $hyperLogLog = new RHyperLogLog($redis, 'test:hll:debug');
    
    echo "Testing RHyperLogLog add method with empty values:\n";
    
    // 测试空字符串
    try {
        $result = $hyperLogLog->add('');
        echo "Empty string: Result = " . var_export($result, true) . " (No exception thrown)\n";
    } catch (\Exception $e) {
        echo "Empty string: Exception = " . $e->getMessage() . "\n";
    }
    
    // 测试null
    try {
        $result = $hyperLogLog->add(null);
        echo "Null: Result = " . var_export($result, true) . " (No exception thrown)\n";
    } catch (\Exception $e) {
        echo "Null: Exception = " . $e->getMessage() . "\n";
    }
    
    // 测试0和'0'（应该允许）
    try {
        $result = $hyperLogLog->add(0);
        echo "Zero: Result = " . var_export($result, true) . "\n";
    } catch (\Exception $e) {
        echo "Zero: Exception = " . $e->getMessage() . "\n";
    }
    
    try {
        $result = $hyperLogLog->add('0');
        echo "String zero: Result = " . var_export($result, true) . "\n";
    } catch (\Exception $e) {
        echo "String zero: Exception = " . $e->getMessage() . "\n";
    }
    
    // 测试正常值
    try {
        $result = $hyperLogLog->add('test');
        echo "Normal value: Result = " . var_export($result, true) . "\n";
    } catch (\Exception $e) {
        echo "Normal value: Exception = " . $e->getMessage() . "\n";
    }
    
    // 清理
    $hyperLogLog->clear();
    
} finally {
    $client->returnRedis($redis);
}