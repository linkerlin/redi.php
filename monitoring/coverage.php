<?php
/**
 * 测试覆盖率报告页面
 * 显示测试覆盖率和测试结果统计
 */

require_once __DIR__ . '/../vendor/autoload.php';

class TestCoverageReporter {
    private string $srcDir;
    private string $testsDir;
    
    public function __construct() {
        $this->srcDir = __DIR__ . '/../src';
        $this->testsDir = __DIR__ . '/../tests';
    }
    
    /**
     * 获取源代码文件列表
     */
    public function getSourceFiles(): array {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->srcDir)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $relativePath = str_replace($this->srcDir . '/', '', $file->getPathname());
                $files[] = [
                    'path' => $file->getPathname(),
                    'relative_path' => $relativePath,
                    'class_name' => $this->extractClassName($file->getPathname()),
                    'lines' => count(file($file->getPathname())),
                ];
            }
        }
        
        return $files;
    }
    
    /**
     * 获取测试文件列表
     */
    public function getTestFiles(): array {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->testsDir)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php' && 
                strpos($file->getFilename(), 'Test.php') !== false) {
                $relativePath = str_replace($this->testsDir . '/', '', $file->getPathname());
                $files[] = [
                    'path' => $file->getPathname(),
                    'relative_path' => $relativePath,
                    'class_name' => $this->extractClassName($file->getPathname()),
                    'test_count' => $this->countTests($file->getPathname()),
                    'lines' => count(file($file->getPathname())),
                ];
            }
        }
        
        return $files;
    }
    
    /**
     * 从文件内容提取类名
     */
    private function extractClassName(string $filePath): string {
        $content = file_get_contents($filePath);
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            return $matches[1];
        }
        return 'Unknown';
    }
    
    /**
     * 统计测试方法数量
     */
    private function countTests(string $filePath): int {
        $content = file_get_contents($filePath);
        preg_match_all('/public\s+function\s+test\w+/', $content, $matches);
        return count($matches[0]);
    }
    
    /**
     * 分析测试覆盖率
     */
    public function analyzeCoverage(): array {
        $sourceFiles = $this->getSourceFiles();
        $testFiles = $this->getTestFiles();
        
        $coverage = [
            'total_source_files' => count($sourceFiles),
            'total_test_files' => count($testFiles),
            'total_tests' => array_sum(array_column($testFiles, 'test_count')),
            'total_source_lines' => array_sum(array_column($sourceFiles, 'lines')),
            'total_test_lines' => array_sum(array_column($testFiles, 'lines')),
            'coverage_percentage' => 0,
            'covered_classes' => [],
            'uncovered_classes' => [],
        ];
        
        // 分析每个源文件的测试覆盖情况
        foreach ($sourceFiles as $sourceFile) {
            $sourceClass = $sourceFile['class_name'];
            $testClass = $sourceClass . 'Test';
            
            $hasTest = false;
            foreach ($testFiles as $testFile) {
                if ($testFile['class_name'] === $testClass) {
                    $hasTest = true;
                    $coverage['covered_classes'][] = [
                        'source_class' => $sourceClass,
                        'test_class' => $testClass,
                        'test_file' => $testFile['relative_path'],
                        'test_count' => $testFile['test_count'],
                        'source_lines' => $sourceFile['lines'],
                        'test_lines' => $testFile['lines'],
                    ];
                    break;
                }
            }
            
            if (!$hasTest) {
                $coverage['uncovered_classes'][] = [
                    'source_class' => $sourceClass,
                    'source_file' => $sourceFile['relative_path'],
                    'source_lines' => $sourceFile['lines'],
                ];
            }
        }
        
        // 计算覆盖率百分比
        if ($coverage['total_source_files'] > 0) {
            $coverage['coverage_percentage'] = round(
                (count($coverage['covered_classes']) / $coverage['total_source_files']) * 100, 
                2
            );
        }
        
        return $coverage;
    }
    
    /**
     * 获取最近的测试结果
     */
    public function getRecentTestResults(): array {
        $results = [];
        
        // 尝试读取PHPUnit的测试结果缓存
        $cacheFile = __DIR__ . '/../.phpunit.result.cache';
        if (file_exists($cacheFile)) {
            $cacheData = file_get_contents($cacheFile);
            if ($cacheData) {
                $results['cache_exists'] = true;
                $results['cache_size'] = filesize($cacheFile);
                $results['cache_modified'] = date('Y-m-d H:i:s', filemtime($cacheFile));
            }
        } else {
            $results['cache_exists'] = false;
        }
        
        // 获取测试文件的最新修改时间
        $testFiles = $this->getTestFiles();
        $latestTestTime = 0;
        foreach ($testFiles as $testFile) {
            $fileTime = filemtime($testFile['path']);
            if ($fileTime > $latestTestTime) {
                $latestTestTime = $fileTime;
            }
        }
        
        $results['latest_test_time'] = $latestTestTime > 0 ? date('Y-m-d H:i:s', $latestTestTime) : '未知';
        $results['test_files_count'] = count($testFiles);
        
        return $results;
    }
    
    /**
     * 运行测试并获取结果
     */
    public function runTests(): array {
        $output = [];
        $returnCode = 0;
        
        // 运行PHPUnit测试
        $command = 'cd ' . escapeshellarg(dirname(__DIR__)) . ' && vendor/bin/phpunit --verbose --colors=never 2>&1';
        exec($command, $output, $returnCode);
        
        return [
            'success' => $returnCode === 0,
            'return_code' => $returnCode,
            'output' => $output,
            'command' => $command,
        ];
    }
}

// 创建报告实例并获取数据
$reporter = new TestCoverageReporter();
$coverageData = $reporter->analyzeCoverage();
$testResults = $reporter->getRecentTestResults();

