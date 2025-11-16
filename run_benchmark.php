<?php

require_once __DIR__ . '/vendor/autoload.php';

use Rediphp\Benchmarks\PerformanceBenchmark;

// 检查Redis连接是否可用
function checkRedisConnection(): bool
{
    try {
        $redis = new Redis();
        $host = $_ENV['REDIS_HOST'] ?? '127.0.0.1';
        $port = $_ENV['REDIS_PORT'] ?? 6379;
        $password = $_ENV['REDIS_PASSWORD'] ?? null;
        
        if (!$redis->connect($host, $port, 2.0)) {
            return false;
        }
        
        if ($password && !$redis->auth($password)) {
            return false;
        }
        
        if (!$redis->ping()) {
            return false;
        }
        
        $redis->close();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// 主函数
function main()
{
    echo "=== Redi.php 性能基准测试 ===\n";
    echo "检查Redis连接...\n";
    
    if (!checkRedisConnection()) {
        echo "❌ Redis连接不可用！请确保Redis服务器正在运行。\n";
        echo "   默认配置: host=" . ($_ENV['REDIS_HOST'] ?? '127.0.0.1') . ", port=" . ($_ENV['REDIS_PORT'] ?? 6379) . "\n";
        echo "   可以通过环境变量设置: REDIS_HOST, REDIS_PORT, REDIS_PASSWORD, REDIS_DB\n";
        exit(1);
    }
    
    echo "✅ Redis连接正常\n\n";
    
    // 获取命令行参数
    $iterations = $_SERVER['argv'][1] ?? 1000;
    $warmup = $_SERVER['argv'][2] ?? 100;
    
    echo "测试参数:\n";
    echo "- 迭代次数: $iterations\n";
    echo "- 预热次数: $warmup\n";
    echo "- 测试配置: 直接连接、连接池、序列化优化\n\n";
    
    echo "开始基准测试...\n";
    
    $startTime = microtime(true);
    
    try {
        $benchmark = new PerformanceBenchmark($iterations, $warmup);
        $results = $benchmark->runAll();
        
        $endTime = microtime(true);
        
        echo "✅ 基准测试完成！总耗时: " . round($endTime - $startTime, 2) . " 秒\n\n";
        
        // 输出结果
        echo $benchmark->formatResults();
        
        // 生成性能对比分析
        echo "=== 性能对比分析 ===\n\n";
        
        $configs = array_keys($results);
        $tests = array_keys($results[$configs[0]]);
        
        foreach ($tests as $test) {
            echo "测试: $test\n";
            echo str_repeat("-", 40) . "\n";
            
            $bestConfig = '';
            $bestOps = 0;
            
            foreach ($configs as $config) {
                $ops = $results[$config][$test]['ops_per_second'];
                $time = $results[$config][$test]['avg_time_per_op'] * 1000;
                
                echo sprintf("%-15s: %8.2f ops/sec | %6.2f ms/op\n", $config, $ops, $time);
                
                if ($ops > $bestOps) {
                    $bestOps = $ops;
                    $bestConfig = $config;
                }
            }
            
            echo "最佳配置: $bestConfig (" . round($bestOps, 2) . " ops/sec)\n\n";
        }
        
        // 生成性能提升百分比
        echo "=== 性能提升百分比 ===\n\n";
        
        $baseConfig = 'direct';
        if (isset($results[$baseConfig])) {
            foreach ($configs as $config) {
                if ($config === $baseConfig) continue;
                
                echo "相对于 $baseConfig 配置，$config 配置的性能提升:\n";
                
                foreach ($tests as $test) {
                    $baseOps = $results[$baseConfig][$test]['ops_per_second'];
                    $configOps = $results[$config][$test]['ops_per_second'];
                    
                    if ($baseOps > 0) {
                        $improvement = (($configOps - $baseOps) / $baseOps) * 100;
                        echo sprintf("%-20s: %+6.1f%%\n", $test, $improvement);
                    }
                }
                echo "\n";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ 基准测试失败: " . $e->getMessage() . "\n";
        echo "   堆栈跟踪: " . $e->getTraceAsString() . "\n";
        exit(1);
    }
}

// 运行主函数
main();