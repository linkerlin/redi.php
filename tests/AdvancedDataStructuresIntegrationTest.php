<?php

namespace Rediphp\Tests;

use Rediphp\RBloomFilter;
use Rediphp\RHyperLogLog;
use Rediphp\RGeo;

/**
 * 高级数据结构集成测试
 * 测试布隆过滤器、HyperLogLog和地理位置等高级Redis数据结构的集成功能
 */
class AdvancedDataStructuresIntegrationTest extends RedissonTestCase
{
    private ?RBloomFilter $bloomFilter = null;
    private ?RHyperLogLog $hyperLogLog = null;
    private ?RGeo $geo = null;

    protected function setUp(): void
    {
        parent::setUp();
        
        try {
            // 获取底层Redis连接
            $redis = $this->client->getRedis();
            
            // 使用正确的Redis连接创建高级数据结构实例
            $this->bloomFilter = new RBloomFilter($redis, 'test:bloom:integration');
            $this->hyperLogLog = new RHyperLogLog($redis, 'test:hll:integration');
            $this->geo = new RGeo($redis, 'test:geo:integration');
            
            // 清理测试数据
            $this->bloomFilter->delete();
            $this->hyperLogLog->clear();
            $this->geo->clear();
            
            // 归还Redis连接
            $this->client->returnRedis($redis);
        } catch (\Exception $e) {
            // 如果高级数据结构不可用，跳过测试
            if (strpos($e->getMessage(), 'BloomFilter') !== false || 
                strpos($e->getMessage(), 'HyperLogLog') !== false ||
                strpos($e->getMessage(), 'Geo') !== false) {
                $this->markTestSkipped('Advanced data structures not available: ' . $e->getMessage());
            }
            throw $e;
        }
    }

    protected function tearDown(): void
    {
        if ($this->bloomFilter) {
            $this->bloomFilter->delete();
        }
        if ($this->hyperLogLog) {
            $this->hyperLogLog->clear();
        }
        if ($this->geo) {
            $this->geo->clear();
        }
        
        parent::tearDown();
    }

    /**
     * 测试布隆过滤器基本功能
     */
    public function testBloomFilterBasicOperations(): void
    {
        // 获取Redis连接
        $redis = $this->client->getRedis();
        
        try {
            $bloomFilter = new RBloomFilter($redis, 'test:bloom:basic');
            
            // 测试初始化
            $this->assertTrue($bloomFilter->tryInit(1000, 0.01));
            
            // 测试添加元素
            $this->assertTrue($bloomFilter->add('test_item_1'));
            $this->assertTrue($bloomFilter->add('test_item_2'));
            
            // 测试元素存在性检查
            $this->assertTrue($bloomFilter->contains('test_item_1'));
            $this->assertTrue($bloomFilter->contains('test_item_2'));
            $this->assertFalse($bloomFilter->contains('nonexistent_item'));
            
            // 测试计数
            $count = $bloomFilter->count();
            $this->assertIsInt($count);
            $this->assertGreaterThanOrEqual(0, $count);
            
            // 清理测试数据
            $bloomFilter->delete();
        } finally {
            $this->client->returnRedis($redis);
        }
    }

    /**
     * 测试布隆过滤器误报率
     */
    public function testBloomFilterFalsePositiveRate(): void
    {
        // 获取Redis连接
        $redis = $this->client->getRedis();
        
        try {
            $bloomFilter = new RBloomFilter($redis, 'test:bloom:false_positive');
            
            // 使用较低的误报率配置
            $this->assertTrue($bloomFilter->tryInit(100, 0.001));
            
            // 添加测试数据
            $testItems = [];
            for ($i = 0; $i < 50; $i++) {
                $item = "test_item_$i";
                $testItems[] = $item;
                $this->assertTrue($bloomFilter->add($item));
            }
            
            // 验证所有添加的元素都存在
            foreach ($testItems as $item) {
                $this->assertTrue($bloomFilter->contains($item));
            }
            
            // 测试误报率（应该很低）
            $falsePositives = 0;
            $testCount = 100;
            
            for ($i = 0; $i < $testCount; $i++) {
                $nonExistentItem = "nonexistent_$i";
                if ($bloomFilter->contains($nonExistentItem)) {
                    $falsePositives++;
                }
            }
            
            $falsePositiveRate = $falsePositives / $testCount;
            $this->assertLessThan(0.05, $falsePositiveRate, "False positive rate should be low");
            
            // 清理测试数据
            $bloomFilter->delete();
        } finally {
            $this->client->returnRedis($redis);
        }
    }

