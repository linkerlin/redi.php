<?php

require __DIR__ . '/../vendor/autoload.php';

use Rediphp\RedissonClient;

// 创建客户端并连接
$client = new RedissonClient([
    'host' => '127.0.0.1',
    'port' => 6379,
]);
$client->connect();

echo "=== RBitSet 示例 ===\n";
$bitSet = $client->getBitSet('exampleBitSet');
$bitSet->clearAll();

// 设置位
$bitSet->set(0);
$bitSet->set(5);
$bitSet->set(10);
$bitSet->set(15);

// 读取位
echo "Bit 0: " . ($bitSet->get(0) ? 'true' : 'false') . "\n";
echo "Bit 3: " . ($bitSet->get(3) ? 'true' : 'false') . "\n";
echo "Bit 5: " . ($bitSet->get(5) ? 'true' : 'false') . "\n";

// 统计设置的位数
echo "Cardinality (bits set): " . $bitSet->cardinality() . "\n";

// 清除一个位
$bitSet->clear(5);
echo "After clearing bit 5, cardinality: " . $bitSet->cardinality() . "\n";

echo "\n=== RBloomFilter 示例 ===\n";
$bloomFilter = $client->getBloomFilter('exampleBloomFilter');
$bloomFilter->delete(); // 清除旧数据

// 初始化布隆过滤器
$bloomFilter->tryInit(10000, 0.01); // 预期10000个元素，1%误判率

// 添加元素
$elements = ['apple', 'banana', 'cherry', 'date', 'elderberry'];
foreach ($elements as $element) {
    $bloomFilter->add($element);
    echo "Added: $element\n";
}

// 检查元素
echo "\nChecking elements:\n";
echo "Contains 'apple': " . ($bloomFilter->contains('apple') ? 'Yes' : 'No') . "\n";
echo "Contains 'banana': " . ($bloomFilter->contains('banana') ? 'Yes' : 'No') . "\n";
echo "Contains 'grape': " . ($bloomFilter->contains('grape') ? 'Yes' : 'No') . "\n";
echo "Contains 'watermelon': " . ($bloomFilter->contains('watermelon') ? 'Yes' : 'No') . "\n";

// 估计元素数量
echo "Estimated count: " . $bloomFilter->count() . "\n";

echo "\n=== RDeque 示例 ===\n";
$deque = $client->getDeque('exampleDeque');
$deque->clear();

// 从两端添加
$deque->addFirst('First');
$deque->addLast('Last');
$deque->addFirst('NewFirst');
$deque->addLast('NewLast');

echo "All elements: " . json_encode($deque->toArray()) . "\n";

// 查看两端
echo "Peek first: " . $deque->peekFirst() . "\n";
echo "Peek last: " . $deque->peekLast() . "\n";

// 从两端移除
echo "Remove first: " . $deque->removeFirst() . "\n";
echo "Remove last: " . $deque->removeLast() . "\n";

echo "Remaining elements: " . json_encode($deque->toArray()) . "\n";

echo "\n=== 发布订阅示例说明 ===\n";
echo "发布订阅需要在单独的进程中运行\n\n";

echo "发布者代码示例:\n";
echo "<?php\n";
echo "require 'vendor/autoload.php';\n";
echo "use Rediphp\RedissonClient;\n";
echo "\$client = new RedissonClient();\n";
echo "\$client->connect();\n";
echo "\$topic = \$client->getTopic('myTopic');\n";
echo "\$topic->publish(['message' => 'Hello World', 'time' => time()]);\n";
echo "echo \"Message published\\n\";\n";
echo "\$client->shutdown();\n\n";

echo "订阅者代码示例 (需要在单独的终端运行):\n";
echo "<?php\n";
echo "require 'vendor/autoload.php';\n";
echo "use Rediphp\RedissonClient;\n";
echo "\$client = new RedissonClient();\n";
echo "\$client->connect();\n";
echo "\$topic = \$client->getTopic('myTopic');\n";
echo "echo \"Waiting for messages...\\n\";\n";
echo "\$topic->subscribe(function(\$message) {\n";
echo "    echo \"Received: \" . json_encode(\$message) . \"\\n\";\n";
echo "});\n";

echo "\n=== 互操作性测试数据 ===\n";
echo "创建与 Java Redisson 兼容的测试数据...\n";

// 创建各种数据结构供 Java 读取
$compatMap = $client->getMap('compat:test:map');
$compatMap->clear();
$compatMap->put('string_key', 'Hello from PHP');
$compatMap->put('number_key', 12345);
$compatMap->put('float_key', 3.14159);
$compatMap->put('bool_key', true);
$compatMap->put('array_key', [1, 2, 3, 4, 5]);
$compatMap->put('object_key', [
    'name' => 'Test Object',
    'properties' => ['prop1' => 'value1', 'prop2' => 'value2']
]);

echo "Created compatible map: compat:test:map\n";

$compatList = $client->getList('compat:test:list');
$compatList->clear();
$compatList->add('PHP Item 1');
$compatList->add('PHP Item 2');
$compatList->add(['nested' => 'object']);

echo "Created compatible list: compat:test:list\n";

$compatCounter = $client->getAtomicLong('compat:test:counter');
$compatCounter->set(1000);

echo "Created compatible atomic long: compat:test:counter (value: 1000)\n";

echo "\n这些数据可以被 Java Redisson 应用读取和修改！\n";

// 关闭连接
$client->shutdown();

echo "\n所有高级示例执行完成！\n";
