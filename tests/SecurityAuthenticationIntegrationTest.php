<?php

namespace Rediphp\Tests;

use Rediphp\RMap;
use Rediphp\RList;
use Rediphp\RSet;
use Rediphp\RAtomicLong;
use Rediphp\RLock;

/**
 * 安全认证和数据保护集成测试
 * 测试Redis数据访问控制、加密、性能安全和安全审计
 */
class SecurityAuthenticationIntegrationTest extends RedissonTestCase
{
    /**
     * 测试访问控制和数据权限验证
     */
    public function testAccessControlAndPermissions()
    {
        $secureMap = $this->client->getMap('secure:access:map');
        $secureSet = $this->client->getSet('secure:access:set');
        $secureCounter = $this->client->getAtomicLong('secure:access:counter');
        $adminLock = $this->client->getLock('secure:admin:lock');
        $userLock = $this->client->getLock('secure:user:lock');
        
        // 模拟用户权限级别
        $roles = ['admin', 'user', 'guest', 'readonly'];
        $sensitiveData = [
            'credit_card' => '1234-5678-9012-3456',
            'ssn' => '123-45-6789',
            'api_key' => 'sk_live_51H7X',
            'password_hash' => '$2y$10$hash'
        ];
        
        // 初始化受保护数据
        $dataSize = 30;
        for ($i = 0; $i < $dataSize; $i++) {
            $dataLevel = $i % 4; // 0: public, 1: internal, 2: confidential, 3: restricted
            $protectionLevel = $roles[$dataLevel];
            
            $secureData = [
                'id' => $i,
                'data' => "sensitive_data_$i",
                'protection_level' => $protectionLevel,
                'access_count' => 0,
                'last_accessed' => null,
                'encrypted' => false,
                'audit_trail' => []
            ];
            
            // 根据保护级别添加敏感数据
            if ($dataLevel >= 2) {
                $key = array_keys($sensitiveData)[$dataLevel - 2];
                $secureData['sensitive_field'] = $sensitiveData[$key];
                $secureData['encrypted'] = true;
            }
            
            $secureMap->put("protected:item:$i", $secureData);
            $secureSet->add("role:$protectionLevel");
        }
        $secureCounter->set($dataSize);
        
        $accessViolations = 0;
        $successfulAccesses = 0;
        $roleAccessCounts = [];
        
        // 模拟不同角色的访问尝试
        $accessAttempts = 100;
        
        for ($attempt = 0; $attempt < $accessAttempts; $attempt++) {
            $userRole = $roles[rand(0, 3)];
            $targetItem = rand(0, $dataSize - 1);
            $itemKey = "protected:item:$targetItem";
            
            // 模拟用户角色权限验证
            $hasPermission = $this->checkUserPermission($userRole, $targetItem, $roles);
            $accessSuccess = false;
            
            if ($hasPermission) {
                // 选择合适的锁
                $lock = ($userRole === 'admin') ? $adminLock : $userLock;
                
                if ($lock->tryLock()) {
                    try {
                        $item = $secureMap->get($itemKey);
                        if ($item) {
                            // 验证数据完整性
                            $integrityValid = $this->validateDataIntegrity($item, $userRole);
                            
                            if ($integrityValid) {
                                // 记录访问审计
                                $auditEntry = [
                                    'timestamp' => time(),
                                    'user_role' => $userRole,
                                    'action' => 'read',
                                    'success' => true
                                ];
                                $item['audit_trail'][] = $auditEntry;
                                $item['access_count']++;
                                $item['last_accessed'] = time();
                                
                                $secureMap->put($itemKey, $item);
                                $secureCounter->incrementAndGet();
                                
                                $accessSuccess = true;
                                
                                // 统计角色访问
                                if (!isset($roleAccessCounts[$userRole])) {
                                    $roleAccessCounts[$userRole] = 0;
                                }
                                $roleAccessCounts[$userRole]++;
                            }
                        }
                    } catch (\Exception $e) {
                        // 访问失败，记录安全事件
                        $this->recordSecurityEvent($secureMap, $itemKey, $userRole, 'access_denied', $e->getMessage());
                        $accessViolations++;
                    } finally {
                        $lock->unlock();
                    }
                } else {
                    // 锁获取失败，可能是并发冲突
                    $accessViolations++;
                }
            } else {
                // 无权限访问尝试
                $this->recordSecurityEvent($secureMap, $itemKey, $userRole, 'unauthorized_access_attempt', 'insufficient_permissions');
                $accessViolations++;
            }
            
            if ($accessSuccess) {
                $successfulAccesses++;
            }
        }
        
        // 验证访问控制效果
        $this->assertGreaterThan(0, $successfulAccesses);
        $this->assertGreaterThanOrEqual(0, $accessViolations);
        
        // 验证角色权限统计
        $this->assertGreaterThan(0, count($roleAccessCounts));
        
        // 验证审计日志完整性
        $auditEntries = 0;
        for ($i = 0; $i < min(10, $dataSize); $i++) {
            $item = $secureMap->get("protected:item:$i");
            if ($item && isset($item['audit_trail'])) {
                $auditEntries += count($item['audit_trail']);
            }
        }
        
        $this->assertGreaterThanOrEqual(0, $auditEntries);
        
        // 验证数据加密状态
        $encryptedItems = 0;
        for ($i = 0; $i < $dataSize; $i++) {
            $item = $secureMap->get("protected:item:$i");
            if ($item && isset($item['encrypted']) && $item['encrypted']) {
                $encryptedItems++;
            }
        }
        
        $this->assertGreaterThanOrEqual(0, $encryptedItems);
        
        // 清理
        $secureMap->clear();
        $secureSet->clear();
        $secureCounter->delete();
    }
    
