<?php

// 检查Redis扩展是否已加载
if (!extension_loaded('redis')) {
    echo "Redis扩展未加载！\n";
    echo "已加载的扩展：\n";
    print_r(get_loaded_extensions());
} else {
    echo "Redis扩展已加载！\n";
    
    // 检查Redis类是否存在
    if (class_exists('Redis')) {
        echo "Redis类存在！\n";
        
        // 测试Redis连接
        $redis = new Redis();
        try {
            $connected = $redis->connect('127.0.0.1', 6379, 5);
            if ($connected) {
                echo "Redis连接成功！\n";
                echo "Ping响应: " . $redis->ping() . "\n";
                $redis->close();
            } else {
                echo "Redis连接失败！\n";
                echo "错误信息: " . $redis->getLastError() . "\n";
            }
        } catch (Exception $e) {
            echo "Redis连接异常: " . $e->getMessage() . "\n";
        }
    } else {
        echo "Redis类不存在！\n";
    }
}

echo "\nPHP版本: " . PHP_VERSION . "\n";
echo "PHP配置文件: " . php_ini_loaded_file() . "\n";