<?php

namespace Rediphp\Benchmarks;

use Rediphp\Config;
use Rediphp\RMap;
use Rediphp\RList;
use Rediphp\RLock;

/**
 * 性能基准测试类
 * 测试不同配置下的Redis操作性能
 */
class PerformanceBenchmark
{
    private array $results = [];
    private int $iterations = 1000;
    private int $warmupIterations = 100;

    public function __construct(int $iterations = 1000, int $warmupIterations = 100)
    {
        $this->iterations = $iterations;
        $this->warmupIterations = $warmupIterations;
    }

    /**
     * 运行所有基准测试
     */
    public function runAll(): array
    {
        $this->results = [];
        
        // 测试不同配置
        $configs = [
            'direct' => $this->createDirectConfig(),
            'pooling' => $this->createPoolingConfig(),
            'serialization' => $this->createSerializationConfig()
        ];

        foreach ($configs as $name => $config) {
            $this->results[$name] = $this->runBenchmarkForConfig($name, $config);
        }

        return $this->results;
    }

    /**
     * 为特定配置运行基准测试
     */
    private function runBenchmarkForConfig(string $name, array $config): array
    {
        $results = [];
        
        // 预热
        $this->warmup($config);
        
        // 测试Map操作
        $results['map_set'] = $this->benchmarkMapSet($config);
        $results['map_get'] = $this->benchmarkMapGet($config);
        $results['map_operations'] = $this->benchmarkMapOperations($config);
        
        // 测试List操作
        $results['list_push'] = $this->benchmarkListPush($config);
        $results['list_pop'] = $this->benchmarkListPop($config);
        $results['list_operations'] = $this->benchmarkListOperations($config);
        
        // 测试Lock操作
        $results['lock_acquire'] = $this->benchmarkLockAcquire($config);
        $results['lock_operations'] = $this->benchmarkLockOperations($config);
        
        // 测试批处理操作
        $results['pipeline'] = $this->benchmarkPipeline($config);
        
        return $results;
    }

    /**
     * 预热测试
     */
    private function warmup(array $config): void
    {
        $client = Config::createClient($config);
        $map = new RMap($client, 'benchmark_warmup');
        
        for ($i = 0; $i < $this->warmupIterations; $i++) {
            $map->put("key_$i", "value_$i");
            $map->get("key_$i");
            $map->remove("key_$i");
        }
        
        $map->clear();
    }

    /**
     * 基准测试Map设置操作
     */
    private function benchmarkMapSet(array $config): array
    {
        $client = Config::createClient($config);
        $map = new RMap($client, 'benchmark_map_set');
        
        $start = microtime(true);
        
        for ($i = 0; $i < $this->iterations; $i++) {
            $map->put("key_$i", [
                'id' => $i,
                'name' => "Item $i",
                'timestamp' => time(),
                'data' => str_repeat('x', 100)
            ]);
        }
        
        $end = microtime(true);
        $map->clear();
        
        return [
            'iterations' => $this->iterations,
            'total_time' => $end - $start,
            'ops_per_second' => $this->iterations / ($end - $start),
            'avg_time_per_op' => ($end - $start) / $this->iterations
        ];
    }

    /**
     * 基准测试Map获取操作
     */
    private function benchmarkMapGet(array $config): array
    {
        $client = Config::createClient($config);
        $map = new RMap($client, 'benchmark_map_get');
        
        // 先设置数据
        for ($i = 0; $i < $this->iterations; $i++) {
            $map->put("key_$i", [
                'id' => $i,
                'name' => "Item $i",
                'timestamp' => time(),
                'data' => str_repeat('x', 100)
            ]);
        }
        
        $start = microtime(true);
        
        for ($i = 0; $i < $this->iterations; $i++) {
            $map->get("key_$i");
        }
        
        $end = microtime(true);
        $map->clear();
        
        return [
            'iterations' => $this->iterations,
            'total_time' => $end - $start,
            'ops_per_second' => $this->iterations / ($end - $start),
            'avg_time_per_op' => ($end - $start) / $this->iterations
        ];
    }

