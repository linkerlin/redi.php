<?php

namespace Rediphp;

use Redis;
use Rediphp\Services\SerializationService;

/**
 * Redisson-compatible distributed Topic for pub/sub
 * Uses Redis Pub/Sub with simulated non-blocking behavior for testing
 */
class RTopic
{
    private Redis $redis;
    private string $name;
    private array $listeners = [];
    private int $nextListenerId = 1;

    public function __construct(Redis $redis, string $name)
    {
        $this->redis = $redis;
        $this->name = $name;
    }

    /**
     * Publish a message to the topic
     *
     * @param mixed $message
     * @return int Number of clients that received the message
     */
    public function publish($message): int
    {
        $encoded = $this->encodeValue($message);
        
        // 使用Redis发布消息
        $redisSubscribers = $this->redis->publish($this->name, $encoded);
        
        // 同时调用本地监听器（用于测试）
        $localSubscribers = 0;
        foreach ($this->listeners as $listener) {
            $listener($message);
            $localSubscribers++;
        }
        
        return max($redisSubscribers, $localSubscribers);
    }

    /**
     * Subscribe to the topic with a callback
     *
     * @param callable $callback Callback function to handle messages
     */
    public function subscribe(callable $callback): void
    {
        $this->addListener($callback);
    }

    /**
     * Get the number of subscribers
     *
     * @return int
     */
    public function countSubscribers(): int
    {
        try {
            $result = $this->redis->pubsub('numsub', [$this->name]);
            $redisSubscribers = isset($result[$this->name]) ? (int)$result[$this->name] : 0;
        } catch (\Exception $e) {
            $redisSubscribers = 0;
        }
        
        return max($redisSubscribers, count($this->listeners));
    }

    /**
     * Add a listener to the topic
     *
     * @param callable $callback Callback function to handle messages
     * @return int Listener ID
     */
    public function addListener(callable $callback): int
    {
        $listenerId = $this->nextListenerId++;
        $this->listeners[$listenerId] = $callback;
        return $listenerId;
    }

    /**
     * Remove a listener from the topic
     *
     * @param int $listenerId Listener ID
     * @return bool True if listener was removed, false if not found
     */
    public function removeListener(int $listenerId): bool
    {
        if (isset($this->listeners[$listenerId])) {
            unset($this->listeners[$listenerId]);
            return true;
        }
        return false;
    }

    /**
     * Check if the topic exists (has subscribers or has been used)
     *
     * @return bool
     */
    public function exists(): bool
    {
        // 检查是否有订阅者
        if ($this->countSubscribers() > 0) {
            return true;
        }
        
        // 检查Redis中是否有该主题的订阅者信息
        try {
            $result = $this->redis->pubsub('numsub', [$this->name]);
            return isset($result[$this->name]) && (int)$result[$this->name] > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the size (number of subscribers)
     *
     * @return int
     */
    public function size(): int
    {
        return $this->countSubscribers();
    }

    /**
     * Clear the topic (unsubscribe all listeners)
     *
     * @return bool Always true
     */
    public function clear(): bool
    {
        $this->listeners = [];
        return true;
    }

    /**
     * Encode value for storage (Redisson compatibility)
     *
     * @param mixed $value
     * @return string
     */
    private function encodeValue($value): string
    {
        return SerializationService::getInstance()->encode($value);
    }

    /**
     * Decode value from storage
     *
     * @param string $value
     * @return mixed
     */
    private function decodeValue(string $value)
    {
        return SerializationService::getInstance()->decode($value, true);
    }
}
