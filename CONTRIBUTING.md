# 贡献指南 / Contributing Guide

感谢您对 redi.php 的关注！我们欢迎所有形式的贡献。

Thank you for your interest in redi.php! We welcome all forms of contributions.

## 如何贡献 / How to Contribute

### 报告问题 / Reporting Issues

如果您发现了 bug 或有功能请求，请：

If you find a bug or have a feature request, please:

1. 检查是否已有相关 Issue / Check if there's already a related issue
2. 提供详细的问题描述 / Provide detailed description
3. 包含重现步骤 / Include steps to reproduce
4. 提供环境信息（PHP 版本、Redis 版本等）/ Provide environment info (PHP version, Redis version, etc.)

### 提交代码 / Submitting Code

1. Fork 本仓库 / Fork this repository
2. 创建您的特性分支 / Create your feature branch
   ```bash
   git checkout -b feature/amazing-feature
   ```
3. 提交您的修改 / Commit your changes
   ```bash
   git commit -m 'Add some amazing feature'
   ```
4. 推送到分支 / Push to the branch
   ```bash
   git push origin feature/amazing-feature
   ```
5. 创建 Pull Request / Create a Pull Request

## 代码规范 / Code Standards

### PHP 代码风格 / PHP Code Style

- 使用 PSR-4 自动加载 / Use PSR-4 autoloading
- 使用 PSR-12 代码风格 / Use PSR-12 coding style
- 所有公共方法必须有文档注释 / All public methods must have doc comments
- 使用类型声明 / Use type declarations

### 命名规范 / Naming Conventions

- 类名使用大驼峰 / Class names use PascalCase
- 方法名使用小驼峰 / Method names use camelCase
- 常量使用大写下划线 / Constants use UPPER_SNAKE_CASE
- 私有/受保护成员使用小驼峰 / Private/protected members use camelCase

### 示例 / Example

```php
<?php

namespace Rediphp;

use Redis;

/**
 * Example class showing coding standards
 */
class ExampleClass
{
    private Redis $redis;
    private string $name;
    
    public function __construct(Redis $redis, string $name)
    {
        $this->redis = $redis;
        $this->name = $name;
    }
    
    /**
     * Example method
     *
     * @param string $key
     * @return mixed
     */
    public function exampleMethod(string $key)
    {
        // Implementation
    }
}
```

## 测试 / Testing

### 编写测试 / Writing Tests

- 所有新功能必须包含测试 / All new features must include tests
- 测试应该覆盖正常和边界情况 / Tests should cover normal and edge cases
- 使用 PHPUnit 编写测试 / Use PHPUnit for tests

### 运行测试 / Running Tests

```bash
# 安装依赖 / Install dependencies
composer install

# 运行所有测试 / Run all tests
vendor/bin/phpunit

# 运行特定测试 / Run specific test
vendor/bin/phpunit tests/RMapTest.php
```

## 兼容性要求 / Compatibility Requirements

### Redisson 兼容性 / Redisson Compatibility

redi.php 的核心目标是与 Redisson 100% 兼容。在实现新功能时：

The core goal of redi.php is 100% compatibility with Redisson. When implementing new features:

1. 研究 Redisson 的实现 / Study Redisson's implementation
2. 使用相同的 Redis 命令和数据结构 / Use the same Redis commands and data structures
3. 使用兼容的数据编码（JSON）/ Use compatible data encoding (JSON)
4. 实现相同的 API 方法 / Implement the same API methods
5. 测试与 Redisson 的互操作性 / Test interoperability with Redisson

### 示例：添加新数据结构 / Example: Adding New Data Structure

如果要添加新的数据结构（如 RHyperLogLog）：

If you want to add a new data structure (e.g., RHyperLogLog):

1. 创建类文件 `src/RHyperLogLog.php`
2. 实现与 Redisson 相同的方法
3. 添加测试 `tests/RHyperLogLogTest.php`
4. 在 `RedissonClient.php` 中添加 getter 方法
5. 更新 README 文档
6. 添加使用示例

## 文档 / Documentation

- 所有公共 API 必须有文档注释 / All public APIs must have doc comments
- 更新 README.md（中文）和 README_EN.md（英文）
- 如有必要，更新 COMPATIBILITY.md
- 添加使用示例到 `examples/` 目录

## 提交信息格式 / Commit Message Format

使用清晰的提交信息：

Use clear commit messages:

```
Add RHyperLogLog distributed data structure

- Implement add() and count() methods
- Add compatibility with Redisson's RHyperLogLog
- Include comprehensive tests
```

## 问题标签 / Issue Labels

- `bug` - 错误报告 / Bug reports
- `enhancement` - 新功能请求 / Feature requests
- `documentation` - 文档改进 / Documentation improvements
- `good first issue` - 适合新手的问题 / Good for newcomers
- `help wanted` - 需要帮助 / Help wanted
- `compatibility` - Redisson 兼容性问题 / Redisson compatibility issues

## 代码审查 / Code Review

所有 Pull Request 都会经过代码审查。审查内容包括：

All Pull Requests go through code review. Reviews include:

1. 代码质量和风格 / Code quality and style
2. 测试覆盖率 / Test coverage
3. Redisson 兼容性 / Redisson compatibility
4. 文档完整性 / Documentation completeness
5. 性能影响 / Performance impact

## 许可证 / License

通过贡献代码，您同意您的贡献将在 Apache License 2.0 下授权。

By contributing code, you agree that your contributions will be licensed under the Apache License 2.0.

## 联系方式 / Contact

- GitHub Issues: https://github.com/linkerlin/redi.php/issues
- Pull Requests: https://github.com/linkerlin/redi.php/pulls

感谢您的贡献！/ Thank you for your contributions!
