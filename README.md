# redi.php

一个纯PHP的分布式数据结构库，等价于Redisson的PHP实现。

## 简介

redi.php 是一个完全兼容 Redisson 的 PHP 分布式数据结构库。它提供了与 Redisson 相同的数据结构和分布式操作能力，可以与 Java 的 Redisson 无缝协作。

## 特性

- ✅ **100% Redisson 兼容** - 数据结构和编码格式与 Redisson 完全一致
- ✅ **丰富的数据结构** - 支持 Map、List、Set、Queue、Lock 等多种分布式数据结构
- ✅ **分布式锁** - 支持分布式锁、读写锁、信号量等同步机制
- ✅ **原子操作** - 支持原子长整型、原子浮点型等原子操作
- ✅ **发布订阅** - 支持 Topic 和 Pattern Topic
- ✅ **高级数据结构** - 支持 BitSet、BloomFilter 等高级数据结构
- ✅ **专业数据结构** - 支持 HyperLogLog、Geo、Stream、TimeSeries 等专业数据结构

## 安装

```bash
composer require linkerlin/redi.php
```

## 要求

- PHP >= 8.2
- Redis 扩展
- Redis 服务器

## 快速开始

### 基本使用

```php
<?php

require 'vendor/autoload.php';

use Rediphp\RedissonClient;

// 创建客户端
$client = new RedissonClient([
    'host' => '127.0.0.1',
    'port' => 6379,
]);

// 连接到 Redis
$client->connect();

// 使用分布式 Map
$map = $client->getMap('myMap');
$map->put('key1', 'value1');
$map->put('key2', ['nested' => 'value']);
echo $map->get('key1'); // 输出: value1

// 使用分布式 List
$list = $client->getList('myList');
$list->add('item1');
$list->add('item2');
print_r($list->toArray()); // 输出: ['item1', 'item2']

// 使用分布式锁
$lock = $client->getLock('myLock');
if ($lock->tryLock()) {
    try {
        // 执行需要同步的代码
        echo "获取锁成功\n";
    } finally {
        $lock->unlock();
    }
}

// 关闭连接
$client->shutdown();
```

## 支持的数据结构

### 基础数据结构

#### RMap - 分布式 Map

```php
$map = $client->getMap('myMap');
$map->put('key', 'value');              // 添加键值对
$value = $map->get('key');              // 获取值
$map->remove('key');                    // 删除键
$map->containsKey('key');               // 检查键是否存在
$size = $map->size();                   // 获取大小
$map->putAll(['k1' => 'v1', 'k2' => 'v2']); // 批量添加
$entries = $map->entrySet();            // 获取所有条目
```

#### RList - 分布式 List

```php
$list = $client->getList('myList');
$list->add('item');                     // 添加元素
$item = $list->get(0);                  // 获取索引元素
$list->remove('item');                  // 删除元素
$list->set(0, 'newItem');              // 设置索引元素
$size = $list->size();                  // 获取大小
$array = $list->toArray();              // 转换为数组
```

#### RSet - 分布式 Set

```php
$set = $client->getSet('mySet');
$set->add('element');                   // 添加元素
$set->remove('element');                // 删除元素
$set->contains('element');              // 检查是否包含
$size = $set->size();                   // 获取大小
$array = $set->toArray();               // 转换为数组
```

#### RQueue - 分布式队列

```php
$queue = $client->getQueue('myQueue');
$queue->offer('item');                  // 入队
$item = $queue->poll();                 // 出队
$item = $queue->peek();                 // 查看队首元素
$size = $queue->size();                 // 获取大小
```

#### RDeque - 分布式双端队列

```php
$deque = $client->getDeque('myDeque');
$deque->addFirst('item');               // 队首添加
$deque->addLast('item');                // 队尾添加
$item = $deque->removeFirst();          // 移除队首
$item = $deque->removeLast();           // 移除队尾
$item = $deque->peekFirst();            // 查看队首
$item = $deque->peekLast();             // 查看队尾
```

#### RSortedSet - 分布式有序集合

