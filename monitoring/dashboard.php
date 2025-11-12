<?php
/**
 * Redis数据结构监控仪表板
 * 显示各种Redis数据结构的使用情况和性能指标
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Rediphp\RedissonClient;

class RedisMonitor {
    private RedissonClient $client;
    
    public function __construct() {
        $this->client = new RedissonClient([
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => (int)(getenv('REDIS_PORT') ?: 6379),
        ]);
        
        if (!$this->client->connect()) {
            throw new Exception('无法连接到Redis服务器');
        }
    }
    
    /**
     * 获取Redis服务器信息
     */
    public function getServerInfo(): array {
        $info = $this->client->getRedis()->info();
        return [
            'version' => $info['redis_version'] ?? '未知',
            'uptime' => $info['uptime_in_seconds'] ?? 0,
            'memory_used' => $info['used_memory_human'] ?? '0B',
            'connected_clients' => $info['connected_clients'] ?? 0,
            'commands_processed' => $info['total_commands_processed'] ?? 0,
            'keyspace_hits' => $info['keyspace_hits'] ?? 0,
            'keyspace_misses' => $info['keyspace_misses'] ?? 0,
        ];
    }
    
    /**
     * 获取数据结构统计信息
     */
    public function getDataStructureStats(): array {
        $stats = [];
        
        // 测试各种数据结构
        $structures = [
            'Map' => $this->client->getMap('test:monitor:map'),
            'Set' => $this->client->getSet('test:monitor:set'),
            'List' => $this->client->getList('test:monitor:list'),
            'Queue' => $this->client->getQueue('test:monitor:queue'),
            'Deque' => $this->client->getDeque('test:monitor:deque'),
            'AtomicLong' => $this->client->getAtomicLong('test:monitor:atomiclong'),
            'AtomicDouble' => $this->client->getAtomicDouble('test:monitor:atomicdouble'),
            'BloomFilter' => $this->client->getBloomFilter('test:monitor:bloom'),
            'BitSet' => $this->client->getBitSet('test:monitor:bitset'),
            'TimeSeries' => $this->client->getTimeSeries('test:monitor:timeseries'),
            'Geo' => $this->client->getGeo('test:monitor:geo'),
            'HyperLogLog' => $this->client->getHyperLogLog('test:monitor:hll'),
            'Stream' => $this->client->getStream('test:monitor:stream'),
        ];
        
        foreach ($structures as $name => $structure) {
            try {
                $stats[$name] = [
                    'exists' => $structure->exists(),
                    'size' => $structure->size(),
                    'isEmpty' => $structure->isEmpty(),
                    'performance' => $this->measurePerformance($structure, $name),
                ];
            } catch (Exception $e) {
                $stats[$name] = [
                    'exists' => false,
                    'size' => 0,
                    'isEmpty' => true,
                    'performance' => 'N/A',
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return $stats;
    }
    
    /**
     * 测量数据结构操作性能
     */
    private function measurePerformance($structure, string $type): array {
        $iterations = 100;
        $results = [];
        
        // 测量添加操作的性能
        $start = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            switch ($type) {
                case 'Map':
                    $structure->put("key_$i", "value_$i");
                    break;
                case 'Set':
                case 'List':
                case 'Queue':
                case 'Deque':
                    $structure->add("item_$i");
                    break;
                case 'AtomicLong':
                case 'AtomicDouble':
                    $structure->set($i);
                    break;
                case 'BloomFilter':
                    $structure->add("element_$i");
                    break;
                case 'BitSet':
                    $structure->set($i);
                    break;
                case 'TimeSeries':
                    $structure->add(time() * 1000 + $i, $i);
                    break;
                case 'Geo':
                    $structure->add(116.4074 + $i/1000, 39.9042 + $i/1000, "location_$i");
                    break;
                case 'HyperLogLog':
                    $structure->add("user_$i");
                    break;
                case 'Stream':
                    $structure->add(['field' => "value_$i"]);
                    break;
            }
        }
        $addTime = microtime(true) - $start;
        
        // 测量读取操作的性能
        $start = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            switch ($type) {
                case 'Map':
                    $structure->get("key_$i");
                    break;
                case 'Set':
                    $structure->contains("item_$i");
                    break;
                case 'List':
                    $structure->get($i);
                    break;
                case 'Queue':
                case 'Deque':
                    if ($i < $structure->size()) {
                        $structure->peek();
                    }
                    break;
                case 'AtomicLong':
                case 'AtomicDouble':
                    $structure->get();
                    break;
                case 'BloomFilter':
                    $structure->contains("element_$i");
                    break;
                case 'BitSet':
                    $structure->get($i);
                    break;
                case 'TimeSeries':
                    if ($i < 10) { // 避免查询过多
                        $structure->getLatest();
                    }
                    break;
                case 'Geo':
                    $structure->position("location_$i");
                    break;
                case 'HyperLogLog':
                    $structure->count();
                    break;
                case 'Stream':
                    if ($i < 10) { // 避免查询过多
                        $structure->length();
                    }
                    break;
            }
        }
        $readTime = microtime(true) - $start;
        
        // 清理测试数据
        $structure->clear();
        
        return [
            'add_ops_per_second' => round($iterations / $addTime, 2),
            'read_ops_per_second' => round($iterations / $readTime, 2),
            'add_time_ms' => round($addTime * 1000, 2),
            'read_time_ms' => round($readTime * 1000, 2),
        ];
    }
    
    /**
     * 获取内存使用情况
     */
    public function getMemoryUsage(): array {
        $redis = $this->client->getRedis();
        
        // 获取所有键的内存使用情况
        $keys = $redis->keys('*');
        $memoryUsage = [];
        
        foreach ($keys as $key) {
            try {
                $memory = $redis->memory('usage', $key);
                $memoryUsage[$key] = [
                    'size' => $memory,
                    'size_human' => $this->formatBytes($memory),
                    'type' => $redis->type($key),
                ];
            } catch (Exception $e) {
                $memoryUsage[$key] = [
                    'size' => 0,
                    'size_human' => '0B',
                    'type' => 'unknown',
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        // 按大小排序
        uasort($memoryUsage, function($a, $b) {
            return $b['size'] <=> $a['size'];
        });
        
        return $memoryUsage;
    }
    
    /**
     * 格式化字节大小
     */
    private function formatBytes($bytes, $precision = 2): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    public function shutdown(): void {
        $this->client->shutdown();
    }
}

// 创建监控实例并获取数据
try {
    $monitor = new RedisMonitor();
    $serverInfo = $monitor->getServerInfo();
    $dataStructureStats = $monitor->getDataStructureStats();
    $memoryUsage = $monitor->getMemoryUsage();
    $monitor->shutdown();
} catch (Exception $e) {
    die("监控错误: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redis数据结构监控仪表板</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; color: #333; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background: #2c3e50; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-item { background: #ecf0f1; padding: 15px; border-radius: 6px; text-align: center; }
        .stat-value { font-size: 24px; font-weight: bold; color: #2c3e50; }
        .stat-label { font-size: 14px; color: #7f8c8d; }
        .table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #ecf0f1; }
        .table th { background: #34495e; color: white; }
        .table tr:hover { background: #f8f9fa; }
        .success { color: #27ae60; }
        .warning { color: #f39c12; }
        .danger { color: #e74c3c; }
        .info { color: #3498db; }
        .refresh-btn { background: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin: 10px 0; }
        .refresh-btn:hover { background: #2980b9; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Redis数据结构监控仪表板</h1>
            <p>实时监控Redis数据结构的性能和使用情况</p>
        </div>
        
        <button class="refresh-btn" onclick="location.reload()">🔄 刷新数据</button>
        
        <!-- 服务器信息 -->
        <div class="card">
            <h2>Redis服务器信息</h2>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-value"><?= htmlspecialchars($serverInfo['version']) ?></div>
                    <div class="stat-label">Redis版本</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= htmlspecialchars($serverInfo['uptime']) ?>s</div>
                    <div class="stat-label">运行时间</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= htmlspecialchars($serverInfo['memory_used']) ?></div>
                    <div class="stat-label">内存使用</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= htmlspecialchars($serverInfo['connected_clients']) ?></div>
                    <div class="stat-label">连接客户端</div>
                </div>
            </div>
        </div>
        
        <!-- 数据结构统计 -->
        <div class="card">
            <h2>数据结构性能统计</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>数据结构</th>
                        <th>存在</th>
                        <th>大小</th>
                        <th>是否为空</th>
                        <th>添加操作/秒</th>
                        <th>读取操作/秒</th>
                        <th>添加时间(ms)</th>
                        <th>读取时间(ms)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dataStructureStats as $name => $stat): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($name) ?></strong></td>
                        <td class="<?= $stat['exists'] ? 'success' : 'danger' ?>"><?= $stat['exists'] ? '是' : '否' ?></td>
                        <td><?= htmlspecialchars($stat['size']) ?></td>
                        <td class="<?= $stat['isEmpty'] ? 'success' : 'warning' ?>"><?= $stat['isEmpty'] ? '是' : '否' ?></td>
                        <td class="info"><?= is_array($stat['performance']) ? htmlspecialchars($stat['performance']['add_ops_per_second']) : 'N/A' ?></td>
                        <td class="info"><?= is_array($stat['performance']) ? htmlspecialchars($stat['performance']['read_ops_per_second']) : 'N/A' ?></td>
                        <td class="info"><?= is_array($stat['performance']) ? htmlspecialchars($stat['performance']['add_time_ms']) : 'N/A' ?></td>
                        <td class="info"><?= is_array($stat['performance']) ? htmlspecialchars($stat['performance']['read_time_ms']) : 'N/A' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- 内存使用情况 -->
        <div class="card">
            <h2>内存使用情况（前20个键）</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>键名</th>
                        <th>类型</th>
                        <th>内存使用</th>
                        <th>大小</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $count = 0; ?>
                    <?php foreach ($memoryUsage as $key => $usage): ?>
                        <?php if ($count++ >= 20) break; ?>
                        <tr>
                            <td title="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars(substr($key, 0, 50)) . (strlen($key) > 50 ? '...' : '') ?></td>
                            <td><?= htmlspecialchars($usage['type']) ?></td>
                            <td class="info"><?= htmlspecialchars($usage['size_human']) ?></td>
                            <td><?= htmlspecialchars($usage['size']) ?> bytes</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="card">
            <h2>监控说明</h2>
            <ul>
                <li><strong>添加操作/秒</strong>：每秒可以执行多少次添加操作</li>
                <li><strong>读取操作/秒</strong>：每秒可以执行多少次读取操作</li>
                <li><strong>添加时间</strong>：执行100次添加操作的总时间（毫秒）</li>
                <li><strong>读取时间</strong>：执行100次读取操作的总时间（毫秒）</li>
                <li>性能测试基于100次操作的平均值</li>
                <li>测试数据会在测量后自动清理</li>
            </ul>
        </div>
    </div>
</body>
</html>