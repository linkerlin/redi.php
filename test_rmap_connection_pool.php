<?php

require_once __DIR__ . '/vendor/autoload.php';

use Rediphp\Config;
use Rediphp\RedissonClient;

echo "=== RMap 连接池测试 ===\n";

// 使用连接池配置创建客户端
$client = Config::createClient([
    'host' => '127.0.0.1',
    'port' => 6379,
    'database' => 0,
    'pool' => [
        'enabled' => true,
        'min_connections' => 2,
        'max_connections' => 10,
        'connection_timeout' => 5,
        'idle_timeout' => 30,
    ]
]);

// 获取 RMap 实例
$map = $client->getMap('test_map');

// 测试1: 添加键值对
echo "1. 添加键值对...\n";
$previous = $map->put('user1', ['name' => '张三', 'age' => 25]);
echo "添加 user1，之前的值: " . json_encode($previous, JSON_UNESCAPED_UNICODE) . "\n";
$map->put('user2', ['name' => '李四', 'age' => 30]);
$map->put('user3', ['name' => '王五', 'age' => 35]);
echo "添加 user2, user3 完成\n\n";

// 测试2: 获取值
echo "2. 获取值...\n";
$user1 = $map->get('user1');
echo "user1: " . json_encode($user1, JSON_UNESCAPED_UNICODE) . "\n";
$nonExistent = $map->get('nonexistent');
echo "不存在的键: " . json_encode($nonExistent, JSON_UNESCAPED_UNICODE) . "\n\n";

// 测试3: 检查键是否存在
echo "3. 检查键是否存在...\n";
$hasUser1 = $map->containsKey('user1');
$hasNonExistent = $map->containsKey('nonexistent');
echo "user1 是否存在: " . ($hasUser1 ? '是' : '否') . "\n";
echo "nonexistent 是否存在: " . ($hasNonExistent ? '是' : '否') . "\n\n";

// 测试4: 查看Map大小
echo "4. 查看Map大小...\n";
$size = $map->size();
echo "Map大小: " . $size . "\n\n";

// 测试5: 获取所有键
echo "5. 获取所有键...\n";
$keys = $map->keySet();
echo "所有键: " . json_encode($keys, JSON_UNESCAPED_UNICODE) . "\n\n";

// 测试6: 获取所有值
echo "6. 获取所有值...\n";
$values = $map->values();
echo "所有值: " . json_encode($values, JSON_UNESCAPED_UNICODE) . "\n\n";

// 测试7: 获取所有条目
echo "7. 获取所有条目...\n";
$entries = $map->entrySet();
echo "所有条目: " . json_encode($entries, JSON_UNESCAPED_UNICODE) . "\n\n";

// 测试8: 如果不存在则添加
echo "8. 如果不存在则添加...\n";
$absent1 = $map->putIfAbsent('user4', ['name' => '赵六', 'age' => 40]);
echo "user4 不存在时添加结果: " . json_encode($absent1, JSON_UNESCAPED_UNICODE) . "\n";
$absent2 = $map->putIfAbsent('user1', ['name' => '新用户', 'age' => 99]);
echo "user1 已存在时添加结果: " . json_encode($absent2, JSON_UNESCAPED_UNICODE) . "\n\n";

// 测试9: 替换值
echo "9. 替换值...\n";
$replaced = $map->replace('user1', ['name' => '张三更新', 'age' => 26]);
echo "替换 user1 的旧值: " . json_encode($replaced, JSON_UNESCAPED_UNICODE) . "\n";
$updatedUser1 = $map->get('user1');
echo "更新后的 user1: " . json_encode($updatedUser1, JSON_UNESCAPED_UNICODE) . "\n\n";

// 测试10: 移除键
echo "10. 移除键...\n";
$removed = $map->remove('user2');
echo "移除 user2 的旧值: " . json_encode($removed, JSON_UNESCAPED_UNICODE) . "\n";
$sizeAfterRemove = $map->size();
echo "移除后的大小: " . $sizeAfterRemove . "\n\n";

// 测试11: 批量添加
echo "11. 批量添加...\n";
$newUsers = [
    'user5' => ['name' => '孙七', 'age' => 45],
    'user6' => ['name' => '周八', 'age' => 50]
];
$map->putAll($newUsers);
echo "批量添加 user5, user6 完成\n";
$finalSize = $map->size();
echo "最终大小: " . $finalSize . "\n\n";

// 测试12: 检查是否为空
echo "12. 检查是否为空...\n";
$isEmpty = $map->isEmpty();
echo "是否为空: " . ($isEmpty ? '是' : '否') . "\n\n";

// 测试13: 清空Map
echo "13. 清空Map...\n";
$map->clear();
$sizeAfterClear = $map->size();
echo "清空后大小: " . $sizeAfterClear . "\n";
$isEmptyAfterClear = $map->isEmpty();
echo "清空后是否为空: " . ($isEmptyAfterClear ? '是' : '否') . "\n\n";

echo "=== RMap 连接池测试完成 ===\n";