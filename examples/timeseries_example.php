<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Rediphp\RedissonClient;

/**
 * RTimeSeries 时间序列数据结构使用示例
 * 
 * RTimeSeries 提供了基于Redis的时间序列数据存储和查询能力，
 * 支持时间戳索引、范围查询、统计计算等功能。
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

echo "=== RTimeSeries 时间序列数据示例 ===\n\n";

// 创建RTimeSeries实例
$timeSeries = $client->getTimeSeries('example:timeseries');
$timeSeries->clear();

// 场景1: 基本时间序列操作
echo "1. 基本时间序列操作\n";
echo "--------------------\n";

$baseTime = time() * 1000; // 毫秒时间戳

// 添加单个数据点
echo "添加数据点:\n";
$timeSeries->add($baseTime, 23.5);
$timeSeries->add($baseTime + 1000, 24.1);
$timeSeries->add($baseTime + 2000, 25.3);

echo "  - 时间: " . date('H:i:s', $baseTime / 1000) . ", 值: 23.5\n";
echo "  - 时间: " . date('H:i:s', ($baseTime + 1000) / 1000) . ", 值: 24.1\n";
echo "  - 时间: " . date('H:i:s', ($baseTime + 2000) / 1000) . ", 值: 25.3\n";

// 获取单个数据点
$value = $timeSeries->get($baseTime + 1000);
echo "\n查询时间点 " . date('H:i:s', ($baseTime + 1000) / 1000) . " 的值: $value\n";

echo "当前时间序列大小: " . $timeSeries->size() . "\n\n";

// 场景2: 批量添加数据
echo "2. 批量添加数据\n";
echo "---------------\n";

// 模拟传感器数据
$sensorData = [
    [$baseTime + 3000, 26.8],
    [$baseTime + 4000, 24.9],
    [$baseTime + 5000, 23.2],
    [$baseTime + 6000, 22.1],
    [$baseTime + 7000, 21.5]
];

echo "批量添加传感器数据:\n";
$result = $timeSeries->addAll($sensorData);
echo "批量添加结果: " . ($result ? '成功' : '失败') . "\n";

echo "\n批量添加后的数据:\n";
foreach ($sensorData as [$timestamp, $val]) {
    echo "  - 时间: " . date('H:i:s', $timestamp / 1000) . ", 值: $val\n";
}

echo "\n";

// 场景3: 范围查询
echo "3. 范围查询\n";
echo "-----------\n";

$startTime = $baseTime + 2000;
$endTime = $baseTime + 6000;

echo "查询时间范围:\n";
echo "  - 开始: " . date('H:i:s', $startTime / 1000) . "\n";
echo "  - 结束: " . date('H:i:s', $endTime / 1000) . "\n";

$rangeData = $timeSeries->range($startTime, $endTime);
echo "\n范围查询结果 (" . count($rangeData) . " 个数据点):\n";
foreach ($rangeData as $point) {
    echo "  - 时间: " . date('H:i:s', $point['timestamp'] / 1000) . ", 值: {$point['value']}\n";
}

echo "\n";

// 场景4: 统计信息
echo "4. 统计信息\n";
echo "-----------\n";

$stats = $timeSeries->getStats();
echo "时间序列统计信息:\n";
echo "  - 数据点数量: {$stats['count']}\n";
echo "  - 最小值: {$stats['min']}\n";
echo "  - 最大值: {$stats['max']}\n";
echo "  - 平均值: " . round($stats['avg'], 2) . "\n";
echo "  - 开始时间: " . date('Y-m-d H:i:s', $stats['startTime'] / 1000) . "\n";
echo "  - 结束时间: " . date('Y-m-d H:i:s', $stats['endTime'] / 1000) . "\n";

echo "\n";

// 场景5: 最新和最早数据点
echo "5. 最新和最早数据点\n";
echo "-------------------\n";

$latest = $timeSeries->getLatest();
if ($latest) {
    echo "最新数据点:\n";
    echo "  - 时间: " . date('Y-m-d H:i:s', $latest['timestamp'] / 1000) . "\n";
    echo "  - 值: {$latest['value']}\n";
}

$earliest = $timeSeries->getEarliest();
if ($earliest) {
    echo "\n最早数据点:\n";
    echo "  - 时间: " . date('Y-m-d H:i:s', $earliest['timestamp'] / 1000) . "\n";
    echo "  - 值: {$earliest['value']}\n";
}

echo "\n";

// 场景6: 数据删除
echo "6. 数据删除\n";
echo "-----------\n";

$deleteTime = $baseTime + 4000;
echo "删除时间点的数据:\n";
echo "  - 时间: " . date('H:i:s', $deleteTime / 1000) . "\n";

$beforeDelete = $timeSeries->size();
$result = $timeSeries->delete($deleteTime);
echo "删除结果: " . ($result ? '成功' : '失败') . "\n";
$afterDelete = $timeSeries->size();
echo "删除前大小: $beforeDelete, 删除后大小: $afterDelete\n";

// 验证删除
$deletedValue = $timeSeries->get($deleteTime);
echo "删除后查询结果: " . ($deletedValue === null ? 'null' : $deletedValue) . "\n\n";

// 场景7: 范围删除
echo "7. 范围删除\n";
echo "-----------\n";

$deleteStart = $baseTime + 5000;
$deleteEnd = $baseTime + 7000;

echo "删除时间范围:\n";
echo "  - 开始: " . date('H:i:s', $deleteStart / 1000) . "\n";
echo "  - 结束: " . date('H:i:s', $deleteEnd / 1000) . "\n";

$beforeRangeDelete = $timeSeries->size();
$result = $timeSeries->deleteRange($deleteStart, $deleteEnd);
echo "范围删除结果: " . ($result ? '成功' : '失败') . "\n";
$afterRangeDelete = $timeSeries->size();
echo "删除前大小: $beforeRangeDelete, 删除后大小: $afterRangeDelete\n\n";

// 场景8: 多时间序列对比
echo "8. 多时间序列对比\n";
echo "-----------------\n";

// 创建多个传感器的时间序列
$temperatureSeries = $client->getTimeSeries('example:temperature');
$humiditySeries = $client->getTimeSeries('example:humidity');
$pressureSeries = $client->getTimeSeries('example:pressure');

$temperatureSeries->clear();
$humiditySeries->clear();
$pressureSeries->clear();

$multiBaseTime = time() * 1000;

// 模拟多个传感器数据
$sensorTypes = [
    'temperature' => [20.0, 25.0], // 温度范围
    'humidity' => [40.0, 70.0],     // 湿度范围
    'pressure' => [1000.0, 1020.0]  // 气压范围
];

$seriesMap = [
    'temperature' => $temperatureSeries,
    'humidity' => $humiditySeries,
    'pressure' => $pressureSeries
];

echo "多传感器数据模拟:\n";
for ($i = 0; $i < 10; $i++) {
    $timestamp = $multiBaseTime + ($i * 1000);
    
    foreach ($sensorTypes as $type => $range) {
        // 生成随机值在指定范围内
        $value = $range[0] + (mt_rand() / mt_getrandmax()) * ($range[1] - $range[0]);
        $seriesMap[$type]->add($timestamp, round($value, 1));
    }
    
    echo "  时间 " . date('H:i:s', $timestamp / 1000) . ": ";
    echo "温度 " . $temperatureSeries->get($timestamp) . "°C, ";
    echo "湿度 " . $humiditySeries->get($timestamp) . "%, ";
    echo "气压 " . $pressureSeries->get($timestamp) . " hPa\n";
}

// 显示统计对比
echo "\n多传感器统计对比:\n";
foreach ($seriesMap as $type => $series) {
    $stats = $series->getStats();
    echo "  $type:\n";
    echo "    最小值: {$stats['min']}, 最大值: {$stats['max']}, 平均值: " . round($stats['avg'], 1) . "\n";
}

echo "\n";

// 场景9: 实际应用 - 股票价格监控
echo "9. 实际应用 - 股票价格监控\n";
echo "---------------------------\n";

$stockSeries = $client->getTimeSeries('example:stock:price');
$stockSeries->clear();

$stockBaseTime = time() * 1000;
$initialPrice = 100.0;
$currentPrice = $initialPrice;

echo "模拟股票价格变化:\n";
echo "初始价格: $$initialPrice\n\n";

// 模拟一天的股票价格（每分钟一个数据点）
$stockData = [];
for ($i = 0; $i < 60; $i++) { // 60分钟
    $timestamp = $stockBaseTime + ($i * 60000); // 每分钟
    
    // 随机价格变化（-2% 到 +2%）
    $change = (mt_rand(-20, 20) / 1000) * $currentPrice;
    $currentPrice += $change;
    
    $stockData[] = [$timestamp, round($currentPrice, 2)];
    
    if ($i % 10 == 0) { // 每10分钟显示一次
        echo "  " . date('H:i', $timestamp / 1000) . ": $$currentPrice\n";
    }
}

$stockSeries->addAll($stockData);

// 显示价格统计
$stockStats = $stockSeries->getStats();
echo "\n股票价格统计:\n";
echo "  - 最高价: ${$stockStats['max']}\n";
echo "  - 最低价: ${$stockStats['min']}\n";
echo "  - 平均价: $" . round($stockStats['avg'], 2) . "\n";
echo "  - 涨跌幅: " . round((($stockStats['avg'] - $initialPrice) / $initialPrice) * 100, 2) . "%\n";

// 显示最近价格
echo "\n最近价格:\n";
$latestStock = $stockSeries->getLatest();
if ($latestStock) {
    echo "  " . date('H:i:s', $latestStock['timestamp'] / 1000) . ": ${$latestStock['value']}\n";
}

echo "\n";

// 场景10: 错误处理
echo "10. 错误处理\n";
echo "------------\n";

$emptySeries = $client->getTimeSeries('example:empty-series');
$emptySeries->clear();

echo "空时间序列状态:\n";
echo "  - 大小: " . $emptySeries->size() . "\n";
echo "  - 是否为空: " . ($emptySeries->isEmpty() ? '是' : '否') . "\n";
echo "  - 是否存在: " . ($emptySeries->exists() ? '是' : '否') . "\n";

// 测试不存在的查询
$nonExistentValue = $emptySeries->get($multiBaseTime);
echo "  - 查询不存在的值: " . ($nonExistentValue === null ? 'null' : $nonExistentValue) . "\n";

// 清空操作
echo "\n清空测试:\n";
$emptySeries->add($multiBaseTime, 42.0);
echo "添加数据后大小: " . $emptySeries->size() . "\n";
$emptySeries->clear();
echo "清空后大小: " . $emptySeries->size() . "\n";
echo "清空后是否为空: " . ($emptySeries->isEmpty() ? '是' : '否') . "\n";

echo "\n";

// 清理数据
echo "清理示例数据...\n";
$timeSeries->clear();
$temperatureSeries->clear();
$humiditySeries->clear();
$pressureSeries->clear();
$stockSeries->clear();
$emptySeries->clear();

// 关闭连接
$client->shutdown();
echo "连接已关闭\n";

echo "\n=== RTimeSeries 示例完成 ===\n";
echo "RTimeSeries 适用于以下场景:\n";
echo "- IoT传感器数据收集\n";
echo "- 股票价格监控\n";
echo "- 系统性能指标\n";
echo "- 应用性能监控\n";
echo "- 日志时间序列分析\n";
echo "优点：时间索引高效，支持范围查询和统计计算\n";