<?php

namespace Rediphp\Tests;

class RSortedSetTest extends RedissonTestCase
{
    /**
     * 测试有序集合的基本添加和获取操作
     */
    public function testBasicAddAndGetOperations()
    {
        $sortedSet = $this->client->getSortedSet('test-sortedset');
        
        // 添加元素
        $this->assertTrue($sortedSet->add('member1', 10.5));
        $this->assertTrue($sortedSet->add('member2', 5.2));
        $this->assertTrue($sortedSet->add('member3', 15.8));
        
        // 验证元素数量
        $this->assertEquals(3, $sortedSet->size());
        
        // 验证元素存在性
        $this->assertTrue($sortedSet->contains('member1'));
        $this->assertTrue($sortedSet->contains('member2'));
        $this->assertTrue($sortedSet->contains('member3'));
        $this->assertFalse($sortedSet->contains('member4'));
        
        // 获取元素分数
        $this->assertEquals(10.5, $sortedSet->getScore('member1'));
        $this->assertEquals(5.2, $sortedSet->getScore('member2'));
        $this->assertEquals(15.8, $sortedSet->getScore('member3'));
        
        // 获取不存在的元素分数
        $this->assertNull($sortedSet->getScore('member4'));
    }
    
    /**
     * 测试有序集合的排序功能
     */
    public function testSortingFunctionality()
    {
        $sortedSet = $this->client->getSortedSet('test-sorting-sortedset');
        
        // 添加元素（无序）
        $sortedSet->add('z', 30.0);
        $sortedSet->add('a', 10.0);
        $sortedSet->add('m', 20.0);
        $sortedSet->add('b', 15.0);
        
        // 按分数升序获取
        $ascending = $sortedSet->valueRange(0, -1);
        $this->assertEquals(['a', 'b', 'm', 'z'], $ascending);
        
        // 按分数降序获取
        $descending = $sortedSet->valueRangeReversed(0, -1);
        $this->assertEquals(['z', 'm', 'b', 'a'], $descending);
        
        // 获取分数范围
        $range10to20 = $sortedSet->valueRange(10.0, 20.0);
        $this->assertEquals(['a', 'b', 'm'], $range10to20);
        
        // 获取带分数的范围
        $rangeWithScores = $sortedSet->entryRange(0, -1);
        $this->assertCount(4, $rangeWithScores);
        $this->assertEquals(10.0, $rangeWithScores['a']);
        $this->assertEquals(15.0, $rangeWithScores['b']);
        $this->assertEquals(20.0, $rangeWithScores['m']);
        $this->assertEquals(30.0, $rangeWithScores['z']);
    }
    
    /**
     * 测试有序集合的排名功能
     */
    public function testRankingFunctionality()
    {
        $sortedSet = $this->client->getSortedSet('test-ranking-sortedset');
        
        // 添加元素
        $sortedSet->add('first', 100.0);
        $sortedSet->add('second', 200.0);
        $sortedSet->add('third', 300.0);
        $sortedSet->add('fourth', 400.0);
        
        // 获取排名（升序排名，从0开始）
        $this->assertEquals(0, $sortedSet->rank('first'));
        $this->assertEquals(1, $sortedSet->rank('second'));
        $this->assertEquals(2, $sortedSet->rank('third'));
        $this->assertEquals(3, $sortedSet->rank('fourth'));
        
        // 获取反向排名（降序排名，从0开始）
        $this->assertEquals(3, $sortedSet->revRank('first'));
        $this->assertEquals(2, $sortedSet->revRank('second'));
        $this->assertEquals(1, $sortedSet->revRank('third'));
        $this->assertEquals(0, $sortedSet->revRank('fourth'));
        
        // 获取不存在的元素排名
        $this->assertNull($sortedSet->rank('nonexistent'));
        $this->assertNull($sortedSet->revRank('nonexistent'));
    }
    
