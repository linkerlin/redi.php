<?php

namespace Rediphp;

use Redis;

/**
 * Redisson-compatible distributed Set implementation
 * Uses Redis Set structure, compatible with Redisson's RSet
 * Enhanced with Pipeline support for batch operations
 */
class RSet extends PipelineableDataStructure
{
    public function __construct($connection, string $name)
    {
        parent::__construct($connection, $name);
    }

    /**
     * Add an element to the set
     *
     * @param mixed $element
     * @return bool True if element was added
     */
    public function add($element): bool
    {
        return $this->executeWithPool(function($redis) use ($element) {
            $encoded = $this->encodeValue($element);
            return $redis->sAdd($this->name, $encoded) > 0;
        });
    }

    /**
     * Add all elements to the set
     *
     * @param array $elements
     * @return bool
     */
    public function addAll(array $elements): bool
    {
        return $this->executeWithPool(function($redis) use ($elements) {
            foreach ($elements as $element) {
                $encoded = $this->encodeValue($element);
                $redis->sAdd($this->name, $encoded);
            }
            return true;
        });
    }

    /**
     * Remove an element from the set
     *
     * @param mixed $element
     * @return bool True if element was removed
     */
    public function remove($element): bool
    {
        return $this->executeWithPool(function($redis) use ($element) {
            $encoded = $this->encodeValue($element);
            return $redis->sRem($this->name, $encoded) > 0;
        });
    }

    /**
     * Check if the set contains an element
     *
     * @param mixed $element
     * @return bool
     */
    public function contains($element): bool
    {
        return $this->executeWithPool(function($redis) use ($element) {
            $encoded = $this->encodeValue($element);
            return $redis->sIsMember($this->name, $encoded);
        });
    }

    /**
     * Get the size of the set
     *
     * @return int
     */
    public function size(): int
    {
        return $this->executeWithPool(function($redis) {
            return $redis->sCard($this->name);
        });
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
        $this->executeWithPool(function($redis) {
            $redis->del($this->name);
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
            $values = $redis->sMembers($this->name);
            return array_map(fn($v) => $this->decodeValue($v), $values);
        });
    }

    /**
     * Get a random element from the set
     *
     * @return mixed
     */
    public function random()
    {
        return $this->executeWithPool(function($redis) {
            $value = $redis->sRandMember($this->name);
            return $value !== false ? $this->decodeValue($value) : null;
        });
    }

    /**
     * Remove and return a random element
     *
     * @return mixed
     */
    public function removeRandom()
    {
        return $this->executeWithPool(function($redis) {
            $value = $redis->sPop($this->name);
            return $value !== false ? $this->decodeValue($value) : null;
        });
    }

    /**
     * Compute the union of this set with another set
     *
     * @param RSet $otherSet
     * @return RSet New set containing the union
     */
    public function union(RSet $otherSet): RSet
    {
        return $this->executeWithPool(function($redis) use ($otherSet) {
            $unionName = $this->name . ':union:' . uniqid();
            $unionSet = new RSet($this->connection ?: $redis, $unionName);
            
            // Get all elements from both sets
            $thisElements = $this->toArray();
            $otherElements = $otherSet->toArray();
            
            // Add all unique elements to the union set
            $allElements = array_unique(array_merge($thisElements, $otherElements));
            $unionSet->addAll($allElements);
            
            return $unionSet;
        });
    }

    /**
     * Compute the intersection of this set with another set
     *
     * @param RSet $otherSet
     * @return RSet New set containing the intersection
     */
    public function intersection(RSet $otherSet): RSet
    {
        return $this->executeWithPool(function($redis) use ($otherSet) {
            $intersectionName = $this->name . ':intersection:' . uniqid();
            $intersectionSet = new RSet($this->connection ?: $redis, $intersectionName);
            
            // Get elements from this set
            $thisElements = $this->toArray();
            
            // Add only elements that exist in both sets
            foreach ($thisElements as $element) {
                if ($otherSet->contains($element)) {
                    $intersectionSet->add($element);
                }
            }
            
            return $intersectionSet;
        });
    }

