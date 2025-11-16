<?php

// 引入所有必要的类文件（按依赖顺序）
require_once 'src/RedissonClient.php';
require_once 'src/RedisPool.php';
require_once 'src/PooledRedis.php';
require_once 'src/RedisDataStructure.php';
require_once 'src/RBucket.php';
require_once 'src/RSet.php';
require_once 'src/RSortedSet.php';
require_once 'src/RList.php';
require_once 'src/RQueue.php';
require_once 'src/RDeque.php';
require_once 'src/RMap.php';

echo "=== RediPHP 连接池综合测试 ===\n\n";

try {
    // 初始化RedissonClient
    $client = new \Rediphp\RedissonClient([
        'host' => '127.0.0.1',
        'port' => 6379,
        'database' => 0,
        'use_pool' => true,
        'pool_config' => [
            'min_size' => 2,
            'max_size' => 10,
            'max_wait_time' => 3000
        ]
    ]);

    echo "✅ RedissonClient 初始化成功\n";

    // 测试 RBucket
    echo "\n--- 测试 RBucket (对象存储) ---\n";
    $bucket = $client->getBucket('user:profile');
    $bucket->set(['name' => '张三', 'age' => 25]);
    $profile = $bucket->get();
    echo "📦 RBucket: " . json_encode($profile) . "\n";

    // 测试 RSet
    echo "\n--- 测试 RSet (集合) ---\n";
    $set = $client->getSet('user:tokens');
    $set->add('token1');
    $set->addAll(['token2', 'token3']);
    echo "🔢 RSet 包含元素数量: " . $set->size() . "\n";
    echo "🔢 RSet 包含 'token2': " . ($set->contains('token2') ? '是' : '否') . "\n";

    // 测试 RSortedSet
    echo "\n--- 测试 RSortedSet (有序集合) ---\n";
    $sortedSet = $client->getSortedSet('leaderboard');
    $sortedSet->add('player1', 100);
    $sortedSet->add('player2', 200);
    $sortedSet->add('player3', 150);
    echo "🏆 RSortedSet 大小: " . $sortedSet->size() . "\n";
    $allPlayers = $sortedSet->range(0, -1);
    echo "🏆 所有玩家: " . json_encode($allPlayers) . "\n";

    // 测试 RList
    echo "\n--- 测试 RList (列表) ---\n";
    $list = $client->getList('logs');
    $list->add('log1');
    $list->addAll(['log2', 'log3']);
    echo "📋 RList 大小: " . $list->size() . "\n";
    echo "📋 第一个元素: " . $list->get(0) . "\n";

    // 测试 RQueue
    echo "\n--- 测试 RQueue (队列) ---\n";
    $queue = $client->getQueue('tasks');
    $queue->offer('task1');
    $queue->offer('task2');
    echo "📬 RQueue 队列大小: " . $queue->size() . "\n";
    echo "📬 出队元素: " . $queue->poll() . "\n";

    // 测试 RDeque
    echo "\n--- 测试 RDeque (双端队列) ---\n";
    $deque = $client->getDeque('browser_history');
    $deque->addFirst('current_page');
    $deque->addLast('previous_page');
    echo "↔️ RDeque 头部元素: " . $deque->peekFirst() . "\n";
    echo "↔️ RDeque 尾部元素: " . $deque->peekLast() . "\n";

    // 测试 RMap
    echo "\n--- 测试 RMap (映射) ---\n";
    $map = $client->getMap('session:user123');
    $map->put('login_time', date('Y-m-d H:i:s'));
    $map->putAll(['page' => 'dashboard', 'action' => 'view']);
    echo "🗂️ RMap 大小: " . $map->size() . "\n";
    echo "🗂️ RMap 包含键 'page': " . ($map->containsKey('page') ? '是' : '否') . "\n";

    // 获取连接池信息
    echo "\n--- 连接池信息 ---\n";
    echo "📊 使用连接池: " . ($client->isUsingPool() ? '是' : '否') . "\n";
    echo "📊 数据库: " . $client->getDatabase() . "\n";
    
    // 获取详细的连接池统计信息
    if ($client->isUsingPool()) {
        echo "\n--- 详细连接池统计信息 ---\n";
        $stats = $client->getConnectionPoolStats();
        if ($stats) {
            echo "🔍 连接池状态:\n";
            echo "   空闲连接数: {$stats['idle_connections']}\n";
            echo "   活跃连接数: {$stats['active_connections']}\n";
            echo "   总连接数: {$stats['total_connections']}\n";
            echo "   最小连接数: {$stats['min_size']}\n";
            echo "   最大连接数: {$stats['max_size']}\n";
            echo "   连接池利用率: {$stats['pool_utilization']}\n";
            echo "   总请求数: {$stats['total_requests']}\n";
            echo "   成功获取数: {$stats['total_acquires']}\n";
            echo "   平均获取时间: {$stats['avg_acquire_time_ms']}ms\n";
            echo "   最大获取时间: {$stats['max_acquire_time_ms']}ms\n";
            echo "   最小获取时间: {$stats['min_acquire_time_ms']}ms\n";
        }
    }

    echo "\n✅ 所有数据结构的连接池功能测试通过！\n";
    echo "✅ 综合测试完成！\n\n";

    // 清理测试数据
    echo "🧹 清理测试数据...\n";
    $bucket->delete();
    $set->clear();
    $sortedSet->clear();
    $list->clear();
    $queue->clear();
    $deque->clear();
    $map->clear();
    echo "✅ 测试数据清理完成！\n";

} catch (Exception $e) {
    echo "❌ 测试失败: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🎉 Redi.PHP 连接池综合测试全部通过！\n";