    /**
     * 基准测试Map复合操作
     */
    private function benchmarkMapOperations(array $config): array
    {
        $client = Config::createClient($config);
        $map = new RMap($client, 'benchmark_map_ops');
        
        $start = microtime(true);
        
        for ($i = 0; $i < $this->iterations; $i++) {
            $map->put("key_$i", "value_$i");
            $map->get("key_$i");
            $map->containsKey("key_$i");
            $map->size();
            $map->remove("key_$i");
        }
        
        $end = microtime(true);
        $map->clear();
        
        return [
            'iterations' => $this->iterations,
            'total_time' => $end - $start,
            'ops_per_second' => $this->iterations / ($end - $start),
            'avg_time_per_op' => ($end - $start) / $this->iterations
        ];
    }

    /**
     * 基准测试List推送操作
     */
    private function benchmarkListPush(array $config): array
    {
        $client = Config::createClient($config);
        $list = new RList($client, 'benchmark_list_push');
        
        $start = microtime(true);
        
        for ($i = 0; $i < $this->iterations; $i++) {
            $list->add([
                'id' => $i,
                'name' => "Item $i",
                'timestamp' => time(),
                'data' => str_repeat('x', 50)
            ]);
        }
        
        $end = microtime(true);
        $list->clear();
        
        return [
            'iterations' => $this->iterations,
            'total_time' => $end - $start,
            'ops_per_second' => $this->iterations / ($end - $start),
            'avg_time_per_op' => ($end - $start) / $this->iterations
        ];
    }

    /**
     * 基准测试List弹出操作
     */
    private function benchmarkListPop(array $config): array
    {
        $client = Config::createClient($config);
        $list = new RList($client, 'benchmark_list_pop');
        
        // 先添加数据
        for ($i = 0; $i < $this->iterations; $i++) {
            $list->add([
                'id' => $i,
                'name' => "Item $i",
                'timestamp' => time(),
                'data' => str_repeat('x', 50)
            ]);
        }
        
        $start = microtime(true);
        
        for ($i = 0; $i < $this->iterations; $i++) {
            $list->remove(0);
        }
        
        $end = microtime(true);
        
        return [
            'iterations' => $this->iterations,
            'total_time' => $end - $start,
            'ops_per_second' => $this->iterations / ($end - $start),
            'avg_time_per_op' => ($end - $start) / $this->iterations
        ];
    }

    /**
     * 基准测试List复合操作
     */
    private function benchmarkListOperations(array $config): array
    {
        $client = Config::createClient($config);
        $list = new RList($client, 'benchmark_list_ops');
        
        $start = microtime(true);
        
        for ($i = 0; $i < $this->iterations; $i++) {
            $list->add("item_$i");
            $list->get(0);
            $list->size();
            $list->contains("item_$i");
            $list->remove(0);
        }
        
        $end = microtime(true);
        $list->clear();
        
        return [
            'iterations' => $this->iterations,
            'total_time' => $end - $start,
            'ops_per_second' => $this->iterations / ($end - $start),
            'avg_time_per_op' => ($end - $start) / $this->iterations
        ];
    }

    /**
     * 基准测试锁获取操作
     */
    private function benchmarkLockAcquire(array $config): array
    {
        $client = Config::createClient($config);
        
        $start = microtime(true);
        
        for ($i = 0; $i < $this->iterations; $i++) {
            $lock = new RLock($client, 'benchmark_lock');
            $lock->tryLock(1, 1); // 1秒超时，1秒租期
            $lock->unlock();
        }
        
        $end = microtime(true);
        
        return [
            'iterations' => $this->iterations,
            'total_time' => $end - $start,
            'ops_per_second' => $this->iterations / ($end - $start),
            'avg_time_per_op' => ($end - $start) / $this->iterations
        ];
    }