    /**
     * Compute the difference of this set with another set
     *
     * @param RSet $otherSet
     * @return RSet New set containing the difference
     */
    public function difference(RSet $otherSet): RSet
    {
        return $this->executeWithPool(function($redis) use ($otherSet) {
            $differenceName = $this->name . ':difference:' . uniqid();
            $differenceSet = new RSet($this->connection ?: $redis, $differenceName);
            
            // Get elements from this set
            $thisElements = $this->toArray();
            
            // Add only elements that exist in this set but not in the other
            foreach ($thisElements as $element) {
                if (!$otherSet->contains($element)) {
                    $differenceSet->add($element);
                }
            }
            
            return $differenceSet;
        });
    }

    /**
     * Remove all specified elements from the set
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
                if ($redis->sRem($this->name, $encoded) > 0) {
                    $removedCount++;
                }
            }
            return $removedCount;
        });
    }

    /**
     * Check if the set exists (has any elements)
     *
     * @return bool
     */
    public function exists(): bool
    {
        return $this->executeWithPool(function($redis) {
            return $redis->exists($this->name) && $this->size() > 0;
        });
    }

    /**
     * Pipeline-supported batch add operations
     *
     * @param array $elements Elements to add
     * @return array Results from batch operation
     */
    public function batchAdd(array $elements): array
    {
        return $this->batchWrite(function($batch) use ($elements) {
            foreach ($elements as $element) {
                $encoded = $this->encodeValue($element);
                $batch->getPipeline()->queueCommand('sAdd', [$this->name, $encoded]);
            }
        });
    }

    /**
     * Pipeline-supported batch remove operations
     *
     * @param array $elements Elements to remove
     * @return array Results from batch operation
     */
    public function batchRemove(array $elements): array
    {
        return $this->batchWrite(function($batch) use ($elements) {
            foreach ($elements as $element) {
                $encoded = $this->encodeValue($element);
                $batch->getPipeline()->queueCommand('sRem', [$this->name, $encoded]);
            }
        });
    }

    /**
     * Pipeline-supported batch contains check
     *
     * @param array $elements Elements to check
     * @return array Results indexed by element
     */
    public function batchContains(array $elements): array
    {
        $results = [];

        return $this->batchRead(function($batch) use ($elements, &$results) {
            foreach ($elements as $element) {
                $encoded = $this->encodeValue($element);
                $batch->getPipeline()->queueCommand('sIsMember', [$this->name, $encoded]);
            }

            $pipelineResults = $batch->getPipeline()->execute();
            foreach ($elements as $index => $element) {
                $result = $pipelineResults[$index] ?? ['success' => false, 'data' => false];
                $results[$element] = $result['success'] && $result['data'] > 0;
            }
        });
    }

    /**
     * Pipeline-supported batch get all elements with membership check
     *
     * @param array $elements Elements to check existence
     * @return array Results with member status
     */
    public function batchGetWithMembership(array $elements): array
    {
        $results = [];

        return $this->batchRead(function($batch) use ($elements, &$results) {
            foreach ($elements as $element) {
                $encoded = $this->encodeValue($element);
                $batch->getPipeline()->queueCommand('sIsMember', [$this->name, $encoded]);
            }

            $pipelineResults = $batch->getPipeline()->execute();
            foreach ($elements as $index => $element) {
                $result = $pipelineResults[$index] ?? ['success' => false, 'data' => false];
                $results[$element] = [
                    'exists' => $result['success'] && $result['data'] > 0,
                    'value' => $element
                ];
            }
        });
    }