    /**
     * 测试HyperLogLog基数统计功能
     */
    public function testHyperLogLogCardinality(): void
    {
        // 获取Redis连接
        $redis = $this->client->getRedis();
        
        try {
            $hyperLogLog = new RHyperLogLog($redis, 'test:hll:cardinality');
            
            // 测试添加元素
            $this->assertTrue($hyperLogLog->add('item1') !== false);
            $this->assertTrue($hyperLogLog->add('item2') !== false);
            $this->assertTrue($hyperLogLog->add('item3') !== false);
            
            // 测试基数统计
            $count = $hyperLogLog->count();
            $this->assertIsInt($count);
            $this->assertGreaterThanOrEqual(0, $count);
            
            // 测试合并功能
            $hll2 = new RHyperLogLog($redis, 'test:hll:integration2');
            $hll2->add('item4');
            $hll2->add('item5');
            
            $this->assertTrue($hyperLogLog->merge(['test:hll:integration2']));
            
            $mergedCount = $hyperLogLog->count();
            $this->assertGreaterThanOrEqual($count, $mergedCount);
            
            // 清理测试数据
            $hyperLogLog->clear();
            $hll2->clear();
        } finally {
            $this->client->returnRedis($redis);
        }
    }

    /**
     * 测试HyperLogLog合并操作
     */
    public function testHyperLogLogMergeOperations(): void
    {
        // 获取Redis连接
        $redis = $this->client->getRedis();
        
        try {
            // 创建多个HyperLogLog实例
            $hll1 = new RHyperLogLog($redis, 'test:hll:merge1');
            $hll2 = new RHyperLogLog($redis, 'test:hll:merge2');
            $hyperLogLog = new RHyperLogLog($redis, 'test:hll:merge_target');
            
            // 向不同实例添加不同元素
            $hll1->addAll(['item_a', 'item_b', 'item_c']);
            $hll2->addAll(['item_c', 'item_d', 'item_e']);
            
            // 验证各自基数
            $this->assertEquals(3, $hll1->count());
            $this->assertEquals(3, $hll2->count());
            
            // 合并到当前实例
            $this->assertTrue($hyperLogLog->merge(['test:hll:merge1', 'test:hll:merge2']));
            
            // 合并后基数应该是5（去重后）
            $this->assertEquals(5, $hyperLogLog->count());
            
            // 测试合并到新键
            $mergedHll = new RHyperLogLog($redis, 'test:hll:merged');
            $this->assertTrue($hll1->merge(['test:hll:merge2'], 'test:hll:merged'));
            $this->assertEquals(5, $mergedHll->count());
            
            // 清理测试数据
            $hll1->clear();
            $hll2->clear();
            $hyperLogLog->clear();
            $mergedHll->clear();
        } finally {
            $this->client->returnRedis($redis);
        }
    }

    /**
     * 测试地理位置基本操作
     */
    public function testGeoBasicOperations(): void
    {
        // 获取Redis连接
        $redis = $this->client->getRedis();
        
        try {
            $geo = new RGeo($redis, 'test:geo:basic_operations');
            
            // 清理之前的数据，确保测试环境干净
            $geo->clear();
            
            // 添加地理位置
            $this->assertEquals(1, $geo->add(116.4074, 39.9042, 'Beijing'));
            $this->assertEquals(1, $geo->add(121.4737, 31.2304, 'Shanghai'));
            $this->assertEquals(1, $geo->add(113.2644, 23.1291, 'Guangzhou'));
            
            // 批量添加
            $locations = [
                [114.0579, 22.5431, 'Shenzhen'],
                [120.1551, 30.2741, 'Hangzhou']
            ];
            $this->assertEquals(2, $geo->addAll($locations));
            
            // 测试坐标获取
            $beijingPos = $geo->position('Beijing');
            $this->assertNotNull($beijingPos);
            $this->assertIsFloat($beijingPos[0]); // 经度
            $this->assertIsFloat($beijingPos[1]); // 纬度
            
            // 测试距离计算
            $distance = $geo->distance('Beijing', 'Shanghai', 'km');
            $this->assertNotNull($distance);
            $this->assertGreaterThan(1000, $distance); // 北京到上海距离应大于1000km
            $this->assertLessThan(1500, $distance);    // 北京到上海距离应小于1500km
            
            // 测试地理哈希
            $beijingHash = $geo->hash('Beijing');
            $this->assertNotNull($beijingHash);
            $this->assertIsString($beijingHash);
            
            // 清理测试数据
            $geo->clear();
        } finally {
            $this->client->returnRedis($redis);
        }
    }

