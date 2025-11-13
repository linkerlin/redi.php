<?php

/**
 * 测试文件：验证IDE是否正确识别Redis类
 * 如果此文件中的Redis类没有红色报错，说明IDE配置成功
 */

// 直接使用Redis类
$redis = new Redis();

// 测试连接
$redis->connect('127.0.0.1', 6379);

// 测试基本操作
$redis->set('test_key', 'test_value');
$value = $redis->get('test_key');

// 测试哈希操作
$redis->hSet('test_hash', 'field1', 'value1');
$hashValue = $redis->hGet('test_hash', 'field1');

// 测试列表操作
$redis->lPush('test_list', 'item1', 'item2');
$listItem = $redis->lPop('test_list');

// 测试集合操作
$redis->sAdd('test_set', 'member1', 'member2');
$isMember = $redis->sIsMember('test_set', 'member1');

// 测试发布订阅
$redis->publish('test_channel', 'test_message');
$subscribers = $redis->pubsub('numsub', 'test_channel');

// 测试事务
$redis->multi();
$redis->set('key1', 'value1');
$redis->set('key2', 'value2');
$results = $redis->exec();

// 测试脚本
$script = "return redis.call('get', KEYS[1])";
$scriptResult = $redis->eval($script, ['test_key'], 1);

// 测试常量
$serializer = Redis::SERIALIZER_JSON;
$mode = Redis::MULTI;

echo "Redis类测试完成，如果IDE没有报错，说明配置成功！\n";