<?php

require_once __DIR__ . '/vendor/autoload.php';

use Rediphp\RedissonClient;

// 创建客户端
$client = new RedissonClient([
    'host' => 'localhost',
    'port' => 6379
]);

try {
    $client->connect();
    echo "Connected to Redis\n";
    
    // 获取时间序列实例
    $ts = $client->getTimeSeries('debug:timeseries');
    $ts->clear();
    
    // 模拟测试数据
    $baseTime = time() * 1000;
    echo "Base time: $baseTime (type: " . gettype($baseTime) . ")\n";
    
    $complexData = [
        [$baseTime, 3.14159],
        [$baseTime + 1000, 2.71828],
        [$baseTime + 2000, 1.41421]
    ];
    
    echo "Data points to add:\n";
    foreach ($complexData as $point) {
        echo "  - Timestamp: {$point[0]} (type: " . gettype($point[0]) . "), Value: {$point[1]}\n";
    }
    
    // 添加数据点
    $result = $ts->addAll($complexData);
    echo "\nAdd result: " . json_encode($result) . "\n";
    
    // 检查存储的数据
    echo "\nChecking stored data:\n";
    foreach ($complexData as [$timestamp, $expectedValue]) {
        echo "  Looking for timestamp: $timestamp\n";
        $data = $ts->get($timestamp);
        if ($data) {
            echo "  Found: " . json_encode($data) . "\n";
        } else {
            echo "  NOT FOUND!\n";
            // 尝试获取附近的时间戳
            echo "  Trying nearby timestamps:\n";
            for ($i = -5; $i <= 5; $i++) {
                $testTimestamp = $timestamp + $i;
                $nearbyData = $ts->get($testTimestamp);
                if ($nearbyData) {
                    echo "    Found at $testTimestamp: " . json_encode($nearbyData) . "\n";
                }
            }
        }
    }
    
    // 检查所有数据点
    echo "\nAll data points in range:\n";
    $allData = $ts->range();
    foreach ($allData as $data) {
        echo "  " . json_encode($data) . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}