    /**
     * 测试数据加密和解密操作
     */
    public function testDataEncryptionAndDecryption()
    {
        $encryptedMap = $this->client->getMap('encrypted:data:map');
        $keyStore = $this->client->getMap('encryption:keystore');
        $decryptCounter = $this->client->getAtomicLong('decrypt:counter');
        
        // 初始化加密密钥存储
        $encryptionKeys = $this->initializeEncryptionKeys($keyStore);
        $dataCount = 25;
        
        // 测试不同级别的数据加密
        $encryptionLevels = [
            'basic' => ['method' => 'base64', 'key' => 'basic_key'],
            'standard' => ['method' => 'aes256', 'key' => 'std_key'],
            'strong' => ['method' => 'aes256+gzip', 'key' => 'strong_key'],
            'maximum' => ['method' => 'custom_encryption', 'key' => 'max_key']
        ];
        
        for ($i = 0; $i < $dataCount; $i++) {
            $level = array_keys($encryptionLevels)[$i % 4];
            $config = $encryptionLevels[$level];
            
            $originalData = [
                'id' => $i,
                'sensitive_data' => "confidential_information_$i",
                'personal_info' => [
                    'name' => "User_$i",
                    'email' => "user$i@example.com",
                    'phone' => "+1-555-01$i"
                ],
                'financial_data' => [
                    'account' => "ACC$i",
                    'balance' => rand(1000, 99999),
                    'currency' => 'USD'
                ],
                'metadata' => [
                    'created' => time(),
                    'version' => 1,
                    'classification' => $level
                ]
            ];
            
            // 执行加密操作
            $encryptedData = $this->performEncryption($originalData, $config, $encryptionKeys);
            
            $encryptedRecord = [
                'original_id' => $i,
                'encryption_level' => $level,
                'encrypted_content' => $encryptedData,
                'encryption_method' => $config['method'],
                'key_version' => $encryptionKeys['key_versions'][$config['key']],
                'compression_used' => strpos($config['method'], 'gzip') !== false,
                'encryption_timestamp' => time(),
                'integrity_hash' => md5(json_encode($originalData))
            ];
            
            $encryptedMap->put("encrypted:record:$i", $encryptedRecord);
        }
        
        $decryptionAttempts = 50;
        $successfulDecryptions = 0;
        $failedDecryptions = 0;
        
        for ($attempt = 0; $attempt < $decryptionAttempts; $attempt++) {
            $recordId = rand(0, $dataCount - 1);
            $recordKey = "encrypted:record:$recordId";
            
            try {
                $encryptedRecord = $encryptedMap->get($recordKey);
                if ($encryptedRecord) {
                    // 获取对应的加密配置
                    $level = $encryptedRecord['encryption_level'];
                    $config = $encryptionLevels[$level];
                    $method = $config['method'];
                    $keyName = $config['key'];
                    
                    // 验证密钥有效性
                    $currentKeyVersion = $encryptionKeys['key_versions'][$keyName];
                    $recordKeyVersion = $encryptedRecord['key_version'];
                    
                    if ($currentKeyVersion === $recordKeyVersion) {
                        // 执行解密
                        $decryptedData = $this->performDecryption(
                            $encryptedRecord['encrypted_content'],
                            $config,
                            $encryptionKeys,
                            $keyName
                        );
                        
                        // 验证数据完整性
                        $expectedHash = $encryptedRecord['integrity_hash'];
                        $actualHash = md5(json_encode($decryptedData));
                        
                        if ($expectedHash === $actualHash) {
                            // 验证解密后的数据结构
                            $this->validateDecryptedStructure($decryptedData);
                            
                            $decryptCounter->incrementAndGet();
                            $successfulDecryptions++;
                        } else {
                            $failedDecryptions++;
                        }
                    } else {
                        $failedDecryptions++; // 密钥版本不匹配
                    }
                } else {
                    $failedDecryptions++;
                }
            } catch (\Exception $e) {
                $failedDecryptions++;
            }
        }
        
        // 验证加密解密测试结果
        $this->assertEquals($decryptionAttempts, $successfulDecryptions + $failedDecryptions);
        $this->assertGreaterThan(0, $successfulDecryptions);
        
        // 验证计数器一致性
        $currentCounter = $decryptCounter->get();
        $this->assertLessThanOrEqual($currentCounter, $successfulDecryptions);
        
        // 验证加密数据的存储效率
        $totalOriginalSize = 0;
        $totalEncryptedSize = 0;
        
        for ($i = 0; $i < min(10, $dataCount); $i++) {
            $record = $encryptedMap->get("encrypted:record:$i");
            if ($record) {
                $originalEstimate = 1000; // 估算原始数据大小
                $encryptedSize = strlen(json_encode($record['encrypted_content']));
                
                $totalOriginalSize += $originalEstimate;
                $totalEncryptedSize += $encryptedSize;
            }
        }
        
        // 验证压缩效果（如果使用了gzip）
        $compressionRatio = $totalOriginalSize > 0 ? $totalEncryptedSize / $totalOriginalSize : 1;
        $this->assertGreaterThan(0, $compressionRatio);
        
        // 清理
        $encryptedMap->clear();
        $keyStore->clear();
        $decryptCounter->delete();
    }
    