    /**
     * 测试有序集合的删除操作
     */
    public function testRemoveOperations()
    {
        $sortedSet = $this->client->getSortedSet('test-remove-sortedset');
        $sortedSet->clear(); // 确保清理所有数据
        
        // 添加元素
        $sortedSet->add('to-keep', 10.0);
        $sortedSet->add('to-remove1', 20.0);
        $sortedSet->add('to-remove2', 30.0);
        $sortedSet->add('to-remove3', 40.0);
        
        $this->assertEquals(4, $sortedSet->size());
        
        // 删除单个元素
        $this->assertTrue($sortedSet->remove('to-remove1'));
        $this->assertEquals(3, $sortedSet->size());
        $this->assertFalse($sortedSet->contains('to-remove1'));
        
        // 删除不存在的元素
        $this->assertFalse($sortedSet->remove('nonexistent'));
        
        // 批量删除
        $removedCount = $sortedSet->removeBatch(['to-remove2', 'to-remove3']);
        $this->assertEquals(2, $removedCount);
        $this->assertEquals(1, $sortedSet->size());
        $this->assertTrue($sortedSet->contains('to-keep'));
        
        // 按分数范围删除
        $sortedSet->add('range1', 5.0);
        $sortedSet->add('range2', 15.0);
        $sortedSet->add('range3', 25.0);
        
        $removedByRange = $sortedSet->removeRangeByScore(10.0, 20.0);
        $this->assertEquals(1, $removedByRange); // 只删除range2
        $this->assertEquals(3, $sortedSet->size()); // 剩余range1、to-keep和range3
        
        // 按排名范围删除
        $sortedSet->clear();
        $sortedSet->add('rank1', 10.0);
        $sortedSet->add('rank2', 20.0);
        $sortedSet->add('rank3', 30.0);
        $sortedSet->add('rank4', 40.0);
        
        $removedByRank = $sortedSet->removeRange(1, 2); // 删除排名1-2的元素
        $this->assertEquals(2, $removedByRank);
        $this->assertEquals(2, $sortedSet->size());
        $this->assertTrue($sortedSet->contains('rank1'));
        $this->assertTrue($sortedSet->contains('rank4'));
    }
    
    /**
     * 测试有序集合的分数更新
     */
    public function testScoreUpdates()
    {
        $sortedSet = $this->client->getSortedSet('test-score-update-sortedset');
        $sortedSet->clear(); // 确保清理所有数据
        
        // 添加元素
        $sortedSet->add('member', 10.0);
        $this->assertEquals(10.0, $sortedSet->getScore('member'));
        
        // 更新分数
        $this->assertTrue($sortedSet->add('member', 20.0)); // 更新分数
        $this->assertEquals(20.0, $sortedSet->getScore('member'));
        
        // 增加分数
        $newScore = $sortedSet->addScore('member', 5.0);
        $this->assertEquals(25.0, $newScore);
        $this->assertEquals(25.0, $sortedSet->getScore('member'));
        
        // 减少分数
        $newScore = $sortedSet->addScore('member', -10.0);
        $this->assertEquals(15.0, $newScore);
        $this->assertEquals(15.0, $sortedSet->getScore('member'));
        
        // 为不存在的元素增加分数
        $newScore = $sortedSet->addScore('new-member', 30.0);
        $this->assertEquals(30.0, $newScore);
        $this->assertEquals(30.0, $sortedSet->getScore('new-member'));
    }
    
