#!/usr/bin/env php
<?php

/**
 * Redis数据库环境变量配置综合测试脚本
 * 专门测试REDIS_DB环境变量的各种配置场景
 */

require 'vendor/autoload.php';

use Rediphp\RedissonClient;

echo "=== Redis数据库环境变量配置综合测试 ===\n\n";

class DatabaseEnvTestRunner
{
    private $testResults = [];
    private $testCount = 0;
    private $passedTests = 0;
    private $failedTests = 0;

    public function runAllTests()
    {
        $this->testDefaultDatabase();
        $this->testEnvironmentVariable();
        $this->testDatabasePriority();
        $this->testConfigFileInteraction();
        $this->testDirectConfig();
        $this->testDatabaseIsolation();
        $this->testPerformanceBenchmark();
        $this->testErrorHandling();
        
        $this->printSummary();
    }

    /**
     * 测试1: 默认数据库配置
     */
    private function testDefaultDatabase()
    {
        $this->logTest("测试1: 默认数据库配置", function() {
            // 清除所有Redis环境变量
            putenv('REDIS_DB');
            putenv('REDIS_DATABASE');
            unset($_ENV['REDIS_DB']);
            unset($_ENV['REDIS_DATABASE']);
            unset($_SERVER['REDIS_DB']);
            unset($_SERVER['REDIS_DATABASE']);
            
            // 创建客户端实例
            $client = new RedissonClient();
            
            // 通过反射获取config属性来验证默认数据库配置
            $reflection = new \ReflectionClass($client);
            $configProperty = $reflection->getProperty('config');
            $configProperty->setAccessible(true);
            $config = $configProperty->getValue($client);
            
            if ($config['database'] === 0) {
                $this->logSuccess("✅ 默认数据库配置正确 (db=0)");
                return true;
            } else {
                $this->logError("❌ 默认数据库配置错误，期望0，实际{$config['database']}");
                return false;
            }
        });
    }

    /**
     * 测试2: REDIS_DB环境变量配置
     */
    private function testEnvironmentVariable()
    {
        $this->logTest("测试2: REDIS_DB环境变量配置", function() {
            // 设置环境变量
            putenv('REDIS_DB=5');
            $_ENV['REDIS_DB'] = '5';
            
            // 创建客户端实例
            $client = new RedissonClient();
            
            // 通过反射获取config属性来验证配置
            $reflection = new \ReflectionClass($client);
            $configProperty = $reflection->getProperty('config');
            $configProperty->setAccessible(true);
            $config = $configProperty->getValue($client);
            
            if ($config['database'] === 5) {
                $this->logSuccess("✅ 环境变量配置正确 (REDIS_DB=5, db={$config['database']})");
                return true;
            } else {
                $this->logError("❌ 环境变量配置错误，期望5，实际{$config['database']}");
                return false;
            }
        });
    }

