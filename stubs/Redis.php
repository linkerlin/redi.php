<?php

/**
 * Redis扩展存根
 * 仅用于IDE提示，实际运行时使用真正的Redis扩展
 */
class Redis
{
    // 连接相关方法
    public function connect($host, $port = 6379, $timeout = 0.0) {}
    public function pconnect($host, $port = 6379, $timeout = 0.0) {}
    public function close() {}
    public function ping() { return true; }
    public function select($db) { return true; }
    
    // 字符串操作
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
    public function mget(array $keys) { return []; }
    public function mset(array $keyValues) { return true; }
    public function getSet($key, $value) { return false; }
    
    // 哈希操作
    public function hGet($key, $field) { return false; }
    public function hSet($key, $field, $value) { return 0; }
    public function hDel($key, $field, ...$other_fields) { return 0; }
    public function hExists($key, $field) { return false; }
    public function hIncrBy($key, $field, $value) { return 0; }
    public function hGetAll($key) { return []; }
    public function hKeys($key) { return []; }
    public function hVals($key) { return []; }
    public function hLen($key) { return 0; }
    public function hMGet($key, array $fields) { return []; }
    public function hMSet($key, array $fieldValues) { return true; }
    
    // 列表操作
    public function lPush($key, ...$values) { return 0; }
    public function rPush($key, ...$values) { return 0; }
    public function lPop($key) { return false; }
    public function rPop($key) { return false; }
    public function lLen($key) { return 0; }
    public function lIndex($key, $index) { return false; }
    public function lSet($key, $index, $value) { return true; }
    public function lRange($key, $start, $end) { return []; }
    public function lTrim($key, $start, $stop) { return true; }
    
    // 集合操作
    public function sAdd($key, ...$members) { return 0; }
    public function sRem($key, ...$members) { return 0; }
    public function sMembers($key) { return []; }
    public function sCard($key) { return 0; }
    public function sIsMember($key, $member) { return false; }
    public function sPop($key, $count = 1) { return false; }
    public function sRandMember($key, $count = 1) { return false; }
    public function sUnion(...$keys) { return []; }
    public function sInter(...$keys) { return []; }
    public function sDiff(...$keys) { return []; }
    
    // 有序集合操作
    public function zAdd($key, $score, $member, ...$more_scores_and_members) { return 0; }
    public function zRem($key, $member, ...$more_members) { return 0; }
    public function zRange($key, $start, $end, $scores = null) { return []; }
    public function zRevRange($key, $start, $end, $scores = null) { return []; }
    public function zRangeByScore($key, $start, $end, array $options = []) { return []; }
    public function zCard($key) { return 0; }
    public function zScore($key, $member) { return false; }
    public function zRank($key, $member) { return false; }
    public function zRevRank($key, $member) { return false; }
    public function zIncrBy($key, $increment, $member) { return 0; }
    
    // 发布订阅
    public function publish($channel, $message) { return 0; }
    public function subscribe(array $channels, $callback) {}
    public function psubscribe(array $patterns, $callback) {}
    public function unsubscribe($channel = null, ...$other_channels) {}
    public function punsubscribe($pattern = null, ...$other_patterns) {}
    public function pubsub($keyword, $argument = null) { return []; }
    
    // 事务
    public function multi() { return true; }
    public function exec() { return []; }
    public function discard() { return true; }
    public function watch($key, ...$other_keys) { return true; }
    public function unwatch() { return true; }
    
    // 脚本
    public function eval($script, $args = [], $numKeys = 0) { return false; }
    public function evalSha($sha, $args = [], $numKeys = 0) { return false; }
    public function script($command, ...$args) { return false; }
    
    // 键管理
    public function expire($key, $ttl) { return false; }
    public function expireAt($key, $timestamp) { return false; }
    public function ttl($key) { return -1; }
    public function keys($pattern) { return []; }
    public function type($key) { return 0; }
    public function rename($key, $newKey) { return true; }
    public function renameNx($key, $newKey) { return true; }
    public function flushDB() { return true; }
    public function flushAll() { return true; }
    
    // 服务器信息
    public function info($section = null) { return []; }
    public function dbSize() { return 0; }
    public function save() { return true; }
    public function bgSave() { return true; }
    public function lastSave() { return 0; }
    public function config($operation, $key, $value = null) { return false; }
    
    // 额外方法
    public function getLastError() { return null; }
    public function clearLastError() {}
    public function getOption($option) { return null; }
    public function setOption($option, $value) { return true; }
    public function pipeline() { return null; }
    public function lInsert($key, $position, $pivot, $value) { return 0; }
    public function blPop($key, $timeout_or_keys, ...$extra_args) { return null; }
    public function brPop($key, $timeout_or_keys, ...$extra_args) { return null; }
    public function sDiffStore($dst, $key1, ...$other_keys) { return 0; }
    public function sInterStore($dst, $key1, ...$other_keys) { return 0; }
    public function sUnionStore($dst, $key1, ...$other_keys) { return 0; }
    public function zRevRangeByScore($key, $start, $end, $options = []) { return []; }
    public function zCount($key, $min, $max) { return 0; }
    public function zIncrBy($key, $increment, $member) { return 0; }
    public function hSetNx($key, $field, $value) { return true; }
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
    public function move($key, $db) { return true; }
    
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
    const FUTURE = 1;
    const SCAN_NORETRY = 0;
    const SCAN_RETRY = 1;
    const SCAN_PREFIX = 2;
    const SCAN_NOPREFIX = 3;
    const FSYNC = 1;
    const ALWAYS = 0;
    const EVERYSEC = 1;
    const NO = 2;
}