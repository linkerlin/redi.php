# Redisson 兼容性指南

## 概述

redi.php 旨在提供与 Java Redisson 库 100% 的兼容性，使得 PHP 和 Java 应用程序可以共享相同的 Redis 数据结构，并在分布式环境中协同工作。

## 数据编码兼容性

### JSON 编码

redi.php 使用 JSON 编码来存储对象，这与 Redisson 的默认 `JsonJacksonCodec` 兼容：

**PHP 代码：**
```php
$map = $client->getMap('user:1');
$map->put('name', 'John Doe');
$map->put('age', 30);
$map->put('data', ['key' => 'value']);
```

**Java/Redisson 代码：**
```java
RMap<String, Object> map = redisson.getMap("user:1");
String name = (String) map.get("name");        // "John Doe"
Integer age = (Integer) map.get("age");        // 30
Map data = (Map) map.get("data");              // {key=value}
```

### 键命名约定

两个库使用相同的键命名约定：

- Map: `{name}`
- List: `{name}`
- Set: `{name}`
- Lock: `{name}`
- ReadWriteLock: `{name}:read` 和 `{name}:write`
- Semaphore: `{name}`
- CountDownLatch: `{name}`
- AtomicLong: `{name}`
- Topic: `{name}`

## 互操作性示例

### 示例 1: 共享分布式 Map

**PHP 写入：**
```php
$client = new RedissonClient(['host' => '127.0.0.1']);
$client->connect();

$map = $client->getMap('shared:config');
$map->put('timeout', 5000);
$map->put('retries', 3);
$map->put('endpoints', ['api1', 'api2']);
```

**Java 读取：**
```java
Config config = new Config();
config.useSingleServer().setAddress("redis://127.0.0.1:6379");
RedissonClient redisson = Redisson.create(config);

RMap<String, Object> map = redisson.getMap("shared:config");
Integer timeout = (Integer) map.get("timeout");      // 5000
Integer retries = (Integer) map.get("retries");      // 3
List endpoints = (List) map.get("endpoints");        // [api1, api2]
```

### 示例 2: 分布式锁协作

**PHP 获取锁：**
```php
$lock = $client->getLock('resource:lock');
if ($lock->tryLock(1000, 30000)) {
    try {
        // 执行关键操作
        processResource();
    } finally {
        $lock->unlock();
    }
}
```

**Java 等待并获取锁：**
```java
RLock lock = redisson.getLock("resource:lock");
try {
    // 等待 PHP 释放锁
    if (lock.tryLock(5, 30, TimeUnit.SECONDS)) {
        try {
            // 执行关键操作
            processResource();
        } finally {
            lock.unlock();
        }
    }
} catch (InterruptedException e) {
    Thread.currentThread().interrupt();
}
```

### 示例 3: 原子计数器

**PHP 增加计数：**
```php
$counter = $client->getAtomicLong('page:views');
$views = $counter->incrementAndGet();
echo "Page views: $views\n";
```

**Java 读取计数：**
```java
RAtomicLong counter = redisson.getAtomicLong("page:views");
long views = counter.get();
System.out.println("Page views: " + views);
```

### 示例 4: 发布订阅

**PHP 发布消息：**
```php
$topic = $client->getTopic('notifications');
$topic->publish([
    'type' => 'alert',
    'message' => 'System update',
    'timestamp' => time()
]);
```

**Java 订阅消息：**
```java
RTopic topic = redisson.getTopic("notifications");
topic.addListener(Map.class, (channel, msg) -> {
    String type = (String) msg.get("type");
    String message = (String) msg.get("message");
    Long timestamp = (Long) msg.get("timestamp");
    System.out.println("Received: " + message);
});
```

## 数据结构映射

| redi.php | Redisson | Redis 结构 |
|----------|----------|-----------|
| RMap | RMap | Hash |
| RList | RList | List |
| RSet | RSet | Set |
| RSortedSet | RScoredSortedSet | Sorted Set |
| RQueue | RQueue | List |
| RDeque | RDeque | List |
| RLock | RLock | String + Lua |
| RReadWriteLock | RReadWriteLock | String + Hash |
| RSemaphore | RSemaphore | String + Lua |
| RCountDownLatch | RCountDownLatch | String |
| RAtomicLong | RAtomicLong | String |
| RAtomicDouble | RAtomicDouble | String |
| RBucket | RBucket | String |
| RBitSet | RBitSet | Bitmap |
| RBloomFilter | RBloomFilter | Bitmap |
| RTopic | RTopic | Pub/Sub |
| RPatternTopic | RPatternTopic | Pattern Pub/Sub |