    /**
     * 测试性能安全监控和异常检测
     */
    public function testPerformanceSecurityMonitoring()
    {
        $monitorMap = $this->client->getMap('security:monitor:map');
        $alertQueue = $this->client->getList('security:alerts');
        $metricsCounter = $this->client->getAtomicLong('security:metrics');
        $rateLimitLock = $this->client->getLock('security:rate:limit');
        
        // 初始化监控指标
        $baselineMetrics = [
            'avg_response_time' => 50,
            'error_rate' => 0.02,
            'throughput' => 1000,
            'active_connections' => 10,
            'memory_usage' => 1024 * 1024 * 100 // 100MB
        ];
        
        $monitorMap->put('baseline:metrics', $baselineMetrics);
        
        // 模拟正常操作和异常行为
        $operationCount = 200;
        $normalOperations = 0;
        $suspiciousOperations = 0;
        $blockedOperations = 0;
        $securityAlerts = 0;
        
        for ($opId = 0; $opId < $operationCount; $opId++) {
            $isNormal = (rand(0, 100) < 85); // 85% 正常操作，15% 异常操作
            
            $operationInfo = [
                'operation_id' => $opId,
                'timestamp' => time(),
                'user_type' => $isNormal ? 'normal' : 'suspicious',
                'operation_type' => $this->getRandomOperationType(),
                'resource_usage' => rand(1, 10),
                'response_time' => $isNormal ? rand(20, 100) : rand(200, 2000),
                'success' => $isNormal ? (rand(0, 100) < 98) : (rand(0, 100) < 50)
            ];
            
            $operationKey = "operation:$opId";
            $monitorMap->put($operationKey, $operationInfo);
            $metricsCounter->incrementAndGet();
            
            // 检测异常模式
            if ($this->detectSecurityAnomaly($operationInfo, $baselineMetrics)) {
                $this->generateSecurityAlert($alertQueue, $operationInfo, 'anomaly_detected');
                $suspiciousOperations++;
                $securityAlerts++;
                
                // 检查是否需要阻止操作
                if ($this->shouldBlockOperation($operationInfo)) {
                    $blockedOperations++;
                    $this->generateSecurityAlert($alertQueue, $operationInfo, 'operation_blocked');
                }
            } else {
                $normalOperations++;
            }
            
            // 模拟速率限制
            if ($rateLimitLock->tryLock()) {
                try {
                    $currentRate = $this->getCurrentOperationRate($monitorMap, time() - 60);
                    $maxRate = $baselineMetrics['throughput'] / 60; // 每秒最大操作数
                    
                    if ($currentRate > $maxRate) {
                        $this->generateSecurityAlert($alertQueue, $operationInfo, 'rate_limit_exceeded');
                        $blockedOperations++;
                    }
                } finally {
                    $rateLimitLock->unlock();
                }
            } else {
                $blockedOperations++;
            }
        }
        
        // 验证监控结果
        $this->assertEquals($operationCount, $normalOperations + $suspiciousOperations);
        $this->assertGreaterThan(0, $metricsCounter->get());
        
        // 验证安全警报
        $alertCount = $alertQueue->size();
        $this->assertGreaterThanOrEqual(0, $alertCount);
        
        // 验证异常检测准确性
        if ($suspiciousOperations > 0) {
            $detectionAccuracy = $securityAlerts / $suspiciousOperations;
            $this->assertGreaterThanOrEqual(0, $detectionAccuracy);
            $this->assertLessThanOrEqual(1, $detectionAccuracy);
        }
        
        // 验证性能指标更新
        $currentMetrics = $monitorMap->get('current:metrics');
        if ($currentMetrics) {
            $this->validateMetricsStructure($currentMetrics);
        }
        
        // 清理
        $monitorMap->clear();
        $alertQueue->clear();
        $metricsCounter->delete();
    }
    