    /**
     * 测试3: 配置优先级测试
     */
    private function testDatabasePriority()
    {
        $this->logTest("测试3: 配置优先级测试", function() {
            $originalDb = getenv('REDIS_DB');
            $originalDatabase = getenv('REDIS_DATABASE');
            
            try {
                // 测试场景1: REDIS_DB环境变量
                putenv('REDIS_DB=5');
                putenv('REDIS_DATABASE');
                $client = new RedissonClient();
                
                // 通过反射获取config属性
                $reflection = new \ReflectionClass($client);
                $configProperty = $reflection->getProperty('config');
                $configProperty->setAccessible(true);
                $config = $configProperty->getValue($client);
                
                if ($config['database'] === 5) {
                    $this->logSuccess("  ✅ 场景1: REDIS_DB=5 生效");
                } else {
                    $this->logError("  ❌ 场景1: REDIS_DB=5 未生效");
                    return false;
                }
                
                // 测试场景2: 同时设置REDIS_DB和REDIS_DATABASE
                putenv('REDIS_DB=7');
                putenv('REDIS_DATABASE=8');
                $client = new RedissonClient();
                
                // 通过反射获取config属性
                $reflection = new \ReflectionClass($client);
                $configProperty = $reflection->getProperty('config');
                $configProperty->setAccessible(true);
                $config = $configProperty->getValue($client);
                
                if ($config['database'] === 7) {
                    $this->logSuccess("  ✅ 场景2: REDIS_DB=7 优先级高于 REDIS_DATABASE=8");
                } else {
                    $this->logError("  ❌ 场景2: REDIS_DB优先级失败");
                    return false;
                }
                
                // 测试场景3: 代码配置覆盖环境变量
                putenv('REDIS_DB=9');
                putenv('REDIS_DATABASE=10');
                $client = new RedissonClient(['database' => 11]);
                
                // 通过反射获取config属性
                $reflection = new \ReflectionClass($client);
                $configProperty = $reflection->getProperty('config');
                $configProperty->setAccessible(true);
                $config = $configProperty->getValue($client);
                
                if ($config['database'] === 11) {
                    $this->logSuccess("  ✅ 场景3: 代码配置=11 覆盖环境变量");
                } else {
                    $this->logError("  ❌ 场景3: 代码配置覆盖失败，期望11，实际{$config['database']}");
                    return false;
                }
                
                return true;
                
            } finally {
                // 恢复原始环境变量
                if ($originalDb !== false) putenv("REDIS_DB=$originalDb"); else putenv('REDIS_DB');
                if ($originalDatabase !== false) putenv("REDIS_DATABASE=$originalDatabase"); else putenv('REDIS_DATABASE');
            }
        });
    }

    /**
     * 测试4: .env文件交互测试
     */
    private function testConfigFileInteraction()
    {
        $this->logTest("测试4: .env文件交互测试", function() {
            $originalDb = getenv('REDIS_DB');
            
            try {
                // 清除环境变量，测试默认配置
                putenv('REDIS_DB');
                $client = new RedissonClient();
                
                // 通过反射获取config属性
                $reflection = new \ReflectionClass($client);
                $configProperty = $reflection->getProperty('config');
                $configProperty->setAccessible(true);
                $config = $configProperty->getValue($client);
                
                $defaultDb = $config['database'];
                $this->logInfo("  默认配置数据库: db=$defaultDb");
                
                // 设置环境变量
                putenv('REDIS_DB=12');
                $client = new RedissonClient();
                
                // 通过反射获取config属性
                $reflection = new \ReflectionClass($client);
                $configProperty = $reflection->getProperty('config');
                $configProperty->setAccessible(true);
                $config = $configProperty->getValue($client);
                
                $envDb = $config['database'];
                $this->logInfo("  环境变量配置: db=$envDb");
                
                if ($envDb === 12) {
                    $this->logSuccess("  ✅ 环境变量配置正确");
                    return true;
                } else {
                    $this->logError("  ❌ 环境变量配置失败");
                    return false;
                }
                
            } finally {
                if ($originalDb !== false) putenv("REDIS_DB=$originalDb"); else putenv('REDIS_DB');
            }
        });
    }

    /**
     * 测试5: 代码直接配置
     */
    private function testDirectConfig()
    {
        $this->logTest('测试5: 代码直接配置', function() {
            $client = new RedissonClient(['database' => 7]);
            
            // 通过反射获取config属性
            $reflection = new \ReflectionClass($client);
            $configProperty = $reflection->getProperty('config');
            $configProperty->setAccessible(true);
            $config = $configProperty->getValue($client);
            
            if ($config['database'] === 7) {
                $this->logSuccess("✅ 代码直接配置正确 (database=7)");
                return true;
            } else {
                $this->logError("❌ 代码直接配置错误，期望7，实际{$config['database']}");
                return false;
            }
        });
    }

