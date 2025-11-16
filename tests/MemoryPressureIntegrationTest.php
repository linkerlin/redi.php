<?php

namespace Rediphp\Tests;

use Rediphp\RMap;
use Rediphp\RList;
use Rediphp\RSet;
use Rediphp\RAtomicLong;
use Rediphp\RBucket;
use Rediphp\RSortedSet;
use Rediphp\RBloomFilter;

/**
 * 内存压力和大数据量集成测试
 * 测试Redis在内存压力和大数据量操作下的性能和稳定性
 */
class MemoryPressureIntegrationTest extends RedissonTestCase
{
    /**
     * 测试大数据量Map操作的内存使用和性能
     */
    public function testLargeScaleMapOperations()
    {
        $largeMap = $this->client->getMap('memory:pressure:large:map');
        $largeCounter = $this->client->getAtomicLong('memory:pressure:large:counter');
        
        // 插入大量数据项
        $dataSize = 1000;
        $batchSize = 100;
        
        $startTime = microtime(true);
        
        for ($batch = 0; $batch < ($dataSize / $batchSize); $batch++) {
            $batchStart = $batch * $batchSize;
            
            // 批量插入数据
            for ($i = 0; $i < $batchSize; $i++) {
                $itemId = $batchStart + $i;
                
                $data = [
                    'id' => $itemId,
                    'content' => str_repeat("data_item_$itemId:", 50) . rand(1000, 9999),
                    'timestamp' => time(),
                    'metadata' => [
                        'batch' => $batch,
                        'size' => strlen(str_repeat("data_item_$itemId:", 50)),
                        'random' => rand(1, 1000)
                    ],
                    'nested_data' => [
                        'category' => "category_" . ($itemId % 10),
                        'tags' => ["tag_" . ($itemId % 5), "tag_" . ($itemId % 7)],
                        'scores' => array_fill(0, 5, rand(1, 100))
                    ]
                ];
                
                $largeMap->put("large_item:$itemId", $data);
                $largeCounter->incrementAndGet();
            }
            
            // 每1000项执行一次清理操作
            if (($batch + 1) % 10 === 0) {
                $this->performMemoryCleanup($largeMap);
            }
        }
        
        $endTime = microtime(true);
        $operationTime = $endTime - $startTime;
        
        // 验证结果
        $this->assertEquals($dataSize, $largeMap->size());
        $this->assertEquals($dataSize, $largeCounter->get());
        
        // 性能验证（应该在合理时间内完成）
        $this->assertLessThan(30, $operationTime, "大数据量插入应该在30秒内完成");
        
        // 验证数据完整性
        $this->validateLargeMapData($largeMap, $dataSize);
        
        // 测试内存压力下的查询性能
        $queryStartTime = microtime(true);
        $sampleItems = $this->sampleLargeMapData($largeMap, 100);
        $queryEndTime = microtime(true);
        $queryTime = $queryEndTime - $queryStartTime;
        
        $this->assertEquals(100, count($sampleItems));
        $this->assertLessThan(5, $queryTime, "大数据量查询应该在5秒内完成");
        
        // 清理
        $largeMap->clear();
        $largeCounter->delete();
    }
    
