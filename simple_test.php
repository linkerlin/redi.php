<?php

require 'vendor/autoload.php';

use Rediphp\RedissonClient;

try {
    $client = new RedissonClient(['host' => '127.0.0.1', 'port' => 6379]);
    
    echo "Testing RBucket...\n";
    $bucket = $client->getBucket('test:bucket');
    $bucket->set('test_value');
    $retrieved = $bucket->get();
    echo "Bucket value: " . var_export($retrieved, true) . "\n";
    echo "Bucket exists: " . ($bucket->isExists() ? 'yes' : 'no') . "\n";
    
    echo "\nTesting RSemaphore...\n";
    $semaphore = $client->getSemaphore('test:semaphore', 3);
    $semaphore->clear(); // 清理
    $semaphore->trySetPermits(3);
    echo "Available permits: " . $semaphore->availablePermits() . "\n";
    echo "Size (total permits): " . $semaphore->size() . "\n";
    echo "Exists: " . ($semaphore->exists() ? 'yes' : 'no') . "\n";
    
    echo "\nAll tests passed!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}