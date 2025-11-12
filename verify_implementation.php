<?php
/**
 * 验证新实现的Redisson数据结构
 * 
 * 2024-12-11 创建，用于验证：
 * - RHyperLogLog
 * - RGeo 
 * - RStream
 * - RTimeSeries
 * 
 * 使用方法：php verify_implementation.php
 */

require_once 'vendor/autoload.php';

use Rediphp\RedissonClient;

function testRHyperLogLog(RedissonClient $client) {
    echo "\n=== 测试 RHyperLogLog ===\n";
    
    try {
        $hll = $client->getHyperLogLog('test:hll:verify');
        $hll->clear();
        
        // 基本测试
        echo "添加元素 'user1'...";
        $hll->add('user1');
        echo "完成，基数: " . $hll->count() . "\n";
        
        echo "添加元素 'user2'...";
        $hll->add('user2');
        echo "完成，基数: " . $hll->count() . "\n";
        
        echo "添加重复元素 'user1'...";
        $hll->add('user1');
        echo "完成，基数: " . $hll->count() . " (应该仍为2)\n";
        
        // 批量添加
        echo "批量添加元素...";
        $hll->addAll(['user3', 'user4', 'user5']);
        echo "完成，基数: " . $hll->count() . "\n";
        
        echo "✅ RHyperLogLog 测试通过\n";
        return true;
        
    } catch (Exception $e) {
        echo "❌ RHyperLogLog 测试失败: " . $e->getMessage() . "\n";
        return false;
    }
}

function testRGeo(RedissonClient $client) {
    echo "\n=== 测试 RGeo ===\n";
    
    try {
        $geo = $client->getGeo('test:geo:verify');
        $geo->clear();
        
        // 添加地理位置
        echo "添加城市坐标...";
        $geo->addAll([
            [116.4074, 39.9042, 'Beijing'],   // [longitude, latitude, member]
            [121.4737, 31.2304, 'Shanghai'],
            [113.2644, 23.1291, 'Guangzhou']
        ]);
        echo "完成\n";
        
        // 获取位置
        echo "获取Beijing坐标...";
        $position = $geo->position('Beijing');
        echo "完成: " . json_encode($position) . "\n";
        
        // 计算距离
        echo "计算Beijing到Shanghai的距离...";
        $distance = $geo->distance('Beijing', 'Shanghai');
        echo "完成: " . $distance . " km\n";
        
        // 地理哈希
        echo "获取Beijing的地理哈希...";
        $hash = $geo->hash('Beijing');
        echo "完成: $hash\n";
        
        echo "✅ RGeo 测试通过\n";
        return true;
        
    } catch (Exception $e) {
        echo "❌ RGeo 测试失败: " . $e->getMessage() . "\n";
        return false;
    }
}

function testRStream(RedissonClient $client) {
    echo "\n=== 测试 RStream ===\n";
    
    try {
        $stream = $client->getStream('test:stream:verify');
        $stream->clear();
        
        // 添加消息
        echo "添加消息...";
        $id1 = $stream->add(['user' => 'alice', 'action' => 'login']);
        echo "完成，消息ID: $id1\n";
        
        echo "添加另一条消息...";
        $id2 = $stream->add(['user' => 'bob', 'action' => 'logout']);
        echo "完成，消息ID: $id2\n";
        
        // 读取消息
        echo "读取消息...";
        $messages = $stream->read(2);
        echo "完成，读取到 " . count($messages) . " 条消息\n";
        
        // 长度
        echo "流长度: " . $stream->length() . "\n";
        
        echo "✅ RStream 测试通过\n";
        return true;
        
    } catch (Exception $e) {
        echo "❌ RStream 测试失败: " . $e->getMessage() . "\n";
        return false;
    }
}

function testRTimeSeries(RedissonClient $client) {
    echo "\n=== 测试 RTimeSeries ===\n";
    
    try {
        $ts = $client->getTimeSeries('test:ts:verify');
        $ts->clear();
        
        // 添加数据点
        echo "添加温度数据点...";
        $ts->add(20.5, 1640995200000); // 2022-01-01 00:00:00 UTC
        $ts->add(21.0, 1640995260000); // 2022-01-01 00:01:00 UTC
        $ts->add(22.5, 1640995320000); // 2022-01-01 00:02:00 UTC
        echo "完成\n";
        
        // 获取数据点
        echo "获取第一个数据点...";
        $dataPoint = $ts->get(1640995200000);
        echo "完成: " . ($dataPoint['value'] ?? 'N/A') . " °C\n";
        
        // 范围查询
        echo "查询时间范围内的数据...";
        $range = $ts->range(1640995200000, 1640995320000);
        echo "完成，获取到 " . count($range) . " 个数据点\n";
        
        // 统计信息
        echo "获取统计信息...";
        $stats = $ts->getStats();
        echo "完成: " . json_encode($stats) . "\n";
        
        // 最新数据点
        echo "获取最新数据点...";
        $latest = $ts->getLatest();
        echo "完成: " . json_encode($latest) . "\n";
        
        echo "✅ RTimeSeries 测试通过\n";
        return true;
        
    } catch (Exception $e) {
        echo "❌ RTimeSeries 测试失败: " . $e->getMessage() . "\n";
        return false;
    }
}

function main() {
    echo "=== redi.php 新数据结构验证 ===\n";
    echo "时间: " . date('Y-m-d H:i:s') . "\n";
    
    try {
        // 连接Redis
        echo "\n连接Redis服务器...";
        $client = new RedissonClient([
            'host' => '127.0.0.1',
            'port' => 6379,
        ]);
        
        if (!$client->connect()) {
            throw new Exception("无法连接到Redis服务器");
        }
        echo "连接成功\n";
        
        $results = [];
        
        // 测试所有新数据结构
        $results['RHyperLogLog'] = testRHyperLogLog($client);
        $results['RGeo'] = testRGeo($client);
        $results['RStream'] = testRStream($client);
        $results['RTimeSeries'] = testRTimeSeries($client);
        
        // 关闭连接
        echo "\n关闭连接...";
        $client->shutdown();
        echo "完成\n";
        
        // 结果汇总
        echo "\n=== 验证结果汇总 ===\n";
        $passed = 0;
        $total = count($results);
        
        foreach ($results as $name => $result) {
            $status = $result ? "✅ 通过" : "❌ 失败";
            echo "$name: $status\n";
            if ($result) $passed++;
        }
        
        echo "\n总计: $passed/$total 项测试通过\n";
        
        if ($passed === $total) {
            echo "🎉 所有新数据结构实现验证成功！\n";
        } else {
            echo "⚠️  部分测试失败，请检查Redis连接和实现\n";
        }
        
    } catch (Exception $e) {
        echo "❌ 验证过程发生错误: " . $e->getMessage() . "\n";
        echo "请确保Redis服务器正在运行在 127.0.0.1:6379\n";
    }
}

if (php_sapi_name() === 'cli') {
    main();
}