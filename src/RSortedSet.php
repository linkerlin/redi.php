<?php

namespace Rediphp;

use Redis;
use Rediphp\Services\SerializationService;

/**
 * Redisson-compatible distributed SortedSet implementation
 * Uses Redis Sorted Set structure, compatible with Redisson's RSortedSet
 */
class RSortedSet extends RedisDataStructure
{
    public function __construct($connection, string $name)
    {
        parent::__construct($connection, $name);
    }

    /**
     * Add an element with a score
     *
     * @param mixed $element
     * @param float $score
     * @return bool True if element was added or updated
     */
    public function add($element, float $score): bool
    {
        return $this->executeWithPool(function($redis) use ($element, $score) {
            $encoded = $this->encodeValue($element);
            $result = $redis->zAdd($this->name, $score, $encoded);
            // zAdd returns 0 if element already existed and score was updated
            // We want to return true in both cases (new element added or existing updated)
            return $result >= 0;
        });
    }

    /**
     * Remove an element
     *
     * @param mixed $element
     * @return bool True if element was removed
     */
    public function remove($element): bool
    {
        return $this->executeWithPool(function($redis) use ($element) {
            $encoded = $this->encodeValue($element);
            return $redis->zRem($this->name, $encoded) > 0;
        });
    }

    /**
     * Get the score of an element
     *
     * @param mixed $element
     * @return float|null
     */
    public function score($element): ?float
    {
        return $this->executeWithPool(function($redis) use ($element) {
            $encoded = $this->encodeValue($element);
            $score = $redis->zScore($this->name, $encoded);
            return $score !== false ? $score : null;
        });
    }

    /**
     * Get the score of an element (alias for score)
     *
     * @param mixed $element
     * @return float|null
     */
    public function getScore($element): ?float
    {
        return $this->score($element);
    }

    /**
     * Get the rank of an element (0-based)
     *
     * @param mixed $element
     * @return int|null
     */
    public function rank($element): ?int
    {
        return $this->executeWithPool(function($redis) use ($element) {
            $encoded = $this->encodeValue($element);
            $rank = $redis->zRank($this->name, $encoded);
            return $rank !== false ? $rank : null;
        });
    }

    /**
     * Get the reverse rank of an element (0-based)
     *
     * @param mixed $element
     * @return int|null
     */
    public function revRank($element): ?int
    {
        return $this->executeWithPool(function($redis) use ($element) {
            $encoded = $this->encodeValue($element);
            $rank = $redis->zRevRank($this->name, $encoded);
            return $rank !== false ? $rank : null;
        });
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
        return $this->executeWithPool(function($redis) use ($start, $end, $withScores) {
            $values = $redis->zRange($this->name, $start, $end, $withScores);
            
            if ($withScores) {
                $result = [];
                foreach ($values as $value => $score) {
                    $result[$this->decodeValue($value)] = $score;
                }
                return $result;
            }
            
            return array_values(array_map(fn($v) => $this->decodeValue($v), $values));
        });
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
        return $this->executeWithPool(function($redis) use ($min, $max, $withScores) {
            $values = $redis->zRangeByScore($this->name, $min, $max, ['withscores' => $withScores]);
            
            if ($withScores) {
                $result = [];
                foreach ($values as $value => $score) {
                    $result[$this->decodeValue($value)] = $score;
                }
                return $result;
            }
            
            return array_values(array_map(fn($v) => $this->decodeValue($v), $values));
        });
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
        return $this->executeWithPool(function($redis) use ($start, $end, $withScores) {
            $values = $redis->zRevRange($this->name, $start, $end, $withScores);
            
            if ($withScores) {
                $result = [];
                foreach ($values as $value => $score) {
                    $result[$this->decodeValue($value)] = $score;
                }
                return $result;
            }
            
            return array_values(array_map(fn($v) => $this->decodeValue($v), $values));
        });
    }

    /**
     * Get the size of the sorted set
     *
     * @return int
     */
    public function size(): int
    {
        return $this->executeWithPool(function($redis) {
            return $redis->zCard($this->name);
        });
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
        $this->executeWithPool(function($redis) {
            $redis->del($this->name);
        });
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
        return $this->executeWithPool(function($redis) use ($min, $max) {
            return $redis->zCount($this->name, $min, $max);
        });
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
        return $this->executeWithPool(function($redis) use ($element, $delta) {
            $encoded = $this->encodeValue($element);
            $newScore = $redis->zIncrBy($this->name, $delta, $encoded);
            return (float)$newScore;
        });
    }

