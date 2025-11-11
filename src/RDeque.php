<?php

namespace Rediphp;

use Redis;

/**
 * Redisson-compatible distributed Deque (Double-ended Queue) implementation
 * Uses Redis List structure, compatible with Redisson's RDeque
 */
class RDeque
{
    private Redis $redis;
    private string $name;

    public function __construct(Redis $redis, string $name)
    {
        $this->redis = $redis;
        $this->name = $name;
    }

    /**
     * Add an element at the head of the deque
     *
     * @param mixed $element
     * @return bool
     */
    public function addFirst($element): bool
    {
        $encoded = $this->encodeValue($element);
        return $this->redis->lPush($this->name, $encoded) !== false;
    }

    /**
     * Add an element at the tail of the deque
     *
     * @param mixed $element
     * @return bool
     */
    public function addLast($element): bool
    {
        $encoded = $this->encodeValue($element);
        return $this->redis->rPush($this->name, $encoded) !== false;
    }

    /**
     * Remove and return the first element
     *
     * @return mixed
     */
    public function removeFirst()
    {
        $value = $this->redis->lPop($this->name);
        return $value !== false ? $this->decodeValue($value) : null;
    }

    /**
     * Remove and return the last element
     *
     * @return mixed
     */
    public function removeLast()
    {
        $value = $this->redis->rPop($this->name);
        return $value !== false ? $this->decodeValue($value) : null;
    }

    /**
     * Get the first element without removing it
     *
     * @return mixed
     */
    public function peekFirst()
    {
        $value = $this->redis->lIndex($this->name, 0);
        return $value !== false ? $this->decodeValue($value) : null;
    }

    /**
     * Get the last element without removing it
     *
     * @return mixed
     */
    public function peekLast()
    {
        $value = $this->redis->lIndex($this->name, -1);
        return $value !== false ? $this->decodeValue($value) : null;
    }

    /**
     * Get the size of the deque
     *
     * @return int
     */
    public function size(): int
    {
        return $this->redis->lLen($this->name);
    }

    /**
     * Check if the deque is empty
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->size() === 0;
    }

    /**
     * Clear all elements from the deque
     */
    public function clear(): void
    {
        $this->redis->del($this->name);
    }

    /**
     * Check if the deque contains an element
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
