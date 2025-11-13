<?php

require_once 'vendor/autoload.php';

use Rediphp\RedissonClient;

// 测试在PHPUnit环境中Redis连接
$client = new RedissonClient([
    'host' => '127.0.0.1',
    'port' => 6379,
]);

try {
    echo "尝试连接Redis...\n";
    
    // 使用反射来访问私有方法，模拟PHPUnit环境中的连接
    $reflection = new ReflectionClass($client);
    $redisProperty = $reflection->getProperty('redis');
    $redisProperty->setAccessible(true);
    $redis = $redisProperty->getValue($client);
    
    echo "Redis对象类型: " . get_class($redis) . "\n";
    
    // 直接测试Redis连接
    $connected = $redis->connect('127.0.0.1', 6379, 5);
    
    if ($connected) {
        echo "Redis连接成功！\n";
        echo "Ping响应: " . $redis->ping() . "\n";
        $redis->close();
    } else {
        echo "Redis连接失败！\n";
        echo "最后错误: " . $redis->getLastError() . "\n";
    }
    
} catch (Exception $e) {
    echo "连接异常: " . $e->getMessage() . "\n";
    echo "异常类型: " . get_class($e) . "\n";
}

echo "\nPHP版本: " . PHP_VERSION . "\n";
echo "PHP配置文件: " . php_ini_loaded_file() . "\n";
echo "已加载扩展: " . implode(', ', get_loaded_extensions()) . "\n";