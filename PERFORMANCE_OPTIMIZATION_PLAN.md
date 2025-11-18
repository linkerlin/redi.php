# 测试性能优化计划

## 问题分析

基于代码审查，测试耗时过长的主要原因：

### 1. 读写锁轮询机制效率低下
- **位置**: [`src/RReadWriteLock.php`](src/RReadWriteLock.php)
- **问题**: 
  - 使用固定的 100ms sleep 时间进行轮询
  - 在锁竞争激烈时产生大量无效重试
  - 累积等待时间显著

### 2. 过度调试日志输出
- **位置**: [`src/RReadWriteLock.php`](src/RReadWriteLock.php:239-498)
- **问题**:
  - 每次锁操作都输出多条日志
  - 日志 I/O 开销大
  - 在生产环境或常规测试中不需要

### 3. 并发测试设计问题
- **位置**: [`tests/DistributedConcurrencyTest.php`](tests/DistributedConcurrencyTest.php)
- **问题**:
  - 使用串行模拟并发，效率低
  - 强制等待 500ms 在每个测试后
  - 迭代次数和进程数设置过高

### 4. 锁竞争导致的重试累积
- **位置**: [`testConcurrentReadWriteLock`](tests/DistributedConcurrencyTest.php:176)
- **问题**:
  - 读写进程同时竞争
  - 写锁获取失败时重试，每次重试 sleep 100ms
  - 多个进程累积的等待时间显著

## 优化方案

### 阶段 1: 移除调试日志 (最高优先级)

#### 目标文件: `src/RReadWriteLock.php`

**移除的日志位置**:
1. ReadLock::tryLock() - 行 239, 244, 250, 256, 264, 269, 277
2. WriteLock::tryLock() - 行 482, 498

**优化效果**: 预计减少 30-40% 的执行时间

### 阶段 2: 优化读写锁轮询机制

#### 目标文件: `src/RReadWriteLock.php`

**ReadLock::tryLock() 优化**:
```php
// 当前: 固定 100ms sleep
usleep(100000);

// 优化: 指数退避算法
$retryCount = 0;
$maxRetryDelay = 50000; // 最大50ms
$retryDelay = min(1000 * pow(2, $retryCount), $maxRetryDelay);
usleep($retryDelay);
$retryCount++;
```

**WriteLock::tryLock() 优化**:
```php
// 当前: 固定 100ms sleep
usleep(100000);

// 优化: 指数退避 + 随机抖动
$retryCount = 0;
$baseDelay = 1000; // 1ms
$maxDelay = 50000; // 50ms
$jitter = rand(0, 10000); // 0-10ms随机抖动
$delay = min($baseDelay * pow(2, $retryCount) + $jitter, $maxDelay);
usleep($delay);
$retryCount++;
```

**优化效果**: 预计减少 40-50% 的锁等待时间

### 阶段 3: 调整测试参数

#### 目标文件: `tests/DistributedConcurrencyTest.php`

**调整参数**:
```php
// testConcurrentMapOperations
$processCount = 3;        // 从 5 减少到 3
$iterationsPerProcess = 20; // 从 50 减少到 20

// testConcurrentListOperations  
$processCount = 2;        // 保持 2
$iterationsPerProcess = 10; // 从 10 保持

// testConcurrentSetOperations
$processCount = 2;        // 保持 2
$iterationsPerProcess = 20; // 从 50 减少到 20

// testConcurrentSortedSetOperations
$processCount = 3;        // 从 5 减少到 3
$iterationsPerProcess = 20; // 从 50 减少到 20

// testConcurrentQueueOperations
$processCount = 2;        // 从 3 减少到 2
$iterationsPerProcess = 20; // 从 50 减少到 20

// testConcurrentLockCompetition
$processCount = 2;        // 从 2 保持
$iterationsPerProcess = 5;  // 从 5 保持

// testConcurrentReadWriteLock
$processCount = 2;        // 从 2 保持
$iterationsPerProcess = 5;  // 从 5 保持

// testConcurrentAtomicOperations
$processCount = 2;        // 从 2 保持
$iterationsPerProcess = 5;  // 从 5 保持

// testConcurrentMixedOperations
$processCount = 2;        // 从 3 减少到 2
$iterationsPerProcess = 20; // 从 50 减少到 20
```

**优化效果**: 预计减少 50-60% 的测试数据量

### 阶段 4: 优化并发测试执行方式

#### 目标文件: `tests/DistributedConcurrencyTest.php`

**移除强制等待**:
```php
// 移除或缩短
// usleep(500000); // 500ms
usleep(100000); // 缩短为 100ms
```

**优化效果**: 每个测试方法减少 400ms 等待时间

## 预期优化效果

### 性能提升预估

| 优化阶段 | 预计时间减少 | 累积效果 |
|---------|-------------|---------|
| 移除调试日志 | 30-40% | 30-40% |
| 优化轮询机制 | 40-50% | 60-70% |
| 调整测试参数 | 50-60% | 80-85% |
| 优化执行方式 | 10-15% | 85-90% |

### 具体测试时间预估

**当前耗时**: 约 5-10 分钟
**优化后预估**: 30-60 秒

## 实施顺序

1. **阶段 1**: 移除调试日志 (快速见效)
2. **阶段 2**: 优化轮询机制 (核心优化)
3. **阶段 3**: 调整测试参数 (数据量减少)
4. **阶段 4**: 优化执行方式 (最后微调)

## 验证方法

1. 运行单个测试方法计时
2. 运行整个测试套件计时
3. 对比优化前后的执行时间
4. 确保所有测试仍然通过

## 风险与注意事项

1. **锁机制变更**: 需要确保优化后的锁机制仍然正确
2. **测试覆盖率**: 参数调整后仍需保持足够的测试覆盖
3. **并发正确性**: 优化不能影响并发测试的正确性验证

## 后续优化建议

1. 考虑使用真正的多进程/多线程并发测试
2. 添加性能基准测试
3. 实现可配置的调试日志开关
4. 考虑使用 Redis 的发布订阅机制优化锁通知