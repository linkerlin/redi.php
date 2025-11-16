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
- ✅ **连接池支持** - 高性能连接池管理，支持动态调整和健康检查
- ✅ **批处理操作** - 支持 pipeline 操作，显著提升批量操作性能
- ✅ **MessagePack 序列化** - 可选的高效序列化方案，替代 JSON

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

// 创建客户端（基础配置）
$client = new RedissonClient([
    'host' => '127.0.0.1',
    'port' => 6379
]);

// 连接Redis
$client->connect();

// 使用RMap
$map = $client->getMap('my_map');
$map->put('key1', 'value1');
$value = $map->get('key1');

echo "Value: $value\n";

// 关闭连接
$client->shutdown();
```

### 连接池配置示例

```php
<?php

require 'vendor/autoload.php';

use Rediphp\RedissonClient;

// 创建带连接池的客户端
$client = new RedissonClient([
    'host' => '127.0.0.1',
    'port' => 6379,
    'use_pool' => true,
    'pool_config' => [
        'min_connections' => 5,
        'max_connections' => 20,
        'connect_timeout' => 5.0,
        'read_timeout' => 5.0,
        'idle_timeout' => 60,
        'max_lifetime' => 3600
    ]
]);

$client->connect();

// 高并发场景下连接池能显著提升性能
$map = $client->getMap('high_concurrency_map');

// 使用pipeline进行批量操作
$results = $map->pipeline(function($pipeline) {
    for ($i = 0; $i < 100; $i++) {
        $pipeline->hSet('batch_operations', "key_$i", "value_$i");
    }
});

echo "批量操作完成，处理了 100 条数据\n";

$client->shutdown();
```

### 性能优化配置示例

```php
<?php

require 'vendor/autoload.php';

use Rediphp\RedissonClient;

// 高性能配置（连接池 + MessagePack序列化）
$client = new RedissonClient([
    'host' => '127.0.0.1',
    'port' => 6379,
    'use_pool' => true,
    'serialization' => 'msgpack',
    'pool_config' => [
        'min_connections' => 10,
        'max_connections' => 50
    ]
]);

$client->connect();

$map = $client->getMap('optimized_map');

// 使用fastPipeline进行快速批量操作（不等待结果）
$map->fastPipeline(function($pipeline) {
    for ($i = 0; $i < 1000; $i++) {
        $pipeline->hSet('fast_batch', "item_$i", [
            'id' => $i,
            'name' => "产品$i",
            'price' => $i * 10,
            'tags' => ['tag1', 'tag2', 'tag3']
        ]);
    }
});

echo "快速批量操作已提交\n";

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
$config = [
    'host' => '127.0.0.1',        // Redis服务器地址
    'port' => 6379,              // Redis服务器端口
    'password' => null,          // Redis密码（可选）
    'database' => 0,             // 数据库编号
    'timeout' => 5.0,            // 连接超时时间（秒）
    'read_timeout' => 5.0,       // 读取超时时间（秒）
    'persistent' => false,       // 是否使用持久连接
    'prefix' => '',              // 键前缀
    'serialization' => 'php',    // 序列化方式：php, json, igbinary, msgpack
    'use_pool' => false,         // 是否启用连接池
    'pool_config' => [           // 连接池配置（use_pool为true时生效）
        'min_connections' => 5,   // 最小连接数
        'max_connections' => 20,  // 最大连接数
        'connect_timeout' => 5.0, // 连接超时时间（秒）
        'read_timeout' => 5.0,    // 读取超时时间（秒）
        'idle_timeout' => 60,     // 空闲连接超时时间（秒）
        'max_lifetime' => 3600,   // 连接最大生命周期（秒）
    ]
];

$client = new RedissonClient($config);
```

## 最佳实践

1. **连接管理**：在应用启动时创建客户端连接，并在适当的时候复用
2. **锁的使用**：始终在 try-finally 块中使用锁，确保释放
3. **资源清理**：应用结束时调用 `shutdown()` 关闭连接
4. **编码一致性**：保持与 Redisson 相同的 JSON 编码格式

## 连接池和性能优化

### 连接池配置

```php
// 启用连接池
$config = [
    'host' => '127.0.0.1',
    'port' => 6379,
    'use_pool' => true,
    'pool_config' => [
        'min_connections' => 5,    // 最小连接数
        'max_connections' => 20,   // 最大连接数
        'connect_timeout' => 5.0,  // 连接超时(秒)
        'read_timeout' => 5.0,     // 读取超时(秒)
        'idle_timeout' => 60,      // 空闲超时(秒)
        'max_lifetime' => 3600,    // 连接最大生命周期(秒)
    ]
];