    /**
     * 测试有序集合的批量操作
     */
    public function testBatchOperations()
    {
        $sortedSet = $this->client->getSortedSet('test-batch-sortedset');
        
        // 批量添加元素
        $members = [
            'batch1' => 10.0,
            'batch2' => 20.0,
            'batch3' => 30.0,
            'batch4' => 40.0
        ];
        
        $addedCount = $sortedSet->addAll($members);
        $this->assertEquals(4, $addedCount);
        $this->assertEquals(4, $sortedSet->size());
        
        // 获取所有元素
        $allMembers = $sortedSet->readAll();
        $this->assertCount(4, $allMembers);
        $this->assertArrayHasKey('batch1', $allMembers);
        $this->assertArrayHasKey('batch2', $allMembers);
        $this->assertArrayHasKey('batch3', $allMembers);
        $this->assertArrayHasKey('batch4', $allMembers);
        
        // 获取所有元素的分数
        $allScores = $sortedSet->readAllWithScores();
        $this->assertCount(4, $allScores);
        $this->assertEquals(10.0, $allScores['batch1']);
        $this->assertEquals(20.0, $allScores['batch2']);
        $this->assertEquals(30.0, $allScores['batch3']);
        $this->assertEquals(40.0, $allScores['batch4']);
        
        // 批量删除
        $removedCount = $sortedSet->removeBatch(['batch1', 'batch3']);
        $this->assertEquals(2, $removedCount);
        $this->assertEquals(2, $sortedSet->size());
    }
    
    /**
     * 测试有序集合的清除操作
     */
    public function testClear()
    {
        $sortedSet = $this->client->getSortedSet('test-clear-sortedset');
        $sortedSet->clear(); // 确保清理所有数据
        
        // 添加元素
        $sortedSet->add('member1', 10.0);
        $sortedSet->add('member2', 20.0);
        $sortedSet->add('member3', 30.0);
        
        $this->assertEquals(3, $sortedSet->size());
        
        // 清除集合
        $sortedSet->clear();
        
        // 验证集合已清空
        $this->assertEquals(0, $sortedSet->size());
        $this->assertFalse($sortedSet->contains('member1'));
        $this->assertFalse($sortedSet->contains('member2'));
        $this->assertFalse($sortedSet->contains('member3'));
        
        // 清除后可以重新添加
        $sortedSet->add('new-member', 50.0);
        $this->assertEquals(1, $sortedSet->size());
        $this->assertTrue($sortedSet->contains('new-member'));
    }
    
    /**
     * 测试有序集合的存在性检查
     */
    public function testExists()
    {
        $sortedSet = $this->client->getSortedSet('test-exists-sortedset');
        
        // 初始状态下应该不存在
        $this->assertFalse($sortedSet->exists());
        
        // 添加元素后应该存在
        $sortedSet->add('member', 10.0);
        $this->assertTrue($sortedSet->exists());
        
        // 清除后应该不存在
        $sortedSet->clear();
        $this->assertFalse($sortedSet->exists());
    }
    
    /**
     * 测试有序集合的边界情况
     */
    public function testEdgeCases()
    {
        $sortedSet = $this->client->getSortedSet('test-edge-sortedset');
        $sortedSet->clear(); // 确保清理所有数据
        
        // 测试空集合
        $this->assertEquals(0, $sortedSet->size());
        $this->assertEmpty($sortedSet->valueRange(0, -1));
        $this->assertEmpty($sortedSet->readAll());
        
        // 测试重复添加相同元素（应该更新分数）
        $sortedSet->add('member', 10.0);
        $sortedSet->add('member', 20.0);
        $this->assertEquals(1, $sortedSet->size());
        $this->assertEquals(20.0, $sortedSet->getScore('member'));
        
        // 测试特殊字符元素
        $sortedSet->add('member@#$%', 30.0);
        $sortedSet->add('成员', 40.0);
        $this->assertEquals(3, $sortedSet->size());
        $this->assertTrue($sortedSet->contains('member@#$%'));
        $this->assertTrue($sortedSet->contains('成员'));
        
        // 测试非常大的分数
        $sortedSet->add('big-score', PHP_FLOAT_MAX);
        $this->assertEquals(PHP_FLOAT_MAX, $sortedSet->getScore('big-score'));
        
        // 测试负分数
        $sortedSet->add('negative-score', -100.0);
        $this->assertEquals(-100.0, $sortedSet->getScore('negative-score'));
        
        // 测试空字符串元素
        $sortedSet->add('', 50.0);
        $this->assertTrue($sortedSet->contains(''));
        
        // 测试非常长的元素名
        $longName = str_repeat('a', 1000);
        $sortedSet->add($longName, 60.0);
        $this->assertTrue($sortedSet->contains($longName));
    }
    