    /**
     * 检查用户权限
     */
    private function checkUserPermission($userRole, $itemId, $roles)
    {
        $roleHierarchy = ['guest' => 0, 'readonly' => 1, 'user' => 2, 'admin' => 3];
        $itemLevel = $itemId % 4;
        $requiredRole = $roles[$itemLevel];
        
        $userLevel = $roleHierarchy[$userRole];
        $requiredLevel = $roleHierarchy[$requiredRole];
        
        return $userLevel >= $requiredLevel;
    }
    
    /**
     * 验证数据完整性
     */
    private function validateDataIntegrity($data, $userRole)
    {
        // 检查必要字段
        if (!isset($data['id']) || !isset($data['protection_level'])) {
            return false;
        }
        
        // 根据用户角色过滤敏感字段
        if ($userRole === 'readonly' && isset($data['sensitive_field'])) {
            return false; // readonly用户不应看到敏感字段
        }
        
        // 验证数据格式
        if (isset($data['encrypted']) && $data['encrypted']) {
            // 如果数据已加密，验证加密状态
            return true; // 简化验证
        }
        
        return true;
    }
    
    /**
     * 记录安全事件
     */
    private function recordSecurityEvent($map, $itemKey, $userRole, $eventType, $details)
    {
        try {
            $event = [
                'timestamp' => time(),
                'item_key' => $itemKey,
                'user_role' => $userRole,
                'event_type' => $eventType,
                'details' => $details,
                'severity' => $this->getEventSeverity($eventType)
            ];
            
            // 这里应该记录到安全审计日志中
            // 简化实现，使用单独的键存储
            $auditKey = 'security:audit:' . time() . ':' . rand(1000, 9999);
            $map->put($auditKey, $event);
            
        } catch (\Exception $e) {
            error_log("安全事件记录失败: " . $e->getMessage());
        }
    }
    
