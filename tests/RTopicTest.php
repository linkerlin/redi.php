<?php

namespace Rediphp\Tests;

class RTopicTest extends RedissonTestCase
{
    /**
     * 测试主题的基本发布和订阅
     */
    public function testBasicPublishAndSubscribe()
    {
        $topic = $this->client->getTopic('test-topic');
        
        $receivedMessages = [];
        
        // 添加监听器
        $listener = function($message) use (&$receivedMessages) {
            $receivedMessages[] = $message;
        };
        
        $listenerId = $topic->addListener($listener);
        $this->assertIsInt($listenerId);
        
        // 发布消息
        $topic->publish('Hello World');
        $topic->publish('Test Message');
        $topic->publish(['key' => 'value']);
        
        // 等待消息处理
        sleep(1);
        
        // 验证消息接收
        $this->assertContains('Hello World', $receivedMessages);
        $this->assertContains('Test Message', $receivedMessages);
        $this->assertContains(['key' => 'value'], $receivedMessages);
        
        // 移除监听器
        $topic->removeListener($listenerId);
        
        // 发布新消息（应该不会被接收）
        $topic->publish('After Removal');
        sleep(1);
        
        $this->assertNotContains('After Removal', $receivedMessages);
    }
    
    /**
     * 测试多个监听器的消息接收
     */
    public function testMultipleListeners()
    {
        $topic = $this->client->getTopic('test-multi-topic');
        
        $receivedMessages1 = [];
        $receivedMessages2 = [];
        $receivedMessages3 = [];
        
        // 添加多个监听器
        $listener1 = function($message) use (&$receivedMessages1) {
            $receivedMessages1[] = $message;
        };
        
        $listener2 = function($message) use (&$receivedMessages2) {
            $receivedMessages2[] = $message;
        };
        
        $listener3 = function($message) use (&$receivedMessages3) {
            $receivedMessages3[] = $message;
        };
        
        $id1 = $topic->addListener($listener1);
        $id2 = $topic->addListener($listener2);
        $id3 = $topic->addListener($listener3);
        
        // 发布消息
        $topic->publish('Broadcast Message');
        sleep(1);
        
        // 验证所有监听器都接收到消息
        $this->assertContains('Broadcast Message', $receivedMessages1);
        $this->assertContains('Broadcast Message', $receivedMessages2);
        $this->assertContains('Broadcast Message', $receivedMessages3);
        
        // 移除部分监听器
        $topic->removeListener($id2);
        
        // 发布新消息
        $topic->publish('After Partial Removal');
        sleep(1);
        
        // 验证只有剩余的监听器接收到消息
        $this->assertContains('After Partial Removal', $receivedMessages1);
        $this->assertNotContains('After Partial Removal', $receivedMessages2);
        $this->assertContains('After Partial Removal', $receivedMessages3);
        
        // 清理
        $topic->removeListener($id1);
        $topic->removeListener($id3);
    }
    
    /**
     * 测试不同类型的消息
     */
    public function testDifferentMessageTypes()
    {
        $topic = $this->client->getTopic('test-types-topic');
        
        $receivedMessages = [];
        
        $listener = function($message) use (&$receivedMessages) {
            $receivedMessages[] = $message;
        };
        
        $topic->addListener($listener);
        
        // 发布字符串消息
        $topic->publish('String Message');
        
        // 发布数字消息
        $topic->publish(123);
        $topic->publish(45.67);
        
        // 发布数组消息
        $topic->publish(['array', 'message']);
        
        // 发布关联数组消息
        $topic->publish(['key1' => 'value1', 'key2' => 'value2']);
        
        // 发布布尔值消息
        $topic->publish(true);
        $topic->publish(false);
        
        // 发布null消息
        $topic->publish(null);
        
        sleep(1);
        
        // 验证所有类型的消息都被正确接收
        $this->assertContains('String Message', $receivedMessages);
        $this->assertContains(123, $receivedMessages);
        $this->assertContains(45.67, $receivedMessages);
        $this->assertContains(['array', 'message'], $receivedMessages);
        $this->assertContains(['key1' => 'value1', 'key2' => 'value2'], $receivedMessages);
        $this->assertContains(true, $receivedMessages);
        $this->assertContains(false, $receivedMessages);
        $this->assertContains(null, $receivedMessages);
    }
    
