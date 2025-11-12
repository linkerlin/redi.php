#!/bin/bash

# Trae IDE Redis类型识别问题修复脚本
# 此脚本将帮助解决IDE中"Undefined type 'Redis'"的问题

echo "开始修复Trae IDE中的Redis类型识别问题..."

# 1. 确保存根目录存在
mkdir -p stubs

# 2. 重新生成自动加载文件
echo "更新Composer自动加载文件..."
composer dump-autoload

# 3. 检查Redis扩展是否安装
echo "检查Redis扩展状态..."
php -m | grep redis

# 4. 验证Redis类是否可用
echo "验证Redis类是否可用..."
php -r "if (class_exists('Redis')) { echo 'Redis类可用\n'; } else { echo 'Redis类不可用\n'; exit(1); }"

# 5. 显示当前PHP配置
echo "当前PHP配置信息:"
php --version
php -i | grep "Configuration File"

# 6. 显示解决方案
echo "\n解决方案:"
echo "1. 重启Trae IDE"
echo "2. 打开test_redis_ide.php文件检查是否还有红色报错"
echo "3. 如果问题仍然存在，请尝试以下方法:"
echo "   a. 在Trae IDE中打开redi.php.code-workspace文件"
echo "   b. 安装推荐的扩展（特别是Intelephense）"
echo "   c. 检查VSCode设置是否正确加载"

echo "\n修复脚本执行完成！"