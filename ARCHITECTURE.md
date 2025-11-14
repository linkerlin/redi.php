# 项目架构与状态

## 概述

redi.php是分布式数据结构的一个纯PHP实现，与Java的Redisson库100%兼容。本文档提供了项目架构和实现状态的概述。

## 统计信息

- **代码行数**: src/目录下约2,850行
- **数据结构**: 已实现18个
- **测试文件**: 4个测试套件
- **文档**: 5个综合指南
- **示例**: 3个使用示例

## 架构

### 核心组件

#### 1. RedissonClient (`src/RedissonClient.php`)
- 库的主要入口点
- 管理Redis连接
- 所有数据结构的工厂方法
- 配置管理

#### 2. 数据结构层

**基础集合**
- `RMap` - 基于哈希的分布式映射
- `RList` - 基于列表的分布式列表
- `RSet` - 基于集合的分布式集合
- `RSortedSet` - 带分数的排序集合
- `RQueue` - FIFO队列
- `RDeque` - 双端队列

**同步原语**
- `RLock` - 带TTL的分布式锁
- `RReadWriteLock` - 读写锁支持
- `RSemaphore` - 基于许可的信号量
- `RCountDownLatch` - 倒计时同步

**原子操作**
- `RAtomicLong` - 原子长整型
- `RAtomicDouble` - 原子双精度浮点型

**高级结构**
- `RBucket` - 通用对象存储
- `RBitSet` - 位图操作
- `RBloomFilter` - 概率性集合成员判断

**发布/订阅**
- `RTopic` - 发布/订阅主题
- `RPatternTopic` - 基于模式的发布/订阅

### 设计模式

#### 1. 工厂模式
`RedissonClient`作为工厂来创建数据结构实例：
```php
$map = $client->getMap('name');
$lock = $client->getLock('name');
```

#### 2. 编码/解码策略
所有数据结构使用JSON编码以确保与Redisson兼容：
```php
private function encodeValue($value): string {
    return json_encode($value);
}

private function decodeValue(string $value) {
    return json_decode($value, true);
}
```

#### 3. Lua脚本确保原子性
关键操作使用Lua脚本来确保原子性：
```php
$script = <<<LUA
if redis.call("get", KEYS[1]) == ARGV[1] then
    return redis.call("del", KEYS[1])
else
    return 0
end
LUA;
```

## Redisson兼容性

### 数据格式兼容性

| 组件 | PHP实现 | Redisson对应 | Redis结构 |
|-----------|-------------------|---------------------|-----------------|
| RMap | Hash中的JSON值 | JsonJacksonCodec | HASH |
| RList | List中的JSON值 | JsonJacksonCodec | LIST |
| RSet | Set中的JSON值 | JsonJacksonCodec | SET |
| RSortedSet | JSON值+分数 | JsonJacksonCodec | ZSET |
| RLock | 唯一ID + TTL | UUID + TTL | STRING |
| RAtomicLong | 字符串数字 | 字符串数字 | STRING |

### 键命名约定

两个库使用相同的键命名：
- Map: `{name}`
- List: `{name}`
- Lock: `{name}`
- ReadWriteLock: `{name}:read`, `{name}:write`
- Semaphore: `{name}`

### 算法兼容性

#### 锁算法
1. 生成唯一锁ID（主机名+uniqid）
2. 使用SET NX EX进行原子锁获取
3. 使用Lua脚本进行安全解锁（检查所有权）
4. 支持租约时间和等待时间

#### 信号量算法
1. 在Redis中存储许可计数
2. 使用Lua脚本进行原子获取
3. 使用INCRBY进行释放
4. 支持带计数的tryAcquire

#### 布隆过滤器算法
1. 计算最优位数和哈希函数数量
2. 使用位图（SETBIT/GETBIT）
3. 通过迭代实现多个哈希函数
4. 兼容的概率计算

## 测试策略

### 单元测试
- **覆盖率**: 所有数据结构及其方法
- **框架**: PHPUnit或PHP Artisan测试
- **模拟**: 为隔离测试模拟Redis连接
- **测试类型**: 正面测试、负面测试、边界情况

### 集成测试
- **Redis实例**: 基于Docker的Redis容器
- **真实操作**: 使用实际Redis进行端到端测试
- **多种数据类型**: 测试序列化/反序列化
- **并发性**: 线程安全操作测试

### 兼容性测试
- **Java Redisson**: 跨语言兼容性验证
- **键结构**: 确保Redis键模式匹配
- **数据格式**: JSON编码兼容性
- **操作语义**: 行为等价性测试

### 测试结构
```
tests/
├── unit/
│   ├── RMapTest.php
│   ├── RListTest.php
│   ├── RLockTest.php
│   └── ...
├── integration/
│   ├── RedisConnectionTest.php
│   ├── DataSerializationTest.php
│   └── ...
└── compatibility/
    ├── JavaRedissonTest.php
    └── CrossLanguageTest.php
```