    /**
     * 测试List在大数据量下的内存使用和操作性能
     */
    public function testLargeScaleListOperations()
    {
        $largeList = $this->client->getList('memory:pressure:large:list');
        $listCounter = $this->client->getAtomicLong('memory:pressure:list:counter');
        
        // 插入大量列表项
        $listSize = 2000;
        $chunkSize = 200;
        
        $startTime = microtime(true);
        
        // 分块插入大列表数据
        for ($chunk = 0; $chunk < ($listSize / $chunkSize); $chunk++) {
            $chunkData = [];
            for ($i = 0; $i < $chunkSize; $i++) {
                $itemId = $chunk * $chunkSize + $i;
                
                $listItem = [
                    'index' => $itemId,
                    'data' => str_repeat("list_item_$itemId:", 30) . base64_encode(random_bytes(100)),
                    'priority' => rand(1, 100),
                    'created_at' => time() + $itemId,
                    'type' => $itemId % 4 === 0 ? 'urgent' : 'normal'
                ];
                
                $chunkData[] = $listItem;
            }
            
            $largeList->addAll($chunkData);
            $listCounter->addAndGet($chunkSize);
        }
        
        $endTime = microtime(true);
        $insertTime = $endTime - $startTime;
        
        // 验证插入结果
        $this->assertEquals($listSize, $largeList->size());
        $this->assertEquals($listSize, $listCounter->get());
        
        // 性能验证
        $this->assertLessThan(20, $insertTime, "大数据量列表插入应该在20秒内完成");
        
        // 测试各种列表操作
        $operationStartTime = microtime(true);
        
        // 测试索引访问
        $sampleIndices = [0, $listSize / 4, $listSize / 2, 3 * $listSize / 4, $listSize - 1];
        $accessedItems = [];
        foreach ($sampleIndices as $index) {
            if ($index < $largeList->size()) {
                $item = $largeList->get($index);
                if ($item) {
                    $accessedItems[] = $item;
                }
            }
        }
        
        $this->assertEquals(count($sampleIndices), count($accessedItems));
        
        // 测试列表裁剪
        $trimmedSize = intval($listSize * 0.8);
        $largeList->trim(0, $trimmedSize - 1);
        
        $this->assertEquals($trimmedSize, $largeList->size());
        
        // 测试列表分页
        $pageSize = 100;
        $pageCount = ceil($largeList->size() / $pageSize);
        
        for ($page = 0; $page < $pageCount; $page++) {
            $start = $page * $pageSize;
            $end = min($start + $pageSize - 1, $largeList->size() - 1);
            
            $pageData = [];
            for ($i = $start; $i <= $end; $i++) {
                $item = $largeList->get($i);
                if ($item) {
                    $pageData[] = $item;
                }
            }
            
            // 验证分页数据
            if ($page < $pageCount - 1) {
                $this->assertEquals($pageSize, count($pageData));
            }
        }
        
        $operationEndTime = microtime(true);
        $operationTime = $operationEndTime - $operationStartTime;
        
        $this->assertLessThan(10, $operationTime, "大数据量列表操作应该在10秒内完成");
        
        // 清理
        $largeList->clear();
        $listCounter->delete();
    }
    
    /**
     * 测试Set大数据量操作和内存效率
     */
    public function testLargeScaleSetOperations()
    {
        $largeSet = $this->client->getSet('memory:pressure:large:set');
        $setCounter = $this->client->getAtomicLong('memory:pressure:set:counter');
        
        // 创建大数据量集合
        $setSize = 1500;
        $batchSize = 150;
        
        $startTime = microtime(true);
        
        // 分批添加集合元素
        for ($batch = 0; $batch < ($setSize / $batchSize); $batch++) {
            $batchElements = [];
            for ($i = 0; $i < $batchSize; $i++) {
                $elementId = $batch * $batchSize + $i;
                
                $setElement = [
                    'element_id' => $elementId,
                    'hash' => md5("element_$elementId"),
                    'category' => "category_" . ($elementId % 20),
                    'weight' => rand(1, 100) / 100,
                    'created' => time(),
                    'features' => array_fill(0, 10, rand(1, 1000))
                ];
                
                // 将对象转换为字符串作为集合元素
                $batchElements[] = json_encode($setElement);
            }
            
            foreach ($batchElements as $element) {
                $largeSet->add($element);
                $setCounter->incrementAndGet();
            }
        }
        
        $endTime = microtime(true);
        $insertTime = $endTime - $startTime;
        
        // 验证集合大小
        $this->assertEquals($setSize, $largeSet->size());
        
        // 性能验证
        $this->assertLessThan(15, $insertTime, "大数据量集合插入应该在15秒内完成");
        
        // 测试集合操作
        $operationStartTime = microtime(true);
        
        // 测试随机抽样
        $sampleSize = 100;
        $samples = $largeSet->sample($sampleSize);
        $this->assertEquals($sampleSize, count($samples));
        
        // 测试集合运算
        $subset1 = $this->client->getSet('memory:pressure:subset1');
        $subset2 = $this->client->getSet('memory:pressure:subset2');
        
        // 创建两个子集
        $elements1 = array_slice($largeSet->toArray(), 0, intval($setSize / 3));
        $elements2 = array_slice($largeSet->toArray(), intval($setSize / 3), intval($setSize / 3));
        
        foreach ($elements1 as $element) {
            $subset1->add($element);
        }
        foreach ($elements2 as $element) {
            $subset2->add($element);
        }
        
        // 测试交集
        $intersection = $subset1->intersect($subset2);
        // 测试并集
        $union = $subset1->union($subset2);
        // 测试差集
        $difference = $subset1->diff($subset2);
        
        $this->assertNotNull($intersection);
        $this->assertNotNull($union);
        $this->assertNotNull($difference);
        
        $operationEndTime = microtime(true);
        $operationTime = $operationEndTime - $operationStartTime;
        
        $this->assertLessThan(8, $operationTime, "大数据量集合操作应该在8秒内完成");
        
        // 清理
        $largeSet->clear();
        $subset1->clear();
        $subset2->clear();
        $setCounter->delete();
    }
    
