#!/bin/bash

# Redis环境变量快速设置脚本
# 使用方法: ./set_redis_env.sh [host] [port] [db]

HOST=${1:-"127.0.0.1"}
PORT=${2:-"6379"}
DB=${3:-"0"}

echo "=== Redis环境变量设置工具 ==="
echo "当前设置:"
echo "  HOST: $HOST"
echo "  PORT: $PORT" 
echo "  DB: $DB"
echo ""

# 验证数据库编号
if ! [[ "$DB" =~ ^[0-9]+$ ]] || [ $DB -lt 0 ] || [ $DB -gt 15 ]; then
    echo "❌ 错误: 数据库编号必须是 0-15 之间的数字"
    exit 1
fi

# 设置环境变量
export REDIS_HOST="$HOST"
export REDIS_PORT="$PORT"
export REDIS_DB="$DB"

echo "✅ 环境变量设置完成:"
echo "  export REDIS_HOST=$HOST"
echo "  export REDIS_PORT=$PORT"
echo "  export REDIS_DB=$DB"
echo ""

# 保存到.env文件
if [ -f .env ]; then
    # 备份原文件
    cp .env .env.backup
    
    # 更新或添加配置
    if grep -q "^REDIS_HOST=" .env; then
        sed -i '' "s/^REDIS_HOST=.*/REDIS_HOST=$HOST/" .env
    else
        echo "REDIS_HOST=$HOST" >> .env
    fi
    
    if grep -q "^REDIS_PORT=" .env; then
        sed -i '' "s/^REDIS_PORT=.*/REDIS_PORT=$PORT/" .env
    else
        echo "REDIS_PORT=$PORT" >> .env
    fi
    
    if grep -q "^REDIS_DB=" .env; then
        sed -i '' "s/^REDIS_DB=.*/REDIS_DB=$DB/" .env
    else
        echo "REDIS_DB=$DB" >> .env
    fi
    
    echo "✅ 已更新 .env 文件 (原文件备份为 .env.backup)"
else
    echo "REDIS_HOST=$HOST" > .env
    echo "REDIS_PORT=$PORT" >> .env
    echo "REDIS_DB=$DB" >> .env
    echo "✅ 已创建 .env 文件"
fi

echo ""
echo "使用方法:"
echo "1. 测试连接: php test_config_unified.php"
echo "2. 数据库测试: php test_database_config.php"
echo "3. 基本用法: php examples/basic_usage.php"
echo ""
echo "环境变量优先级: 命令行参数 > .env文件 > 默认值"