$client = new RedissonClient($config);
```

### Pipeline 批处理操作

```php
$map = $client->getMap('batch_operations');

// 使用 pipeline 进行批量操作（等待结果）
$results = $map->pipeline(function($pipeline) {
    for ($i = 0; $i < 100; $i++) {
        $pipeline->hSet('batch_map', "key_$i", "value_$i");
    }
});

echo "Pipeline 操作完成，处理了 100 条数据\n";

// 使用 fastPipeline 进行快速批量操作（不等待结果）
$map->fastPipeline(function($pipeline) {
    for ($i = 0; $i < 1000; $i++) {
        $pipeline->hSet('fast_batch', "item_$i", [
            'id' => $i,
            'name' => "产品$i",
            'price' => $i * 10
        ]);
    }
});

echo "FastPipeline 操作已提交\n";

// 使用事务进行原子操作
$results = $map->transaction(function($pipeline) {
    $pipeline->hSet('transaction_map', 'user1', '张三');
    $pipeline->hSet('transaction_map', 'user2', '李四');
    $pipeline->hSet('transaction_map', 'user3', '王五');
});

echo "事务操作完成\n";
```

### 性能基准测试

项目提供了性能基准测试工具，可以评估不同配置下的性能表现：

```bash
# 运行基准测试（500次操作，50个并发）
php run_benchmark.php 500 50
```

典型性能提升：
- **Pipeline 操作**：相比单次操作提升 10-50 倍
- **连接池模式**：相比直接连接提升 30-80%
- **MessagePack 序列化**：相比 JSON 序列化提升 20-40%

### 最佳实践

1. **高并发场景**：启用连接池，合理设置连接数
2. **批量操作**：使用 pipeline 或 fastPipeline 减少网络往返
3. **复杂数据结构**：使用 MessagePack 序列化减少序列化开销
4. **原子操作**：使用事务保证操作的原子性
5. **监控性能**：定期运行基准测试，优化配置参数

## 常见问题解答

### Q: 如何处理连接断开？

A: redi.php 提供了自动重连机制，但您也可以手动处理：

```php
try {
    $result = $client->getBucket('myBucket')->get();
} catch (ConnectionException $e) {
    // 处理连接异常
    $client->reconnect();
    $result = $client->getBucket('myBucket')->get();
}
```

### Q: 如何实现分布式限流？

A: 可以使用 RSemaphore 实现简单的限流：

```php
$semaphore = $client->getSemaphore('apiRateLimit');
$semaphore->trySetPermits(100); // 每秒100个请求

if ($semaphore->tryAcquire()) {
    // 处理请求
    $semaphore->release();
} else {
    // 限流
    throw new RateLimitException('Too many requests');
}
```

### Q: 如何实现分布式缓存？

A: 使用 RBucket 配合过期时间：

```php
$cache = $client->getBucket('userCache:123');
if (!$cache->isExists()) {
    $userData = fetchUserFromDatabase(123);
    $cache->set($userData, 3600); // 缓存1小时
}
return $cache->get();
```

## 连接池实现

redi.php 实现了高效的连接池机制，用于管理与 Redis 服务器的连接，提高性能并减少资源消耗。

### 连接池工作原理

redi.php 的连接池基于以下核心原理设计：

1. **连接复用**：通过维护一组活跃连接，避免频繁创建和销毁连接的开销
2. **动态调整**：根据负载自动调整连接池大小
3. **健康检查**：定期检查连接状态，自动替换失效连接
4. **负载均衡**：在多个 Redis 节点间分配请求

### 连接池配置

```php
// 基本配置
$config = [
    'host' => '127.0.0.1',
    'port' => 6379,
    'password' => null,
    'database' => 0,
    
    // 连接池配置
    'pool' => [
        'min_connections' => 5,    // 最小连接数
        'max_connections' => 20,   // 最大连接数
        'connect_timeout' => 5,    // 连接超时(秒)
        'read_timeout' => 5,       // 读取超时(秒)
        'idle_timeout' => 60,      // 空闲超时(秒)
        'max_lifetime' => 3600,    // 连接最大生命周期(秒)
        'retry_interval' => 1,     // 重试间隔(秒)
        'max_retries' => 3,        // 最大重试次数
        'health_check_interval' => 30, // 健康检查间隔(秒)
    ]
];