    /**
     * 测试内存压力下的SortedSet操作
     */
    public function testMemoryPressureSortedSetOperations()
    {
        $sortedSet = $this->client->getSortedSet('memory:pressure:sorted:set');
        $sortedCounter = $this->client->getAtomicLong('memory:pressure:sorted:counter');
        
        // 创建大数据量有序集合
        $elementCount = 1200;
        $batchSize = 120;
        
        $startTime = microtime(true);
        
        // 分批添加有序集合元素
        for ($batch = 0; $batch < ($elementCount / $batchSize); $batch++) {
            for ($i = 0; $i < $batchSize; $i++) {
                $elementId = $batch * $batchSize + $i;
                $score = rand(1, 10000) / 100; // 0.01 到 100.00
                
                $sortedSet->add($score, "element:$elementId");
                $sortedCounter->incrementAndGet();
            }
        }
        
        $endTime = microtime(true);
        $insertTime = $endTime - $startTime;
        
        // 验证元素数量
        $this->assertEquals($elementCount, $sortedSet->size());
        
        // 性能验证
        $this->assertLessThan(18, $insertTime, "大数据量有序集合插入应该在18秒内完成");
        
        // 测试有序集合操作
        $operationStartTime = microtime(true);
        
        // 测试排名查询
        $rankStartTime = microtime(true);
        $sampleElements = [
            "element:0", 
            "element:" . ($elementCount / 2), 
            "element:" . ($elementCount - 1)
        ];
        
        foreach ($sampleElements as $element) {
            $rank = $sortedSet->rank($element);
            $this->assertNotNull($rank);
        }
        
        $rankEndTime = microtime(true);
        $rankTime = $rankEndTime - $rankStartTime;
        $this->assertLessThan(2, $rankTime, "排名查询应该在2秒内完成");
        
        // 测试分数区间查询
        $rangeStartTime = microtime(true);
        $rangeResults = $sortedSet->rangeByScore(20.0, 80.0, 0, 50);
        $rangeEndTime = microtime(true);
        $rangeTime = $rangeEndTime - $rangeStartTime;
        
        $this->assertLessThan(3, $rangeTime, "分数区间查询应该在3秒内完成");
        
        // 测试分页查询
        $pageSize = 50;
        $totalPages = ceil($elementCount / $pageSize);
        
        for ($page = 0; $page < min(5, $totalPages); $page++) {
            $start = $page * $pageSize;
            $pageData = $sortedSet->range($start, $start + $pageSize - 1);
            
            if ($page < $totalPages - 1) {
                $this->assertEquals($pageSize, count($pageData));
            }
        }
        
        // 测试分数统计
        $stats = [
            'min' => $sortedSet->score("element:0"),
            'max' => $sortedSet->score("element:" . ($elementCount - 1)),
            'count' => $sortedSet->count(0, 100)
        ];
        
        $this->assertNotNull($stats['min']);
        $this->assertNotNull($stats['max']);
        $this->assertGreaterThan(0, $stats['count']);
        
        $operationEndTime = microtime(true);
        $operationTime = $operationEndTime - $operationStartTime;
        
        $this->assertLessThan(10, $operationTime, "有序集合操作应该在10秒内完成");
        
        // 清理
        $sortedSet->clear();
        $sortedCounter->delete();
    }
    