    /**
     * 基准测试锁复合操作
     */
    private function benchmarkLockOperations(array $config): array
    {
        $client = Config::createClient($config);
        
        $start = microtime(true);
        
        for ($i = 0; $i < $this->iterations; $i++) {
            $lock = new RLock($client, "benchmark_lock_$i");
            $acquired = $lock->tryLock(1, 2);
            if ($acquired) {
                $lock->isLocked();
                $lock->unlock();
            }
        }
        
        $end = microtime(true);
        
        return [
            'iterations' => $this->iterations,
            'total_time' => $end - $start,
            'ops_per_second' => $this->iterations / ($end - $start),
            'avg_time_per_op' => ($end - $start) / $this->iterations
        ];
    }

    /**
     * 基准测试批处理操作
     */
    private function benchmarkPipeline(array $config): array
    {
        $client = Config::createClient($config);
        $map = $client->getMap('benchmark_pipeline_map');
        $mapName = $map->getName();
        
        $start = microtime(true);
        
        // 使用批处理操作
        $results = $map->pipeline(function($pipeline) use ($mapName) {
            for ($i = 0; $i < $this->iterations; $i++) {
                $pipeline->hSet($mapName, "key_$i", "value_$i");
            }
        });
        
        $end = microtime(true);
        $map->clear();
        
        return [
            'iterations' => $this->iterations,
            'total_time' => $end - $start,
            'ops_per_second' => $this->iterations / ($end - $start),
            'avg_time_per_op' => ($end - $start) / $this->iterations
        ];
    }

    /**
     * 创建直接连接配置
     */
    private function createDirectConfig(): array
    {
        return [
            'host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
            'port' => $_ENV['REDIS_PORT'] ?? 6379,
            'password' => $_ENV['REDIS_PASSWORD'] ?? null,
            'database' => $_ENV['REDIS_DB'] ?? 0,
            'timeout' => 5.0,
            'read_timeout' => 5.0,
            'persistent' => false,
            'use_pool' => false,
            'pool_config' => []
        ];
    }

    /**
     * 创建连接池配置
     */
    private function createPoolingConfig(): array
    {
        return [
            'host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
            'port' => $_ENV['REDIS_PORT'] ?? 6379,
            'password' => $_ENV['REDIS_PASSWORD'] ?? null,
            'database' => $_ENV['REDIS_DB'] ?? 0,
            'timeout' => 5.0,
            'read_timeout' => 5.0,
            'persistent' => false,
            'use_pool' => true,
            'pool_config' => [
                'min_connections' => 2,
                'max_connections' => 10,
                'idle_timeout' => 3600,
                'max_lifetime' => 7200,
            ]
        ];
    }

    /**
     * 创建序列化配置
     */
    private function createSerializationConfig(): array
    {
        return [
            'host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
            'port' => $_ENV['REDIS_PORT'] ?? 6379,
            'password' => $_ENV['REDIS_PASSWORD'] ?? null,
            'database' => $_ENV['REDIS_DB'] ?? 0,
            'timeout' => 5.0,
            'read_timeout' => 5.0,
            'persistent' => false,
            'use_pool' => true,
            'pool_config' => [
                'min_connections' => 2,
                'max_connections' => 10,
                'idle_timeout' => 3600,
                'max_lifetime' => 7200,
            ]
        ];
    }

    /**
     * 格式化并输出结果
     */
    public function formatResults(): string
    {
        $output = "=== Redi.php 性能基准测试结果 ===\n\n";
        
        foreach ($this->results as $configName => $tests) {
            $output .= "配置: $configName\n";
            $output .= str_repeat("-", 50) . "\n";
            
            foreach ($tests as $testName => $result) {
                $output .= sprintf("%-20s: %8.2f ops/sec | %8.4f ms/op\n", 
                    $testName, 
                    $result['ops_per_second'], 
                    $result['avg_time_per_op'] * 1000
                );
            }
            
            $output .= "\n";
        }
        
        return $output;
    }
}

// 命令行运行支持
if (php_sapi_name() === 'cli' && isset($argv[0]) && basename($argv[0]) === 'PerformanceBenchmark.php') {
    $iterations = $argv[1] ?? 1000;
    $warmup = $argv[2] ?? 100;
    
    $benchmark = new PerformanceBenchmark($iterations, $warmup);
    $results = $benchmark->runAll();
    
    echo $benchmark->formatResults();
}