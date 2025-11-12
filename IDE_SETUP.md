# IDE 配置指南

## 解决 "Undefined type 'Redis'" 问题

当使用IDE（如PHPStorm或VS Code）开发此项目时，可能会遇到 "Undefined type 'Redis'" 的错误提示。这是因为IDE无法识别PHP扩展类型。以下是针对不同IDE的解决方案。

## 验证Redis扩展

首先，确认Redis扩展已正确安装：

```bash
php check_redis_ext.php
```

如果输出显示 "Redis扩展已加载！" 和 "Redis类存在！"，则表示Redis扩展已正确安装。

## PHPStorm 解决方案

### 方法1：使用已创建的配置文件

项目已包含 `.idea` 目录，其中包含必要的配置文件。只需重新打开项目即可。

### 方法2：手动配置

1. 打开 **File > Settings > Languages & Frameworks > PHP**
2. 确保选择了正确的PHP解释器（PHP 8.4）
3. 点击 "..." 按钮添加解释器，指向 `/opt/homebrew/bin/php`
4. 在 "PHP Runtime" 部分，确保勾选了 "Redis" 扩展

### 方法3：添加存根

1. 打开 **File > Settings > Languages & Frameworks > PHP > Include Path**
2. 添加Redis扩展的存根路径（如果有的话）

## VS Code 解决方案

### 方法1：使用已创建的配置文件

项目已包含 `.vscode/settings.json` 文件，其中包含必要的配置。只需重新打开项目即可。

### 方法2：手动配置

1. 安装PHP Intelephense扩展
2. 打开设置（JSON格式）
3. 添加以下配置：

```json
{
    "php.executablePath": "/opt/homebrew/bin/php",
    "php.validate.executablePath": "/opt/homebrew/bin/php",
    "intelephense.environment.phpVersion": "8.4",
    "intelephense.stubs": [
        "redis",
        "json",
        "mbstring",
        "curl",
        "openssl"
    ]
}
``

### 方法3：安装Redis存根

1. 安装Redis PHP存根包：

```bash
composer require --dev jetbrains/phpstorm-stubs
```

2. 在VS Code设置中添加存根路径

## 通用解决方案

### 1. 安装PHP存根

```bash
composer require --dev jetbrains/phpstorm-stubs
```

### 2. 创建自定义存根

在项目中创建 `stubs/Redis.php` 文件：

```php
<?php

/**
 * Redis扩展存根
 * 仅用于IDE提示，实际运行时使用真正的Redis扩展
 */
