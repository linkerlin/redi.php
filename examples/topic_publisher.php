<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Rediphp\RedissonClient;

/**
 * RTopic 发布者示例
 * 这个脚本作为消息发布者，向不同主题发布消息
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

echo "=== RTopic 发布者 ===\n\n";

// 创建不同主题
$orderTopic = $client->getTopic('orders');
$paymentTopic = $client->getTopic('payments');
$notificationTopic = $client->getTopic('notifications');
$analyticsTopic = $client->getTopic('analytics');

// 发布不同类型的消息
$messages = [
    [
        'topic' => $orderTopic,
        'data' => [
            'type' => 'order_created',
            'order_id' => 'ORD001',
            'customer' => '张三',
            'amount' => 299.99,
            'items' => ['商品A', '商品B', '商品C'],
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ],
    [
        'topic' => $paymentTopic,
        'data' => [
            'type' => 'payment_success',
            'payment_id' => 'PAY001',
            'order_id' => 'ORD001',
            'amount' => 299.99,
            'method' => 'alipay',
            'status' => 'completed',
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ],
    [
        'topic' => $notificationTopic,
        'data' => [
            'type' => 'email_notification',
            'recipient' => 'customer@example.com',
            'subject' => '订单确认',
            'content' => '您的订单 ORD001 已确认，正在处理中...',
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ],
    [
        'topic' => $analyticsTopic,
        'data' => [
            'type' => 'user_behavior',
            'user_id' => 'USER001',
            'action' => 'purchase',
            'value' => 299.99,
            'category' => 'electronics',
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]
];

echo "发布消息:\n";
foreach ($messages as $index => $message) {
    $topicName = '';
    switch ($message['topic']) {
        case $orderTopic: $topicName = 'orders'; break;
        case $paymentTopic: $topicName = 'payments'; break;
        case $notificationTopic: $topicName = 'notifications'; break;
        case $analyticsTopic: $topicName = 'analytics'; break;
    }
    
    echo ($index + 1) . ". 发布到主题 [$topicName]: " . $message['data']['type'] . "\n";
    $message['topic']->publish($message['data']);
    usleep(100000); // 100ms 延迟
}

echo "\n所有消息发布完成！\n";

// 持续发布实时消息
echo "\n开始持续发布实时消息（按 Ctrl+C 停止）...\n";
$counter = 1;

while (true) {
    $realTimeMessage = [
        'type' => 'real_time_event',
        'event_id' => 'EVT' . str_pad($counter, 6, '0', STR_PAD_LEFT),
        'data' => 'Real-time message ' . $counter,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    $analyticsTopic->publish($realTimeMessage);
    echo "发布实时消息 [$counter]: " . $realTimeMessage['event_id'] . "\n";
    
    $counter++;
    sleep(2); // 每2秒发布一条消息
}

// 注意：这个脚本会持续运行，需要手动停止
// 关闭连接的操作不会执行到
// $client->shutdown();