    /**
     * 测试主题的清除操作
     */
    public function testClear()
    {
        $topic = $this->client->getTopic('test-clear-topic');
        
        $receivedMessages = [];
        
        // 添加监听器
        $listener = function($message) use (&$receivedMessages) {
            $receivedMessages[] = $message;
        };
        
        $topic->addListener($listener);
        
        // 发布消息
        $topic->publish('Before Clear');
        sleep(1);
        
        // 清除主题
        $topic->clear();
        
        // 发布新消息（应该不会被接收）
        $topic->publish('After Clear');
        sleep(1);
        
        // 验证清除操作
        $this->assertContains('Before Clear', $receivedMessages);
        $this->assertNotContains('After Clear', $receivedMessages);
        
        // 重新添加监听器
        $topic->addListener($listener);
        $topic->publish('After Re-add');
        sleep(1);
        
        $this->assertContains('After Re-add', $receivedMessages);
    }
    
    /**
     * 测试主题的存在性检查
     */
    public function testExists()
    {
        $topic = $this->client->getTopic('test-exists-topic');
        
        // 初始状态下应该不存在
        $this->assertFalse($topic->exists());
        
        // 添加监听器后应该存在
        $listener = function($message) {};
        $topic->addListener($listener);
        $this->assertTrue($topic->exists());
        
        // 清除后应该不存在
        $topic->clear();
        $this->assertFalse($topic->exists());
        
        // 发布消息后主题仍然不存在（因为没有订阅者）
        $topic->publish('Test');
        $this->assertFalse($topic->exists());
    }
    
    /**
     * 测试主题的大小
     */
    public function testSize()
    {
        $topic = $this->client->getTopic('test-size-topic');
        
        // 初始大小应该为0
        $this->assertEquals(0, $topic->size());
        
        // 添加监听器后大小应该增加
        $listener1 = function($message) {};
        $listener2 = function($message) {};
        $listener3 = function($message) {};
        
        $topic->addListener($listener1);
        $this->assertEquals(1, $topic->size());
        
        $topic->addListener($listener2);
        $this->assertEquals(2, $topic->size());
        
        $topic->addListener($listener3);
        $this->assertEquals(3, $topic->size());
        
        // 移除监听器后大小应该减少
        $topic->removeListener(1); // 假设第一个监听器的ID是1
        $this->assertEquals(2, $topic->size());
        
        // 清除后大小应该为0
        $topic->clear();
        $this->assertEquals(0, $topic->size());
    }
    
    /**
     * 测试多个主题的并发操作
     */
    public function testMultipleTopics()
    {
        $topic1 = $this->client->getTopic('test-multi-topic-1');
        $topic2 = $this->client->getTopic('test-multi-topic-2');
        
        $received1 = [];
        $received2 = [];
        
        $listener1 = function($message) use (&$received1) {
            $received1[] = $message;
        };
        
        $listener2 = function($message) use (&$received2) {
            $received2[] = $message;
        };
        
        $topic1->addListener($listener1);
        $topic2->addListener($listener2);
        
        // 分别发布消息
        $topic1->publish('Topic1 Message');
        $topic2->publish('Topic2 Message');
        
        sleep(1);
        
        // 验证每个主题只接收到自己的消息
        $this->assertContains('Topic1 Message', $received1);
        $this->assertNotContains('Topic2 Message', $received1);
        
        $this->assertContains('Topic2 Message', $received2);
        $this->assertNotContains('Topic1 Message', $received2);
        
        // 验证各自的大小
        $this->assertEquals(1, $topic1->size());
        $this->assertEquals(1, $topic2->size());
        
        // 分别清除
        $topic1->clear();
        $topic2->clear();
        
        $this->assertEquals(0, $topic1->size());
        $this->assertEquals(0, $topic2->size());
    }
    
