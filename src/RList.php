<?php

namespace Rediphp;

use Redis;

/**
 * Redisson-compatible distributed List implementation
 * Uses Redis List structure, compatible with Redisson's RList
 * Enhanced with Pipeline support for batch operations
 */
class RList extends PipelineableDataStructure
{
    public function __construct($connection, string $name)
    {
        parent::__construct($connection, $name);
    }

    /**
     * Add an element to the end of the list
     *
     * @param mixed $element
     * @return bool
     */
    public function add($element): bool
    {
        return $this->executeWithPool(function($redis) use ($element) {
            $encoded = $this->encodeValue($element);
            return $redis->rPush($this->name, $encoded) !== false;
        });
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
        return $this->executeWithPool(function($redis) use ($index) {
            $value = $redis->lIndex($this->name, $index);
            return $value !== false ? $this->decodeValue($value) : null;
        });
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
        return $this->executeWithPool(function($redis) use ($index, $element) {
            $prevValue = $redis->lIndex($this->name, $index);
            $prev = $prevValue !== false ? $this->decodeValue($prevValue) : null;
            $encoded = $this->encodeValue($element);
            $redis->lSet($this->name, $index, $encoded);
            return $prev;
        });
    }

    /**
     * Remove an element by value
     *
     * @param mixed $element
     * @return bool
     */
    public function remove($element): bool
    {
        return $this->executeWithPool(function($redis) use ($element) {
            $encoded = $this->encodeValue($element);
            return $redis->lRem($this->name, $encoded, 1) > 0;
        });
    }

    /**
     * Remove an element by index
     *
     * @param int $index
     * @return mixed Removed element
     */
    public function removeByIndex(int $index)
    {
        return $this->executeWithPool(function($redis) use ($index) {
            $value = $redis->lIndex($this->name, $index);
            if ($value === false) {
                return null;
            }
            
            $decodedValue = $this->decodeValue($value);
            
            // Use a placeholder to mark for deletion
            $placeholder = '__REDIPHP_REMOVE_' . uniqid() . '__';
            $redis->lSet($this->name, $index, $placeholder);
            $redis->lRem($this->name, $placeholder, 1);
            
            return $decodedValue;
        });
    }

    /**
     * Get the size of the list
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
        $this->executeWithPool(function($redis) {
            $redis->del($this->name);
        });
    }

    /**
     * Check if the list contains an element
     *
     * @param mixed $element
     * @return bool
     */
    public function contains($element): bool
    {
        return $this->executeWithPool(function($redis) use ($element) {
            $values = $redis->lRange($this->name, 0, -1);
            foreach ($values as $value) {
                if ($this->decodeValue($value) === $element) {
                    return true;
                }
            }
            return false;
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
     * Get a range of elements
     *
     * @param int $start
     * @param int $end
     * @return array
     */
    public function range(int $start, int $end): array
    {
        return $this->executeWithPool(function($redis) use ($start, $end) {
            $values = $redis->lRange($this->name, $start, $end);
            return array_map(fn($v) => $this->decodeValue($v), $values);
        });
    }

    /**
     * Trim the list to the specified range
     *
     * @param int $start
     * @param int $end
     */
    public function trim(int $start, int $end): void
    {
        $this->executeWithPool(function($redis) use ($start, $end) {
            $redis->lTrim($this->name, $start, $end);
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
            $batch->listAdd($this->name, $elements);
        });
    }

    /**
     * Pipeline-supported batch get operations
     *
     * @param array $indices Indices to get
     * @return array Results indexed by position
     */
    public function batchGet(array $indices): array
    {
        $results = [];

        return $this->batchRead(function($batch) use ($indices, &$results) {
            foreach ($indices as $index) {
                $batch->getMultiple([$index], function($data) use (&$results, $index) {
                    $results[$index] = $data[$index] ?? null;
                });
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
                $batch->getPipeline()->queueCommand('lRem', [$this->name, $encoded, 1]);
            }
        });
    }

    /**
     * Pipeline-supported bulk get range operation
     *
     * @param array $ranges Array of [start, end] pairs
     * @return array Results indexed by range
     */
    public function batchRange(array $ranges): array
    {
        $results = [];

        return $this->batchRead(function($batch) use ($ranges, &$results) {
            foreach ($ranges as $index => $range) {
                list($start, $end) = $range;
                $batch->listRange($this->name, $start, $end, function($data) use (&$results, $index) {
                    $results[$index] = $data;
                });
            }
        });
    }

    /**
     * Get pipeline statistics for this List
     *
     * @return array
     */
    public function getPipelineStats(): array
    {
        $baseStats = parent::getPipelineStats();
        $baseStats['data_structure'] = 'RList';
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
}
