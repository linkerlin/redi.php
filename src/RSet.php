<?php

namespace Rediphp;

use Redis;

/**
 * Redisson-compatible distributed Set implementation
 * Uses Redis Set structure, compatible with Redisson's RSet
 */
class RSet
{
    private Redis $redis;
    private string $name;

    public function __construct(Redis $redis, string $name)
    {
        $this->redis = $redis;
        $this->name = $name;
    }

    /**
     * Add an element to the set
     *
     * @param mixed $element
     * @return bool True if element was added
     */
    public function add($element): bool
    {
        $encoded = $this->encodeValue($element);
        return $this->redis->sAdd($this->name, $encoded) > 0;
    }

    /**
     * Add all elements to the set
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
     * Remove an element from the set
     *
     * @param mixed $element
     * @return bool True if element was removed
     */
    public function remove($element): bool
    {
        $encoded = $this->encodeValue($element);
        return $this->redis->sRem($this->name, $encoded) > 0;
    }

    /**
     * Check if the set contains an element
     *
     * @param mixed $element
     * @return bool
     */
    public function contains($element): bool
    {
        $encoded = $this->encodeValue($element);
        return $this->redis->sIsMember($this->name, $encoded);
    }

    /**
     * Get the size of the set
     *
     * @return int
     */
    public function size(): int
    {
        return $this->redis->sCard($this->name);
    }

    /**
     * Check if the set is empty
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->size() === 0;
    }

    /**
     * Clear all elements from the set
     */
    public function clear(): void
    {
        $this->redis->del($this->name);
    }

    /**
     * Get all elements as an array
     *
     * @return array
     */
    public function toArray(): array
    {
        $values = $this->redis->sMembers($this->name);
        return array_map(fn($v) => $this->decodeValue($v), $values);
    }

    /**
     * Get a random element from the set
     *
     * @return mixed
     */
    public function random()
    {
        $value = $this->redis->sRandMember($this->name);
        return $value !== false ? $this->decodeValue($value) : null;
    }

    /**
     * Remove and return a random element
     *
     * @return mixed
     */
    public function removeRandom()
    {
        $value = $this->redis->sPop($this->name);
        return $value !== false ? $this->decodeValue($value) : null;
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