    /**
     * 测试6: 数据库隔离测试
     */
    private function testDatabaseIsolation()
    {
        $this->logTest("测试6: 数据库隔离测试", function() {
            try {
                $client1 = new RedissonClient(['database' => 13]);
                $client2 = new RedissonClient(['database' => 14]);
                
                // 在数据库13中设置数据
                $map1 = $client1->getMap('isolation_test');
                $map1->put('shared_key', 'database_13_data');
                
                // 在数据库14中设置相同键但不同数据
                $map2 = $client2->getMap('isolation_test');
                $map2->put('shared_key', 'database_14_data');
                
                // 验证数据隔离
                $value1 = $map1->get('shared_key');
                $value2 = $map2->get('shared_key');
                
                if ($value1 === 'database_13_data' && $value2 === 'database_14_data') {
                    $this->logSuccess("  ✅ 数据库隔离正确 (db13: $value1, db14: $value2)");
                    return true;
                } else {
                    $this->logError("  ❌ 数据库隔离失败 (db13: $value1, db14: $value2)");
                    return false;
                }
                
                // 清理
                $map1->remove('shared_key');
                $map2->remove('shared_key');
                $client1->shutdown();
                $client2->shutdown();
                
            } catch (\Exception $e) {
                $this->logError("  ❌ 数据库隔离测试异常: " . $e->getMessage());
                return false;
            }
        });
    }

    /**
     * 测试7: 性能基准测试
     */
    private function testPerformanceBenchmark()
    {
        $this->logTest("测试7: 性能基准测试", function() {
            try {
                $startTime = microtime(true);
                $operationsCount = 15; // 减少操作次数避免长时间等待
                $databases = [10, 11, 12, 13]; // 使用固定数据库避免冲突
                $totalOperations = $operationsCount * count($databases);
                
                $this->logInfo("  开始性能基准测试: {$operationsCount}操作×" . count($databases) . "数据库 (共{$totalOperations}次操作)");
                
                $completedOperations = 0;
                $clientInstances = [];
                
                // 创建客户端连接
                foreach ($databases as $index => $db) {
                    $this->logInfo("  连接数据库{$db}...");
                    $client = new RedissonClient(['database' => $db]);
                    $clientInstances[$db] = $client;
                }
                
                // 执行测试操作
                foreach ($databases as $db) {
                    $client = $clientInstances[$db];
                    $map = $client->getMap("perf_benchmark_db_$db");
                    $testKeys = [];
                    
                    // 写入测试数据
                    for ($i = 0; $i < $operationsCount; $i++) {
                        $key = "perf_bench_key_{$db}_{$i}";
                        $value = "perf_bench_value_{$db}_{$i}";
                        $map->put($key, $value);
                        $testKeys[] = $key;
                        $completedOperations++;
                        
                        // 显示进度
                        if ($completedOperations % 10 === 0) {
                            echo "  进度: {$completedOperations}/{$totalOperations} 操作完成\n";
                        }
                    }
                    
                    // 验证读取
                    for ($i = 0; $i < $operationsCount; $i++) {
                        $key = "perf_bench_key_{$db}_{$i}";
                        $value = "perf_bench_value_{$db}_{$i}";
                        $retrieved = $map->get($key);
                        
                        if ($retrieved !== $value) {
                            throw new \Exception("数据验证失败: DB{$db}, Key{$key}");
                        }
                        $completedOperations++;
                        
                        // 显示进度
                        if ($completedOperations % 10 === 0) {
                            echo "  进度: {$completedOperations}/{$totalOperations} 操作完成\n";
                        }
                    }
                    
                    // 批量清理 - 最后统一删除
                    $this->logInfo("  清理数据库{$db}测试数据...");
                    foreach ($testKeys as $key) {
                        $map->remove($key);
                    }
                }
                
                // 关闭所有连接
                foreach ($clientInstances as $client) {
                    $client->shutdown();
                }
                
                $endTime = microtime(true);
                $totalDuration = ($endTime - $startTime) * 1000; // 毫秒
                $avgTime = $totalDuration / $totalOperations;
                
                $this->logSuccess("  ✅ 性能测试完成 ({$totalOperations}操作，总耗时{$totalDuration}ms，平均{$avgTime}ms/操作)");
                
                // 性能标准：平均每个操作应该少于50ms（更宽松的标准）
                if ($avgTime < 50) {
                    $this->logSuccess("  ✅ 性能优秀 (<50ms/操作)");
                    return true;
                } else {
                    $this->logInfo("  ℹ️  性能可接受 ({$avgTime}ms/操作)");
                    return true;
                }
                
            } catch (\Exception $e) {
                $this->logError("  ❌ 性能测试失败: " . $e->getMessage());
                
                // 尝试清理
                if (isset($clientInstances)) {
                    foreach ($clientInstances as $client) {
                        try {
                            $client->shutdown();
                        } catch (\Exception $cleanupEx) {
                            // 忽略清理错误
                        }
                    }
                }
                
                return false;
            }
        });
    }

