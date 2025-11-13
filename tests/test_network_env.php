<?php

// 测试网络环境
require_once 'vendor/autoload.php';

echo "=== 网络环境测试 ===\n";

// 测试1: 检查本地回环地址连接
echo "\n=== 测试1: 本地回环地址连接 ===\n";
$redis = new Redis();

try {
    echo "尝试连接 127.0.0.1:6379...\n";
    $connected = $redis->connect('127.0.0.1', 6379, 2);
    if ($connected) {
        echo "✓ 127.0.0.1 连接成功\n";
        echo "  Ping: " . $redis->ping() . "\n";
    } else {
        echo "✗ 127.0.0.1 连接失败\n";
        echo "  错误: " . $redis->getLastError() . "\n";
    }
    $redis->close();
} catch (Exception $e) {
    echo "✗ 127.0.0.1 连接异常: " . $e->getMessage() . "\n";
}

// 测试2: 检查localhost连接
echo "\n=== 测试2: localhost连接 ===\n";
$redis2 = new Redis();
try {
    echo "尝试连接 localhost:6379...\n";
    $connected = $redis2->connect('localhost', 6379, 2);
    if ($connected) {
        echo "✓ localhost 连接成功\n";
        echo "  Ping: " . $redis2->ping() . "\n";
    } else {
        echo "✗ localhost 连接失败\n";
        echo "  错误: " . $redis2->getLastError() . "\n";
    }
    $redis2->close();
} catch (Exception $e) {
    echo "✗ localhost 连接异常: " . $e->getMessage() . "\n";
}

// 测试3: 检查DNS解析
echo "\n=== 测试3: DNS解析测试 ===\n";
$hosts = ['127.0.0.1', 'localhost', '0.0.0.0'];
foreach ($hosts as $host) {
    $ip = gethostbyname($host);
    echo "  $host -> $ip\n";
}

// 测试4: 检查网络接口
echo "\n=== 测试4: 网络接口检查 ===\n";
$interfaces = @net_get_interfaces();
if ($interfaces) {
    foreach ($interfaces as $name => $interface) {
        echo "  接口: $name\n";
        if (isset($interface['unicast'])) {
            foreach ($interface['unicast'] as $addr) {
                if (isset($addr['address'])) {
                    echo "    IP: " . $addr['address'] . "\n";
                }
            }
        }
    }
} else {
    echo "  无法获取网络接口信息\n";
}

// 测试5: 检查防火墙状态
echo "\n=== 测试5: 防火墙检查 ===\n";
$output = [];
$return = 0;
@exec('sudo pfctl -s info 2>/dev/null', $output, $return);
if ($return === 0) {
    echo "  pf防火墙状态:\n";
    foreach ($output as $line) {
        echo "    $line\n";
    }
} else {
    echo "  pf防火墙未运行或无权限检查\n";
}

echo "\n=== 环境信息 ===\n";
echo "PHP版本: " . PHP_VERSION . "\n";
echo "当前用户: " . get_current_user() . "\n";
echo "进程ID: " . getmypid() . "\n";