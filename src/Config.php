<?php

namespace Rediphp;

/**
 * 环境配置管理器
 * 统一管理Redis连接配置
 */
class Config
{
    private static ?array $config = null;

    /**
     * 加载配置
     * 优先级：.env文件 > 环境变量 > 默认值
     */
    public static function load(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        // 尝试加载.env文件
        self::loadEnvFile();

        // 构建配置
        self::$config = [
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => (int)(getenv('REDIS_PORT') ?: 6379),
            'password' => getenv('REDIS_PASSWORD') ?: null,
            'database' => (int)(getenv('REDIS_DB') ?: getenv('REDIS_DATABASE') ?: 0),
            'timeout' => (float)(getenv('REDIS_TIMEOUT') ?: 0.0),
        ];

        return self::$config;
    }

    /**
     * 加载.env文件
     */
    private static function loadEnvFile(): void
    {
        $envFile = __DIR__ . '/../.env';
        if (!file_exists($envFile)) {
            $envFile = __DIR__ . '/../../.env';
        }

        if (!file_exists($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // 跳过注释行
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // 解析 KEY=VALUE 格式
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // 只设置尚未设置的环境变量
                if (!getenv($key)) {
                    putenv("$key=$value");
                }
            }
        }
    }

    /**
     * 获取Redis配置
     */
    public static function getRedisConfig(): array
    {
        return self::load();
    }

    /**
     * 创建RedissonClient实例
     */
    public static function createClient(array $additionalConfig = []): RedissonClient
    {
        $config = array_merge(self::load(), $additionalConfig);
        return new RedissonClient($config);
    }
}