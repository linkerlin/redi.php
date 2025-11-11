<?php

namespace Rediphp;

use Redis;

/**
 * Redisson-compatible distributed Topic for pub/sub
 * Uses Redis Pub/Sub, compatible with Redisson's RTopic
 */
class RTopic
{
    private Redis $redis;
    private string $name;

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
        return $this->redis->publish($this->name, $encoded);
    }

    /**
     * Subscribe to the topic with a callback
     *
     * @param callable $callback Callback function to handle messages
     */
    public function subscribe(callable $callback): void
    {
        $this->redis->subscribe([$this->name], function ($redis, $channel, $message) use ($callback) {
            $decoded = $this->decodeValue($message);
            $callback($decoded);
        });
    }

    /**
     * Get the number of subscribers
     *
     * @return int
     */
    public function countSubscribers(): int
    {
        $result = $this->redis->pubsub('numsub', $this->name);
        return isset($result[$this->name]) ? (int)$result[$this->name] : 0;
    }

    /**
     * Encode value for storage (Redisson compatibility)
     *
     * @param mixed $value
     * @return string
     */
    private function encodeValue($value): string
    {
        return json_encode($value);
    }

    /**
     * Decode value from storage
     *
     * @param string $value
     * @return mixed
     */
    private function decodeValue(string $value)
    {
        return json_decode($value, true);
    }
}
