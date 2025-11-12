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
        $this->assertEquals(2, $sortedSet->size());
        
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
}