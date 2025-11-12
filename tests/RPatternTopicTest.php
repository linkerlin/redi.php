<?php

namespace Rediphp\Tests;

class RPatternTopicTest extends RedissonTestCase
{
    /**
     * 测试模式主题的基本订阅功能
     */
    public function testBasicSubscription()
    {
        $topic = $this->client->getPatternTopic('test.*');
        
        // 验证模式主题创建成功
        $this->assertNotNull($topic);
        
        // 测试订阅者数量
        $subscriberCount = $topic->countSubscribers();
        $this->assertIsInt($subscriberCount);
        $this->assertGreaterThanOrEqual(0, $subscriberCount);
    }
    
    /**
     * 测试模式主题的订阅者数量统计
     */
    public function testSubscriberCount()
    {
        $topic = $this->client->getPatternTopic('test.*');
        
        // 测试初始订阅者数量
        $initialCount = $topic->countSubscribers();
        $this->assertIsInt($initialCount);
        
        // 测试不同模式主题的订阅者数量
        $topic2 = $this->client->getPatternTopic('dev.*');
        $count2 = $topic2->countSubscribers();
        $this->assertIsInt($count2);
        
        // 验证订阅者数量统计功能正常工作
        $this->assertGreaterThanOrEqual(0, $initialCount);
        $this->assertGreaterThanOrEqual(0, $count2);
    }
    
    /**
     * 测试不同模式主题的创建
     */
    public function testDifferentPatterns()
    {
        // 测试 * 模式
        $topic1 = $this->client->getPatternTopic('test.*');
        $this->assertNotNull($topic1);
        
        // 测试 ? 模式
        $topic2 = $this->client->getPatternTopic('test.cha?nel');
        $this->assertNotNull($topic2);
        
        // 测试字符范围模式
        $topic3 = $this->client->getPatternTopic('test.[abc]*');
        $this->assertNotNull($topic3);
        
        // 测试不同模式主题的订阅者数量
        $count1 = $topic1->countSubscribers();
        $count2 = $topic2->countSubscribers();
        $count3 = $topic3->countSubscribers();
        
        $this->assertIsInt($count1);
        $this->assertIsInt($count2);
        $this->assertIsInt($count3);
        $this->assertGreaterThanOrEqual(0, $count1);
        $this->assertGreaterThanOrEqual(0, $count2);
        $this->assertGreaterThanOrEqual(0, $count3);
    }
    
    /**
     * 测试模式主题的重复创建
     */
    public function testRepeatedCreation()
    {
        // 多次创建相同的模式主题
        $topic1 = $this->client->getPatternTopic('test.*');
        $topic2 = $this->client->getPatternTopic('test.*');
        $topic3 = $this->client->getPatternTopic('test.*');
        
        // 验证所有实例都成功创建
        $this->assertNotNull($topic1);
        $this->assertNotNull($topic2);
        $this->assertNotNull($topic3);
        
        // 测试订阅者数量的一致性
        $count1 = $topic1->countSubscribers();
        $count2 = $topic2->countSubscribers();
        $count3 = $topic3->countSubscribers();
        
        $this->assertIsInt($count1);
        $this->assertIsInt($count2);
        $this->assertIsInt($count3);
        
        // 相同模式主题的订阅者数量应该相同
        $this->assertEquals($count1, $count2);
        $this->assertEquals($count2, $count3);
    }
    
    /**
     * 测试模式主题的订阅者数量变化
     */
    public function testSubscriberCountChanges()
    {
        $topic = $this->client->getPatternTopic('test.*');
        
        // 获取初始订阅者数量
        $initialCount = $topic->countSubscribers();
        $this->assertIsInt($initialCount);
        
        // 创建另一个模式主题实例
        $topic2 = $this->client->getPatternTopic('test.*');
        $count2 = $topic2->countSubscribers();
        
        // 验证两个实例的订阅者数量相同
        $this->assertEquals($initialCount, $count2);
        
        // 测试不同模式主题的订阅者数量
        $topic3 = $this->client->getPatternTopic('dev.*');
        $count3 = $topic3->countSubscribers();
        
        $this->assertIsInt($count3);
        $this->assertGreaterThanOrEqual(0, $count3);
    }
    