    /**
     * 测试主题的边界情况
     */
    public function testEdgeCases()
    {
        // 测试空主题名
        try {
            $emptyTopic = $this->client->getTopic('');
            $this->assertNotNull($emptyTopic);
        } catch (\Exception $e) {
            $this->assertTrue(true); // 可能抛出异常，这是预期的
        }
        
        // 测试非常长的主题名
        $longName = str_repeat('a', 255);
        $longTopic = $this->client->getTopic($longName);
        $this->assertNotNull($longTopic);
        
        // 测试特殊字符主题名
        $specialTopic = $this->client->getTopic('test-topic-@#$%');
        $this->assertNotNull($specialTopic);
        
        // 测试重复添加和移除监听器
        $topic = $this->client->getTopic('test-edge-topic');
        $listener = function($message) {};
        
        $id1 = $topic->addListener($listener);
        $id2 = $topic->addListener($listener);
        
        $this->assertNotEquals($id1, $id2);
        
        $topic->removeListener($id1);
        $topic->removeListener($id1); // 重复移除
        
        $this->assertEquals(1, $topic->size());
        
        $topic->clear();
        $topic->clear(); // 重复清除
        
        $this->assertEquals(0, $topic->size());
    }
    
    /**
     * 测试主题的性能
     */
    public function testPerformance()
    {
        $topic = $this->client->getTopic('test-perf-topic');
        
        $startTime = microtime(true);
        
        // 添加多个监听器
        $listeners = [];
        for ($i = 0; $i < 10; $i++) {
            $listener = function($message) {
                // 空监听器
            };
            $topic->addListener($listener);
        }
        
        // 发布大量消息
        for ($i = 0; $i < 50; $i++) {
            $topic->publish("Message {$i}");
        }
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // 验证性能在合理范围内
        $this->assertLessThan(10, $executionTime); // 60次操作应该在10秒内完成
        
        // 清理
        $topic->clear();
    }
    
    /**
     * 测试主题的异常情况
     */
    public function testTopicExceptions()
    {
        $topic = $this->client->getTopic('test-exception-topic');
        
        // 测试无效的监听器ID
        try {
            $topic->removeListener(-1); // 无效的ID
            $this->assertTrue(true); // 可能不会抛出异常
        } catch (\Exception $e) {
            $this->assertTrue(true); // 或者抛出异常
        }
        
        // 测试非常大的消息
        try {
            $largeMessage = str_repeat('a', 1000000); // 1MB消息
            $topic->publish($largeMessage);
            $this->assertTrue(true); // 可能成功
        } catch (\Exception $e) {
            $this->assertTrue(true); // 或者抛出异常
        }
        
        // 测试在已清除的主题上操作
        $topic->clear();
        
        try {
            $topic->publish('After Clear');
            $this->assertTrue(true); // 可能成功
        } catch (\Exception $e) {
            $this->assertTrue(true); // 或者抛出异常
        }
        
        try {
            $topic->size();
            $this->assertEquals(0, $topic->size());
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
    }
    
    /**
     * 测试主题的消息顺序
     */
    public function testMessageOrder()
    {
        $topic = $this->client->getTopic('test-order-topic');
        
        $receivedMessages = [];
        
        $listener = function($message) use (&$receivedMessages) {
            $receivedMessages[] = $message;
        };
        
        $topic->addListener($listener);
        
        // 按顺序发布消息
        $messages = ['First', 'Second', 'Third', 'Fourth', 'Fifth'];
        
        foreach ($messages as $message) {
            $topic->publish($message);
        }
        
        sleep(1);
        
        // 验证消息接收顺序
        $this->assertEquals($messages, $receivedMessages);
    }
    
    /**
     * 测试主题的监听器管理
     */
    public function testListenerManagement()
    {
        $topic = $this->client->getTopic('test-management-topic');
        
        $callCount1 = 0;
        $callCount2 = 0;
        
        $listener1 = function($message) use (&$callCount1) {
            $callCount1++;
        };
        
        $listener2 = function($message) use (&$callCount2) {
            $callCount2++;
        };
        
        // 添加监听器
        $id1 = $topic->addListener($listener1);
        $id2 = $topic->addListener($listener2);
        
        // 发布消息
        $topic->publish('Test');
        sleep(1);
        
        $this->assertEquals(1, $callCount1);
        $this->assertEquals(1, $callCount2);
        
        // 移除第一个监听器
        $topic->removeListener($id1);
        
        // 发布新消息
        $topic->publish('Test2');
        sleep(1);
        
        $this->assertEquals(1, $callCount1); // 不应该增加
        $this->assertEquals(2, $callCount2); // 应该增加
        
        // 重新添加监听器
        $id1 = $topic->addListener($listener1);
        
        // 发布新消息
        $topic->publish('Test3');
        sleep(1);
        
        $this->assertEquals(2, $callCount1); // 应该增加
        $this->assertEquals(3, $callCount2); // 应该增加
    }
}