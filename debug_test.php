<?php

require 'vendor/autoload.php';

use Rediphp\RedissonClient;

try {
    $client = new RedissonClient(['host' => '127.0.0.1', 'port' => 6379]);
    
    echo "=== Redis连接测试 ===\n";
    
    // 测试RMap
    echo "\n=== RMap测试 ===\n";
    $map = $client->getMap('debug:test:map');
    $map->clear();
    
    echo "put('key1', 'value1')\n";
    $result = $map->put('key1', 'value1');
    echo "Previous value: " . var_export($result, true) . "\n";
    
    echo "get('key1')\n";
    $value = $map->get('key1');
    echo "Retrieved value: " . var_export($value, true) . "\n";
    
    echo "size(): " . $map->size() . "\n";
    
    // 测试RBucket
    echo "\n=== RBucket测试 ===\n";
    $bucket = $client->getBucket('debug:test:bucket');
    $bucket->set('test_value');
    $retrieved = $bucket->get();
    echo "Bucket value: " . var_export($retrieved, true) . "\n";
    
    // 测试RSemaphore
    echo "\n=== RSemaphore测试 ===\n";
    $semaphore = $client->getSemaphore('debug:test:semaphore', 1);
    $semaphore->clear();
    $semaphore->trySetPermits(1);
    echo "Available permits: " . $semaphore->availablePermits() . "\n";
    echo "Size: " . $semaphore->size() . "\n";
    
    echo "\n=== 所有基础测试完成 ===\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}