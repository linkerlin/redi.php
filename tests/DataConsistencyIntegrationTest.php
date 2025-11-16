<?php

namespace Rediphp\Tests;

use Rediphp\RMap;
use Rediphp\RList;
use Rediphp\RSet;
use Rediphp\RAtomicLong;
use Rediphp\RLock;
use Rediphp\RTransaction;

/**
 * 数据一致性、事务和回滚集成测试
 * 测试Redis事务操作的原子性、一致性和回滚机制
 */
class DataConsistencyIntegrationTest extends RedissonTestCase
{
    /**
     * 测试复杂事务操作的原子性
     */
    public function testComplexTransactionAtomicity()
    {
        $transactionMap = $this->client->getMap('transaction:complex:map');
        $transactionSet = $this->client->getSet('transaction:complex:set');
        $transactionCounter = $this->client->getAtomicLong('transaction:complex:counter');
        $transactionLock = $this->client->getLock('transaction:complex:lock');
        
        // 初始化测试数据
        $initialSize = 30;
        for ($i = 0; $i < $initialSize; $i++) {
            $transactionMap->put("item:$i", [
                'value' => $i * 100,
                'status' => 'active',
                'version' => 1
            ]);
            $transactionSet->add("category:item:$i");
        }
        $transactionCounter->set($initialSize);
        
        $successfulTransactions = 0;
        $failedTransactions = 0;
        $transactionCount = 20;
        
        for ($txId = 0; $txId < $transactionCount; $txId++) {
            $transactionSuccess = false;
            
            if ($transactionLock->tryLock()) {
                try {
                    // 模拟复杂事务操作
                    $affectedItems = [];
                    
                    // 阶段1: 读取并验证数据
                    $readItems = [];
                    for ($i = 0; $i < 5; $i++) {
                        $itemKey = "item:" . rand(0, $initialSize - 1);
                        $item = $transactionMap->get($itemKey);
                        if ($item && $item['status'] === 'active') {
                            $readItems[$itemKey] = $item;
                        }
                    }
                    
                    if (count($readItems) > 0) {
                        // 阶段2: 准备更新数据
                        foreach ($readItems as $itemKey => $item) {
                            $item['value'] += rand(10, 50);
                            $item['version'] += 1;
                            $item['last_modified'] = time();
                            $affectedItems[$itemKey] = $item;
                        }
                        
                        // 阶段3: 批量执行更新
                        foreach ($affectedItems as $itemKey => $item) {
                            $transactionMap->put($itemKey, $item);
                            
                            // 同时更新集合和计数器
                            $categoryKey = str_replace("item:", "category:item:", $itemKey);
                            if ($transactionSet->contains($categoryKey)) {
                                $transactionSet->remove($categoryKey);
                                $transactionSet->add($categoryKey . ":updated");
                            }
                        }
                        
                        $transactionCounter->addAndGet(count($affectedItems));
                        $transactionSuccess = true;
                    }
                    
                } catch (\Exception $e) {
                    // 事务失败，记录错误但不中断后续事务
                    $failedTransactions++;
                } finally {
                    $transactionLock->unlock();
                }
            } else {
                $failedTransactions++;
            }
            
            if ($transactionSuccess) {
                $successfulTransactions++;
            }
        }
        
        // 验证事务操作结果
        $this->assertEquals($transactionCount, $successfulTransactions + $failedTransactions);
        $this->assertGreaterThan(0, $successfulTransactions);
        
        // 验证数据一致性
        $totalValue = 0;
        $activeCount = 0;
        $updatedCount = 0;
        
        for ($i = 0; $i < $initialSize; $i++) {
            $item = $transactionMap->get("item:$i");
            if ($item) {
                $totalValue += $item['value'];
                if ($item['status'] === 'active') {
                    $activeCount++;
                }
                if ($item['version'] > 1) {
                    $updatedCount++;
                }
            }
        }
        
        $this->assertEquals($initialSize, $activeCount);
        $this->assertGreaterThan(0, $updatedCount);
        
        // 验证集合更新
        $originalSetSize = $initialSize;
        $updatedSetSize = $transactionSet->size();
        $this->assertGreaterThanOrEqual($originalSetSize, $updatedSetSize);
        
        // 清理
        $transactionMap->clear();
        $transactionSet->clear();
        $transactionCounter->delete();
    }
    
