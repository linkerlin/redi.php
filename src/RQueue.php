<?php

namespace Rediphp;

use Redis;

/**
 * Redisson-compatible distributed Queue implementation
 * Uses Redis List structure, compatible with Redisson's RQueue
 */
class RQueue extends RedisDataStructure
{
    public function __construct($connection, string $name)
    {
        parent::__construct($connection, $name);
    }

    /**
     * Add an element to the queue (enqueue)
     *
     * @param mixed $element
     * @return bool
     */
    public function offer($element): bool
    {
        return $this->executeWithPool(function($redis) use ($element) {
            $encoded = $this->encodeValue($element);
            return $redis->rPush($this->name, $encoded) !== false;
        });
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
        return $this->executeWithPool(function($redis) {
            $value = $redis->lPop($this->name);
            return $value !== false ? $this->decodeValue($value) : null;
        });
    }

    /**
     * Retrieve but do not remove the head of the queue
     *
     * @return mixed
     */
    public function peek()
    {
        return $this->executeWithPool(function($redis) {
            $value = $redis->lIndex($this->name, 0);
            return $value !== false ? $this->decodeValue($value) : null;
        });
    }

    /**
     * Get the size of the queue
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
     * Check if the queue is empty
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
     * Clear all elements from the queue
     */
    public function clear(): void
    {
        $this->executeWithPool(function($redis) {
            $redis->del($this->name);
        });
    }

    /**
     * Check if an element exists in the queue
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

    /**
     * Remove a specific element from the queue
     *
     * @param mixed $element
     * @return bool True if element was found and removed
     */
    public function remove($element): bool
    {
        return $this->executeWithPool(function($redis) use ($element) {
            $encoded = $this->encodeValue($element);
            $count = $redis->lRem($this->name, $encoded, 1); // Remove first occurrence
            return $count > 0;
        });
    }

    /**
     * Remove all specified elements from the queue
     *
     * @param array $elements
     * @return int Number of elements removed
     */
    public function removeAll(array $elements): int
    {
        return $this->executeWithPool(function($redis) use ($elements) {
            $removedCount = 0;
            foreach ($elements as $element) {
                $encoded = $this->encodeValue($element);
                $count = $redis->lRem($this->name, $encoded, 1);
                if ($count > 0) {
                    $removedCount++;
                }
            }
            return $removedCount;
        });
    }

    /**
     * Check if the queue exists (has any elements)
     *
     * @return bool
     */
    public function exists(): bool
    {
        return $this->executeWithPool(function($redis) {
            return $redis->exists($this->name) && $redis->lLen($this->name) > 0;
        });
    }


}
