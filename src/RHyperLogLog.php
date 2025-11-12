<?php

namespace Rediphp;

use Redis;

/**
 * Redisson-compatible distributed HyperLogLog implementation
 * Uses Redis HyperLogLog structure, compatible with Redisson's RHyperLogLog
 * 
 * HyperLogLog is a probabilistic data structure used for cardinality estimation
 * with a standard error of 0.81% and memory usage of 12KB per key
 */
class RHyperLogLog
{
    private Redis $redis;
    private string $name;

    public function __construct(Redis $redis, string $name)
    {
        $this->redis = $redis;
        $this->name = $name;
    }

    /**
     * Add an element to the HyperLogLog
     *
     * @param mixed $element The element to add
     * @return bool True if the element was added, false otherwise
     */
    public function add($element): bool
    {
        $encoded = $this->encodeValue($element);
        return $this->redis->pfAdd($this->name, [$encoded]) === 1;
    }

    /**
     * Add multiple elements to the HyperLogLog
     *
     * @param array $elements Array of elements to add
     * @return bool True if at least one element was added, false otherwise
     */
    public function addAll(array $elements): bool
    {
        if (empty($elements)) {
            return false;
        }

        $encodedElements = array_map([$this, 'encodeValue'], $elements);
        return $this->redis->pfAdd($this->name, $encodedElements) === 1;
    }

    /**
     * Get the estimated cardinality (number of unique elements)
     *
     * @return int The estimated number of unique elements
     */
    public function count(): int
    {
        return $this->redis->pfCount($this->name);
    }

    /**
     * Get the estimated cardinality (alias for count)
     *
     * @return int The estimated number of unique elements
     */
    public function size(): int
    {
        return $this->count();
    }

    /**
     * Merge this HyperLogLog with other HyperLogLogs
     *
     * @param string|array $otherKeys Single key or array of keys to merge with
     * @param string|null $destinationKey Optional destination key for the merged result
     * @return bool True on success, false on failure
     */
    public function merge($otherKeys, ?string $destinationKey = null): bool
    {
        if (!is_array($otherKeys)) {
            $otherKeys = [$otherKeys];
        }

        $sourceKeys = array_merge([$this->name], $otherKeys);
        $destKey = $destinationKey ?? $this->name;

        return $this->redis->pfMerge($destKey, $sourceKeys);
    }

    /**
     * Check if the HyperLogLog contains any elements
     *
     * @return bool True if the HyperLogLog is empty, false otherwise
     */
    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * Clear all elements from the HyperLogLog
     */
    public function clear(): void
    {
        $this->redis->del($this->name);
    }

    /**
     * Get the memory usage of the HyperLogLog in bytes
     *
     * @return int Memory usage in bytes
     */
    public function getMemoryUsage(): int
    {
        $info = $this->redis->memory('usage', $this->name);
        return $info !== false ? (int)$info : 0;
    }

    /**
     * Check if the HyperLogLog exists
     *
     * @return bool True if the key exists, false otherwise
     */
    public function exists(): bool
    {
        return $this->redis->exists($this->name) > 0;
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