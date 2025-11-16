# 真实世界场景集成测试

本目录包含真实世界场景的集成测试用例，展示如何在实际应用中使用redi.php库。

## 测试文件

- `RealWorldScenariosIntegrationTest.php` - 包含多个真实世界场景的集成测试用例

## 测试场景

### 1. 电商购物车场景 (testEcommerceShoppingCart)

测试电商应用中的购物车功能，包括：
- 用户购物车管理
- 商品库存管理
- 用户会话管理
- 购物车计数器

**使用的redi.php组件：**
- `RMap` - 存储购物车内容和库存信息
- `RBucket` - 存储用户会话数据
- `RAtomicLong` - 购物车商品计数器

### 2. 分布式任务队列场景 (testDistributedTaskQueue)

测试分布式任务处理系统，包括：
- 任务队列管理
- 失败任务处理
- 工作进程信号量控制
- 任务计数统计

**使用的redi.php组件：**
- `RQueue` - 任务队列管理
- `RSemaphore` - 工作进程并发控制
- `RAtomicLong` - 任务计数器

### 3. 用户会话管理场景 (testUserSessionManagement)

测试用户会话管理系统，包括：
- 活跃用户跟踪
- 用户会话存储
- 会话超时处理
- 在线用户统计

**使用的redi.php组件：**
- `RSet` - 活跃用户集合
- `RMap` - 用户会话映射
- `RQueue` - 会话超时队列
- `RAtomicLong` - 在线用户计数器
- `RReadWriteLock` - 会话读写锁

### 4. 实时通知系统场景 (testRealTimeNotificationSystem)

测试实时通知系统，包括：
- 通知发布与订阅
- 用户通知偏好设置
- 通知历史记录
- 未读通知计数

**使用的redi.php组件：**
- `RTopic` - 通知主题发布
- `RMap` - 用户订阅映射和未读计数
- `RList` - 通知历史记录

### 5. 分布式限流场景 (testDistributedRateLimiting)

测试分布式限流系统，包括：
- 用户级别限流
- 全局限流
- 限流配置管理
- 被限流用户跟踪

**使用的redi.php组件：**
- `RMap` - 用户计数器和限流配置
- `RAtomicLong` - 全局计数器
- `RBucket` - 限流配置存储
- `RSet` - 被限流用户集合

### 6. 分布式缓存场景 (testDistributedCache)

测试分布式缓存系统，包括：
- 缓存数据存储
- 缓存元数据管理
- 缓存命中率统计
- 布隆过滤器优化

**使用的redi.php组件：**
- `RMap` - 缓存数据和元数据
- `RAtomicLong` - 缓存统计计数器
- `RBloomFilter` - 快速判断缓存是否存在

## 运行测试

要运行这些测试，请使用以下命令：

```bash
# 运行所有真实世界场景测试
php vendor/bin/phpunit tests/RealWorldScenariosIntegrationTest.php

# 运行特定测试方法
php vendor/bin/phpunit tests/RealWorldScenariosIntegrationTest.php --filter testEcommerceShoppingCart
```

## 测试最佳实践

1. **测试隔离**：每个测试方法都会清理自己创建的数据，确保测试之间不会相互影响。
2. **真实场景**：测试用例基于真实应用场景，展示redi.php在实际应用中的使用方式。
3. **组件组合**：测试展示了如何组合使用多个redi.php组件来构建复杂的应用功能。
4. **错误处理**：测试中包含了基本的错误处理和边界条件检查。

## 扩展测试

您可以根据自己的应用场景扩展这些测试用例：

1. 添加新的测试方法来测试您的特定场景
2. 扩展现有测试方法以包含更多边界条件
3. 添加性能测试来评估redi.php在您的应用中的表现
4. 添加并发测试来验证redi.php在高并发场景下的行为

## 注意事项

1. 这些测试需要Redis服务器运行在本地或可访问的位置。
2. 测试会创建和删除Redis中的数据，请确保不要在生产环境中运行这些测试。
3. 某些测试可能需要调整参数以适应您的特定环境或需求。