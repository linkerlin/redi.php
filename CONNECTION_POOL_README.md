# RediPHP 连接池实现

## 概述

RediPHP 实现了一个高效的 Redis 连接池，用于管理和复用 Redis 连接，提高应用程序性能并减少资源消耗。

## 核心组件

### 1. RedisConnectionPool 类

位置：`src/Redis/ConnectionPool.php`

主要功能：
- 管理多个 Redis 连接
- 提供连接获取和释放机制
- 监控连接池状态和统计信息
- 自动处理连接健康检查

### 2. RedissonClient 类

位置：`src/RedissonClient.php`

主要功能：
- 封装连接池操作
- 提供统一的 Redis 操作接口
- 管理不同数据结构的操作

## 连接池配置

```php
$config = [
    'host' => '127.0.0.1',
    'port' => 6379,
    'password' => null,
    'database' => 0,
    'timeout' => 2.0,
    'pool' => [
        'min_connections' => 2,
        'max_connections' => 10,
        'connection_timeout' => 5.0,
        'read_timeout' => 5.0,
        'retry_interval' => 1.0,
        'max_retries' => 3,
        'health_check_interval' => 30,
        'idle_timeout' => 300,
    ]
];
```

## 使用方法

### 基本使用

```php
use RediPHP\RedissonClient;

// 创建客户端
$client = new RedissonClient($config);

// 获取连接池
$pool = $client->getConnectionPool();

// 获取连接
$connection = $pool->getConnection();

// 执行 Redis 命令
$result = $connection->set('key', 'value');

// 释放连接
$pool->releaseConnection($connection);
```

### 使用数据结构

```php
// 使用 RBucket
$bucket = $client->getBucket('user:1');
$bucket->set(['name' => '张三', 'age' => 25]);
$user = $bucket->get();

// 使用 RSet
$set = $client->getSet('tokens');
$set->add('token1', 'token2', 'token3');
$contains = $set->contains('token2');

// 使用 RList
$list = $client->getList('logs');
$list->add('log1', 'log2', 'log3');
$first = $list->get(0);

// 使用 RMap
$map = $client->getMap('session');
$map->put('page', 'home');
$map->put('user', '12345');
$hasPage = $map->containsKey('page');
```

## 连接池统计信息

```php
// 获取连接池统计信息
$stats = $pool->getStats();

// 输出统计信息
echo "空闲连接数: " . $stats['idle_connections'] . "\n";
echo "活跃连接数: " . $stats['active_connections'] . "\n";
echo "总连接数: " . $stats['total_connections'] . "\n";
echo "连接池利用率: " . $stats['pool_utilization'] . "%\n";
echo "总请求数: " . $stats['total_requests'] . "\n";
echo "成功获取数: " . $stats['successful_acquisitions'] . "\n";
```

## 连接池特性

1. **动态连接管理**：根据负载自动调整连接数量
2. **连接健康检查**：定期检查连接有效性
3. **连接超时处理**：自动处理超时和重试
4. **统计信息收集**：提供详细的连接池使用统计
5. **资源清理**：自动清理空闲连接

## 测试

运行连接池测试：

```bash
php test_connection_pool.php
```

运行综合测试：

```bash
php test_comprehensive_connection_pool.php
```

## 性能优化

1. **合理设置连接池大小**：根据应用负载调整最小和最大连接数
2. **设置适当的超时时间**：避免长时间等待
3. **定期监控连接池状态**：及时发现和解决问题
4. **使用连接池统计信息**：优化连接池配置

## 注意事项

1. 确保正确释放连接，避免连接泄漏
2. 在高并发场景下适当增加最大连接数
3. 定期检查连接池状态，确保连接健康
4. 在应用关闭时正确关闭连接池

## 示例输出

```
=== RediPHP 连接池综合测试 ===

✅ RedissonClient 初始化成功

--- 测试 RBucket (对象存储) ---
📦 RBucket: {"name":"张三","age":25}

--- 测试 RSet (集合) ---
🔢 RSet 包含元素数量: 3
🔢 RSet 包含 'token2': 是

--- 连接池信息 ---
📊 使用连接池: 是
📊 数据库: 0

--- 详细连接池统计信息 ---
🔍 连接池状态:
   空闲连接数: 2
   活跃连接数: 0
   总连接数: 2
   最小连接数: 2
   最大连接数: 10
   连接池利用率: 0%
   总请求数: 29
   成功获取数: 29
   平均获取时间: 0ms
   最大获取时间: 0.01ms
   最小获取时间: 0ms

✅ 所有数据结构的连接池功能测试通过！
```

## 总结

RediPHP 连接池实现提供了一个高效、可靠的 Redis 连接管理解决方案，通过连接复用和动态管理，显著提高了应用程序的性能和资源利用率。连接池的统计信息和健康检查机制确保了系统的稳定性和可靠性。