// 如果请求运行测试
$runTests = isset($_GET['run_tests']) && $_GET['run_tests'] === 'true';
$testRunResults = null;
if ($runTests) {
    $testRunResults = $reporter->runTests();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>测试覆盖率报告</title>
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
        .btn { background: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin: 10px 0; text-decoration: none; display: inline-block; }
        .btn:hover { background: #2980b9; }
        .btn-success { background: #27ae60; }
        .btn-success:hover { background: #229954; }
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        .coverage-bar { background: #ecf0f1; height: 20px; border-radius: 10px; overflow: hidden; margin: 10px 0; }
        .coverage-fill { background: #27ae60; height: 100%; transition: width 0.3s; }
        .test-output { background: #2c3e50; color: white; padding: 15px; border-radius: 6px; font-family: monospace; white-space: pre-wrap; max-height: 400px; overflow-y: auto; }
        .test-success { border-left: 4px solid #27ae60; }
        .test-failure { border-left: 4px solid #e74c3c; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>测试覆盖率报告</h1>
            <p>分析测试覆盖率和测试结果统计</p>
        </div>
        
        <div class="card">
            <h2>覆盖率概览</h2>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-value"><?= $coverageData['coverage_percentage'] ?>%</div>
                    <div class="stat-label">测试覆盖率</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= $coverageData['total_source_files'] ?></div>
                    <div class="stat-label">源文件数量</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= $coverageData['total_test_files'] ?></div>
                    <div class="stat-label">测试文件数量</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= $coverageData['total_tests'] ?></div>
                    <div class="stat-label">测试方法总数</div>
                </div>
            </div>
            
            <!-- 覆盖率进度条 -->
            <div class="coverage-bar">
                <div class="coverage-fill" style="width: <?= $coverageData['coverage_percentage'] ?>%"></div>
            </div>
            
            <p>最后测试时间: <?= $testResults['latest_test_time'] ?></p>
            
            <a href="?run_tests=true" class="btn btn-success">🚀 运行所有测试</a>
            <a href="dashboard.php" class="btn">📊 查看监控仪表板</a>
        </div>
        
        <!-- 测试运行结果 -->
        <?php if ($runTests && $testRunResults): ?>
        <div class="card <?= $testRunResults['success'] ? 'test-success' : 'test-failure' ?>">
            <h2>测试运行结果</h2>
            <p>状态: <strong class="<?= $testRunResults['success'] ? 'success' : 'danger' ?>">
                <?= $testRunResults['success'] ? '✅ 测试通过' : '❌ 测试失败' ?>
            </strong></p>
            <p>退出码: <?= $testRunResults['return_code'] ?></p>
            <div class="test-output">
                <?= htmlspecialchars(implode("\n", $testRunResults['output'])) ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- 已覆盖的类 -->
        <div class="card">
            <h2>已测试的类 (<?= count($coverageData['covered_classes']) ?>)</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>源类</th>
                        <th>测试类</th>
                        <th>测试文件</th>
                        <th>测试方法数</th>
                        <th>源文件行数</th>
                        <th>测试文件行数</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($coverageData['covered_classes'] as $class): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($class['source_class']) ?></strong></td>
                        <td class="success"><?= htmlspecialchars($class['test_class']) ?></td>
                        <td><?= htmlspecialchars($class['test_file']) ?></td>
                        <td class="info"><?= $class['test_count'] ?></td>
                        <td><?= $class['source_lines'] ?></td>
                        <td><?= $class['test_lines'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- 未覆盖的类 -->
        <div class="card">
            <h2>未测试的类 (<?= count($coverageData['uncovered_classes']) ?>)</h2>
            <?php if (empty($coverageData['uncovered_classes'])): ?>
                <p class="success">🎉 所有类都有对应的测试！</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>源类</th>
                            <th>源文件</th>
                            <th>行数</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($coverageData['uncovered_classes'] as $class): ?>
                        <tr>
                            <td><strong class="danger"><?= htmlspecialchars($class['source_class']) ?></strong></td>
                            <td><?= htmlspecialchars($class['source_file']) ?></td>
                            <td><?= $class['source_lines'] ?></td>
                            <td>
                                <a href="#" class="btn btn-danger" onclick="alert('需要手动创建测试文件: <?= $class['source_class'] ?>Test.php')">创建测试</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- 测试统计 -->
        <div class="card">
            <h2>测试统计信息</h2>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-value"><?= $coverageData['total_source_lines'] ?></div>
                    <div class="stat-label">源代码总行数</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= $coverageData['total_test_lines'] ?></div>
                    <div class="stat-label">测试代码总行数</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">
                        <?= $coverageData['total_source_files'] > 0 ? 
                            round($coverageData['total_test_lines'] / $coverageData['total_source_files'], 2) : 0 ?>
                    </div>
                    <div class="stat-label">平均测试行数/文件</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">
                        <?= $coverageData['total_source_files'] > 0 ? 
                            round($coverageData['total_tests'] / $coverageData['total_source_files'], 2) : 0 ?>
                    </div>
                    <div class="stat-label">平均测试方法/文件</div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <h2>覆盖率说明</h2>
            <ul>
                <li><strong>测试覆盖率</strong>：有对应测试文件的源文件占总源文件的比例</li>
                <li><strong>已测试的类</strong>：有对应测试文件的Redis数据结构类</li>
                <li><strong>未测试的类</strong>：缺少对应测试文件的Redis数据结构类</li>
                <li>覆盖率计算基于文件级别的对应关系，不包含代码行级别的覆盖率</li>
                <li>建议目标覆盖率：100%</li>
            </ul>
        </div>
    </div>
</body>
</html>