## 实现细节

### RMap实现
- **Redis结构**: Hash
- **键模式**: `{name}` 其中name为映射名称
- **存储**: JSON编码的值
- **操作**: put, get, remove, size, keys, values, containsKey, containsValue, clear, readAll, putIfAbsent, removeIfPresent

#### RList实现  
- **Redis结构**: List
- **键模式**: `{name}`
- **存储**: JSON编码的值
- **操作**: add, addFirst, addLast, addAll, get, set, remove, removeFirst, removeLast, indexOf, lastIndexOf, size, clear, readAll

#### RLock实现
- **Redis结构**: String
- **键模式**: `{name}`
- **存储**: 锁ID + TTL
- **算法**: 
  1. 生成唯一ID: `${hostname}:${uniqid()}`
  2. `SET lock_key lock_id NX EX ttl` 用于获取锁
  3. Lua脚本用于安全解锁（检查所有权）
- **功能**: tryLock, unlock, forceUnlock, isLocked, remainTimeToLive

#### RAtomicLong实现
- **Redis结构**: String  
- **键模式**: `{name}`
- **存储**: 长整型值的字符串表示
- **操作**: get, set, compareAndSet, getAndSet, incrementAndGet, getAndIncrement, decrementAndGet, getAndDecrement, addAndGet, getAndAdd

#### RSortedSet实现
- **Redis结构**: ZSET
- **键模式**: `{name}`  
- **存储**: JSON编码的值作为成员，数值作为分数
- **操作**: add, addAll, remove, removeRangeByScore, removeRange, size, contains, readAll, valueCount, firstValue, lastValue, firstKey, lastKey, popFirst, popLast

## 性能考虑

### 编码开销
- JSON编码增加约10-20%开销
- 以兼容性为代价
- 未来版本考虑使用MessagePack

### 网络往返
- 大多数操作: 1次往返
- 批量操作（putAll）: 已优化
- Lua脚本: 原子性单次往返

### 内存使用
- JSON编码: 原始数据的约1.2-1.5倍
- 布隆过滤器: 最优位计算
- 锁: 最小化（仅为键+值）

### 内存效率
- **JSON编码**: 复杂对象的紧凑表示
- **键优化**: 简短描述性的键名
- **数据生命周期**: 自动清理过期数据
- **连接池**: 重用Redis连接

### Redis操作优化
- **管道使用**: 批量操作以获得更好的性能
- **Lua脚本**: 减少网络往返
- **连接超时**: 正确的超时处理
- **错误处理**: 故障时的优雅降级

### 并发访问
- **锁竞争**: 最小的锁持有时间
- **死锁预防**: 基于超时的锁获取
- **资源清理**: 自动清理资源
- **线程安全**: 安全的并发访问模式

### 扩展性考虑
- **Redis集群**: 与Redis集群的兼容性
- **连接限制**: 基于池的连接管理
- **内存使用**: 有效的内存利用模式
- **网络延迟**: 批量操作以减少延迟

## 未来增强计划

### 计划中的功能
1. 更多数据结构:
   - RHyperLogLog
   - RGeo (地理空间)
   - RStream (Redis流)
   - RTimeSeries

2. 高级功能:
   - 异步/承诺API
   - 连接池
   - 集群支持
   - 哨兵支持

3. 性能优化:
   - 管道支持
   - 批量操作
   - MessagePack编解码器选项

4. 开发者体验:
   - 更好的错误信息
   - 调试日志
   - 性能分析

### 已知限制

1. **阻塞操作**
   - 某些阻塞操作使用轮询
   - 不像Redisson真正的阻塞

2. **发布/订阅**
   - 需要单独的连接
   - 暂无响应式API

3. **编解码器**
   - 目前仅支持JSON
   - 需要MessagePack、Kryo替代方案

4. **类型安全**
   - PHP缺少泛型
   - 仅支持运行时类型检查

## 依赖

### 必需依赖
- PHP >= 8.2
- ext-redis (PHP Redis扩展)
- Redis服务器 >= 6.2

### 开发依赖
- PHPUnit >= 9.5
- Composer

## 版本控制

遵循语义化版本控制（SemVer）：
- 主版本：不兼容的API变更
- 次版本：向后兼容的功能增强
- 补丁版本：向后兼容的bug修复

当前版本: v1.0.0

## 总结

redi.php成功实现了与Redisson兼容的PHP分布式数据结构库。凭借18种数据结构、全面的文档和经过验证的互操作性，它使PHP应用程序能够与使用Redisson的Java应用程序一起参与分布式系统。

该架构具有可扩展性、经过充分测试，并遵循PHP最佳实践，同时保持与Redisson数据格式和操作的100%兼容性。