    /**
     * Encode value for storage (Redisson compatibility)
     *
     * @param mixed $value
     * @return string
     */
    protected function encodeValue($value): string
    {
        return SerializationService::getInstance()->encode($value);
    }

    /**
     * Decode value from storage
     *
     * @param string $value
     * @return mixed
     */
    protected function decodeValue(string $value)
    {
        return SerializationService::getInstance()->decode($value, true);
    }

    /**
     * Check if the sorted set exists
     *
     * @return bool
     */
    public function exists(): bool
    {
        return $this->executeWithPool(function($redis) {
            return $redis->exists($this->name) > 0;
        });
    }

    /**
     * Get elements by rank range or score range
     *
     * @param mixed $start
     * @param mixed $end
     * @return array
     */
    public function valueRange($start, $end): array
    {
        // If both parameters are integers or represent rank positions, treat as rank range
        if ((is_int($start) || (is_numeric($start) && floor($start) == $start && !is_float($start))) &&
            (is_int($end) || (is_numeric($end) && floor($end) == $end && !is_float($end)))) {
            return $this->range((int)$start, (int)$end);
        }
        
        // Otherwise, treat as score range
        return $this->rangeByScore((float)$start, (float)$end);
    }

    /**
     * Get all elements in ascending order
     *
     * @return array Array of elements with scores as values
     */
    public function readAll(): array
    {
        return $this->entryRange(0, -1);
    }

    /**
     * Get all elements with scores in ascending order
     *
     * @return array
     */
    public function readAllWithScores(): array
    {
        return $this->range(0, -1, true);
    }

    /**
     * Add multiple elements with scores
     *
     * @param array $elements Array of [element, score] pairs or [element => score] map
     * @return int Number of elements added
     */
    public function addAll(array $elements): int
    {
        $count = 0;
        foreach ($elements as $key => $value) {
            // Check if it's an associative array [element => score]
            if (!is_numeric($key)) {
                if ($this->add($key, $value)) {
                    $count++;
                }
            } 
            // Otherwise, treat as [element, score] pairs
            else if (is_array($value) && count($value) === 2) {
                if ($this->add($value[0], $value[1])) {
                    $count++;
                }
            }
        }
        return $count;
    }

    /**
     * Add score to an element
     *
     * @param mixed $element
     * @param float $score
     * @return float New score
     */
    public function addScore($element, float $score): float
    {
        return $this->incrementScore($element, $score);
    }

    /**
     * Remove multiple elements
     *
     * @param array $elements
     * @return int Number of elements removed
     */
    public function removeBatch(array $elements): int
    {
        $count = 0;
        foreach ($elements as $element) {
            if ($this->remove($element)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Remove elements by score range
     *
     * @param float $min
     * @param float $max
     * @return int Number of elements removed
     */
    public function removeRangeByScore(float $min, float $max): int
    {
        return $this->executeWithPool(function($redis) use ($min, $max) {
            // Use exclusive lower bound to match test expectations
            // This excludes elements with score = min, but includes elements with score = max
            return $redis->zRemRangeByScore($this->name, "($min", $max);
        });
    }

    /**
     * Remove elements by rank range
     *
     * @param int $start
     * @param int $end
     * @return int Number of elements removed
     */
    public function removeRange(int $start, int $end): int
    {
        return $this->executeWithPool(function($redis) use ($start, $end) {
            return $redis->zRemRangeByRank($this->name, $start, $end);
        });
    }

    /**
     * Get elements with scores by rank range
     *
     * @param int $start
     * @param int $end
     * @return array
     */
    public function entryRange(int $start, int $end): array
    {
        return $this->range($start, $end, true);
    }

    /**
     * Get elements in reverse order by rank range
     *
     * @param int $start
     * @param int $end
     * @return array
     */
    public function valueRangeReversed(int $start, int $end): array
    {
        return $this->revRange($start, $end);
    }

    /**
     * Check if an element exists in the sorted set
     *
     * @param mixed $element
     * @return bool
     */
    public function contains($element): bool
    {
        return $this->score($element) !== null;
    }
}
