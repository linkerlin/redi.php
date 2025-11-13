<?php

/**
 * 测试IDE是否正确识别Redis类
 * 如果IDE不再显示"Undefined type 'Redis'"错误，说明配置成功
 */

// 尝试创建Redis实例
$redis = new Redis();

// 尝试调用Redis方法
$redis->connect('127.0.0.1', 6379);
$redis->set('test_key', 'test_value');
$value = $redis->get('test_key');

// 尝试使用Redis常量
$mode = Redis::MULTI;

// 尝试使用Redis发布订阅功能
$redis->publish('test_channel', 'test_message');
$redis->subscribe(['test_channel'], function ($redis, $channel, $message) {
    echo "Received message: $message\n";
});

// 尝试使用Redis事务
$redis->multi();
$redis->set('key1', 'value1');
$redis->set('key2', 'value2');
$redis->exec();

// 尝试使用Redis哈希操作
$redis->hSet('hash_key', 'field1', 'value1');
$hashValue = $redis->hGet('hash_key', 'field1');

// 尝试使用Redis列表操作
$redis->lPush('list_key', 'item1', 'item2');
$listItem = $redis->lPop('list_key');

// 尝试使用Redis集合操作
$redis->sAdd('set_key', 'member1', 'member2');
$members = $redis->sMembers('set_key');

// 尝试使用Redis有序集合操作
$redis->zAdd('zset_key', 1, 'member1');
$zsetMembers = $redis->zRange('zset_key', 0, -1);

// 尝试使用Redis位操作
$redis->setBit('bit_key', 0, 1);
$bitValue = $redis->getBit('bit_key', 0);

// 尝试使用Redis地理位置操作
$redis->geoAdd('geo_key', 13.361389, 38.115556, 'Palermo');
$geoPos = $redis->geoPos('geo_key', 'Palermo');

// 尝试使用Redis流操作
$redis->xAdd('stream_key', '*', ['field1' => 'value1']);
$streamMessages = $redis->xRange('stream_key', '-', '+');

// 关闭连接
$redis->close();

echo "Redis类识别测试完成\n";