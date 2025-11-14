<?php

namespace Rediphp;

use Redis;

/**
 * Redisson-compatible distributed Deque (Double-ended Queue) implementation
 * Uses Redis List structure, compatible with Redisson's RDeque
 */
class RDeque extends RedisDataStructure
{
    public function __construct($connection, string $name)
    {
        parent::__construct($connection, $name);
    }

    /**
     * Add an element at the head of the deque
     *
     * @param mixed $element
     * @return bool
     */
    public function addFirst($element): bool
    {
        return $this->executeWithPool(function($redis) use ($element) {
            $encoded = $this->encodeValue($element);
            return $redis->lPush($this->name, $encoded) !== false;
        });
    }

    /**
     * Add an element at the tail of the deque
     *
     * @param mixed $element
     * @return bool
     */
    public function addLast($element): bool
    {
        return $this->executeWithPool(function($redis) use ($element) {
            $encoded = $this->encodeValue($element);
            return $redis->rPush($this->name, $encoded) !== false;
        });
    }

    /**
     * Remove and return the first element
     *
     * @return mixed
     */
    public function removeFirst()
    {
        return $this->executeWithPool(function($redis) {
            $value = $redis->lPop($this->name);
            return $value !== false ? $this->decodeValue($value) : null;
        });
    }

    /**
     * Remove and return the last element
     *
     * @return mixed
     */
    public function removeLast()
    {
        return $this->executeWithPool(function($redis) {
            $value = $redis->rPop($this->name);
            return $value !== false ? $this->decodeValue($value) : null;
        });
    }

    /**
     * Get the first element without removing it
     *
     * @return mixed
     */
    public function peekFirst()
    {
        return $this->executeWithPool(function($redis) {
            $value = $redis->lIndex($this->name, 0);
            return $value !== false ? $this->decodeValue($value) : null;
        });
    }

    /**
     * Get the last element without removing it
     *
     * @return mixed
     */
    public function peekLast()
    {
        return $this->executeWithPool(function($redis) {
            $value = $redis->lIndex($this->name, -1);
            return $value !== false ? $this->decodeValue($value) : null;
        });
    }

    /**
     * Get the size of the deque
     *
     * @return int
     */
    public function size(): int
    {
        return $this->executeWithPool(function($redis) {
            return $redis->lLen($this->name);
        });
    }

    /**
     * Check if the deque is empty
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->executeWithPool(function($redis) {
            return $redis->lLen($this->name) === 0;
        });
    }

    /**
     * Clear all elements from the deque
     */
    public function clear(): void
    {
        $this->executeWithPool(function($redis) {
            $redis->del($this->name);
        });
    }

    /**
     * Check if the deque contains an element
     *
     * @param mixed $element
     * @return bool
     */
    public function contains($element): bool
    {
        return $this->executeWithPool(function($redis) use ($element) {
            $values = $redis->lRange($this->name, 0, -1);
            $encoded = $this->encodeValue($element);
            return in_array($encoded, $values, true);
        });
    }

    /**
     * Get all elements as an array
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->executeWithPool(function($redis) {
            $values = $redis->lRange($this->name, 0, -1);
            return array_map(fn($v) => $this->decodeValue($v), $values);
        });
    }


}
