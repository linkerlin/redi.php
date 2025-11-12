<?php

require 'vendor/autoload.php';

use Rediphp\RedissonClient;

try {
    $client = new RedissonClient(['host' => '127.0.0.1', 'port' => 6379]);
    
    echo "=== RMap 迭代器测试 ===\n";
    
    // 测试数据损坏检测
    echo "\n=== 数据恢复测试 ===\n";
    $primaryData = $client->getMap('debug:primary:data');
    $backupData = $client->getMap('debug:backup:data');
    
    // 清理
    $primaryData->clear();
    $backupData->clear();
    
    // 设置备份数据
    $backupData->put('key1', 'backup_value1');
    $backupData->put('key2', 'backup_value2');
    
    echo "备份数据设置完成\n";
    echo "backupData->get('key1'): " . var_export($backupData->get('key1'), true) . "\n";
    echo "backupData->size(): " . $backupData->size() . "\n";
    
    // 从备份恢复
    $primaryData->clear();
    echo "开始从备份恢复数据...\n";
    
    $iterationCount = 0;
    foreach ($backupData as $key => $value) {
        $iterationCount++;
        echo "迭代 $iterationCount: key=" . var_export($key, true) . ", value=" . var_export($value, true) . "\n";
        $primaryData->put($key, $value);
    }
    
    echo "迭代完成，共处理 $iterationCount 个条目\n";
    
    // 验证恢复结果
    echo "primaryData->get('key1'): " . var_export($primaryData->get('key1'), true) . "\n";
    echo "primaryData->get('key2'): " . var_export($primaryData->get('key2'), true) . "\n";
    echo "primaryData->size(): " . $primaryData->size() . "\n";
    
    echo "\n=== 测试完成 ===\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}