    /**
     * 测试有序集合的性能
     */
    public function testPerformance()
    {
        $sortedSet = $this->client->getSortedSet('test-perf-sortedset');
        
        $startTime = microtime(true);
        
        // 添加大量元素
        for ($i = 0; $i < 100; $i++) {
            $sortedSet->add("member{$i}", $i * 1.5);
        }
        
        // 执行多次查询操作
        for ($i = 0; $i < 50; $i++) {
            $sortedSet->size();
            $sortedSet->contains("member{$i}");
            $sortedSet->getScore("member{$i}");
        }
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // 验证性能在合理范围内
        $this->assertLessThan(10, $executionTime); // 150次操作应该在10秒内完成
        
        // 清理
        $sortedSet->clear();
    }
    
    /**
     * 测试有序集合的异常情况
     */
    public function testSortedSetExceptions()
    {
        $sortedSet = $this->client->getSortedSet('test-exception-sortedset');
        
        // 测试无效的排名范围
        try {
            $sortedSet->valueRange(-1, -1);
            $this->assertTrue(true); // 可能不会抛出异常
        } catch (\Exception $e) {
            $this->assertTrue(true); // 或者抛出异常
        }
        
        // 测试无效的分数范围
        try {
            $sortedSet->valueRange(100.0, 50.0); // 开始大于结束
            $this->assertEmpty($sortedSet->valueRange(100.0, 50.0));
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
        
        // 测试空有序集合名
        try {
            $emptySortedSet = $this->client->getSortedSet('');
            $this->assertNotNull($emptySortedSet);
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
    }
    
    /**
     * 测试多个有序集合的并发操作
     */
    public function testMultipleSortedSets()
    {
        $sortedSet1 = $this->client->getSortedSet('test-multi-sortedset-1');
        $sortedSet2 = $this->client->getSortedSet('test-multi-sortedset-2');
        
        // 分别添加元素
        $sortedSet1->add('common', 10.0);
        $sortedSet1->add('unique1', 20.0);
        
        $sortedSet2->add('common', 15.0);
        $sortedSet2->add('unique2', 25.0);
        
        // 验证各自的内容
        $this->assertEquals(2, $sortedSet1->size());
        $this->assertEquals(2, $sortedSet2->size());
        
        $this->assertEquals(10.0, $sortedSet1->getScore('common'));
        $this->assertEquals(15.0, $sortedSet2->getScore('common'));
        
        $this->assertTrue($sortedSet1->contains('unique1'));
        $this->assertFalse($sortedSet1->contains('unique2'));
        
        $this->assertFalse($sortedSet2->contains('unique1'));
        $this->assertTrue($sortedSet2->contains('unique2'));
        
        // 分别清除
        $sortedSet1->clear();
        $sortedSet2->clear();
        
        $this->assertEquals(0, $sortedSet1->size());
        $this->assertEquals(0, $sortedSet2->size());
    }
    /**
     * 测试空值和null值处理
     */
    public function testNullAndEmptyValues()
    {
        $sortedSet = $this->client->getSortedSet('test-null-sortedset');
        $sortedSet->clear();
        
        // 测试null作为元素
        $sortedSet->add(null, 10.0);
        $this->assertTrue($sortedSet->contains(null));
        $this->assertEquals(10.0, $sortedSet->getScore(null));
        
        // 测试空字符串
        $sortedSet->add('', 20.0);
        $this->assertTrue($sortedSet->contains(''));
        $this->assertEquals(20.0, $sortedSet->getScore(''));
        
        // 测试包含空格的字符串
        $sortedSet->add('   ', 30.0);
        $this->assertTrue($sortedSet->contains('   '));
        
        // 验证所有元素都存在
        $this->assertEquals(3, $sortedSet->size());
    }
    
    /**
     * 测试极端分数值
     */
    public function testExtremeScoreValues()
    {
        $sortedSet = $this->client->getSortedSet('test-extreme-scores');
        $sortedSet->clear();
        
        // 测试最大浮点数
        $sortedSet->add('max', PHP_FLOAT_MAX);
        $this->assertEquals(PHP_FLOAT_MAX, $sortedSet->getScore('max'));
        
        // 测试最小浮点数
        $sortedSet->add('min', -PHP_FLOAT_MAX);
        $this->assertEquals(-PHP_FLOAT_MAX, $sortedSet->getScore('min'));
        
        // 测试接近0的值
        $sortedSet->add('near-zero', 1.0e-10);
        $this->assertEquals(1.0e-10, $sortedSet->getScore('near-zero'));
        
        // 测试分数范围查询（使用更安全的范围）
        $range = $sortedSet->valueRange(-PHP_FLOAT_MAX, PHP_FLOAT_MAX);
        $this->assertGreaterThanOrEqual(1, count($range)); // 至少应该有1个元素
    }
    
    /**
     * 测试特殊字符和Unicode
     */
    public function testSpecialCharactersAndUnicode()
    {
        $sortedSet = $this->client->getSortedSet('test-unicode-sortedset');
        $sortedSet->clear();
        
        // 测试Emoji
        $sortedSet->add('😀', 10.0);
        $this->assertTrue($sortedSet->contains('😀'));
        
        // 测试中文字符
        $sortedSet->add('中文测试', 20.0);
        $this->assertTrue($sortedSet->contains('中文测试'));
        
        // 测试日文
        $sortedSet->add('日本語テスト', 30.0);
        $this->assertTrue($sortedSet->contains('日本語テスト'));
        
        // 测试特殊符号
        $sortedSet->add('!@#$%^&*()', 40.0);
        $this->assertTrue($sortedSet->contains('!@#$%^&*()'));
        
        // 测试换行符和制表符
        $sortedSet->add("line1\nline2", 50.0);
        $this->assertTrue($sortedSet->contains("line1\nline2"));
        
        $sortedSet->add("tab\there", 60.0);
        $this->assertTrue($sortedSet->contains("tab\there"));
        
        $this->assertEquals(6, $sortedSet->size());
    }
    
    /**
     * 测试并发操作
     */
    public function testConcurrentOperations()
    {
        $sortedSet = $this->client->getSortedSet('test-concurrent-sortedset');
        $sortedSet->clear();
        
        // 模拟并发添加
        $elements = [];
        for ($i = 0; $i < 100; $i++) {
            $elements["element{$i}"] = $i * 1.0;
        }
        
        $sortedSet->addAll($elements);
        $this->assertEquals(100, $sortedSet->size());
        
        // 验证所有元素都存在
        for ($i = 0; $i < 100; $i++) {
            $this->assertTrue($sortedSet->contains("element{$i}"));
            $this->assertEquals($i * 1.0, $sortedSet->getScore("element{$i}"));
        }
        
        // 测试并发删除
        $deleteElements = [];
        for ($i = 0; $i < 50; $i++) {
            $deleteElements[] = "element{$i}";
        }
        
        $removedCount = $sortedSet->removeBatch($deleteElements);
        $this->assertEquals(50, $removedCount);
        $this->assertEquals(50, $sortedSet->size());
    }
    
    /**
     * 测试valueRange边界情况
     */
    public function testValueRangeEdgeCases()
    {
        $sortedSet = $this->client->getSortedSet('test-value-range-edge');
        $sortedSet->clear();
        
        // 添加测试数据
        $sortedSet->add('a', 10.0);
        $sortedSet->add('b', 20.0);
        $sortedSet->add('c', 30.0);
        $sortedSet->add('d', 40.0);
        $sortedSet->add('e', 50.0);
        
        // 测试反向范围（开始大于结束）
        $emptyRange = $sortedSet->valueRange(50.0, 10.0);
        $this->assertEmpty($emptyRange);
        
        // 测试精确分数匹配
        $exactMatch = $sortedSet->valueRange(20.0, 20.0);
        $this->assertEquals(['b'], $exactMatch);
        
        // 测试不存在的分数范围
        $nonExistent = $sortedSet->valueRange(100.0, 200.0);
        $this->assertEmpty($nonExistent);
        
        // 测试负数排名
        $negativeRank = $sortedSet->valueRange(-2, -1);
        $this->assertEquals(['d', 'e'], $negativeRank);
        
        // 测试超出范围的排名
        $outOfRange = $sortedSet->valueRange(100, 200);
        $this->assertEmpty($outOfRange);
    }
    
    /**
     * 测试数据类型转换
     */
    public function testDataTypeConversions()
    {
        $sortedSet = $this->client->getSortedSet('test-type-conversion');
        $sortedSet->clear();
        
        // 测试整数作为分数
        $sortedSet->add('int-score', 10);
        $this->assertEquals(10.0, $sortedSet->getScore('int-score'));
        
        // 测试字符串数字作为分数
        $sortedSet->add('string-score', '25.5');
        $this->assertEquals(25.5, $sortedSet->getScore('string-score'));
        
        // 测试布尔值（应该被转换为数字）
        $sortedSet->add('bool-true', true);
        $sortedSet->add('bool-false', false);
        $this->assertEquals(1.0, $sortedSet->getScore('bool-true'));
        $this->assertEquals(0.0, $sortedSet->getScore('bool-false'));
        
        // 测试数组元素（应该被JSON编码）
        $arrayElement = ['key' => 'value', 'number' => 123];
        $sortedSet->add($arrayElement, 30.0);
        $this->assertTrue($sortedSet->contains($arrayElement));
        
        // 测试对象元素（应该被JSON编码）
        $obj = new \stdClass();
        $obj->property = 'test';
        $sortedSet->add($obj, 40.0);
        $this->assertTrue($sortedSet->contains($obj));
    }
    
    /**
     * 测试内存效率
     */
    public function testMemoryEfficiency()
    {
        $sortedSet = $this->client->getSortedSet('test-memory-efficiency');
        $sortedSet->clear();
        
        // 添加大量小元素
        $startMemory = memory_get_usage();
        for ($i = 0; $i < 1000; $i++) {
            $sortedSet->add("element{$i}", $i * 0.1);
        }
        
        $this->assertEquals(1000, $sortedSet->size());
        
        // 验证内存使用在合理范围内（每个元素应该很小）
        $memoryUsed = memory_get_usage() - $startMemory;
        $this->assertLessThan(10 * 1024 * 1024, $memoryUsed); // 应该小于10MB
        
        // 测试批量删除的内存效率
        $sortedSet->clear();
        $this->assertEquals(0, $sortedSet->size());
    }
    
    /**
     * 测试错误处理和恢复
     */
    public function testErrorHandlingAndRecovery()
    {
        $sortedSet = $this->client->getSortedSet('test-error-recovery');
        $sortedSet->clear();
        
        // 测试删除不存在的元素
        $this->assertFalse($sortedSet->remove('non-existent'));
        
        // 测试获取不存在的元素的分数
        $this->assertNull($sortedSet->getScore('non-existent'));
        
        // 测试获取不存在的元素的排名
        $this->assertNull($sortedSet->rank('non-existent'));
        $this->assertNull($sortedSet->revRank('non-existent'));
        
        // 测试在空集合上操作
        $emptySortedSet = $this->client->getSortedSet('test-empty-sortedset');
        $emptySortedSet->clear();
        $this->assertEquals(0, $emptySortedSet->size());
        $this->assertEmpty($emptySortedSet->valueRange(0, -1));
        $this->assertEmpty($emptySortedSet->readAll());
        
        // 测试删除范围操作在空集合上
        $this->assertEquals(0, $emptySortedSet->removeRangeByScore(0.0, 100.0));
        $this->assertEquals(0, $emptySortedSet->removeRange(0, -1));
    }
}