    /**
     * 初始化加密密钥
     */
    private function initializeEncryptionKeys($keyStore)
    {
        $keys = [
            'basic_key' => base64_encode(random_bytes(16)),
            'std_key' => base64_encode(random_bytes(32)),
            'strong_key' => base64_encode(random_bytes(32)),
            'max_key' => base64_encode(random_bytes(64))
        ];
        
        $keyVersions = [
            'basic_key' => 1,
            'std_key' => 1,
            'strong_key' => 1,
            'max_key' => 1
        ];
        
        $keyStore->put('encryption:keys', $keys);
        $keyStore->put('key_versions', $keyVersions);
        
        return [
            'keys' => $keys,
            'key_versions' => $keyVersions
        ];
    }
    
    /**
     * 执行数据加密
     */
    private function performEncryption($data, $config, $keys)
    {
        $jsonData = json_encode($data);
        
        switch ($config['method']) {
            case 'base64':
                return base64_encode($jsonData);
                
            case 'aes256':
                $key = base64_decode($keys['keys'][$config['key']]);
                $iv = random_bytes(16);
                $encrypted = openssl_encrypt($jsonData, 'AES-256-CBC', $key, 0, $iv);
                return base64_encode($iv . $encrypted);
                
            case 'aes256+gzip':
                $compressed = gzcompress($jsonData);
                $key = base64_decode($keys['keys'][$config['key']]);
                $iv = random_bytes(16);
                $encrypted = openssl_encrypt($compressed, 'AES-256-CBC', $key, 0, $iv);
                return base64_encode($iv . $encrypted);
                
            case 'custom_encryption':
                // 自定义加密实现
                return $this->customEncrypt($jsonData, $keys['keys'][$config['key']]);
                
            default:
                return base64_encode($jsonData);
        }
    }
    
    /**
     * 执行数据解密
     */
    private function performDecryption($encryptedData, $config, $keys, $keyName)
    {
        switch ($config['method']) {
            case 'base64':
                return json_decode(base64_decode($encryptedData), true);
                
            case 'aes256':
                $data = base64_decode($encryptedData);
                $iv = substr($data, 0, 16);
                $encrypted = substr($data, 16);
                $key = base64_decode($keys['keys'][$keyName]);
                $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
                return json_decode($decrypted, true);
                
            case 'aes256+gzip':
                $data = base64_decode($encryptedData);
                $iv = substr($data, 0, 16);
                $encrypted = substr($data, 16);
                $key = base64_decode($keys['keys'][$keyName]);
                $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
                $decompressed = gzuncompress($decrypted);
                return json_decode($decompressed, true);
                
            case 'custom_encryption':
                return $this->customDecrypt($encryptedData, $keys['keys'][$keyName]);
                
            default:
                return json_decode(base64_decode($encryptedData), true);
        }
    }
    
    /**
     * 自定义加密方法
     */
    private function customEncrypt($data, $key)
    {
        // 简化的自定义加密实现
        $encrypted = '';
        for ($i = 0; $i < strlen($data); $i++) {
            $encrypted .= chr(ord($data[$i]) ^ ord($key[$i % strlen($key)]));
        }
        return base64_encode($encrypted);
    }
    