    /**
     * 测试地理位置范围搜索
     */
    public function testGeoRadiusSearch(): void
    {
        // 获取Redis连接
        $redis = $this->client->getRedis();
        
        try {
            $geo = new RGeo($redis, 'test:geo:radius_search');
            
            // 清理之前的数据，确保测试环境干净
            $geo->clear();
            
            // 添加测试数据
            $cities = [
                ['Beijing', 116.4074, 39.9042],
                ['Shanghai', 121.4737, 31.2304],
                ['Guangzhou', 113.2644, 23.1291],
                ['Shenzhen', 114.0579, 22.5431],
                ['Hangzhou', 120.1551, 30.2741]
            ];
            
            foreach ($cities as $city) {
                $geo->add($city[1], $city[2], $city[0]);
            }
            
            // 测试半径搜索（上海为中心，500km半径）
            $results = $geo->radius(121.4737, 31.2304, 500, 'km', [
                'withCoord' => true,
                'withDist' => true,
                'count' => 10,
                'sort' => 'ASC'
            ]);
            
            $this->assertIsArray($results);
            $this->assertGreaterThanOrEqual(1, count($results)); // 至少包含上海本身
            
            // 验证结果格式
            if (count($results) > 0) {
                $firstResult = $results[0];
                $this->assertIsArray($firstResult);
                $this->assertArrayHasKey(0, $firstResult); // 成员名称
                $this->assertIsString($firstResult[0]);
            }
            
            // 测试基于成员的半径搜索
            $memberResults = $geo->radiusByMember('Shanghai', 300, 'km', [
                'withDist' => true
            ]);
            
            $this->assertIsArray($memberResults);
            
            // 清理测试数据
            $geo->clear();
        } finally {
            $this->client->returnRedis($redis);
        }
    }

    /**
     * 测试高级数据结构并发操作
     */
    public function testAdvancedDataStructuresConcurrentOperations(): void
    {
        // 获取Redis连接
        $redis = $this->client->getRedis();
        
        try {
            $bloomFilter = new RBloomFilter($redis, 'test:bloom:concurrent');
            $hyperLogLog = new RHyperLogLog($redis, 'test:hll:concurrent');
            $geo = new RGeo($redis, 'test:geo:concurrent');
            
            // 初始化布隆过滤器
            $bloomFilter->tryInit(1000, 0.01);
            
            $operations = 50;
            $successCount = 0;
            
            // 并发操作测试
            for ($i = 0; $i < $operations; $i++) {
                try {
                    // 并发添加布隆过滤器元素
                    $bloomFilter->add("concurrent_item_$i");
                    
                    // 并发添加HyperLogLog元素
                    $hyperLogLog->add("concurrent_unique_$i");
                    
                    // 并发添加地理位置
                    $lat = 30 + ($i * 0.1);
                    $lon = 120 + ($i * 0.1);
                    $geo->add($lon, $lat, "city_$i");
                    
                    $successCount++;
                } catch (\Exception $e) {
                    // 记录失败但不中断测试
                    continue;
                }
            }
            
            $this->assertEquals($operations, $successCount, "All concurrent operations should succeed");
            
            // 验证并发操作结果
            $this->assertTrue($bloomFilter->contains("concurrent_item_0"));
            $this->assertEquals($operations, $hyperLogLog->count());
            $this->assertNotNull($geo->position("city_0"));
            
            // 清理测试数据
            $bloomFilter->delete();
            $hyperLogLog->clear();
            $geo->clear();
        } finally {
            $this->client->returnRedis($redis);
        }
    }

    /**
     * 测试高级数据结构错误处理
     */
    public function testAdvancedDataStructuresErrorHandling(): void
    {
        // 获取Redis连接
        $redis = $this->client->getRedis();
        
        try {
            $bloomFilter = new RBloomFilter($redis, 'test:bloom:error');
            $hyperLogLog = new RHyperLogLog($redis, 'test:hll:error');
            $geo = new RGeo($redis, 'test:geo:error');
            
            // 初始化布隆过滤器
            $bloomFilter->tryInit(1000, 0.01);

            // 测试布隆过滤器错误处理
            try {
                // 尝试添加空值
                $bloomFilter->add('');
                $this->fail('Should throw exception for empty value');
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('empty', $e->getMessage());
            }

            // 测试HyperLogLog错误处理
            try {
                // 尝试添加空值
                $hyperLogLog->add('');
                $this->fail('Should throw exception for empty value');
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('empty', $e->getMessage());
            }

            // 测试地理位置错误处理
            try {
                // 尝试添加无效坐标
                $geo->add(200, 200, 'invalid_city');
                $this->fail('Should throw exception for invalid coordinates');
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('invalid', strtolower($e->getMessage()));
            }

            // 测试不存在的键操作
            $this->assertFalse($bloomFilter->contains('nonexistent'));
            $this->assertNull($geo->position('nonexistent_city'));
            
            // 清理测试数据
            $bloomFilter->delete();
            $hyperLogLog->clear();
            $geo->clear();
        } finally {
            $this->client->returnRedis($redis);
        }
    }

