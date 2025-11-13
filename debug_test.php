<?php
require_once __DIR__ . '/vendor/autoload.php';

use Rediphp\RedissonClient;

$client = new RedissonClient([
    'host' => '127.0.0.1',
    'port' => 6379,
    'password' => null,
    'database' => 0
]);

$sortedSet = $client->getSortedSet('test-remove-sortedset');

// 完整重现测试逻辑
$sortedSet->clear();

// 添加初始元素
$sortedSet->add('to-remove1', 10.0);
$sortedSet->add('to-remove2', 20.0);
$sortedSet->add('to-remove3', 30.0);
$sortedSet->add('to-keep', 40.0);

echo "初始集合内容:\n";
$members = $sortedSet->readAllWithScores();
foreach ($members as $member => $score) {
    echo "  $member: $score\n";
}
echo "初始大小: " . $sortedSet->size() . "\n\n";

// 删除单个元素
$sortedSet->remove('to-remove1');
echo "删除to-remove1后:\n";
$members = $sortedSet->readAllWithScores();
foreach ($members as $member => $score) {
    echo "  $member: $score\n";
}
echo "大小: " . $sortedSet->size() . "\n\n";

// 批量删除
$sortedSet->removeBatch(['to-remove2', 'to-remove3']);
echo "批量删除后:\n";
$members = $sortedSet->readAllWithScores();
foreach ($members as $member => $score) {
    echo "  $member: $score\n";
}
echo "大小: " . $sortedSet->size() . "\n\n";

// 按分数范围删除
$sortedSet->add('range1', 5.0);
$sortedSet->add('range2', 15.0);
$sortedSet->add('range3', 25.0);

echo "添加range元素后:\n";
$members = $sortedSet->readAllWithScores();
foreach ($members as $member => $score) {
    echo "  $member: $score\n";
}
echo "大小: " . $sortedSet->size() . "\n\n";

$removedByRange = $sortedSet->removeRangeByScore(10.0, 20.0);
echo "removeRangeByScore(10.0, 20.0) 返回值: $removedByRange\n";

echo "删除后的集合内容:\n";
$members = $sortedSet->readAllWithScores();
foreach ($members as $member => $score) {
    echo "  $member: $score\n";
}
echo "大小: " . $sortedSet->size() . "\n";