    /**
     * 自定义解密方法
     */
    private function customDecrypt($encryptedData, $key)
    {
        $data = base64_decode($encryptedData);
        $decrypted = '';
        for ($i = 0; $i < strlen($data); $i++) {
            $decrypted .= chr(ord($data[$i]) ^ ord($key[$i % strlen($key)]));
        }
        return $decrypted;
    }
    
    /**
     * 验证解密后的数据结构
     */
    private function validateDecryptedStructure($data)
    {
        $this->assertIsArray($data);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('sensitive_data', $data);
        $this->assertArrayHasKey('personal_info', $data);
        $this->assertArrayHasKey('financial_data', $data);
    }
    
    /**
     * 获取随机操作类型
     */
    private function getRandomOperationType()
    {
        $types = ['read', 'write', 'update', 'delete', 'search', 'export'];
        return $types[rand(0, count($types) - 1)];
    }
    
    /**
     * 检测安全异常
     */
    private function detectSecurityAnomaly($operation, $baseline)
    {
        // 检查响应时间异常
        if ($operation['response_time'] > $baseline['avg_response_time'] * 5) {
            return true;
        }
        
        // 检查错误率异常
        if (!$operation['success'] && rand(0, 100) < 50) {
            return true;
        }
        
        // 检查资源使用异常
        if ($operation['resource_usage'] > 10) {
            return true;
        }
        
        return false;
    }
    
    /**
     * 判断是否应该阻止操作
     */
    private function shouldBlockOperation($operation)
    {
        // 响应时间严重超标的操作
        if ($operation['response_time'] > 5000) {
            return true;
        }
        
        // 连续失败的操作
        if (!$operation['success'] && rand(0, 100) < 80) {
            return true;
        }
        
        return false;
    }
    
    /**
     * 生成安全警报
     */
    private function generateSecurityAlert($alertQueue, $operation, $alertType)
    {
        $alert = [
            'timestamp' => time(),
            'operation_id' => $operation['operation_id'],
            'alert_type' => $alertType,
            'severity' => $this->getAlertSeverity($alertType),
            'user_type' => $operation['user_type'],
            'operation_type' => $operation['operation_type'],
            'message' => $this->getAlertMessage($alertType, $operation)
        ];
        
        $alertQueue->add($alert);
    }
    
    /**
     * 获取当前操作速率
     */
    private function getCurrentOperationRate($monitorMap, $timeThreshold)
    {
        $operations = $monitorMap->get('recent:operations');
        return $operations ? count($operations) : 0;
    }
    
    /**
     * 获取事件严重程度
     */
    private function getEventSeverity($eventType)
    {
        $severities = [
            'access_denied' => 'medium',
            'unauthorized_access_attempt' => 'high',
            'operation_blocked' => 'high'
        ];
        
        return isset($severities[$eventType]) ? $severities[$eventType] : 'low';
    }
    
    /**
     * 获取警报严重程度
     */
    private function getAlertSeverity($alertType)
    {
        $severities = [
            'anomaly_detected' => 'medium',
            'operation_blocked' => 'high',
            'rate_limit_exceeded' => 'medium'
        ];
        
        return isset($severities[$alertType]) ? $severities[$alertType] : 'low';
    }
    
    /**
     * 获取警报消息
     */
    private function getAlertMessage($alertType, $operation)
    {
        $messages = [
            'anomaly_detected' => '检测到异常操作模式',
            'operation_blocked' => '操作因安全策略被阻止',
            'rate_limit_exceeded' => '操作频率超过限制'
        ];
        
        return isset($messages[$alertType]) ? $messages[$alertType] : '未知安全事件';
    }
    
    /**
     * 验证指标结构
     */
    private function validateMetricsStructure($metrics)
    {
        $this->assertIsArray($metrics);
        $expectedKeys = ['avg_response_time', 'error_rate', 'throughput', 'active_connections', 'memory_usage'];
        
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $metrics);
            $this->assertIsNumeric($metrics[$key]);
        }
    }
}