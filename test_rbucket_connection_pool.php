<?php

require_once __DIR__ . '/vendor/autoload.php';

use Rediphp\Config;
use Rediphp\RedissonClient;

echo "=== RBucket 连接池测试 ===\n";

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

// 获取 RBucket 实例
$bucket = $client->getBucket('test_bucket');

// 测试1: 设置值
echo "1. 设置值...\n";
$bucket->set(['name' => '张三', 'age' => 25]);
echo "设置结果: 成功\n\n";

// 测试2: 获取值
echo "2. 获取值...\n";
$value = $bucket->get();
echo "获取的值: " . json_encode($value, JSON_UNESCAPED_UNICODE) . "\n\n";

// 测试3: 检查是否存在
echo "3. 检查是否存在...\n";
$exists = $bucket->isExists();
echo "是否存在: " . ($exists ? '是' : '否') . "\n\n";

// 测试4: 尝试设置（如果不存在）
echo "4. 尝试设置新值（如果不存在）...\n";
$trySetResult = $bucket->trySet(['name' => '李四', 'age' => 30]);
echo "尝试设置结果: " . ($trySetResult ? '成功' : '失败（已存在）') . "\n\n";

// 测试5: 获取并设置新值
echo "5. 获取并设置新值...\n";
$oldValue = $bucket->getAndSet(['name' => '王五', 'age' => 35]);
echo "旧值: " . json_encode($oldValue, JSON_UNESCAPED_UNICODE) . "\n";
$newValue = $bucket->get();
echo "新值: " . json_encode($newValue, JSON_UNESCAPED_UNICODE) . "\n\n";

// 测试6: 比较并设置
echo "6. 比较并设置...\n";
$currentValue = $bucket->get();
echo "当前值: " . json_encode($currentValue, JSON_UNESCAPED_UNICODE) . "\n";
$compareResult = $bucket->compareAndSet($currentValue, ['name' => '赵六', 'age' => 40]);
echo "比较并设置结果: " . ($compareResult ? '成功' : '失败') . "\n";
if ($compareResult) {
    $finalValue = $bucket->get();
    echo "最终值: " . json_encode($finalValue, JSON_UNESCAPED_UNICODE) . "\n";
}
echo "\n";

// 测试7: 设置带过期时间的值
echo "7. 设置带过期时间的值...\n";
$bucket->setWithTTL(['name' => '临时用户', 'age' => 25], 5000); // 5秒过期
echo "设置带TTL结果: 成功\n";
$ttlValue = $bucket->get();
echo "TTL值: " . json_encode($ttlValue, JSON_UNESCAPED_UNICODE) . "\n\n";

// 测试8: 获取并删除
echo "8. 获取并删除...\n";
$deletedValue = $bucket->getAndDelete();
echo "删除的值: " . json_encode($deletedValue, JSON_UNESCAPED_UNICODE) . "\n";
$existsAfterDelete = $bucket->isExists();
echo "删除后是否存在: " . ($existsAfterDelete ? '是' : '否') . "\n\n";

// 测试9: 删除不存在的值
echo "9. 删除不存在的值...\n";
$deleteResult = $bucket->delete();
echo "删除结果: " . ($deleteResult ? '成功' : '失败（不存在）') . "\n\n";

// 测试10: 设置简单值
echo "10. 设置简单字符串值...\n";
$bucket->set('Hello World');
$simpleValue = $bucket->get();
echo "简单值: " . $simpleValue . "\n";

// 清理
$bucket->delete();

echo "\n=== RBucket 连接池测试完成 ===\n";