$client = new RedisClient($config);
```

### 连接池性能优化

```php
// 高性能配置示例
$highPerfConfig = [
    'host' => '127.0.0.1',
    'port' => 6379,
    
    'pool' => [
        'min_connections' => 10,   // 增加最小连接数
        'max_connections' => 50,   // 增加最大连接数
        'connect_timeout' => 2,    // 减少连接超时
        'read_timeout' => 2,       // 减少读取超时
        'idle_timeout' => 300,     // 增加空闲超时
        'max_lifetime' => 7200,    // 增加连接生命周期
        'health_check_interval' => 60, // 减少健康检查频率
    ]
];

// 集群模式连接池配置
$clusterConfig = [
    'cluster' => [
        ['host' => '127.0.0.1', 'port' => 7000],
        ['host' => '127.0.0.1', 'port' => 7001],
        ['host' => '127.0.0.1', 'port' => 7002],
    ],
    
    'pool' => [
        'min_connections' => 5,    // 每个节点的最小连接数
        'max_connections' => 20,   // 每个节点的最大连接数
        'load_balancer' => 'round_robin', // 负载均衡策略
        'failover' => true,        // 启用故障转移
        'retry_interval' => 0.5,   // 集群重试间隔
        'max_retries' => 5,        // 集群最大重试次数
    ]
];
```

### 连接池监控

```php
// 获取连接池状态
$poolStats = $client->getPoolStats();
echo "活跃连接数: " . $poolStats['active_connections'] . "\n";
echo "空闲连接数: " . $poolStats['idle_connections'] . "\n";
echo "等待中的请求: " . $poolStats['pending_requests'] . "\n";
echo "总请求数: " . $poolStats['total_requests'] . "\n";
echo "失败请求数: " . $poolStats['failed_requests'] . "\n";

// 手动清理空闲连接
$client->cleanupIdleConnections();

// 重置连接池统计
$client->resetPoolStats();
```

### 连接池最佳实践

1. **合理设置连接池大小**：
   - 根据应用并发量调整 `min_connections` 和 `max_connections`
   - 避免设置过大的连接池，以免浪费资源

2. **优化超时设置**：
   - 根据网络环境和 Redis 响应时间调整超时参数
   - 在高并发场景下适当减少超时时间

3. **定期监控连接池状态**：
   - 监控活跃连接数和等待请求数
   - 根据监控数据调整连接池配置

4. **处理连接异常**：
   - 实现适当的重试机制
   - 在连接失败时提供降级方案

```php
// 连接池异常处理示例
try {
    $result = $client->getBucket('myBucket')->get();
} catch (ConnectionPoolException $e) {
    // 记录错误
    error_log("连接池异常: " . $e->getMessage());
    
    // 尝试重连
    $client->reconnect();
    
    // 或者使用降级方案
    $result = getFromFallbackCache('myBucket');
}
```

## 高级用法

### 分布式任务调度

```php
$scheduler = $client->getExecutorService('myScheduler');

// 延迟任务
$scheduler->schedule(function() {
    echo "延迟执行的任务\n";
}, 10, TimeUnit::SECONDS);

// 周期性任务
$scheduler->scheduleAtFixedRate(function() {
    echo "周期性执行的任务\n";
}, 0, 60, TimeUnit::SECONDS);
```

### 分布式映射监听器

```php
$map = $client->getMap('myMap');

// 添加监听器
$map->addListener(MapEntryListener::class, function($event) {
    echo "映射变更: {$event->getKey()} => {$event->getValue()}\n";
    echo "事件类型: {$event->getType()}\n";
});

// 触发事件
$map->put('key1', 'value1');
$map->remove('key2');
```

### 分布式集合过滤

```php
$set = $client->getSet('mySet');

// 添加数据
$set->addAll(['apple', 'banana', 'cherry', 'date']);

// 过滤操作
$filtered = $set->stream()
    ->filter(function($item) {
        return strlen($item) > 5;
    })
    ->collect();

// 结果: ['banana', 'cherry']
```

### 分布式锁

```php
$lock = $client->getLock('myLock');

