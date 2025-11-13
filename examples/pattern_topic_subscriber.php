<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Rediphp\RedissonClient;

/**
 * RPatternTopic 订阅者示例
 * 这个脚本作为模式订阅者，需要在单独的终端运行
 */

// 创建客户端
$client = new RedissonClient([
    'host' => '127.0.0.1',
    'port' => 6379,
]);

// 连接到Redis
if (!$client->connect()) {
    die("无法连接到Redis服务器\n");
}

echo "=== RPatternTopic 订阅者 ===\n\n";

// 场景1: 监控所有订单相关主题
echo "1. 启动订单模式订阅者（监听 order:*）\n";
$orderPatternTopic = $client->getPatternTopic('order:*');

$orderPatternTopic->subscribe(function($channel, $message) {
    echo "\n[订单监控] 收到消息:\n";
    echo "  来源频道: $channel\n";
    echo "  消息内容: " . json_encode($message, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
    // 根据频道名称进行不同的处理
    if (strpos($channel, 'order:created') !== false) {
        echo "  [处理] 新订单创建，开始处理...\n";
    } elseif (strpos($channel, 'order:updated') !== false) {
        echo "  [处理] 订单更新，同步状态...\n";
    } elseif (strpos($channel, 'order:cancelled') !== false) {
        echo "  [处理] 订单取消，执行退款...\n";
    }
});

echo "订单模式订阅者已启动，按 Ctrl+C 停止\n";

// 保持脚本运行
while (true) {
    sleep(1);
}

// 关闭连接
$client->shutdown();