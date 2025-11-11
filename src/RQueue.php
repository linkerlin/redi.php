<?php

namespace Rediphp;

use Redis;

/**
 * Redisson-compatible distributed Queue implementation
 * Uses Redis List structure, compatible with Redisson's RQueue
 */
class RQueue
{
    private Redis $redis;
    private string $name;

    public function __construct(Redis $redis, string $name)
    {
        $this->redis = $redis;
        $this->name = $name;
    }

    /**
     * Add an element to the queue (enqueue)
     *
     * @param mixed $element
     * @return bool
     */
    public function offer($element): bool
    {
        $encoded = $this->encodeValue($element);
        return $this->redis->rPush($this->name, $encoded) !== false;
    }

    /**
     * Add an element to the queue (alias for offer)
     *
     * @param mixed $element
     * @return bool
     */
    public function add($element): bool
    {
        return $this->offer($element);
    }

    /**
     * Retrieve and remove the head of the queue (dequeue)
     *
     * @return mixed
     */
    public function poll()
    {
        $value = $this->redis->lPop($this->name);
        return $value !== false ? $this->decodeValue($value) : null;
    }

    /**
     * Retrieve but do not remove the head of the queue
     *
     * @return mixed
     */
    public function peek()
    {
        $value = $this->redis->lIndex($this->name, 0);
        return $value !== false ? $this->decodeValue($value) : null;
    }

    /**
     * Get the size of the queue
     *
     * @return int
     */
    public function size(): int
    {
        return $this->redis->lLen($this->name);
    }

    /**
     * Check if the queue is empty
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->size() === 0;
    }

    /**
     * Clear all elements from the queue
     */
    public function clear(): void
    {
        $this->redis->del($this->name);
    }

    /**
     * Check if the queue contains an element
     *
     * @param mixed $element
     * @return bool
     */
    public function contains($element): bool
    {
        $all = $this->toArray();
        foreach ($all as $item) {
            if ($item === $element) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get all elements as an array
     *
     * @return array
     */
    public function toArray(): array
    {
        $values = $this->redis->lRange($this->name, 0, -1);
        return array_map(fn($v) => $this->decodeValue($v), $values);
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