```php
$sortedSet = $client->getSortedSet('mySortedSet');
$sortedSet->add(1.0, 'element1');       // 添加元素和分数
$sortedSet->add(2.0, 'element2');
$score = $sortedSet->score('element1'); // 获取分数
$rank = $sortedSet->rank('element1');   // 获取排名
$elements = $sortedSet->range(0, 10);   // 获取范围元素
$elements = $sortedSet->rangeByScore(1.0, 5.0); // 按分数范围获取
```

### 分布式同步机制

#### RLock - 分布式锁

```php
$lock = $client->getLock('myLock');
$lock->lock();                          // 获取锁
$locked = $lock->tryLock(1000, 30000);  // 尝试获取锁（等待时间、租期）
$lock->unlock();                        // 释放锁
$isLocked = $lock->isLocked();          // 检查是否被锁定
```

#### RReadWriteLock - 分布式读写锁

```php
$rwLock = $client->getReadWriteLock('myRWLock');
$readLock = $rwLock->readLock();        // 获取读锁
$writeLock = $rwLock->writeLock();      // 获取写锁

$readLock->lock();                      // 加读锁
// ... 读操作 ...
$readLock->unlock();                    // 释放读锁

$writeLock->lock();                     // 加写锁
// ... 写操作 ...
$writeLock->unlock();                   // 释放写锁
```

#### RSemaphore - 分布式信号量

```php
$semaphore = $client->getSemaphore('mySemaphore');
$semaphore->trySetPermits(5);           // 设置许可数
$semaphore->acquire();                  // 获取许可
$semaphore->release();                  // 释放许可
$available = $semaphore->availablePermits(); // 获取可用许可数
```

#### RCountDownLatch - 分布式倒计时锁存器

```php
$latch = $client->getCountDownLatch('myLatch');
$latch->trySetCount(10);                // 设置计数
$latch->countDown();                    // 减少计数
$latch->await(5000);                    // 等待计数归零（超时毫秒）
$count = $latch->getCount();            // 获取当前计数
```

### 原子操作

#### RAtomicLong - 分布式原子长整型

```php
$atomicLong = $client->getAtomicLong('myAtomicLong');
$atomicLong->set(100);                  // 设置值
$value = $atomicLong->get();            // 获取值
$newValue = $atomicLong->incrementAndGet(); // 自增并获取
$newValue = $atomicLong->addAndGet(10); // 加法并获取
$success = $atomicLong->compareAndSet(100, 200); // 比较并设置
```

#### RAtomicDouble - 分布式原子浮点型

```php
$atomicDouble = $client->getAtomicDouble('myAtomicDouble');
$atomicDouble->set(3.14);               // 设置值
$value = $atomicDouble->get();          // 获取值
$newValue = $atomicDouble->addAndGet(1.5); // 加法并获取
```

### 高级数据结构

#### RBucket - 分布式对象持有者

```php
$bucket = $client->getBucket('myBucket');
$bucket->set(['data' => 'value']);      // 设置对象
$data = $bucket->get();                 // 获取对象
$bucket->trySet(['new' => 'data']);     // 仅当不存在时设置
$old = $bucket->getAndSet(['updated' => 'data']); // 获取并设置
```

#### RBitSet - 分布式位集合

```php
$bitSet = $client->getBitSet('myBitSet');
$bitSet->set(10);                       // 设置第10位
$value = $bitSet->get(10);              // 获取第10位
$bitSet->clear(10);                     // 清除第10位
$count = $bitSet->cardinality();        // 获取设置的位数
```

#### RBloomFilter - 分布式布隆过滤器

```php
$bloomFilter = $client->getBloomFilter('myBloomFilter');
$bloomFilter->tryInit(1000000, 0.01);   // 初始化（预期插入数、误判率）
$bloomFilter->add('element');           // 添加元素
$exists = $bloomFilter->contains('element'); // 检查元素是否可能存在
```

### 专业数据结构

#### RHyperLogLog - 分布式基数统计