class Redis
{
    public function connect($host, $port = 6379, $timeout = 0.0) {}
    public function pconnect($host, $port = 6379, $timeout = 0.0) {}
    public function close() {}
    public function ping() { return true; }
    public function get($key) { return false; }
    public function set($key, $value, $timeout = null) { return true; }
    public function setex($key, $ttl, $value) { return true; }
    public function setnx($key, $value) { return true; }
    public function del($key, ...$other_keys) { return 0; }
    public function exists($key, ...$other_keys) { return 0; }
    public function incr($key) { return 0; }
    public function incrBy($key, $value) { return 0; }
    public function decr($key) { return 0; }
    public function decrBy($key, $value) { return 0; }
    public function expire($key, $ttl) { return true; }
    public function ttl($key) { return -1; }
    public function keys($pattern) { return []; }
    public function flushAll() { return true; }
    public function flushDB() { return true; }
    public function select($db) { return true; }
    public function move($key, $db) { return true; }
    public function rename($key, $newkey) { return true; }
    public function renameNx($key, $newkey) { return true; }
    public function getLastError() { return null; }
    public function clearLastError() {}
    public function getOption($option) { return null; }
    public function setOption($option, $value) { return true; }
    public function publish($channel, $message) { return 0; }
    public function subscribe($channels, $callback) { return null; }
    public function unsubscribe($channels = null) { return null; }
    public function psubscribe($patterns, $callback) { return null; }
    public function punsubscribe($patterns = null) { return null; }
    public function pubsub($cmd, ...$args) { return []; }
    public function multi($mode = Redis::MULTI) { return null; }
    public function exec() { return null; }
    public function discard() { return true; }
    public function watch($key, ...$other_keys) { return true; }
    public function unwatch() { return true; }
    public function pipeline() { return null; }
    public function lPush($key, $value1, ...$value2) { return 0; }
    public function rPush($key, $value1, ...$value2) { return 0; }
    public function lPop($key) { return null; }
    public function rPop($key) { return null; }
    public function blPop($key, $timeout_or_keys, ...$extra_args) { return null; }
    public function brPop($key, $timeout_or_keys, ...$extra_args) { return null; }
    public function lLen($key) { return 0; }
    public function lRange($key, $start, $end) { return []; }
    public function lTrim($key, $start, $stop) { return true; }
    public function lSet($key, $index, $value) { return true; }
    public function lIndex($key, $index) { return null; }
    public function lInsert($key, $position, $pivot, $value) { return 0; }
    public function sAdd($key, $value1, ...$value2) { return 0; }
    public function sRem($key, $member1, ...$member2) { return 0; }
    public function sPop($key, $count = 0) { return null; }
    public function sRandMember($key, $count = 0) { return null; }
    public function sMembers($key) { return []; }
    public function sIsMember($key, $value) { return false; }
    public function sCard($key) { return 0; }
    public function sDiff($key1, ...$other_keys) { return []; }
    public function sDiffStore($dst, $key1, ...$other_keys) { return 0; }
    public function sInter($key1, ...$other_keys) { return []; }
    public function sInterStore($dst, $key1, ...$other_keys) { return 0; }
    public function sUnion($key1, ...$other_keys) { return []; }
    public function sUnionStore($dst, $key1, ...$other_keys) { return 0; }
    public function zAdd($key, $score1, $value1, ...$more) { return 0; }
    public function zRem($key, $member1, ...$member2) { return 0; }
    public function zRange($key, $start, $end, $scores = null) { return []; }
    public function zRevRange($key, $start, $end, $scores = null) { return []; }
    public function zRangeByScore($key, $start, $end, $options = []) { return []; }
    public function zRevRangeByScore($key, $start, $end, $options = []) { return []; }
    public function zCount($key, $min, $max) { return 0; }
    public function zCard($key) { return 0; }
    public function zScore($key, $member) { return null; }
    public function zRank($key, $member) { return null; }
    public function zRevRank($key, $member) { return null; }
    public function zIncrBy($key, $increment, $member) { return 0; }
    public function hSet($key, $field, $value) { return 0; }
    public function hSetNx($key, $field, $value) { return true; }
    public function hGet($key, $field) { return null; }
    public function hLen($key) { return 0; }
    public function hDel($key, $field1, ...$other_fields) { return 0; }
    public function hKeys($key) { return []; }
    public function hVals($key) { return []; }
    public function hGetAll($key) { return []; }
    public function hExists($key, $field) { return false; }
    public function hIncrBy($key, $field, $increment) { return 0; }
    public function hIncrByFloat($key, $field, $increment) { return 0.0; }
    public function scan(&$iterator, $pattern = null, $count = 0) { return []; }
    public function hScan($key, &$iterator, $pattern = null, $count = 0) { return []; }
    public function zScan($key, &$iterator, $pattern = null, $count = 0) { return []; }
    public function sScan($key, &$iterator, $pattern = null, $count = 0) { return []; }
    public function getBit($key, $offset) { return 0; }
    public function setBit($key, $offset, $value) { return 0; }
    public function bitCount($key, $start = 0, $end = -1) { return 0; }
    public function bitOp($operation, $ret_key, $key1, ...$other_keys) { return 0; }
    public function geoAdd($key, $longitude, $latitude, $member, ...$other_triples) { return 0; }
    public function geoPos($key, $member1, ...$other_members) { return []; }
    public function geoDist($key, $member1, $member2, $unit = 'm') { return null; }
    public function geoHash($key, $member1, ...$other_members) { return []; }
    public function geoRadius($key, $longitude, $latitude, $radius, $unit, $options = []) { return []; }
    public function geoRadiusByMember($key, $member, $radius, $unit, $options = []) { return []; }
    public function xAdd($key, $id, $values, $maxLen = 0, $approx = false) { return null; }
    public function xDel($key, $id) { return 0; }
    public function xLen($key) { return 0; }
    public function xRange($key, $start, $end, $count = -1) { return []; }
    public function xRevRange($key, $end, $start, $count = -1) { return []; }
    public function xRead($streams, $count = null, $block = null) { return []; }
    public function xReadGroup($group, $consumer, $streams, $count = null, $block = null) { return []; }
    public function xAck($key, $group, $id, ...$other_ids) { return 0; }
    public function xGroup($operation, $key, $group, $id = null, $mkstream = false) { return true; }
    public function xInfo($operation, $key, $group = null) { return []; }
    public function xClaim($key, $group, $consumer, $minIdleTime, $id, ...$other_ids) { return []; }
    public function xPending($key, $group, $start = null, $end = null, $count = null, $consumer = null) { return []; }
    public function xTrim($key, $maxLen, $approx = false) { return 0; }
    public function rawCommand($cmd, ...$args) { return null; }
    
    // 常量
    const AFTER = 'after';
    const BEFORE = 'before';
    const MULTI = 1;
    const PIPELINE = 2;
    const OPT_SERIALIZER = 1;
    const OPT_PREFIX = 2;
    const OPT_READ_TIMEOUT = 3;
    const SERIALIZER_NONE = 0;
    const SERIALIZER_PHP = 1;
    const SERIALIZER_IGBINARY = 2;
    const SERIALIZER_JSON = 3;
    const ATOMIC = 0;
    const FSYNC = 1;
    const ALWAYS = 0;
    const EVERYSEC = 1;
    const NO = 2;
}
```

然后在IDE中将此目录添加到包含路径中。

## 验证解决方案

完成上述配置后，重新打开IDE或重新加载项目，"Undefined type 'Redis'" 错误应该消失。

## 其他IDE

对于其他IDE（如Eclipse PDT、NetBeans等），通常需要：

1. 设置正确的PHP解释器路径
2. 添加Redis扩展到包含路径
3. 安装Redis存根文件

## 注意事项

- 这些配置仅用于IDE提示，不影响实际运行
- 实际运行时，PHP会使用已安装的Redis扩展
- 如果更新PHP版本或Redis扩展，可能需要更新IDE配置