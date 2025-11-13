<?php

require __DIR__ . '/../vendor/autoload.php';

use Rediphp\RedissonClient;

// 创建客户端并连接
$client = new RedissonClient([
    'host' => '127.0.0.1',
    'port' => 6379,
]);
$client->connect();

echo "=== RMap 示例 ===\n";
$map = $client->getMap('exampleMap');
$map->clear(); // 清空以前的数据

// 添加数据
$map->put('name', 'Zhang San');
$map->put('age', 30);
$map->put('city', 'Beijing');
$map->put('hobbies', ['reading', 'coding', 'music']);

// 读取数据
echo "Name: " . $map->get('name') . "\n";
echo "Age: " . $map->get('age') . "\n";
echo "Hobbies: " . json_encode($map->get('hobbies')) . "\n";

// 检查键是否存在
echo "Contains 'name': " . ($map->containsKey('name') ? 'Yes' : 'No') . "\n";

// 获取所有键值对
echo "All entries:\n";
print_r($map->entrySet());

// Map 大小
echo "Map size: " . $map->size() . "\n";

echo "\n=== RList 示例 ===\n";
$list = $client->getList('exampleList');
$list->clear();

// 添加元素
$list->add('Apple');
$list->add('Banana');
$list->add('Cherry');
$list->add('Date');

// 读取元素
echo "Element at index 0: " . $list->get(0) . "\n";
echo "Element at index 2: " . $list->get(2) . "\n";

// 修改元素
$list->set(1, 'Blueberry');
echo "After update, element at index 1: " . $list->get(1) . "\n";

// 获取所有元素
echo "All elements: " . json_encode($list->toArray()) . "\n";

// List 大小
echo "List size: " . $list->size() . "\n";

echo "\n=== RSet 示例 ===\n";
$set = $client->getSet('exampleSet');
$set->clear();

// 添加元素
$set->add('Red');
$set->add('Green');
$set->add('Blue');
$set->add('Red'); // 重复元素不会被添加

// 检查元素
echo "Contains 'Red': " . ($set->contains('Red') ? 'Yes' : 'No') . "\n";
echo "Contains 'Yellow': " . ($set->contains('Yellow') ? 'Yes' : 'No') . "\n";

// 获取所有元素
echo "All elements: " . json_encode($set->toArray()) . "\n";

// Set 大小
echo "Set size: " . $set->size() . "\n";

echo "\n=== RQueue 示例 ===\n";
$queue = $client->getQueue('exampleQueue');
$queue->clear();

// 入队
$queue->offer('Task 1');
$queue->offer('Task 2');
$queue->offer('Task 3');

// 查看队首
echo "Peek: " . $queue->peek() . "\n";

// 出队
echo "Poll: " . $queue->poll() . "\n";
echo "Poll: " . $queue->poll() . "\n";

// 队列大小
echo "Queue size: " . $queue->size() . "\n";

echo "\n=== RSortedSet 示例 ===\n";
$sortedSet = $client->getSortedSet('exampleSortedSet');
$sortedSet->clear();

// 添加带分数的元素
$sortedSet->add('Player A', 100.0);
$sortedSet->add('Player B', 200.0);
$sortedSet->add('Player C', 150.0);
$sortedSet->add('Player D', 175.0);

// 获取排名
echo "Rank of Player C: " . $sortedSet->rank('Player C') . "\n";

// 获取分数
echo "Score of Player B: " . $sortedSet->score('Player B') . "\n";

// 获取排名范围
echo "Top 3 players: " . json_encode($sortedSet->range(0, 2)) . "\n";

// 获取分数范围
echo "Players with score 100-180: " . json_encode($sortedSet->rangeByScore(100.0, 180.0)) . "\n";

echo "\n=== RBucket 示例 ===\n";
$bucket = $client->getBucket('exampleBucket');

// 设置对象
$bucket->set([
    'user' => 'admin',
    'permissions' => ['read', 'write', 'delete'],
    'lastLogin' => date('Y-m-d H:i:s')
]);

// 获取对象
$data = $bucket->get();
echo "Bucket data: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";

// 关闭连接
$client->shutdown();

echo "\n所有示例执行完成！\n";
