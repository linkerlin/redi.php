<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Rediphp\RedissonClient;

/**
 * 专业数据结构使用示例
 * 
 * 本示例演示如何使用RHyperLogLog、RGeo、RStream、RTimeSeries等
 * 专业分布式数据结构
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

echo "=== 专业数据结构使用示例 ===\n\n";

// 1. RHyperLogLog - 基数统计
echo "1. RHyperLogLog - 基数统计\n";
echo "------------------------\n";

$hyperLogLog = $client->getHyperLogLog('demo:hyperloglog');
$hyperLogLog->clear();

// 模拟用户访问统计
$users = ['user1', 'user2', 'user3', 'user4', 'user5', 'user1', 'user2', 'user6'];
echo "添加用户访问记录:\n";
foreach ($users as $user) {
    $hyperLogLog->add($user);
    echo "  - 用户: $user\n";
}

$uniqueUsers = $hyperLogLog->count();
echo "独立用户数估计: $uniqueUsers\n";
echo "实际独立用户数: 6\n\n";

// 2. RGeo - 地理空间数据
echo "2. RGeo - 地理空间数据\n";
echo "----------------------\n";

$geo = $client->getGeo('demo:geo');
$geo->clear();

// 添加城市坐标
$cities = [
    'Beijing' => [116.4074, 39.9042],
    'Shanghai' => [121.4737, 31.2304],
    'Guangzhou' => [113.2644, 23.1291],
    'Shenzhen' => [114.0579, 22.5431],
    'Chengdu' => [104.0665, 30.5723]
];

echo "添加城市坐标:\n";
foreach ($cities as $city => $coords) {
    $geo->add($city, $coords[0], $coords[1]);
    echo "  - $city: 经度 {$coords[0]}, 纬度 {$coords[1]}\n";
}

// 计算距离
$distance = $geo->distance('Beijing', 'Shanghai', 'km');
echo "\n北京到上海距离: " . round($distance, 2) . " 公里\n";

// 范围搜索
echo "\n北京1000公里内的城市:\n";
$nearbyCities = $geo->radius(116.4074, 39.9042, 1000, 'km');
foreach ($nearbyCities as $city) {
    echo "  - $city\n";
}

// 地理哈希
$hash = $geo->hash('Beijing');
echo "\n北京的地理哈希: $hash\n\n";

// 3. RStream - 流数据
echo "3. RStream - 流数据\n";
echo "--------------------\n";

$stream = $client->getStream('demo:stream');
$stream->clear();

// 创建消费者组
$stream->createGroup('demo-group', '0');
echo "创建消费者组: demo-group\n";

// 添加流条目
echo "\n添加流条目:\n";
$events = [
    ['event' => 'login', 'user' => 'user1', 'timestamp' => time()],
    ['event' => 'purchase', 'user' => 'user2', 'product' => 'item123', 'amount' => 99.99],
    ['event' => 'logout', 'user' => 'user1', 'duration' => 3600]
];

$entryIds = [];
foreach ($events as $event) {
    $id = $stream->add($event);
    $entryIds[] = $id;
    echo "  - 添加事件: {$event['event']}, ID: $id\n";
}

// 读取流条目
echo "\n读取流条目:\n";
$entries = $stream->read();
foreach ($entries as $id => $data) {
    echo "  - ID: $id, 数据: " . json_encode($data) . "\n";
}

// 消费者组消费
echo "\n消费者组消费:\n";
$groupEntries = $stream->readGroup('demo-group', 'consumer1', '0');
foreach ($groupEntries as $id => $data) {
    echo "  - ID: $id, 数据: " . json_encode($data) . "\n";
}

// 确认消费
$acked = $stream->ack('demo-group', $entryIds);
echo "\n确认消费条目数: $acked\n\n";

// 4. RTimeSeries - 时间序列数据
echo "4. RTimeSeries - 时间序列数据\n";
echo "-----------------------------\n";

$timeSeries = $client->getTimeSeries('demo:timeseries');
$timeSeries->clear();

// 模拟传感器数据
$baseTime = time() * 1000; // 毫秒时间戳
echo "添加传感器数据:\n";

$dataPoints = [
    [$baseTime, 23.5],
    [$baseTime + 1000, 24.1],
    [$baseTime + 2000, 25.3],
    [$baseTime + 3000, 26.8],
    [$baseTime + 4000, 24.9],
    [$baseTime + 5000, 23.2]
];

$timeSeries->addAll($dataPoints);
foreach ($dataPoints as [$timestamp, $value]) {
    $timeStr = date('H:i:s', $timestamp / 1000);
    echo "  - 时间: $timeStr, 温度: {$value}°C\n";
}

// 获取统计数据
$stats = $timeSeries->getStats();
echo "\n时间序列统计信息:\n";
echo "  - 数据点数: {$stats['count']}\n";
echo "  - 最小值: {$stats['min']}°C\n";
echo "  - 最大值: {$stats['max']}°C\n";
echo "  - 平均值: " . round($stats['avg'], 2) . "°C\n";
echo "  - 开始时间: " . date('Y-m-d H:i:s', $stats['startTime'] / 1000) . "\n";
echo "  - 结束时间: " . date('Y-m-d H:i:s', $stats['endTime'] / 1000) . "\n";

// 范围查询
$rangeData = $timeSeries->range($baseTime, $baseTime + 3000);
echo "\n范围查询结果 (前4秒):\n";
foreach ($rangeData as $point) {
    $timeStr = date('H:i:s', $point['timestamp'] / 1000);
    echo "  - 时间: $timeStr, 温度: {$point['value']}°C\n";
}

// 获取最新值
$latest = $timeSeries->getLatest();
if ($latest) {
    $timeStr = date('H:i:s', $latest['timestamp'] / 1000);
    echo "\n最新数据点:\n";
    echo "  - 时间: $timeStr, 温度: {$latest['value']}°C\n";
}

// 获取最早值
$earliest = $timeSeries->getEarliest();
if ($earliest) {
    $timeStr = date('H:i:s', $earliest['timestamp'] / 1000);
    echo "\n最早数据点:\n";
    echo "  - 时间: $timeStr, 温度: {$earliest['value']}°C\n";
}

echo "\n=== 示例完成 ===\n";

// 清理演示数据
echo "\n清理演示数据...\n";
$hyperLogLog->clear();
$geo->clear();
$stream->clear();
$timeSeries->clear();

// 关闭连接
$client->shutdown();
echo "连接已关闭\n";