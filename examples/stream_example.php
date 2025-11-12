<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Rediphp\RedissonClient;

/**
 * RStream 流数据结构使用示例
 * 
 * RStream 提供了基于Redis Streams的流数据处理能力，
 * 支持消息队列、事件溯源、日志收集等应用场景。
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

echo "=== RStream 流数据示例 ===\n\n";

// 创建RStream实例
$stream = $client->getStream('example:stream');
$stream->clear();

// 场景1: 基本流操作
echo "1. 基本流操作\n";
echo "-------------\n";

echo "添加流条目:\n";
$entries = [
    ['event' => 'user_login', 'user_id' => '1001', 'ip' => '192.168.1.100', 'timestamp' => time()],
    ['event' => 'page_view', 'user_id' => '1001', 'page' => '/home', 'timestamp' => time()],
    ['event' => 'product_view', 'user_id' => '1001', 'product_id' => 'SKU123', 'timestamp' => time()]
];

$entryIds = [];
foreach ($entries as $entry) {
    $id = $stream->add($entry);
    $entryIds[] = $id;
    echo "  - 添加事件: {$entry['event']}, ID: $id\n";
}

echo "\n流长度: " . $stream->length() . "\n";

// 读取所有条目
echo "\n读取所有流条目:\n";
$allEntries = $stream->read();
foreach ($allEntries as $id => $data) {
    echo "  ID: $id => " . json_encode($data) . "\n";
}

echo "\n";

// 场景2: 范围查询
echo "2. 范围查询\n";
echo "-----------\n";

// 添加更多条目用于范围查询
$moreEntries = [
    ['event' => 'search_query', 'query' => 'laptop', 'results' => '150'],
    ['event' => 'add_to_cart', 'product_id' => 'SKU123', 'quantity' => '1'],
    ['event' => 'checkout_start', 'cart_value' => '899.99'],
    ['event' => 'payment_success', 'order_id' => 'ORD456', 'amount' => '899.99']
];

foreach ($moreEntries as $entry) {
    $stream->add($entry);
}

// 范围查询（假设我们知道某些ID）
$entryIdArray = array_keys($allEntries);
if (count($entryIdArray) >= 2) {
    $startId = $entryIdArray[1];
    $endId = $entryIdArray[count($entryIdArray) - 1];
    
    echo "范围查询从 $startId 到 $endId:\n";
    $rangeEntries = $stream->read($startId, $endId);
    foreach ($rangeEntries as $id => $data) {
        echo "  ID: $id => " . json_encode($data) . "\n";
    }
}

echo "\n";

// 场景3: 消费者组
echo "3. 消费者组\n";
echo "-----------\n";

// 创建消费者组
$stream->createGroup('analytics-group', '0');
echo "创建消费者组: analytics-group\n";

$stream->createGroup('monitoring-group', '0');
echo "创建消费者组: monitoring-group\n";

// 从不同消费者组读取
$analyticsEntries = $stream->readGroup('analytics-group', 'consumer-1', '0');
echo "\nAnalytics组读取的条目数: " . count($analyticsEntries) . "\n";

$monitoringEntries = $stream->readGroup('monitoring-group', 'consumer-1', '0');
echo "Monitoring组读取的条目数: " . count($monitoringEntries) . "\n";

// 确认消费
$ackIds = array_slice(array_keys($analyticsEntries), 0, 2);
if (!empty($ackIds)) {
    $acked = $stream->ack('analytics-group', $ackIds);
    echo "Analytics组确认消费条目数: $acked\n";
}

echo "\n";

// 场景4: 待处理消息查询
echo "4. 待处理消息查询\n";
echo "-----------------\n";

$pendingInfo = $stream->pending('analytics-group');
echo "Analytics组待处理消息信息:\n";
if (is_array($pendingInfo)) {
    foreach ($pendingInfo as $key => $value) {
        echo "  $key: " . (is_array($value) ? json_encode($value) : $value) . "\n";
    }
}

echo "\n";

// 场景5: 流修剪
echo "5. 流修剪\n";
echo "---------\n";

$originalLength = $stream->length();
echo "原始流长度: $originalLength\n";

// 修剪到指定长度
$trimmed = $stream->trim(5);
echo "修剪后移除的条目数: $trimmed\n";

$newLength = $stream->length();
echo "修剪后流长度: $newLength\n";

echo "\n修剪后的流条目:\n";
$trimmedEntries = $stream->read();
foreach ($trimmedEntries as $id => $data) {
    echo "  ID: $id => " . json_encode($data) . "\n";
}

echo "\n";

// 场景6: 最大长度限制
echo "6. 最大长度限制\n";
echo "---------------\n";

$limitedStream = $client->getStream('example:limited-stream');
$limitedStream->clear();

echo "创建有限长度的流（maxlen=3）:\n";
for ($i = 1; $i <= 5; $i++) {
    $id = $limitedStream->add(['message' => "message-$i"], '*', ['maxlen' => 3]);
    echo "  添加消息 $i, ID: $id, 当前长度: " . $limitedStream->length() . "\n";
}

echo "\n最终流内容:\n";
$limitedEntries = $limitedStream->read();
foreach ($limitedEntries as $id => $data) {
    echo "  ID: $id => " . json_encode($data) . "\n";
}

echo "\n";

// 场景7: 批量添加
echo "7. 批量添加\n";
echo "-----------\n";

$batchStream = $client->getStream('example:batch-stream');
$batchStream->clear();

$batchEntries = [
    [['sensor' => 'temp-001', 'value' => '23.5', 'unit' => 'celsius'], '*'],
    [['sensor' => 'temp-002', 'value' => '24.1', 'unit' => 'celsius'], '*'],
    [['sensor' => 'humidity-001', 'value' => '65.2', 'unit' => 'percent'], '*'],
    [['sensor' => 'pressure-001', 'value' => '1013.25', 'unit' => 'hpa'], '*']
];

$batchIds = $batchStream->addAll($batchEntries);
echo "批量添加 " . count($batchEntries) . " 个传感器读数\n";
echo "生成的ID数量: " . count($batchIds) . "\n";

echo "\n批量添加的传感器数据:\n";
$batchResults = $batchStream->read();
foreach ($batchResults as $id => $data) {
    echo "  ID: $id => " . json_encode($data) . "\n";
}

echo "\n";

// 场景8: 实际应用 - 日志收集系统
echo "8. 实际应用 - 日志收集系统\n";
echo "---------------------------\n";

$logStream = $client->getStream('example:application-logs');
$logStream->clear();

// 模拟应用日志
$logLevels = ['INFO', 'WARNING', 'ERROR', 'DEBUG'];
$components = ['auth', 'database', 'api', 'cache', 'queue'];

for ($i = 0; $i < 10; $i++) {
    $logEntry = [
        'timestamp' => time() + $i,
        'level' => $logLevels[array_rand($logLevels)],
        'component' => $components[array_rand($components)],
        'message' => "Sample log message $i",
        'request_id' => 'req-' . uniqid()
    ];
    
    $logId = $logStream->add($logEntry);
    echo "  添加日志: [{$logEntry['level']}] {$logEntry['component']} - {$logEntry['message']}, ID: $logId\n";
}

echo "\n日志流长度: " . $logStream->length() . "\n";

// 创建日志消费者组
$logStream->createGroup('log-processor', '0');
echo "创建日志处理消费者组\n";

// 模拟日志处理
$processedLogs = $logStream->readGroup('log-processor', 'log-worker-1', '0', 5);
echo "\n处理的日志条目数: " . count($processedLogs) . "\n";

// 确认处理完成
if (!empty($processedLogs)) {
    $logIds = array_keys($processedLogs);
    $ackedLogs = $logStream->ack('log-processor', $logIds);
    echo "确认处理的日志数: $ackedLogs\n";
}

echo "\n";

// 场景9: 错误处理
echo "9. 错误处理\n";
echo "-----------\n";

$emptyStream = $client->getStream('example:empty-stream');
$emptyStream->clear();

echo "空流长度: " . $emptyStream->length() . "\n";
echo "空流是否存在: " . ($emptyStream->exists() ? '是' : '否') . "\n";

// 测试删除操作
$deleteTestStream = $client->getStream('example:delete-test');
$deleteTestStream->clear();

$deleteId = $deleteTestStream->add(['test' => 'data']);
echo "\n添加测试数据，ID: $deleteId\n";
echo "删除前长度: " . $deleteTestStream->length() . "\n";

$deleted = $deleteTestStream->delete([$deleteId]);
echo "删除结果: $deleted\n";
echo "删除后长度: " . $deleteTestStream->length() . "\n";

echo "\n";

// 清理数据
echo "清理示例数据...\n";
$stream->clear();
$limitedStream->clear();
$batchStream->clear();
$logStream->clear();
$emptyStream->clear();
$deleteTestStream->clear();

// 删除消费者组
$stream->deleteGroup('analytics-group');
$stream->deleteGroup('monitoring-group');
$logStream->deleteGroup('log-processor');

// 关闭连接
$client->shutdown();
echo "连接已关闭\n";

echo "\n=== RStream 示例完成 ===\n";
echo "RStream 适用于以下场景:\n";
echo "- 消息队列系统\n";
echo "- 事件溯源\n";
echo "- 日志收集和处理\n";
echo "- 实时数据流处理\n";
echo "- 传感器数据收集\n";
echo "优点：支持消费者组，消息持久化，可按时间范围查询\n";