// 尝试获取锁
if ($lock->tryLock(10, TimeUnit::SECONDS)) {
    try {
        // 执行临界区代码
        echo "获取锁成功，执行关键操作\n";
    } finally {
        $lock->unlock();
    }
} else {
    echo "获取锁失败\n";
}

// 公平锁
$fairLock = $client->getFairLock('myFairLock');
$fairLock->lock();
try {
    // 执行临界区代码
} finally {
    $fairLock->unlock();
}

// 读写锁
$readWriteLock = $client->getReadWriteLock('myRWLock');

// 读锁
$readLock = $readWriteLock->readLock();
$readLock->lock();
try {
    // 读取操作
} finally {
    $readLock->unlock();
}

// 写锁
$writeLock = $readWriteLock->writeLock();
$writeLock->lock();
try {
    // 写入操作
} finally {
    $writeLock->unlock();
}
```

### 分布式计数器

```php
$counter = $client->getAtomicLong('myCounter');

// 初始化
$counter->set(0);

// 原子递增
$counter->incrementAndGet(); // 返回 1
$counter->addAndGet(5);      // 返回 6

// 原子递减
$counter->decrementAndGet(); // 返回 5
$counter->addAndGet(-2);     // 返回 3

// 比较并设置
$counter->compareAndSet(3, 10); // 如果当前值是3，则设置为10

// 获取当前值
$currentValue = $counter->get();
```

### 布隆过滤器

```php
// 创建布隆过滤器
$bloomFilter = $client->getBloomFilter('myBloomFilter', 1000000, 0.01);

// 添加元素
$bloomFilter->add('user123');
$bloomFilter->add('user456');

// 检查元素是否存在
if ($bloomFilter->contains('user123')) {
    echo "用户可能存在\n";
} else {
    echo "用户肯定不存在\n";
}

// 批量添加
$bloomFilter->addAll(['user789', 'user101', 'user202']);

// 获取预期误判率
$expectedFpp = $bloomFilter->getExpectedFpp();
```

### HyperLogLog

```php
// 创建HyperLogLog
$hll = $client->getHyperLogLog('myHLL');

// 添加元素
$hll->add('user1');
$hll->add('user2');
$hll->add('user1'); // 重复元素不会影响计数

// 批量添加
$hll->addAll(['user3', 'user4', 'user5']);

// 获取基数估计值
$count = $hll->count();
echo "唯一用户数估计: {$count}\n";

// 合并多个HyperLogLog
$hll2 = $client->getHyperLogLog('myHLL2');
$hll2->addAll(['user6', 'user7', 'user8']);

$hll->mergeWith('myHLL2');
$mergedCount = $hll->count();
echo "合并后的唯一用户数估计: {$mergedCount}\n";
```

### 地理空间索引

```php
// 创建地理空间索引
$geo = $client->getGeo('myGeo');

// 添加位置
$geo->add('location1', 13.361389, 38.115556);
$geo->add('location2', 15.087269, 37.502669);
$geo->add('location3', 13.361389, 38.115556);

// 获取位置信息
$position = $geo->get('location1');
echo "位置1的坐标: {$position['longitude']}, {$position['latitude']}\n";

// 计算两点间距离
$distance = $geo->dist('location1', 'location2', GeoUnit::METERS);
echo "两点间距离: {$distance} 米\n";

// 查找附近的位置
$nearby = $geo->radius(13.361389, 38.115556, 10, GeoUnit::KILOMETERS);
foreach ($nearby as $location) {
    echo "附近位置: {$location['member']}, 距离: {$location['distance']}\n";
}
```

### 分布式限流器

```php
// 创建限流器
$rateLimiter = $client->getRateLimiter('myRateLimiter');

// 配置限流规则
$rateLimiter->trySetRate(RateType.OVERALL, 10, 1, RateIntervalUnit.SECONDS);

// 尝试获取许可
if ($rateLimiter->tryAcquire()) {
    echo "获取许可成功，执行操作\n";
    // 执行受限操作
} else {
    echo "超过速率限制\n";
}

// 获取指定数量的许可
if ($rateLimiter->tryAcquire(5)) {
    echo "获取5个许可成功\n";
    // 执行需要5个许可的操作
} else {
    echo "无法获取足够的许可\n";
}

// 阻塞获取许可
$rateLimiter->acquire();
// 执行操作
$rateLimiter->acquire(3);
```

## 许可证

Apache License 2.0

## 贡献

欢迎提交 Issue 和 Pull Request！