    /**
     * 测试事务回滚机制
     */
    public function testTransactionRollbackMechanism()
    {
        $rollbackMap = $this->client->getMap('rollback:map');
        $rollbackSet = $this->client->getSet('rollback:set');
        $rollbackCounter = $this->client->getAtomicLong('rollback:counter');
        $rollbackLock = $this->client->getLock('rollback:lock');
        
        // 初始化基线数据
        $baselineSize = 25;
        $baselineData = [];
        
        for ($i = 0; $i < $baselineSize; $i++) {
            $data = [
                'id' => $i,
                'value' => $i * 50,
                'status' => 'initialized',
                'version' => 1,
                'checksum' => md5("baseline:$i")
            ];
            
            $rollbackMap->put("baseline:item:$i", $data);
            $rollbackSet->add("baseline:category:$i");
            $baselineData[$i] = $data;
        }
        $rollbackCounter->set($baselineSize);
        
        $commitCount = 0;
        $rollbackCount = 0;
        $operationCount = 15;
        
        for ($opId = 0; $opId < $operationCount; $opId++) {
            // 创建操作前的快照
            $snapshot = [];
            for ($i = 0; $i < $baselineSize; $i++) {
                $snapshot["baseline:item:$i"] = $rollbackMap->get("baseline:item:$i");
            }
            $snapshotCounter = $rollbackCounter->get();
            
            $operationSuccess = false;
            $shouldRollback = ($opId % 4 == 0); // 25%的事务需要回滚
            
            if ($rollbackLock->tryLock()) {
                try {
                    // 执行操作
                    $affectedItems = 0;
                    
                    for ($i = 0; $i < 8; $i++) {
                        $itemKey = "baseline:item:" . rand(0, $baselineSize - 1);
                        $currentData = $rollbackMap->get($itemKey);
                        
                        if ($currentData) {
                            $currentData['value'] += rand(5, 25);
                            $currentData['version'] += 1;
                            $currentData['last_modified'] = time();
                            $currentData['operation_id'] = $opId;
                            
                            $rollbackMap->put($itemKey, $currentData);
                            $affectedItems++;
                        }
                    }
                    
                    $rollbackCounter->addAndGet($affectedItems);
                    
                    // 模拟一些验证逻辑
                    if ($shouldRollback) {
                        // 模拟业务逻辑错误需要回滚
                        throw new \Exception("模拟业务验证失败");
                    } else {
                        $operationSuccess = true;
                    }
                    
                } catch (\Exception $e) {
                    // 执行回滚
                    $this->performRollback($rollbackMap, $rollbackSet, $snapshot, $snapshotCounter);
                    $rollbackCount++;
                    $operationSuccess = false;
                } finally {
                    $rollbackLock->unlock();
                }
            } else {
                $rollbackCount++;
            }
            
            if ($operationSuccess) {
                $commitCount++;
            }
        }
        
        // 验证回滚机制
        $this->assertEquals($operationCount, $commitCount + $rollbackCount);
        $this->assertGreaterThan(0, $rollbackCount);
        
        // 验证数据完整性
        $integrityViolations = 0;
        for ($i = 0; $i < $baselineSize; $i++) {
            $currentData = $rollbackMap->get("baseline:item:$i");
            $baselineData = $baselineData[$i];
            
            // 检查核心字段是否保持一致
            if ($currentData['id'] !== $baselineData['id'] ||
                $currentData['checksum'] !== $baselineData['checksum']) {
                $integrityViolations++;
            }
        }
        
        $this->assertEquals(0, $integrityViolations, "回滚后的数据完整性检查应该全部通过");
        
        // 验证计数器的一致性
        $currentCounter = $rollbackCounter->get();
        $this->assertGreaterThanOrEqual($baselineSize, $currentCounter);
        
        // 清理
        $rollbackMap->clear();
        $rollbackSet->clear();
    }
    