    /**
     * 测试高级数据结构性能基准
     */
    public function testAdvancedDataStructuresPerformanceBenchmark(): void
    {
        // 获取Redis连接
        $redis = $this->client->getRedis();
        
        try {
            $bloomFilter = new RBloomFilter($redis, 'test:bloom:perf');
            $hyperLogLog = new RHyperLogLog($redis, 'test:hll:perf');
            $geo = new RGeo($redis, 'test:geo:perf');
            
            // 初始化布隆过滤器
            $bloomFilter->tryInit(2000, 0.01);
            
            $iterations = 1000;
            
            // 布隆过滤器性能测试
            $startTime = microtime(true);
            for ($i = 0; $i < $iterations; $i++) {
                $bloomFilter->add("perf_item_$i");
            }
            $bloomAddTime = microtime(true) - $startTime;
            
            $startTime = microtime(true);
            for ($i = 0; $i < $iterations; $i++) {
                $bloomFilter->contains("perf_item_$i");
            }
            $bloomQueryTime = microtime(true) - $startTime;
            
            // HyperLogLog性能测试
            $startTime = microtime(true);
            for ($i = 0; $i < $iterations; $i++) {
                $hyperLogLog->add("perf_unique_$i");
            }
            $hllAddTime = microtime(true) - $startTime;
            
            $startTime = microtime(true);
            for ($i = 0; $i < 10; $i++) {
                $hyperLogLog->count();
            }
            $hllQueryTime = microtime(true) - $startTime;
            
            // 地理位置性能测试
            $startTime = microtime(true);
            for ($i = 0; $i < $iterations; $i++) {
                $lat = 30 + ($i * 0.001);
                $lon = 120 + ($i * 0.001);
                $geo->add($lon, $lat, "perf_city_$i");
            }
            $geoAddTime = microtime(true) - $startTime;
            
            $startTime = microtime(true);
            for ($i = 0; $i < 10; $i++) {
                $geo->position("perf_city_$i");
            }
            $geoQueryTime = microtime(true) - $startTime;
            
            // 验证性能在合理范围内
            $this->assertLessThan(1.0, $bloomAddTime, "Bloom filter add operations should be fast");
            $this->assertLessThan(1.0, $bloomQueryTime, "Bloom filter query operations should be fast");
            $this->assertLessThan(1.0, $hllAddTime, "HyperLogLog add operations should be fast");
            $this->assertLessThan(0.1, $hllQueryTime, "HyperLogLog query operations should be fast");
            $this->assertLessThan(1.0, $geoAddTime, "Geo add operations should be fast");
            $this->assertLessThan(0.1, $geoQueryTime, "Geo query operations should be fast");
            
            // 清理测试数据
            $bloomFilter->delete();
            $hyperLogLog->clear();
            $geo->clear();
        } finally {
            $this->client->returnRedis($redis);
        }
    }

    /**
     * 测试高级数据结构内存使用
     */
    public function testAdvancedDataStructuresMemoryUsage(): void
    {
        // 获取Redis连接
        $redis = $this->client->getRedis();
        
        try {
            // 获取初始内存使用
            $initialMemory = memory_get_usage(true);
            
            // 测试布隆过滤器内存使用
            $bloomFilter = new RBloomFilter($redis, 'test:bloom:memory');
            $bloomFilter->tryInit(1000, 0.01);
            
            for ($i = 0; $i < 100; $i++) {
                $bloomFilter->add("memory_item_$i");
            }
            
            $bloomMemory = memory_get_usage(true) - $initialMemory;
            
            // 测试HyperLogLog内存使用
            $hll = new RHyperLogLog($redis, 'test:hll:memory');
            
            for ($i = 0; $i < 100; $i++) {
                $hll->add("memory_unique_$i");
            }
            
            $hllMemory = memory_get_usage(true) - $initialMemory - $bloomMemory;
            
            // 测试地理位置内存使用
            $geo = new RGeo($redis, 'test:geo:memory');
            
            for ($i = 0; $i < 100; $i++) {
                $lat = 30 + ($i * 0.001);
                $lon = 120 + ($i * 0.001);
                $geo->add($lon, $lat, "memory_city_$i");
            }
            
            $geoMemory = memory_get_usage(true) - $initialMemory - $bloomMemory - $hllMemory;
            
            // 验证内存使用在合理范围内
            $this->assertLessThan(1024 * 1024, $bloomMemory, "Bloom filter memory usage should be reasonable");
            $this->assertLessThan(1024 * 1024, $hllMemory, "HyperLogLog memory usage should be reasonable");
            $this->assertLessThan(1024 * 1024, $geoMemory, "Geo memory usage should be reasonable");
            
            // 清理测试数据
            $bloomFilter->delete();
            $hll->clear();
            $geo->clear();
        } finally {
            $this->client->returnRedis($redis);
        }
    }
}