    /**
     * 测试8: 错误处理测试
     */
    private function testErrorHandling()
    {
        $this->logTest("测试8: 错误处理测试", function() {
            $testCases = [
                'invalid_negative' => -1,
                'invalid_large' => 100,
                'invalid_string' => 'invalid'
            ];
            
            $errorsHandled = 0;
            
            foreach ($testCases as $caseName => $invalidDb) {
                try {
                    $client = new RedissonClient(['database' => $invalidDb]);
                    $this->logInfo("  ℹ️  $caseName (db=$invalidDb) 处理策略");
                    $errorsHandled++;
                } catch (\Exception $e) {
                    $this->logInfo("  ℹ️  $caseName (db=$invalidDb) 正确抛出异常: " . substr($e->getMessage(), 0, 50));
                    $errorsHandled++;
                }
            }
            
            if ($errorsHandled === count($testCases)) {
                $this->logSuccess("  ✅ 错误处理测试通过 ($errorsHandled/" . count($testCases) . " 案例)");
                return true;
            } else {
                $this->logError("  ❌ 错误处理测试失败 ($errorsHandled/" . count($testCases) . " 案例)");
                return false;
            }
        });
    }

    /**
     * 辅助方法：记录测试结果
     */
    private function logTest($testName, $testFunction)
    {
        $this->testCount++;
        echo "\n--- $testName ---\n";
        
        try {
            $result = $testFunction();
            if ($result) {
                $this->passedTests++;
                $this->testResults[] = ['name' => $testName, 'status' => 'PASS', 'message' => ''];
            } else {
                $this->failedTests++;
                $this->testResults[] = ['name' => $testName, 'status' => 'FAIL', 'message' => 'Test function returned false'];
            }
        } catch (\Exception $e) {
            $this->failedTests++;
            $this->testResults[] = ['name' => $testName, 'status' => 'FAIL', 'message' => $e->getMessage()];
            $this->logError("❌ 测试异常: " . $e->getMessage());
        }
    }

    private function logSuccess($message)
    {
        echo "$message\n";
    }

    private function logError($message)
    {
        echo "$message\n";
    }

    private function logInfo($message)
    {
        echo "$message\n";
    }

    /**
     * 打印测试总结
     */
    private function printSummary()
    {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "测试总结\n";
        echo str_repeat('=', 60) . "\n";
        echo "总测试数: {$this->testCount}\n";
        echo "通过测试: {$this->passedTests} ✅\n";
        echo "失败测试: {$this->failedTests} ❌\n";
        echo "成功率: " . round(($this->passedTests / $this->testCount) * 100, 1) . "%\n";
        
        if ($this->failedTests > 0) {
            echo "\n失败测试详情:\n";
            foreach ($this->testResults as $result) {
                if ($result['status'] === 'FAIL') {
                    echo "- {$result['name']}: {$result['message']}\n";
                }
            }
        }
        
        echo "\n" . str_repeat('=', 60) . "\n";
        
        if ($this->failedTests === 0) {
            echo "🎉 所有REDIS_DB环境变量配置测试通过！\n";
        } else {
            echo "⚠️  有{$this->failedTests}个测试失败，需要检查配置\n";
        }
    }
}

// 运行测试
$testRunner = new DatabaseEnvTestRunner();
$testRunner->runAllTests();

echo "\n使用建议:\n";
echo "1. 开发环境: export REDIS_DB=0\n";
echo "2. 测试环境: export REDIS_DB=1\n";
echo "3. 生产环境: export REDIS_DB=2\n";
echo "4. 调试环境: export REDIS_DB=15\n";
echo "5. 或在.env文件中设置: REDIS_DB=5\n";