    /**
     * Pipeline-supported batch size check
     *
     * @param array $sets Set names to check sizes
     * @return array Results indexed by set name
     */
    public function batchGetSizes(array $sets): array
    {
        $results = [];

        return $this->batchRead(function($batch) use ($sets, &$results) {
            foreach ($sets as $setName) {
                $batch->getPipeline()->queueCommand('scard', [$setName]);
            }

            $pipelineResults = $batch->getPipeline()->execute();
            foreach ($sets as $index => $setName) {
                $result = $pipelineResults[$index] ?? ['success' => false, 'data' => 0];
                $results[$setName] = $result['success'] ? $result['data'] : 0;
            }
        });
    }

    /**
     * Get pipeline statistics for this Set
     *
     * @return array
     */
    public function getPipelineStats(): array
    {
        $baseStats = parent::getPipelineStats();
        $baseStats['data_structure'] = 'RSet';
        $baseStats['name'] = $this->name;

        return $baseStats;
    }

    /**
     * Performance-optimized bulk operations using fast pipeline
     *
     * @param callable $operations
     * @return array
     */
    public function fastBatch(callable $operations): array
    {
        return parent::fastPipeline($operations, $this->name . '_fast');
    }

    /**
     * Pipeline-supported conditional add operations
     *
     * @param array $conditions Array of ['element' => element, 'condition' => callable]
     * @return array Results from batch operation
     */
    public function batchAddIf(array $conditions): array
    {
        return $this->batchWrite(function($batch) use ($conditions) {
            foreach ($conditions as $condition) {
                $element = $condition['element'] ?? null;
                $testFunc = $condition['condition'] ?? null;

                if ($element !== null && $testFunc !== null) {
                    // Check if we should add based on condition
                    if ($testFunc($this->size())) {
                        $encoded = $this->encodeValue($element);
                        $batch->getPipeline()->queueCommand('sAdd', [$this->name, $encoded]);
                    }
                }
            }
        });
    }

    /**
     * Pipeline-supported batch union with other sets
     *
     * @param array $otherSets Array of RSet instances
     * @return array Results from batch operation
     */
    public function batchUnion(array $otherSets): array
    {
        return $this->batchWrite(function($batch) use ($otherSets) {
            foreach ($otherSets as $setName => $set) {
                if ($set instanceof RSet) {
                    // Get elements from other set and add to current
                    $elements = $set->toArray();
                    foreach ($elements as $element) {
                        $encoded = $this->encodeValue($element);
                        $batch->getPipeline()->queueCommand('sAdd', [$this->name, $encoded]);
                    }
                }
            }
        });
    }

    /**
     * Pipeline-supported batch intersection with other sets
     *
     * @param array $otherSets Array of RSet instances  
     * @return array Results from batch operation
     */
    public function batchIntersection(array $otherSets): array
    {
        return $this->batchWrite(function($batch) use ($otherSets) {
            // Get current set elements
            $currentElements = $this->toArray();
            
            foreach ($otherSets as $set) {
                if ($set instanceof RSet) {
                    // Keep only elements that exist in all sets
                    $intersection = [];
                    foreach ($currentElements as $element) {
                        if ($set->contains($element)) {
                            $intersection[] = $element;
                        }
                    }
                    $currentElements = $intersection;
                }
            }
            
            // Clear current set and add intersection
            $batch->getPipeline()->queueCommand('del', [$this->name]);
            foreach ($currentElements as $element) {
                $encoded = $this->encodeValue($element);
                $batch->getPipeline()->queueCommand('sAdd', [$this->name, $encoded]);
            }
        });
    }

    /**
     * Pipeline-supported bulk random operations
     *
     * @param int $count Number of random elements to get
     * @param bool $remove Whether to remove the elements
     * @return array Results from batch operation
     */
    public function batchRandom(int $count = 1, bool $remove = false): array
    {
        $results = [];

        return $this->batchRead(function($batch) use ($count, $remove, &$results) {
            $command = $remove ? 'sPop' : 'sRandMember';
            $batch->getPipeline()->queueCommand($command, [$this->name, $count]);
        });
    }
}
