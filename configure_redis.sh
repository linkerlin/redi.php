#!/bin/bash

# Redis配置统一管理脚本
# 用途：在不同环境中快速配置Redis连接

echo "=== Redis配置管理工具 ==="

# 创建.env文件（如果不存在）
if [ ! -f .env ]; then
    echo "创建.env文件..."
    cp .env.example .env
    echo "✅ 已创建.env文件，请根据需要修改配置"
else
    echo "✅ .env文件已存在"
fi

# 显示当前配置
echo -e "\n当前Redis配置："
if [ -f .env ]; then
    grep "^REDIS_" .env | while read line; do
        echo "  $line"
    done
else
    echo "  REDIS_HOST=127.0.0.1 (默认)"
    echo "  REDIS_PORT=6379 (默认)"
    echo "  REDIS_DB=0 (默认数据库)"
fi

# 提供快速配置选项
echo -e "\n快速配置选项："
echo "1. 开发环境 (localhost)"
echo "2. 生产环境 (自定义IP)"  
echo "3. Docker环境 (redis-server)"
echo "4. 数据库配置 (设置REDIS_DB)"
echo "5. 保持当前配置"

read -p "请选择 (1-5): " choice

case $choice in
    1)
        echo "REDIS_HOST=localhost" > .env
        echo "REDIS_PORT=6379" >> .env
        echo "REDIS_DB=0" >> .env
        echo "✅ 已配置为开发环境 (localhost:6379, db=0)"
        ;;
    2)
        read -p "请输入Redis服务器IP: " redis_host
        read -p "请输入数据库编号 (0-15): " redis_db
        echo "REDIS_HOST=$redis_host" > .env
        echo "REDIS_PORT=6379" >> .env
        echo "REDIS_DB=${redis_db:-0}" >> .env
        echo "✅ 已配置为生产环境 ($redis_host:6379, db=${redis_db:-0})"
        ;;
    3)
        echo "REDIS_HOST=redis-server" > .env
        echo "REDIS_PORT=6379" >> .env
        echo "REDIS_DB=0" >> .env
        echo "✅ 已配置为Docker环境 (redis-server:6379, db=0)"
        ;;
    4)
        echo "数据库配置选项："
        echo "1. db=0 (开发环境，默认)"
        echo "2. db=1 (测试环境)"
        echo "3. db=2 (生产环境)"
        echo "4. 自定义数据库编号 (0-15)"
        
        read -p "请选择数据库配置 (1-4): " db_choice
        
        case $db_choice in
            1)
                echo "REDIS_DB=0" >> .env
                echo "✅ 已设置数据库: db=0"
                ;;
            2)
                echo "REDIS_DB=1" >> .env
                echo "✅ 已设置数据库: db=1"
                ;;
            3)
                echo "REDIS_DB=2" >> .env
                echo "✅ 已设置数据库: db=2"
                ;;
            4)
                read -p "请输入数据库编号 (0-15): " custom_db
                if [[ $custom_db =~ ^[0-9]+$ ]] && [ $custom_db -ge 0 ] && [ $custom_db -le 15 ]; then
                    echo "REDIS_DB=$custom_db" >> .env
                    echo "✅ 已设置数据库: db=$custom_db"
                else
                    echo "❌ 无效的数据库编号，请输入 0-15 之间的数字"
                    exit 1
                fi
                ;;
            *)
                echo "❌ 无效选择"
                exit 1
                ;;
        esac
        ;;
    5)
        echo "✅ 保持当前配置不变"
        ;;
    *)
        echo "❌ 无效选择"
        exit 1
        ;;
esac

echo -e "\n配置完成！使用以下命令测试连接："
echo "php examples/basic_usage.php"