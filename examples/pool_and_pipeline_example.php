<?php

require __DIR__ . '/../vendor/autoload.php';

use Rediphp\RedissonClient;

// 示例1：连接池配置示例
echo "=== 连接池配置示例 ===\n";

$poolConfig = [
    'host' => '127.0.0.1',
    'port' => 6379,
    'use_pool' => true,           // 启用连接池
    'pool_config' => [
        'min_connections' => 5,   // 最小连接数
        'max_connections' => 20,  // 最大连接数
        'connect_timeout' => 5.0, // 连接超时(秒)
        'read_timeout' => 5.0,    // 读取超时(秒)
        'idle_timeout' => 60,     // 空闲超时(秒)
        'max_lifetime' => 3600,   // 连接最大生命周期(秒)
    ]
];

$client = new RedissonClient($poolConfig);
$client->connect();

$map = $client->getMap('pool_example_map');
$map->clear();

// 测试连接池性能
echo "连接池模式性能测试...\n";
$startTime = microtime(true);

for ($i = 0; $i < 1000; $i++) {
    $map->put("key_$i", "value_$i");
}

$endTime = microtime(true);
$duration = $endTime - $startTime;
echo "连接池模式完成 1000 次操作耗时: " . round($duration * 1000, 2) . " ms\n";
echo "平均操作耗时: " . round($duration * 1000 / 1000, 3) . " ms/op\n";

// 示例2：Pipeline 操作示例
echo "\n=== Pipeline 操作示例 ===\n";

$map->clear();

// 使用 pipeline 进行批量操作
echo "使用 pipeline 进行批量操作...\n";
$startTime = microtime(true);

$results = $map->pipeline(function($pipeline) {
    for ($i = 0; $i < 1000; $i++) {
        $pipeline->hSet('pipeline_example_map', "key_$i", "value_$i");
    }
});

$endTime = microtime(true);
$duration = $endTime - $startTime;
echo "Pipeline 模式完成 1000 次操作耗时: " . round($duration * 1000, 2) . " ms\n";
echo "平均操作耗时: " . round($duration * 1000 / 1000, 3) . " ms/op\n";

// 示例3：快速 Pipeline 操作
echo "\n=== 快速 Pipeline 操作示例 ===\n";

$map->clear();

// 使用 fastPipeline 进行批量操作（不等待结果）
echo "使用 fastPipeline 进行批量操作...\n";
$startTime = microtime(true);

$map->fastPipeline(function($pipeline) {
    for ($i = 0; $i < 1000; $i++) {
        $pipeline->hSet('fast_pipeline_example_map', "key_$i", "value_$i");
    }
});

$endTime = microtime(true);
$duration = $endTime - $startTime;
echo "FastPipeline 模式完成 1000 次操作耗时: " . round($duration * 1000, 2) . " ms\n";
echo "平均操作耗时: " . round($duration * 1000 / 1000, 3) . " ms/op\n";

// 示例4：事务操作示例
echo "\n=== 事务操作示例 ===\n";

$map->clear();

// 使用事务进行原子操作
echo "使用事务进行原子操作...\n";
$startTime = microtime(true);

$results = $map->transaction(function($pipeline) {
    $pipeline->hSet('transaction_example_map', 'user1', '张三');
    $pipeline->hSet('transaction_example_map', 'user2', '李四');
    $pipeline->hSet('transaction_example_map', 'user3', '王五');
});

$endTime = microtime(true);
$duration = $endTime - $startTime;
echo "事务模式完成 3 次操作耗时: " . round($duration * 1000, 2) . " ms\n";

// 验证事务结果
$user1 = $map->hGet('transaction_example_map', 'user1');
$user2 = $map->hGet('transaction_example_map', 'user2');
$user3 = $map->hGet('transaction_example_map', 'user3');

echo "事务执行结果: user1=$user1, user2=$user2, user3=$user3\n";

// 示例5：MessagePack 序列化示例
echo "\n=== MessagePack 序列化示例 ===\n";

$msgpackConfig = [
    'host' => '127.0.0.1',
    'port' => 6379,
    'serialization' => 'msgpack'  // 使用 MessagePack 序列化
];

$msgpackClient = new RedissonClient($msgpackConfig);
$msgpackClient->connect();

$msgpackMap = $msgpackClient->getMap('msgpack_example_map');
$msgpackMap->clear();

// 测试 MessagePack 序列化性能
echo "MessagePack 序列化性能测试...\n";
$startTime = microtime(true);

$complexData = [
    'user' => 'admin',
    'permissions' => ['read', 'write', 'delete'],
    'profile' => [
        'name' => '张三',
        'age' => 30,
        'address' => '北京市朝阳区'
    ],
    'settings' => [
        'theme' => 'dark',
        'language' => 'zh-CN',
        'notifications' => true
    ]
];

$msgpackMap->put('complex_data', $complexData);
$retrievedData = $msgpackMap->get('complex_data');

$endTime = microtime(true);
$duration = $endTime - $startTime;
echo "MessagePack 序列化操作耗时: " . round($duration * 1000, 2) . " ms\n";
echo "序列化数据大小对比: JSON=" . strlen(json_encode($complexData)) . " bytes\n";

// 清理测试数据
$map->clear();
$msgpackMap->clear();

// 关闭连接
$client->shutdown();
$msgpackClient->shutdown();

echo "\n所有示例执行完成！\n";
echo "=== 性能总结 ===\n";
echo "1. 连接池模式：适合高并发场景，减少连接创建开销\n";
echo "2. Pipeline 模式：适合批量操作，显著减少网络往返\n";
echo "3. FastPipeline：适合不需要结果的批量操作\n";
echo "4. 事务模式：保证操作的原子性\n";
echo "5. MessagePack：减少序列化开销，提升性能\n";