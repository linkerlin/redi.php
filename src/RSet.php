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
     * Compute the union of this set with another set
     *
     * @param RSet $otherSet
     * @return RSet New set containing the union
     */
    public function union(RSet $otherSet): RSet
    {
        $unionName = $this->name . ':union:' . uniqid();
        $unionSet = new RSet($this->redis, $unionName);
        
        // Get all elements from both sets
        $thisElements = $this->toArray();
        $otherElements = $otherSet->toArray();
        
        // Add all unique elements to the union set
        $allElements = array_unique(array_merge($thisElements, $otherElements));
        $unionSet->addAll($allElements);
        
        return $unionSet;
    }

    /**
     * Compute the intersection of this set with another set
     *
     * @param RSet $otherSet
     * @return RSet New set containing the intersection
     */
    public function intersection(RSet $otherSet): RSet
    {
        $intersectionName = $this->name . ':intersection:' . uniqid();
        $intersectionSet = new RSet($this->redis, $intersectionName);
        
        // Get elements from this set
        $thisElements = $this->toArray();
        
        // Add only elements that exist in both sets
        foreach ($thisElements as $element) {
            if ($otherSet->contains($element)) {
                $intersectionSet->add($element);
            }
        }
        
        return $intersectionSet;
    }

    /**
     * Compute the difference of this set with another set
     *
     * @param RSet $otherSet
     * @return RSet New set containing the difference
     */
    public function difference(RSet $otherSet): RSet
    {
        $differenceName = $this->name . ':difference:' . uniqid();
        $differenceSet = new RSet($this->redis, $differenceName);
        
        // Get elements from this set
        $thisElements = $this->toArray();
        
        // Add only elements that exist in this set but not in the other
        foreach ($thisElements as $element) {
            if (!$otherSet->contains($element)) {
                $differenceSet->add($element);
            }
        }
        
        return $differenceSet;
    }

    /**
     * Remove all specified elements from the set
     *
     * @param array $elements
     * @return int Number of elements removed
     */
    public function removeAll(array $elements): int
    {
        $removedCount = 0;
        foreach ($elements as $element) {
            if ($this->remove($element)) {
                $removedCount++;
            }
        }
        return $removedCount;
    }

    /**
     * Check if the set exists (has any elements)
     *
     * @return bool
     */
    public function exists(): bool
    {
        return $this->redis->exists($this->name) && $this->size() > 0;
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