    /**
     * 测试内存压力下的并发操作
     */
    public function testMemoryPressureConcurrentOperations()
    {
        $concurrentMap = $this->client->getMap('memory:pressure:concurrent:map');
        $concurrentCounter = $this->client->getAtomicLong('memory:pressure:concurrent:counter');
        $concurrentLock = $this->client->getLock('memory:pressure:concurrent:lock');
        
        $concurrentCount = 500;
        $successCount = 0;
        $errorCount = 0;
        $startTime = microtime(true);
        
        // 模拟并发操作
        for ($i = 0; $i < $concurrentCount; $i++) {
            $threadId = $i % 5; // 模拟5个线程
            $data = [
                'thread_id' => $threadId,
                'operation_id' => $i,
                'timestamp' => time(),
                'data' => str_repeat("concurrent_item_$i:", 20) . rand(1000, 9999)
            ];
            
            if ($concurrentLock->tryLock()) {
                try {
                    $concurrentMap->put("concurrent:item:$i", $data);
                    $concurrentCounter->incrementAndGet();
                    $successCount++;
                } catch (\Exception $e) {
                    $errorCount++;
                } finally {
                    $concurrentLock->unlock();
                }
            } else {
                // 如果锁不可用，使用CAS操作
                $currentValue = $concurrentCounter->get();
                if ($concurrentCounter->compareAndSet($currentValue, $currentValue + 1)) {
                    $concurrentMap->put("concurrent:item:$i", $data);
                    $successCount++;
                } else {
                    $errorCount++;
                }
            }
        }
        
        $endTime = microtime(true);
        $operationTime = $endTime - $startTime;
        
        // 验证并发操作结果
        $this->assertEquals($concurrentCount, $successCount + $errorCount);
        $this->assertEquals($successCount, $concurrentCounter->get());
        $this->assertEquals($concurrentCount, $concurrentMap->size());
        
        // 性能验证
        $this->assertLessThan(25, $operationTime, "并发操作应该在25秒内完成");
        
        // 验证数据一致性
        $validDataCount = 0;
        for ($i = 0; $i < $concurrentCount; $i++) {
            $data = $concurrentMap->get("concurrent:item:$i");
            if ($data && isset($data['thread_id']) && isset($data['operation_id'])) {
                $validDataCount++;
            }
        }
        
        $this->assertEquals($concurrentCount, $validDataCount);
        
        // 清理
        $concurrentMap->clear();
        $concurrentCounter->delete();
    }
    
    /**
     * 执行内存清理操作
     */
    private function performMemoryCleanup($map)
    {
        $keys = array_keys($map->toArray());
        $cleanupBatchSize = 50;
        
        foreach (array_chunk($keys, $cleanupBatchSize) as $batch) {
            foreach ($batch as $key) {
                $data = $map->get($key);
                if ($data && isset($data['metadata']['batch'])) {
                    // 清理旧批次数据
                    if ($data['metadata']['batch'] % 5 === 0) {
                        $map->remove($key);
                    }
                }
            }
        }
    }
    
    /**
     * 验证大数据Map数据完整性
     */
    private function validateLargeMapData($map, $expectedSize)
    {
        $validationErrors = 0;
        $checkedItems = min($expectedSize, 100); // 检查前100项或全部数据
        
        for ($i = 0; $i < $checkedItems; $i++) {
            $data = $map->get("large_item:$i");
            
            if (!$data) {
                $validationErrors++;
                continue;
            }
            
            // 验证必要字段
            if (!isset($data['id']) || 
                !isset($data['content']) || 
                !isset($data['metadata'])) {
                $validationErrors++;
                continue;
            }
            
            // 验证数据值
            if ($data['id'] !== $i) {
                $validationErrors++;
            }
        }
        
        $this->assertEquals(0, $validationErrors, "数据验证错误应该为0");
    }
    
    /**
     * 抽样获取大数据Map数据
     */
    private function sampleLargeMapData($map, $sampleSize)
    {
        $allKeys = array_keys($map->toArray());
        $sampledKeys = array_slice($allKeys, 0, $sampleSize);
        $sampledData = [];
        
        foreach ($sampledKeys as $key) {
            $data = $map->get($key);
            if ($data) {
                $sampledData[] = $data;
            }
        }
        
        return $sampledData;
    }
}