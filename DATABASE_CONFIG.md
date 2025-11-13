# Redis数据库配置功能

## 🎯 功能概述

现在redi.php完全支持Redis数据库编号配置，可以在0-15范围内灵活选择数据库，并通过环境变量REDIS_DB进行配置。

## 🔧 支持的配置方式

### 1. 环境变量配置（推荐）

```bash
# 设置使用数据库1
export REDIS_DB=1

# 设置使用数据库5
export REDIS_DB=5

# 临时设置（单次命令）
REDIS_DB=2 php test_database_config.php
```

### 2. .env文件配置

```bash
# 在.env文件中添加
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_DB=3
```

### 3. 代码中直接配置

```php
use Rediphp\RedissonClient;

// 使用自定义数据库
$client = new RedissonClient(['database' => 5]);
$client->connect();
```

## 🛠️ 快速配置工具

### 交互式配置脚本
```bash
./configure_redis.sh
```
选择选项4进行数据库配置

### 快速环境变量设置
```bash
# 基本用法
./set_redis_env.sh

# 指定配置
./set_redis_env.sh localhost 6379 10
# 参数：host port database
```

## 📊 数据库编号说明

| 数据库编号 | 用途建议 | 示例环境 |
|-----------|----------|----------|
| db=0 | 开发环境 | 开发调试 |
| db=1 | 测试环境 | 单元测试 |
| db=2 | 生产环境 | 正式业务 |
| db=15 | 缓存环境 | 临时缓存 |

**范围限制**: Redis原生支持0-15共16个数据库

## 🧪 测试和验证

### 完整功能测试
```bash
# 数据库配置专项测试
php test_database_config.php

# 统一配置测试
php test_config_unified.php

# 语法检查
php -l src/Config.php src/RedissonClient.php
```

### 环境变量生效验证
```bash
export REDIS_DB=7
php -r "
use Rediphp\RedissonClient;
\$client = new RedissonClient();
\$client->connect();
echo '✅ REDIS_DB=7 生效\n';
"
```

## 📝 配置优先级

配置生效优先级（从高到低）：
1. 代码中直接传入的配置参数
2. 环境变量（REDIS_DB）
3. .env文件中的配置
4. 默认值（db=0）

## 🔍 常用场景示例

### 开发环境配置
```bash
export REDIS_HOST=localhost
export REDIS_DB=0
php examples/basic_usage.php
```

### 测试环境配置
```bash
export REDIS_HOST=test-redis-server
export REDIS_DB=1
php tests/IntegrationTest.php
```

### 生产环境配置
```bash
export REDIS_HOST=prod-redis.company.com
export REDIS_DB=2
export REDIS_PASSWORD=secure_password
```

### Docker环境配置
```bash
export REDIS_HOST=redis-server
export REDIS_DB=0
docker-compose up -d
```

## 🚀 性能建议

1. **数据库隔离**: 不同应用使用不同数据库编号
2. **内存管理**: 定期清理不用的数据库
3. **监控告警**: 监控各数据库的内存使用情况
4. **备份策略**: 不同数据库采用不同备份频率

## ⚠️ 注意事项

1. **数据库切换**: 每次连接只能访问一个数据库
2. **事务限制**: 无法跨数据库执行事务
3. **键空间**: 不同数据库的键空间相互独立
4. **连接池**: 大量数据库时注意连接池配置

## 🎉 升级完成

✅ 支持REDIS_DB环境变量  
✅ 支持0-15数据库范围  
✅ 完整的配置管理工具  
✅ 丰富的测试和文档  
✅ 向后兼容REDIS_DATABASE  

现在您可以灵活地配置Redis数据库，满足不同环境和应用的需求！