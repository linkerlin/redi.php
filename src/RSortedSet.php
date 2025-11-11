<?php

namespace Rediphp;

use Redis;

/**
 * Redisson-compatible distributed SortedSet implementation
 * Uses Redis Sorted Set structure, compatible with Redisson's RSortedSet
 */
class RSortedSet
{
    private Redis $redis;
    private string $name;

    public function __construct(Redis $redis, string $name)
    {
        $this->redis = $redis;
        $this->name = $name;
    }

    /**
     * Add an element with a score
     *
     * @param float $score
     * @param mixed $element
     * @return bool True if element was added
     */
    public function add(float $score, $element): bool
    {
        $encoded = $this->encodeValue($element);
        return $this->redis->zAdd($this->name, $score, $encoded) > 0;
    }

    /**
     * Remove an element
     *
     * @param mixed $element
     * @return bool True if element was removed
     */
    public function remove($element): bool
    {
        $encoded = $this->encodeValue($element);
        return $this->redis->zRem($this->name, $encoded) > 0;
    }

    /**
     * Get the score of an element
     *
     * @param mixed $element
     * @return float|null
     */
    public function score($element): ?float
    {
        $encoded = $this->encodeValue($element);
        $score = $this->redis->zScore($this->name, $encoded);
        return $score !== false ? $score : null;
    }

    /**
     * Get the rank of an element (0-based)
     *
     * @param mixed $element
     * @return int|null
     */
    public function rank($element): ?int
    {
        $encoded = $this->encodeValue($element);
        $rank = $this->redis->zRank($this->name, $encoded);
        return $rank !== false ? $rank : null;
    }

    /**
     * Get the reverse rank of an element (0-based)
     *
     * @param mixed $element
     * @return int|null
     */
    public function revRank($element): ?int
    {
        $encoded = $this->encodeValue($element);
        $rank = $this->redis->zRevRank($this->name, $encoded);
        return $rank !== false ? $rank : null;
    }

    /**
     * Get elements by rank range
     *
     * @param int $start
     * @param int $end
     * @param bool $withScores
     * @return array
     */
    public function range(int $start, int $end, bool $withScores = false): array
    {
        $values = $this->redis->zRange($this->name, $start, $end, $withScores);
        
        if ($withScores) {
            $result = [];
            foreach ($values as $value => $score) {
                $result[$this->decodeValue($value)] = $score;
            }
            return $result;
        }
        
        return array_map(fn($v) => $this->decodeValue($v), $values);
    }

    /**
     * Get elements by score range
     *
     * @param float $min
     * @param float $max
     * @param bool $withScores
     * @return array
     */
    public function rangeByScore(float $min, float $max, bool $withScores = false): array
    {
        $values = $this->redis->zRangeByScore($this->name, $min, $max, ['withscores' => $withScores]);
        
        if ($withScores) {
            $result = [];
            foreach ($values as $value => $score) {
                $result[$this->decodeValue($value)] = $score;
            }
            return $result;
        }
        
        return array_map(fn($v) => $this->decodeValue($v), $values);
    }

    /**
     * Get elements in reverse order by rank range
     *
     * @param int $start
     * @param int $end
     * @param bool $withScores
     * @return array
     */
    public function revRange(int $start, int $end, bool $withScores = false): array
    {
        $values = $this->redis->zRevRange($this->name, $start, $end, $withScores);
        
        if ($withScores) {
            $result = [];
            foreach ($values as $value => $score) {
                $result[$this->decodeValue($value)] = $score;
            }
            return $result;
        }
        
        return array_map(fn($v) => $this->decodeValue($v), $values);
    }

    /**
     * Get the size of the sorted set
     *
     * @return int
     */
    public function size(): int
    {
        return $this->redis->zCard($this->name);
    }

    /**
     * Check if the sorted set is empty
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->size() === 0;
    }

    /**
     * Clear all elements from the sorted set
     */
    public function clear(): void
    {
        $this->redis->del($this->name);
    }

    /**
     * Count elements with scores in the given range
     *
     * @param float $min
     * @param float $max
     * @return int
     */
    public function count(float $min, float $max): int
    {
        return $this->redis->zCount($this->name, $min, $max);
    }

    /**
     * Increment the score of an element
     *
     * @param mixed $element
     * @param float $delta
     * @return float New score
     */
    public function incrementScore($element, float $delta): float
    {
        $encoded = $this->encodeValue($element);
        return $this->redis->zIncrBy($this->name, $delta, $encoded);
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
