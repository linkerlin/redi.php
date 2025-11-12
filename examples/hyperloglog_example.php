<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Rediphp\RedissonClient;

/**
 * RHyperLogLog 使用示例
 * 
 * HyperLogLog 是一种用于基数估计的概率数据结构，
 * 可以在极小的内存占用下估算大量数据的独立元素数量。
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

echo "=== RHyperLogLog 基数统计示例 ===\n\n";

// 创建HyperLogLog实例
$hyperLogLog = $client->getHyperLogLog('example:hyperloglog');
$hyperLogLog->clear();

// 场景1: 网站UV统计
echo "1. 网站UV统计示例\n";
echo "-----------------\n";

$visitors = [];
for ($i = 1; $i <= 1000; $i++) {
    $visitors[] = "user_" . $i;
}

// 模拟用户访问（包含重复访问）
echo "模拟1000个独立用户的访问（含重复）:\n";
$accessLog = [];
for ($i = 0; $i < 2000; $i++) {
    $user = $visitors[array_rand($visitors)];
    $accessLog[] = $user;
    $hyperLogLog->add($user);
}

$estimatedUv = $hyperLogLog->count();
$actualUv = count(array_unique($accessLog));

echo "实际独立访客数: $actualUv\n";
echo "HyperLogLog估计值: " . round($estimatedUv) . "\n";
echo "误差率: " . round(abs($estimatedUv - $actualUv) / $actualUv * 100, 2) . "%\n\n";

// 场景2: 多页面UV统计
echo "2. 多页面UV统计示例\n";
echo "-------------------\n";

$homePageHll = $client->getHyperLogLog('example:homepage:uv');
$productPageHll = $client->getHyperLogLog('example:productpage:uv');
$cartPageHll = $client->getHyperLogLog('example:cartpage:uv');

$homePageHll->clear();
$productPageHll->clear();
$cartPageHll->clear();

// 模拟不同页面的访问
$homeUsers = ['user1', 'user2', 'user3', 'user4', 'user5'];
$productUsers = ['user2', 'user3', 'user6', 'user7', 'user8'];
$cartUsers = ['user3', 'user8', 'user9', 'user10'];

foreach ($homeUsers as $user) {
    $homePageHll->add($user);
}
foreach ($productUsers as $user) {
    $productPageHll->add($user);
}
foreach ($cartUsers as $user) {
    $cartPageHll->add($user);
}

echo "首页UV: " . round($homePageHll->count()) . "\n";
echo "商品页UV: " . round($productPageHll->count()) . "\n";
echo "购物车页UV: " . round($cartPageHll->count()) . "\n";

// 合并统计总UV
$totalHll = $client->getHyperLogLog('example:total:uv');
$totalHll->clear();
$totalHll->merge('example:homepage:uv');
$totalHll->merge('example:productpage:uv');
$totalHll->merge('example:cartpage:uv');

echo "总UV（合并后）: " . round($totalHll->count()) . "\n\n";

// 场景3: 批量添加性能测试
echo "3. 批量添加性能测试\n";
echo "------------------\n";

$batchHll = $client->getHyperLogLog('example:batch:hyperloglog');
$batchHll->clear();

$batchSize = 10000;
$batchData = [];
for ($i = 0; $i < $batchSize; $i++) {
    $batchData[] = "batch_user_" . $i;
}

$startTime = microtime(true);
$batchHll->addAll($batchData);
$endTime = microtime(true);

$estimatedCount = $batchHll->count();
$executionTime = ($endTime - $startTime) * 1000;

echo "批量添加 $batchSize 个元素\n";
echo "执行时间: " . round($executionTime, 2) . " 毫秒\n";
echo "估计基数: " . round($estimatedCount) . "\n";
echo "误差率: " . round(abs($estimatedCount - $batchSize) / $batchSize * 100, 2) . "%\n\n";

// 场景4: 内存使用对比
echo "4. 内存使用对比\n";
echo "---------------\n";

$comparisonHll = $client->getHyperLogLog('example:comparison:hyperloglog');
$comparisonHll->clear();

// 添加大量数据
$largeDataset = [];
for ($i = 0; $i < 100000; $i++) {
    $largeDataset[] = "large_user_" . md5($i);
}

$comparisonHll->addAll($largeDataset);
$estimatedLarge = $comparisonHll->count();

// 估算内存使用（理论值）
$theoreticalMemory = 12 * 1024; // HyperLogLog标准实现约12KB
$setMemory = count(array_unique($largeDataset)) * 32; // 假设每个字符串约32字节

echo "数据集大小: " . count($largeDataset) . " 个元素\n";
echo "HyperLogLog估计基数: " . round($estimatedLarge) . "\n";
echo "HyperLogLog内存使用（理论）: " . round($theoreticalMemory / 1024, 2) . " KB\n";
echo "Set存储内存使用（估算）: " . round($setMemory / 1024, 2) . " KB\n";
echo "内存节省比例: " . round($setMemory / $theoreticalMemory, 1) . " 倍\n\n";

// 场景5: 错误处理
echo "5. 错误处理示例\n";
echo "---------------\n";

$errorHll = $client->getHyperLogLog('example:error:hyperloglog');
$emptyHll = $client->getHyperLogLog('example:empty:hyperloglog');

$errorHll->clear();
$emptyHll->clear();

// 测试空HyperLogLog
echo "空HyperLogLog计数: " . $emptyHll->count() . "\n";
echo "空HyperLogLog大小: " . $emptyHll->size() . "\n";
echo "空HyperLogLog是否为空: " . ($emptyHll->isEmpty() ? '是' : '否') . "\n";

// 测试存在性
echo "HyperLogLog是否存在: " . ($errorHll->exists() ? '是' : '否') . "\n";

// 清除操作
$errorHll->add('test_user');
echo "添加元素后大小: " . $errorHll->size() . "\n";
$errorHll->clear();
echo "清除后大小: " . $errorHll->size() . "\n";
echo "清除后是否为空: " . ($errorHll->isEmpty() ? '是' : '否') . "\n\n";

// 清理数据
echo "清理示例数据...\n";
$hyperLogLog->clear();
$homePageHll->clear();
$productPageHll->clear();
$cartPageHll->clear();
$totalHll->clear();
$batchHll->clear();
$comparisonHll->clear();
$emptyHll->clear();

// 关闭连接
$client->shutdown();
echo "连接已关闭\n";

echo "\n=== RHyperLogLog 示例完成 ===\n";
echo "HyperLogLog 是一种高效的概率数据结构，适用于:\n";
echo "- 网站UV统计\n";
echo "- 数据库去重\n";
echo "- 流量分析\n";
echo "- 大数据集基数估计\n";
echo "优点：内存占用小，性能高，适合大数据场景\n";