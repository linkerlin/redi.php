<?php

namespace Rediphp;

use Redis;

/**
 * Redisson-compatible distributed BitSet implementation
 * Uses Redis Bitmap for distributed bitset, compatible with Redisson's RBitSet
 */
class RBitSet
{
    private Redis $redis;
    private string $name;

    public function __construct(Redis $redis, string $name)
    {
        $this->redis = $redis;
        $this->name = $name;
    }

    /**
     * Set a bit to 1
     *
     * @param int $bitIndex
     * @return bool Previous value
     */
    public function set(int $bitIndex): bool
    {
        $prev = $this->redis->getBit($this->name, $bitIndex);
        $this->redis->setBit($this->name, $bitIndex, 1);
        return $prev === 1;
    }

    /**
     * Set a bit to a specific value
     *
     * @param int $bitIndex
     * @param bool $value
     * @return bool Previous value
     */
    public function setBit(int $bitIndex, bool $value): bool
    {
        $prev = $this->redis->getBit($this->name, $bitIndex);
        $this->redis->setBit($this->name, $bitIndex, $value ? 1 : 0);
        return $prev === 1;
    }

    /**
     * Get a bit value
     *
     * @param int $bitIndex
     * @return bool
     */
    public function get(int $bitIndex): bool
    {
        return $this->redis->getBit($this->name, $bitIndex) === 1;
    }

    /**
     * Clear a bit (set to 0)
     *
     * @param int $bitIndex
     */
    public function clear(int $bitIndex): void
    {
        $this->redis->setBit($this->name, $bitIndex, 0);
    }

    /**
     * Clear all bits
     */
    public function clearAll(): void
    {
        $this->redis->del($this->name);
    }

    /**
     * Count the number of bits set to 1
     *
     * @return int
     */
    public function cardinality(): int
    {
        return $this->redis->bitCount($this->name);
    }

    /**
     * Get the length of the bitset
     *
     * @return int
     */
    public function length(): int
    {
        $value = $this->redis->get($this->name);
        return $value !== false ? strlen($value) * 8 : 0;
    }

    /**
     * Check if the bitset is empty
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->cardinality() === 0;
    }

    /**
     * Delete the bitset
     *
     * @return bool
     */
    public function delete(): bool
    {
        return $this->redis->del($this->name) > 0;
    }

    /**
     * Convert to byte array
     *
     * @return array
     */
    public function toByteArray(): array
    {
        $value = $this->redis->get($this->name);
        if ($value === false) {
            return [];
        }
        return array_values(unpack('C*', $value));
    }
}
