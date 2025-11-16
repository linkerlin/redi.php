<?php

namespace RediPhp;

/**
 * RedissonPool接口
 * 定义Redis连接池的公共方法
 */
interface RedissonPoolInterface
{
    /**
     * 从连接池获取一个Redis连接
     *
     * @param float|null $timeout 获取连接的超时时间(秒)，为null时使用默认超时
     * @return Redis Redis连接实例
     * @throws RuntimeException 当获取连接失败或超时时抛出
     * @throws IllegalStateException 当连接池已关闭时抛出
     */
    public function acquire(?float $timeout = null): Redis;

    /**
     * 将Redis连接归还到连接池
     *
     * @param Redis $connection Redis连接实例
     * @throws RuntimeException 当连接归还失败时抛出
     * @throws IllegalArgumentException 当连接不属于此连接池时抛出
     */
    public function release(Redis $connection): void;

    /**
     * 关闭连接池，释放所有资源
     *
     * @return void
     */
    public function close(): void;

    /**
     * 获取连接池当前大小
     *
     * @return int 连接池大小
     */
    public function getPoolSize(): int;

    /**
     * 获取当前活跃连接数
     *
     * @return int 活跃连接数
     */
    public function getActiveConnections(): int;

    /**
     * 获取当前空闲连接数
     *
     * @return int 空闲连接数
     */
    public function getIdleConnections(): int;

    /**
     * 获取连接池统计信息
     *
     * @return array 包含各种统计信息的数组
     */
    public function getStats(): array;

    /**
     * 预热连接池，创建最小数量的连接
     *
     * @return void
     */
    public function warmUp(): void;

    /**
     * 检查连接池是否已关闭
     *
     * @return bool 连接池是否已关闭
     */
    public function isClosed(): bool;
}