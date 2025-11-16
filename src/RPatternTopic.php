<?php

namespace Rediphp;

use Redis;
use Rediphp\Services\SerializationService;

/**
 * Redisson-compatible distributed PatternTopic for pattern-based pub/sub
 * Uses Redis Pattern Pub/Sub, compatible with Redisson's RPatternTopic
 */
class RPatternTopic
{
    private Redis $redis;
    private string $pattern;

    public function __construct(Redis $redis, string $pattern)
    {
        $this->redis = $redis;
        $this->pattern = $pattern;
    }

    /**
     * Subscribe to the pattern topic with a callback
     *
     * @param callable $callback Callback function to handle messages (channel, message)
     */
    public function subscribe(callable $callback): void
    {
        $this->redis->psubscribe([$this->pattern], function ($redis, $pattern, $channel, $message) use ($callback) {
            $decoded = $this->decodeValue($message);
            $callback($channel, $decoded);
        });
    }

    /**
     * Get the number of pattern subscribers
     *
     * @return int
     */
    public function countSubscribers(): int
    {
        $result = $this->redis->pubsub('numpat');
        return is_int($result) ? $result : 0;
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
