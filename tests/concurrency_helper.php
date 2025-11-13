<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Rediphp\RedissonClient;

// 获取参数
$paramsStr = $argv[1] ?? '';
$params = json_decode(base64_decode($paramsStr), true);

if (!$params) {
    exit(1);
}

try {
    // 创建客户端
    $client = new RedissonClient(['host' => '127.0.0.1', 'port' => 6379]);
    $client->connect();
    
    $type = $params['type'];
    $name = $params['name'];
    $processId = $params['process_id'];
    $iterations = $params['iterations'];
    
    switch ($type) {
        case 'map_write':
            $map = $client->getMap($name);
            for ($i = 0; $i < $iterations; $i++) {
                $key = "process_{$processId}_key_{$i}";
                $value = "value_from_process_{$processId}_iteration_{$i}";
                $map->put($key, $value);
            }
            break;
            
        case 'map_read':
            $map = $client->getMap($name);
            for ($i = 0; $i < $iterations; $i++) {
                $map->size();
                $map->values();
            }
            break;
            
        case 'list_push':
            $list = $client->getList($name);
            for ($i = 0; $i < $iterations; $i++) {
                $list->add("process_{$processId}_element_{$i}");
            }
            break;
            
        case 'set_add':
            $set = $client->getSet($name);
            $overlap = $params['overlap'] ?? false;
            for ($i = 0; $i < $iterations; $i++) {
                if ($overlap) {
                    // 有重叠的元素
                    $element = "shared_element_" . ($i % 50);
                } else {
                    $element = "process_{$processId}_element_{$i}";
                }
                $set->add($element);
            }
            break;
            
        case 'sortedset_add':
            $sortedSet = $client->getSortedSet($name);
            for ($i = 0; $i < $iterations; $i++) {
                $sortedSet->add("process_{$processId}_element_{$i}", $processId * 100 + $i);
            }
            break;
            
        case 'queue_produce':
            $queue = $client->getQueue($name);
            for ($i = 0; $i < $iterations; $i++) {
                $queue->add("process_{$processId}_message_{$i}");
            }
            break;
            
        case 'queue_consume':
            $queue = $client->getQueue($name);
            for ($i = 0; $i < $iterations; $i++) {
                $queue->poll();
            }
            break;
            
        case 'lock_compete':
            $lock = $client->getLock($params['lock_name']);
            $counter = $client->getAtomicLong($params['counter_name']);
            for ($i = 0; $i < $iterations; $i++) {
                if ($lock->tryLock()) {
                    try {
                        $current = $counter->get();
                        $counter->set($current + 1);
                    } finally {
                        $lock->unlock();
                    }
                }
            }
            break;
            
        case 'rwlock_read':
            $rwLock = $client->getReadWriteLock($params['lock_name']);
            $counter = $client->getAtomicLong($params['counter_name']);
            for ($i = 0; $i < $iterations; $i++) {
                if ($rwLock->readLock()->tryLock()) {
                    try {
                        $counter->incrementAndGet();
                        usleep(1000); // 模拟读操作
                    } finally {
                        $rwLock->readLock()->unlock();
                    }
                }
            }
            break;
            
        case 'rwlock_write':
            $rwLock = $client->getReadWriteLock($params['lock_name']);
            $counter = $client->getAtomicLong($params['counter_name']);
            for ($i = 0; $i < $iterations; $i++) {
                if ($rwLock->writeLock()->tryLock()) {
                    try {
                        $counter->incrementAndGet();
                        usleep(5000); // 模拟写操作
                    } finally {
                        $rwLock->writeLock()->unlock();
                    }
                }
            }
            break;
            
        case 'atomic_increment':
            $atomic = $client->getAtomicLong($name);
            for ($i = 0; $i < $iterations; $i++) {
                $atomic->incrementAndGet();
            }
            break;
            
        case 'mixed_operations':
            $map = $client->getMap($params['map_name']);
            $list = $client->getList($params['list_name']);
            $set = $client->getSet($params['set_name']);
            
            for ($i = 0; $i < $iterations; $i++) {
                // 随机选择一种操作
                $operation = rand(0, 2);
                switch ($operation) {
                    case 0:
                        $map->put("key_{$i}", "value_{$i}");
                        break;
                    case 1:
                        $list->add("element_{$i}");
                        break;
                    case 2:
                        $set->add("set_element_{$i}");
                        break;
                }
            }
            break;
    }
    
    exit(0);
} catch (Exception $e) {
    file_put_contents('/tmp/concurrency_test_error.log', $e->getMessage() . "\n", FILE_APPEND);
    exit(1);
}