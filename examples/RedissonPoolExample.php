<?php

require_once __DIR__ . '/../src/RedissonPool.php';

use RediPhp\RedissonPool;

/**
 * RedissonPool使用示例
 * 
 * 本示例展示了如何使用RedissonPool连接池来管理Redis连接
 */

// 基本配置
$config = [
    'host' => '127.0.0.1',
    'port' => 6379,
    'timeout' => 3.0,
    'password' => null, // 如果有密码，请填写
    'database' => 0,
    'prefix' => 'redi:',
    'min_size' => 2,
    'max_size' => 10,
    'connect_timeout' => 2.0,
    'read_timeout' => 2.0,
    'retry_interval' => 1.0,
    'max_retries' => 3,
    'validate_on_acquire' => true,
    'validate_on_return' => true,
    'max_idle_time' => 60,
    'max_lifetime' => 300,
    'wait_timeout' => 5.0,
    'acquire_timeout' => 3.0,
];

// 创建连接池
$pool = new RedissonPool($config);

// 示例1: 基本使用
function basicUsageExample(RedissonPool $pool) {
    echo "=== 基本使用示例 ===\n";
    
    try {
        // 获取连接
        $redis = $pool->acquire();
        
        // 使用连接执行Redis命令
        $redis->set('example:key', 'Hello, RedissonPool!');
        $value = $redis->get('example:key');
        echo "设置并获取值: $value\n";
        
        // 释放连接回池中
        $pool->release($redis);
        
        // 使用with方法自动管理连接
        $result = $pool->with(function($redis) {
            $redis->incr('example:counter');
            return $redis->get('example:counter');
        });
        echo "计数器值: $result\n";
        
    } catch (Exception $e) {
        echo "错误: " . $e->getMessage() . "\n";
    }
}

// 示例2: 连接池状态监控
function poolStatusExample(RedissonPool $pool) {
    echo "\n=== 连接池状态监控示例 ===\n";
    
    $status = $pool->getPoolStatus();
    echo "连接池状态:\n";
    echo "  总连接数: {$status['total_connections']}\n";
    echo "  空闲连接数: {$status['idle_connections']}\n";
    echo "  活跃连接数: {$status['active_connections']}\n";
    echo "  等待中的请求数: {$status['waiting_requests']}\n";
    echo "  连接池是否关闭: " . ($status['is_closed'] ? '是' : '否') . "\n";
    
    $stats = $pool->getAcquireStats();
    echo "\n获取连接统计:\n";
    echo "  总获取次数: {$stats['total_acquires']}\n";
    echo "  平均等待时间: " . number_format($stats['avg_wait_time'] * 1000, 2) . "ms\n";
    echo "  最大等待时间: " . number_format($stats['max_wait_time'] * 1000, 2) . "ms\n";
    echo "  最小等待时间: " . number_format($stats['min_wait_time'] * 1000, 2) . "ms\n";
}

// 示例3: 并发使用模拟
function concurrentUsageExample(RedissonPool $pool) {
    echo "\n=== 并发使用模拟示例 ===\n";
    
    // 模拟多个并发请求
    $tasks = [];
    for ($i = 0; $i < 5; $i++) {
        $tasks[] = function() use ($pool, $i) {
            return $pool->with(function($redis) use ($i) {
                // 模拟一些工作
                $key = "concurrent:task:$i";
                $redis->set($key, "Task $i completed at " . date('Y-m-d H:i:s'));
                usleep(100000); // 100ms
                return $redis->get($key);
            });
        };
    }
    
    // 执行任务
    foreach ($tasks as $index => $task) {
        try {
            $result = $task();
            echo "任务 $index 结果: $result\n";
        } catch (Exception $e) {
            echo "任务 $index 错误: " . $e->getMessage() . "\n";
        }
    }
}

// 示例4: 错误处理
function errorHandlingExample(RedissonPool $pool) {
    echo "\n=== 错误处理示例 ===\n";
    
    try {
        // 尝试执行一个可能失败的命令
        $pool->with(function($redis) {
            // 故意使用一个无效的命令
            $redis->rawCommand('INVALID_COMMAND');
        });
    } catch (Exception $e) {
        echo "捕获到错误: " . $e->getMessage() . "\n";
    }
    
    // 检查连接池状态
    $status = $pool->getPoolStatus();
    echo "错误后连接池状态: 总连接数 {$status['total_connections']}, 空闲连接数 {$status['idle_connections']}\n";
}

// 示例5: 连接池配置调整
function configurationExample() {
    echo "\n=== 连接池配置示例 ===\n";
    
    // 高负载配置
    $highLoadConfig = [
        'host' => '127.0.0.1',
        'port' => 6379,
        'min_size' => 5,
        'max_size' => 50,
        'acquire_timeout' => 5.0,
        'wait_timeout' => 10.0,
        'max_idle_time' => 30,
        'max_lifetime' => 180,
    ];
    
    // 低延迟配置
    $lowLatencyConfig = [
        'host' => '127.0.0.1',
        'port' => 6379,
        'min_size' => 10,
        'max_size' => 20,
        'connect_timeout' => 0.5,
        'read_timeout' => 0.5,
        'acquire_timeout' => 1.0,
        'validate_on_acquire' => false, // 减少验证开销
        'validate_on_return' => false,
    ];
    
    echo "高负载配置: 最小连接数 {$highLoadConfig['min_size']}, 最大连接数 {$highLoadConfig['max_size']}\n";
    echo "低延迟配置: 连接超时 {$lowLatencyConfig['connect_timeout']}s, 获取超时 {$lowLatencyConfig['acquire_timeout']}s\n";
}

// 运行示例
echo "RedissonPool 使用示例\n";
echo "====================\n";

// 基本使用
basicUsageExample($pool);

// 连接池状态
poolStatusExample($pool);

// 并发使用
concurrentUsageExample($pool);

// 错误处理
errorHandlingExample($pool);

// 配置示例
configurationExample();

// 关闭连接池
echo "\n关闭连接池...\n";
$pool->close();
echo "连接池已关闭\n";