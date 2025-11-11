<?php

namespace Rediphp;

use Redis;

/**
 * Redisson-compatible distributed List implementation
 * Uses Redis List structure, compatible with Redisson's RList
 */
class RList
{
    private Redis $redis;
    private string $name;

    public function __construct(Redis $redis, string $name)
    {
        $this->redis = $redis;
        $this->name = $name;
    }

    /**
     * Add an element to the end of the list
     *
     * @param mixed $element
     * @return bool
     */
    public function add($element): bool
    {
        $encoded = $this->encodeValue($element);
        return $this->redis->rPush($this->name, $encoded) !== false;
    }

    /**
     * Add all elements to the end of the list
     *
     * @param array $elements
     * @return bool
     */
    public function addAll(array $elements): bool
    {
        foreach ($elements as $element) {
            $this->add($element);
        }
        return true;
    }

    /**
     * Get an element by index
     *
     * @param int $index
     * @return mixed
     */
    public function get(int $index)
    {
        $value = $this->redis->lIndex($this->name, $index);
        return $value !== false ? $this->decodeValue($value) : null;
    }

    /**
     * Set an element at a specific index
     *
     * @param int $index
     * @param mixed $element
     * @return mixed Previous value
     */
    public function set(int $index, $element)
    {
        $prev = $this->get($index);
        $encoded = $this->encodeValue($element);
        $this->redis->lSet($this->name, $index, $encoded);
        return $prev;
    }

    /**
     * Remove an element by value
     *
     * @param mixed $element
     * @return bool
     */
    public function remove($element): bool
    {
        $encoded = $this->encodeValue($element);
        return $this->redis->lRem($this->name, $encoded, 1) > 0;
    }

    /**
     * Remove an element by index
     *
     * @param int $index
     * @return mixed Removed element
     */
    public function removeByIndex(int $index)
    {
        $value = $this->get($index);
        if ($value === null) {
            return null;
        }
        
        // Use a placeholder to mark for deletion
        $placeholder = '__REDIPHP_REMOVE_' . uniqid() . '__';
        $this->redis->lSet($this->name, $index, $placeholder);
        $this->redis->lRem($this->name, $placeholder, 1);
        
        return $value;
    }

    /**
     * Get the size of the list
     *
     * @return int
     */
    public function size(): int
    {
        return $this->redis->lLen($this->name);
    }

    /**
     * Check if the list is empty
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->size() === 0;
    }

    /**
     * Clear all elements from the list
     */
    public function clear(): void
    {
        $this->redis->del($this->name);
    }

    /**
     * Check if the list contains an element
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
     * Get a range of elements
     *
     * @param int $start
     * @param int $end
     * @return array
     */
    public function range(int $start, int $end): array
    {
        $values = $this->redis->lRange($this->name, $start, $end);
        return array_map(fn($v) => $this->decodeValue($v), $values);
    }

    /**
     * Trim the list to the specified range
     *
     * @param int $start
     * @param int $end
     */
    public function trim(int $start, int $end): void
    {
        $this->redis->lTrim($this->name, $start, $end);
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