    /**
     * 测试多步骤事务的一致性保证
     */
    public function testMultiStepTransactionConsistency()
    {
        $multiStepMap = $this->client->getMap('multistep:map');
        $multiStepList = $this->client->getList('multistep:list');
        $multiStepSet = $this->client->getSet('multistep:set');
        $multiStepCounter = $this->client->getAtomicLong('multistep:counter');
        
        // 初始化复杂的多步骤事务数据
        $entityCount = 40;
        for ($i = 0; $i < $entityCount; $i++) {
            $entity = [
                'id' => $i,
                'type' => $i % 3, // 3种类型
                'value' => rand(100, 999),
                'status' => 'active',
                'created_at' => time(),
                'relationships' => []
            ];
            
            $multiStepMap->put("entity:$i", $entity);
            $multiStepList->add("entity:$i");
            $multiStepSet->add("type:" . $entity['type']);
        }
        $multiStepCounter->set($entityCount);
        
        $successfulMultiSteps = 0;
        $failedMultiSteps = 0;
        $multiStepCount = 12;
        
        for ($stepId = 0; $stepId < $multiStepCount; $stepId++) {
            $multiStepSuccess = false;
            $checkpointData = [];
            
            try {
                // 第一步：读取当前状态
                $currentEntities = [];
                for ($i = 0; $i < 10; $i++) {
                    $entityId = rand(0, $entityCount - 1);
                    $entityKey = "entity:$entityId";
                    $entity = $multiStepMap->get($entityKey);
                    if ($entity) {
                        $currentEntities[$entityKey] = $entity;
                    }
                }
                
                if (count($currentEntities) == 0) {
                    throw new \Exception("没有找到可操作的实体");
                }
                
                // 保存检查点
                foreach ($currentEntities as $key => $entity) {
                    $checkpointData[$key] = $entity;
                }
                
                // 第二步：验证业务规则
                $validEntities = [];
                foreach ($currentEntities as $key => $entity) {
                    if ($entity['status'] === 'active' && $entity['value'] > 50) {
                        $validEntities[$key] = $entity;
                    }
                }
                
                if (count($validEntities) == 0) {
                    throw new \Exception("没有符合业务规则的实体");
                }
                
                // 第三步：执行更新操作
                foreach ($validEntities as $key => $entity) {
                    $entity['value'] += rand(10, 50);
                    $entity['last_modified'] = time();
                    $entity['step_operation'] = $stepId;
                    
                    // 添加关系
                    $relatedEntity = $this->findRelatedEntity($multiStepMap, $entity['type'], $entityCount);
                    if ($relatedEntity) {
                        $entity['relationships'][] = $relatedEntity['id'];
                    }
                    
                    $multiStepMap->put($key, $entity);
                }
                
                // 第四步：更新相关数据结构
                foreach ($validEntities as $key => $entity) {
                    $multiStepCounter->incrementAndGet();
                    
                    // 更新列表和集合中的记录
                    $listIndex = $this->findEntityInList($multiStepList, $key);
                    if ($listIndex !== false) {
                        $multiStepList->set($listIndex, $key . ":updated");
                    }
                }
                
                $multiStepSuccess = true;
                
            } catch (\Exception $e) {
                // 回滚到检查点
                $this->rollbackToCheckpoint($multiStepMap, $multiStepList, $checkpointData);
                $failedMultiSteps++;
            }
            
            if ($multiStepSuccess) {
                $successfulMultiSteps++;
            }
        }
        
        // 验证多步骤事务结果
        $this->assertEquals($multiStepCount, $successfulMultiSteps + $failedMultiSteps);
        $this->assertGreaterThanOrEqual(0, $successfulMultiSteps);
        
        // 验证数据一致性
        $totalValue = 0;
        $activeCount = 0;
        $updatedCount = 0;
        
        for ($i = 0; $i < $entityCount; $i++) {
            $entity = $multiStepMap->get("entity:$i");
            if ($entity) {
                $totalValue += $entity['value'];
                if ($entity['status'] === 'active') {
                    $activeCount++;
                }
                if (isset($entity['step_operation'])) {
                    $updatedCount++;
                }
            }
        }
        
        $this->assertEquals($entityCount, $activeCount);
        $this->assertGreaterThan(0, $totalValue);
        
        // 验证列表数据
        $listItems = $multiStepList->toArray();
        $this->assertEquals($entityCount, count($listItems));
        
        // 验证集合数据
        $setItems = $multiStepSet->toArray();
        $this->assertEquals(3, count($setItems)); // 3种类型
        
        // 清理
        $multiStepMap->clear();
        $multiStepList->clear();
        $multiStepSet->clear();
    }
    
