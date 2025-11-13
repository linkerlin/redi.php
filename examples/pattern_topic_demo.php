<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Rediphp\RedissonClient;

/**
 * RPatternTopic 模式匹配发布订阅示例
 * 演示如何使用模式匹配订阅多个相关主题
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

echo "=== RPatternTopic 模式匹配发布订阅示例 ===\n\n";

// 场景1: 监控所有订单相关主题
echo "1. 监控所有订单相关主题\n";
echo "------------------------\n";

// 创建模式主题订阅者（匹配所有以 'order:' 开头的主题）
$orderPatternTopic = $client->getPatternTopic('order:*');

$orderPatternTopic->subscribe(function($channel, $message) {
    echo "\n[模式订阅者] 收到订单消息:\n";
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

echo "模式订阅者已启动，监听所有 'order:*' 主题\n";

// 场景2: 监控系统事件
echo "\n2. 监控系统事件\n";
echo "--------------\n";

// 创建系统事件模式订阅者（匹配所有以 'system:' 开头的主题）
$systemPatternTopic = $client->getPatternTopic('system:*');

$systemPatternTopic->subscribe(function($channel, $message) {
    echo "\n[系统监控] 收到系统事件:\n";
    echo "  事件类型: $channel\n";
    echo "  事件级别: " . $message['level'] . "\n";
    echo "  消息: " . $message['message'] . "\n";
    echo "  时间: " . $message['timestamp'] . "\n";
    
    // 根据事件级别进行处理
    switch ($message['level']) {
        case 'ERROR':
            echo "  [警告] 严重错误，发送告警！\n";
            break;
        case 'WARNING':
            echo "  [注意] 警告信息，记录日志\n";
            break;
        case 'INFO':
            echo "  [信息] 普通信息，更新监控面板\n";
            break;
    }
});

echo "系统监控订阅者已配置，监听所有 'system:*' 主题\n";
// 场景3: 发布测试消息
echo "\n3. 发布测试消息\n";
echo "--------------\n";

// 创建具体主题
$orderCreatedTopic = $client->getTopic('order:created');
$orderUpdatedTopic = $client->getTopic('order:updated');
$orderCancelledTopic = $client->getTopic('order:cancelled');

$systemErrorTopic = $client->getTopic('system:error');
$systemWarningTopic = $client->getTopic('system:warning');
$systemInfoTopic = $client->getTopic('system:info');

// 发布订单相关消息
echo "发布订单消息:\n";

$orderCreatedTopic->publish([
    'order_id' => 'ORD001',
    'customer' => '张三',
    'amount' => 299.99,
    'status' => 'pending',
    'timestamp' => date('Y-m-d H:i:s')
]);

usleep(100000); // 100ms 延迟

$orderUpdatedTopic->publish([
    'order_id' => 'ORD001',
    'status' => 'processing',
    'updated_by' => 'system',
    'timestamp' => date('Y-m-d H:i:s')
]);

usleep(100000);

$orderCancelledTopic->publish([
    'order_id' => 'ORD002',
    'reason' => 'customer_request',
    'refund_amount' => 199.99,
    'timestamp' => date('Y-m-d H:i:s')
]);

echo "订单消息发布完成\n";

// 发布系统事件消息
echo "\n发布系统事件消息:\n";

$systemErrorTopic->publish([
    'level' => 'ERROR',
    'message' => '数据库连接失败',
    'component' => 'database',
    'timestamp' => date('Y-m-d H:i:s')
]);

usleep(100000);

$systemWarningTopic->publish([
    'level' => 'WARNING',
    'message' => '磁盘空间使用率超过80%',
    'component' => 'storage',
    'timestamp' => date('Y-m-d H:i:s')
]);

usleep(100000);

$systemInfoTopic->publish([
    'level' => 'INFO',
    'message' => '系统启动完成',
    'component' => 'system',
    'timestamp' => date('Y-m-d H:i:s')
]);

echo "系统事件消息发布完成\n";

// 场景4: 统计订阅者数量
echo "\n4. 订阅者统计\n";
echo "-------------\n";

echo "订单模式订阅者数量: " . $orderPatternTopic->countSubscribers() . "\n";
echo "系统模式订阅者数量: " . $systemPatternTopic->countSubscribers() . "\n";

// 场景5: 实际应用场景 - 微服务监控
echo "\n5. 实际应用场景 - 微服务监控\n";
echo "-----------------------------\n";

// 创建微服务监控模式订阅者
$microservicePatternTopic = $client->getPatternTopic('microservice:*');

$microservicePatternTopic->subscribe(function($channel, $message) {
    echo "\n[微服务监控] 服务事件:\n";
    echo "  服务名称: $channel\n";
    echo "  服务状态: " . $message['status'] . "\n";
    echo "  响应时间: " . $message['response_time'] . "ms\n";
    echo "  错误率: " . $message['error_rate'] . "%\n";
    
    // 健康检查
    if ($message['error_rate'] > 5) {
        echo "  [警告] 错误率过高，需要关注！\n";
    }
    if ($message['response_time'] > 1000) {
        echo "  [警告] 响应时间过长，可能影响用户体验！\n";
    }
});

echo "微服务监控订阅者已启动\n";

// 模拟微服务健康报告
$services = ['user-service', 'order-service', 'payment-service', 'notification-service'];

echo "\n模拟微服务健康报告:\n";
foreach ($services as $service) {
    $serviceTopic = $client->getTopic("microservice:$service");
    
    $healthReport = [
        'status' => 'healthy',
        'response_time' => rand(50, 150),
        'error_rate' => rand(0, 3),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    $serviceTopic->publish($healthReport);
    echo "  $service: 状态=" . $healthReport['status'] . 
         ", 响应时间=" . $healthReport['response_time'] . "ms" .
         ", 错误率=" . $healthReport['error_rate'] . "%\n";
    
    usleep(50000); // 50ms 延迟
}

// 场景6: 动态主题创建和订阅
echo "\n6. 动态主题创建和订阅\n";
echo "---------------------\n";

// 动态创建一些主题并发布消息
$dynamicTopics = ['news:sports', 'news:tech', 'news:finance', 'news:entertainment'];

// 创建动态模式订阅者
$newsPatternTopic = $client->getPatternTopic('news:*');

$newsPatternTopic->subscribe(function($channel, $message) {
    echo "\n[新闻订阅] 新文章发布:\n";
    echo "  分类: " . str_replace('news:', '', $channel) . "\n";
    echo "  标题: " . $message['title'] . "\n";
    echo "  作者: " . $message['author'] . "\n";
    echo "  发布时间: " . $message['publish_time'] . "\n";
});

echo "新闻订阅者已启动，监听所有 'news:*' 主题\n";

echo "\n发布新闻文章:\n";
foreach ($dynamicTopics as $topicName) {
    $topic = $client->getTopic($topicName);
    
    $article = [
        'title' => 'Sample Article for ' . str_replace('news:', '', $topicName),
        'author' => 'Author ' . rand(1, 5),
        'content' => 'This is a sample news article content...',
        'publish_time' => date('Y-m-d H:i:s')
    ];
    
    $topic->publish($article);
    echo "  发布到 $topicName: " . $article['title'] . "\n";
    
    usleep(50000);
}

// 清理
echo "\n清理示例数据...\n";
$orderCreatedTopic->clear();
$orderUpdatedTopic->clear();
$orderCancelledTopic->clear();
$systemErrorTopic->clear();
$systemWarningTopic->clear();
$systemInfoTopic->clear();

foreach ($services as $service) {
    $client->getTopic("microservice:$service")->clear();
}

foreach ($dynamicTopics as $topicName) {
    $client->getTopic($topicName)->clear();
}

// 关闭连接
$client->shutdown();
echo "连接已关闭\n";

echo "\n=== RPatternTopic 示例完成 ===\n";
echo "RPatternTopic 适用于以下场景:\n";
echo "- 微服务监控和告警\n";
echo "- 日志聚合和分析\n";
echo "- 事件总线\n";
echo "- 动态主题订阅\n";
echo "- 系统监控\n";
echo "优点：支持通配符模式匹配，一次订阅多个相关主题，灵活的事件处理\n";