```php
$hyperLogLog = $client->getHyperLogLog('myHyperLogLog');
$hyperLogLog->add('user1');             // 添加元素
$hyperLogLog->add('user2');             // 添加元素
$hyperLogLog->addAll(['user3', 'user4']); // 批量添加
$count = $hyperLogLog->count();         // 获取基数估计值
$hyperLogLog->merge('otherHyperLogLog'); // 合并其他HyperLogLog
```

#### RGeo - 分布式地理空间数据结构

```php
$geo = $client->getGeo('myGeo');
$geo->add('Beijing', 116.4074, 39.9042); // 添加地理坐标
$geo->add('Shanghai', 121.4737, 31.2304);
$geo->add('Guangzhou', 113.2644, 23.1291);

$distance = $geo->distance('Beijing', 'Shanghai', 'km'); // 计算距离
$hash = $geo->hash('Beijing');          // 获取地理哈希
$position = $geo->position('Beijing'); // 获取坐标
$nearby = $geo->radius(116.4074, 39.9042, 100, 'km'); // 范围搜索
```

#### RStream - 分布式流数据结构

```php
$stream = $client->getStream('myStream');
$stream->add(['field1' => 'value1']);   // 添加流条目
$stream->add(['field2' => 'value2'], '*', ['maxlen' => 1000]); // 带最大长度
$entries = $stream->read();             // 读取所有条目
$stream->createGroup('myGroup', '0');  // 创建消费者组
$groupEntries = $stream->readGroup('myGroup', 'consumer1', '0'); // 组消费
$stream->ack('myGroup', [$entryId]);   // 确认消费
```

#### RTimeSeries - 分布式时间序列数据结构

```php
$timeSeries = $client->getTimeSeries('myTimeSeries');
$timestamp = time() * 1000;            // 毫秒时间戳
$timeSeries->add($timestamp, 42.5);    // 添加数据点
$timeSeries->addAll([                   // 批量添加
    [$timestamp + 1000, 43.0],
    [$timestamp + 2000, 44.5]
]);
$value = $timeSeries->get($timestamp); // 获取数据点
$range = $timeSeries->range($startTime, $endTime); // 范围查询
$stats = $timeSeries->getStats();    // 获取统计信息
```

### 发布订阅

#### RTopic - 分布式主题

```php
$topic = $client->getTopic('myTopic');
$topic->publish(['message' => 'Hello']); // 发布消息

// 订阅（需要在单独的进程/连接中）
$topic->subscribe(function($message) {
    echo "收到消息: " . json_encode($message) . "\n";
});
```

#### RPatternTopic - 模式主题

```php
$patternTopic = $client->getPatternTopic('myTopic.*');
$patternTopic->subscribe(function($channel, $message) {
    echo "从频道 $channel 收到消息: " . json_encode($message) . "\n";
});
```

## 与 Redisson 的兼容性

redi.php 使用与 Redisson 相同的数据编码格式，确保了完全的互操作性：

- **数据格式**：使用 JSON 编码，与 Redisson 的默认编码器兼容
- **键命名**：使用相同的键命名约定
- **分布式算法**：实现了相同的分布式锁和同步算法
- **Lua 脚本**：对于需要原子操作的场景，使用了相同的 Lua 脚本逻辑

这意味着：
- PHP 应用可以读取和修改 Java Redisson 应用创建的数据
- Java Redisson 应用可以读取和修改 PHP redi.php 应用创建的数据
- 分布式锁可以在 PHP 和 Java 应用之间正常工作

## 配置选项

```php
$client = new RedissonClient([
    'host' => '127.0.0.1',      // Redis 主机
    'port' => 6379,             // Redis 端口
    'password' => null,         // Redis 密码（可选）
    'database' => 0,            // Redis 数据库编号
    'timeout' => 0.0,           // 连接超时（秒）
]);
```

## 最佳实践

1. **连接管理**：在应用启动时创建客户端连接，并在适当的时候复用
2. **锁的使用**：始终在 try-finally 块中使用锁，确保释放
3. **资源清理**：应用结束时调用 `shutdown()` 关闭连接
4. **编码一致性**：保持与 Redisson 相同的 JSON 编码格式

## 许可证

Apache License 2.0

## 贡献

欢迎提交 Issue 和 Pull Request！