## Lua 脚本兼容性

对于需要原子操作的场景（如锁、信号量、CAS 操作），redi.php 使用与 Redisson 相同的 Lua 脚本逻辑，确保操作的原子性和一致性。

### 分布式锁 Lua 脚本

**解锁脚本（PHP 和 Java 相同）：**
```lua
if redis.call("get", KEYS[1]) == ARGV[1] then
    return redis.call("del", KEYS[1])
else
    return 0
end
```

### 信号量 Lua 脚本

**获取许可脚本：**
```lua
local value = redis.call('get', KEYS[1])
if value == false then
    return 0
end
local current = tonumber(value)
if current >= tonumber(ARGV[1]) then
    redis.call('decrby', KEYS[1], ARGV[1])
    return 1
else
    return 0
end
```

## 已知差异

### 1. 编码器配置

- **Redisson**: 支持多种编码器（Jackson JSON, MsgPack, Kryo 等）
- **redi.php**: 目前仅支持 JSON 编码

**解决方案**: 在 Java 端配置 Redisson 使用 `JsonJacksonCodec`：

```java
Config config = new Config();
config.setCodec(new JsonJacksonCodec());
```

### 2. 泛型支持

- **Redisson**: 支持 Java 泛型
- **redi.php**: PHP 不支持泛型，使用动态类型

这不影响数据兼容性，只是类型系统的差异。

### 3. 阻塞操作

某些 Redisson 的阻塞操作在 redi.php 中使用轮询实现：

- `await()` 在 RCountDownLatch 中使用轮询而非真正阻塞
- 订阅操作可能有轻微的行为差异

### 4. 异步 API

- **Redisson**: 提供异步和响应式 API
- **redi.php**: 目前仅提供同步 API

## 最佳实践

### 1. 使用一致的键前缀

```php
// PHP
$map = $client->getMap('app:user:profile');

// Java
RMap<String, Object> map = redisson.getMap("app:user:profile");
```

### 2. 保持数据结构简单

为了最佳兼容性，使用简单的 JSON 可序列化数据类型：
- 字符串
- 数字
- 布尔值
- 数组/列表
- 对象/映射

### 3. 测试跨语言兼容性

在集成环境中测试 PHP 和 Java 应用的互操作性：

```bash
# 启动 Redis
redis-server

# 运行 PHP 测试
php tests/compatibility_test.php

# 运行 Java 测试
mvn test -Dtest=CompatibilityTest
```

### 4. 监控和日志

记录跨语言操作以便调试：

```php
$map->put('key', 'value');
error_log('PHP stored: ' . json_encode(['key' => 'value']));
```

## 测试工具

### 兼容性测试脚本

创建测试脚本验证 PHP 和 Java 之间的数据交换：

**test_compatibility.php:**
```php
<?php
require 'vendor/autoload.php';

$client = new Rediphp\RedissonClient();
$client->connect();

// 写入测试数据
$map = $client->getMap('compat:test');
$map->clear();
$map->put('string', 'Hello');
$map->put('number', 123);
$map->put('float', 3.14);
$map->put('bool', true);
$map->put('array', [1, 2, 3]);
$map->put('object', ['key' => 'value']);

echo "PHP wrote test data\n";
echo "Run Java test to verify\n";
```

**CompatibilityTest.java:**
```java
public class CompatibilityTest {
    @Test
    public void testReadFromPHP() {
        Config config = new Config();
        config.useSingleServer().setAddress("redis://127.0.0.1:6379");
        config.setCodec(new JsonJacksonCodec());
        
        RedissonClient redisson = Redisson.create(config);
        RMap<String, Object> map = redisson.getMap("compat:test");
        
        assertEquals("Hello", map.get("string"));
        assertEquals(123, map.get("number"));
        assertEquals(3.14, (Double) map.get("float"), 0.01);
        assertEquals(true, map.get("bool"));
        assertEquals(Arrays.asList(1, 2, 3), map.get("array"));
        
        Map<String, String> obj = (Map) map.get("object");
        assertEquals("value", obj.get("key"));
    }
}
```

## 版本兼容性

- redi.php v1.0.x 兼容 Redisson 3.x
- 未来版本将持续跟踪 Redisson 的新特性

## 支持和反馈

如果发现兼容性问题，请提交 Issue 并提供：
1. redi.php 版本
2. Redisson 版本
3. Redis 版本
4. 重现步骤
5. 预期行为 vs 实际行为