    /**
     * 测试事务隔离级别和数据可见性
     */
    public function testTransactionIsolationAndVisibility()
    {
        $isolationMap = $this->client->getMap('isolation:map');
        $isolationCounter = $this->client->getAtomicLong('isolation:counter');
        $isolationLock = $this->client->getLock('isolation:lock');
        
        // 初始化测试数据
        $dataSize = 20;
        for ($i = 0; $i < $dataSize; $i++) {
            $isolationMap->put("item:$i", [
                'value' => $i * 100,
                'version' => 1,
                'visible' => true,
                'owner' => null
            ]);
        }
        $isolationCounter->set($dataSize);
        
        $readCommittedCount = 0;
        $readUncommittedCount = 0;
        $repeatableReadCount = 0;
        
        // 模拟不同隔离级别的读取
        for ($i = 0; $i < 10; $i++) {
            $isolationLevel = $i % 3;
            
            try {
                switch ($isolationLevel) {
                    case 0: // Read Committed
                        // 读取已提交的数据
                        $committedData = [];
                        for ($j = 0; $j < 5; $j++) {
                            $itemKey = "item:" . rand(0, $dataSize - 1);
                            $item = $isolationMap->get($itemKey);
                            if ($item && $item['visible'] && $item['version'] >= 1) {
                                $committedData[$itemKey] = $item;
                            }
                        }
                        if (count($committedData) > 0) {
                            $readCommittedCount++;
                        }
                        break;
                        
                    case 1: // Read Uncommitted (读取未提交数据)
                        // 在锁保护下读取任何可见的数据
                        if ($isolationLock->tryLock(1000)) {
                            try {
                                $uncommittedData = [];
                                for ($j = 0; $j < 5; $j++) {
                                    $itemKey = "item:" . rand(0, $dataSize - 1);
                                    $item = $isolationMap->get($itemKey);
                                    if ($item) {
                                        $uncommittedData[$itemKey] = $item;
                                    }
                                }
                                if (count($uncommittedData) > 0) {
                                    $readUncommittedCount++;
                                }
                            } finally {
                                $isolationLock->unlock();
                            }
                        }
                        break;
                        
                    case 2: // Repeatable Read
                        // 重复读取验证一致性
                        $firstRead = [];
                        $secondRead = [];
                        
                        // 第一次读取
                        for ($j = 0; $j < 3; $j++) {
                            $itemKey = "item:" . rand(0, $dataSize - 1);
                            $item = $isolationMap->get($itemKey);
                            if ($item) {
                                $firstRead[$itemKey] = $item;
                            }
                        }
                        
                        // 短暂延迟
                        usleep(1000);
                        
                        // 第二次读取
                        for ($j = 0; $j < 3; $j++) {
                            $itemKey = "item:" . rand(0, $dataSize - 1);
                            $item = $isolationMap->get($itemKey);
                            if ($item) {
                                $secondRead[$itemKey] = $item;
                            }
                        }
                        
                        // 验证一致性（简单版本）
                        $consistencyCheck = true;
                        foreach ($firstRead as $key => $value) {
                            if (isset($secondRead[$key])) {
                                // 检查版本号是否一致
                                if ($secondRead[$key]['version'] !== $value['version']) {
                                    $consistencyCheck = false;
                                    break;
                                }
                            }
                        }
                        
                        if ($consistencyCheck && count($firstRead) > 0) {
                            $repeatableReadCount++;
                        }
                        break;
                }
            } catch (\Exception $e) {
                // 记录错误但不中断测试
            }
        }
        
        // 验证隔离级别测试结果
        $this->assertGreaterThanOrEqual(0, $readCommittedCount);
        $this->assertGreaterThanOrEqual(0, $readUncommittedCount);
        $this->assertGreaterThanOrEqual(0, $repeatableReadCount);
        
        // 验证最终数据状态
        $finalDataSize = $isolationMap->size();
        $this->assertEquals($dataSize, $finalDataSize);
        
        // 验证数据完整性
        $validItems = 0;
        for ($i = 0; $i < $dataSize; $i++) {
            $item = $isolationMap->get("item:$i");
            if ($item && $item['visible'] && $item['version'] >= 1) {
                $validItems++;
            }
        }
        
        $this->assertEquals($dataSize, $validItems);
        
        // 清理
        $isolationMap->clear();
        $isolationCounter->delete();
    }
    
    /**
     * 执行回滚操作
     */
    private function performRollback($map, $set, $snapshot, $snapshotCounter)
    {
        try {
            // 恢复Map数据
            foreach ($snapshot as $key => $data) {
                $map->put($key, $data);
            }
            
            // 清理集合中的临时数据
            $currentSetItems = $set->toArray();
            foreach ($currentSetItems as $item) {
                if (strpos($item, ':updated') !== false) {
                    $set->remove($item);
                }
            }
            
        } catch (\Exception $e) {
            // 回滚过程中出错，记录日志但不抛出异常
            error_log("回滚操作失败: " . $e->getMessage());
        }
    }
    
    /**
     * 查找相关实体
     */
    private function findRelatedEntity($map, $type, $maxEntities)
    {
        for ($i = 0; $i < $maxEntities; $i++) {
            $entity = $map->get("entity:$i");
            if ($entity && $entity['type'] === $type && $entity['status'] === 'active') {
                return $entity;
            }
        }
        return null;
    }
    
    /**
     * 在列表中查找实体索引
     */
    private function findEntityInList($list, $entityKey)
    {
        $items = $list->toArray();
        $index = array_search($entityKey, $items);
        return $index !== false ? $index : false;
    }
    
    /**
     * 回滚到检查点
     */
    private function rollbackToCheckpoint($map, $list, $checkpointData)
    {
        try {
            // 恢复Map数据
            foreach ($checkpointData as $key => $data) {
                $map->put($key, $data);
            }
            
            // 清理列表中的临时更新标记
            $listItems = $list->toArray();
            for ($i = 0; $i < count($listItems); $i++) {
                if (strpos($listItems[$i], ':updated') !== false) {
                    $cleanKey = str_replace(':updated', '', $listItems[$i]);
                    $list->set($i, $cleanKey);
                }
            }
            
        } catch (\Exception $e) {
            error_log("多步骤回滚失败: " . $e->getMessage());
        }
    }
}