    /**
     * 测试模式主题的实例独立性
     */
    public function testInstanceIndependence()
    {
        // 创建多个不同的模式主题
        $topic1 = $this->client->getPatternTopic('test.*');
        $topic2 = $this->client->getPatternTopic('dev.*');
        $topic3 = $this->client->getPatternTopic('prod.*');
        
        // 验证所有实例都成功创建
        $this->assertNotNull($topic1);
        $this->assertNotNull($topic2);
        $this->assertNotNull($topic3);
        
        // 测试每个实例的订阅者数量
        $count1 = $topic1->countSubscribers();
        $count2 = $topic2->countSubscribers();
        $count3 = $topic3->countSubscribers();
        
        $this->assertIsInt($count1);
        $this->assertIsInt($count2);
        $this->assertIsInt($count3);
        
        // 不同模式主题的订阅者数量可能不同
        $this->assertGreaterThanOrEqual(0, $count1);
        $this->assertGreaterThanOrEqual(0, $count2);
        $this->assertGreaterThanOrEqual(0, $count3);
    }
    
    /**
     * 测试多个模式主题的订阅者数量统计
     */
    public function testMultiplePatternTopics()
    {
        // 创建多个模式主题
        $topic1 = $this->client->getPatternTopic('test.*');
        $topic2 = $this->client->getPatternTopic('dev.*');
        $topic3 = $this->client->getPatternTopic('prod.*');
        
        // 验证所有实例都成功创建
        $this->assertNotNull($topic1);
        $this->assertNotNull($topic2);
        $this->assertNotNull($topic3);
        
        // 测试每个模式主题的订阅者数量
        $count1 = $topic1->countSubscribers();
        $count2 = $topic2->countSubscribers();
        $count3 = $topic3->countSubscribers();
        
        $this->assertIsInt($count1);
        $this->assertIsInt($count2);
        $this->assertIsInt($count3);
        
        // 验证订阅者数量统计功能正常工作
        $this->assertGreaterThanOrEqual(0, $count1);
        $this->assertGreaterThanOrEqual(0, $count2);
        $this->assertGreaterThanOrEqual(0, $count3);
        
        // 测试相同模式主题的订阅者数量一致性
        $topic4 = $this->client->getPatternTopic('test.*');
        $count4 = $topic4->countSubscribers();
        
        $this->assertEquals($count1, $count4);
    }
    
    /**
     * 测试模式主题的边界情况
     */
    public function testEdgeCases()
    {
        // 测试空模式
        $topic1 = $this->client->getPatternTopic('');
        $this->assertNotNull($topic1);
        
        // 测试长模式
        $longPattern = str_repeat('a', 100);
        $topic2 = $this->client->getPatternTopic($longPattern);
        $this->assertNotNull($topic2);
        
        // 测试特殊字符模式
        $specialPattern = 'test.*[special]';
        $topic3 = $this->client->getPatternTopic($specialPattern);
        $this->assertNotNull($topic3);
        
        // 测试所有模式主题的订阅者数量
        $count1 = $topic1->countSubscribers();
        $count2 = $topic2->countSubscribers();
        $count3 = $topic3->countSubscribers();
        
        $this->assertIsInt($count1);
        $this->assertIsInt($count2);
        $this->assertIsInt($count3);
        
        $this->assertGreaterThanOrEqual(0, $count1);
        $this->assertGreaterThanOrEqual(0, $count2);
        $this->assertGreaterThanOrEqual(0, $count3);
    }
    
    /**
     * 测试模式主题的性能
     */
    public function testPerformance()
    {
        $startTime = microtime(true);
        
        // 批量创建模式主题
        $topics = [];
        for ($i = 0; $i < 100; $i++) {
            $topic = $this->client->getPatternTopic('test.' . $i);
            $topics[] = $topic;
        }
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // 验证所有主题都成功创建
        $this->assertCount(100, $topics);
        foreach ($topics as $topic) {
            $this->assertNotNull($topic);
        }
        
        // 验证性能在可接受范围内
        $this->assertLessThan(5, $executionTime); // 5秒内完成
        
        // 测试订阅者数量统计的性能
        $startTime2 = microtime(true);
        
        $totalSubscribers = 0;
        foreach ($topics as $topic) {
            $count = $topic->countSubscribers();
            $totalSubscribers += $count;
        }
        
        $endTime2 = microtime(true);
        $executionTime2 = $endTime2 - $startTime2;
        
        // 验证订阅者数量统计性能
        $this->assertLessThan(3, $executionTime2); // 3秒内完成
        $this->assertGreaterThanOrEqual(0, $totalSubscribers);
    }
}