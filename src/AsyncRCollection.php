<?php

namespace Rediphp;

use Rediphp\RedisPromise;

/**
 * AsyncRCollection - Asynchronous wrapper for RCollection
 * Provides Promise-based API for all collection operations
 */
class AsyncRCollection
{
    private RCollection $collection;
    
    public function __construct(RCollection $collection)
    {
        $this->collection = $collection;
    }

    /**
     * Add an element
     */
    public function add($element): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->collection->add($element));
        });
    }

    /**
     * Add multiple elements
     */
    public function addAll(array $elements): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->collection->addAll($elements));
        });
    }

    /**
     * Add element if not exists
     */
    public function addIfNotExists($element): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->collection->addIfNotExists($element));
        });
    }

    /**
     * Add all if not exists
     */
    public function addAllIfNotExists(array $elements): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->collection->addAllIfNotExists($elements));
        });
    }

    /**
     * Add with condition
     */
    public function addIf(callable $condition, $element): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($condition, $element) {
            $resolve($this->collection->addIf($condition, $element));
        });
    }

    /**
     * Add element in order
     */
    public function addOrdered($element): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->collection->addOrdered($element));
        });
    }

    /**
     * Add multiple elements in order
     */
    public function addAllOrdered(array $elements): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->collection->addAllOrdered($elements));
        });
    }

    /**
     * Remove element
     */
    public function remove($element): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->collection->remove($element));
        });
    }

    /**
     * Remove if exists
     */
    public function removeIfExists($element): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->collection->removeIfExists($element));
        });
    }

    /**
     * Remove with condition
     */
    public function removeIf(callable $condition, $element): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($condition, $element) {
            $resolve($this->collection->removeIf($condition, $element));
        });
    }

    /**
     * Remove all elements
     */
    public function removeAll(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->collection->removeAll());
        });
    }

    /**
     * Remove all matching condition
     */
    public function removeAllIf(callable $condition): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($condition) {
            $resolve($this->collection->removeAllIf($condition));
        });
    }

    /**
     * Remove range of elements
     */
    public function removeRange(int $start, int $end): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->collection->removeRange($start, $end));
        });
    }

    /**
     * Get element at index
     */
    public function get(int $index): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->collection->get($index));
        });
    }

    /**
     * Set element at index
     */
    public function set(int $index, $element): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->collection->set($index, $element));
        });
    }

    /**
     * Get range of elements
     */
    public function range(int $start, int $end): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->collection->range($start, $end));
        });
    }

    /**
     * Find element index
     */
    public function indexOf($element): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->collection->indexOf($element));
        });
    }

    /**
     * Find last element index
     */
    public function lastIndexOf($element): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->collection->lastIndexOf($element));
        });
    }

    /**
     * Find all indices of element
     */
    public function indicesOf($element): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->collection->indicesOf($element));
        });
    }

    /**
     * Get size of collection
     */
    public function size(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->collection->size());
        });
    }

    /**
     * Check if empty
     */
    public function isEmpty(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->collection->isEmpty());
        });
    }

    /**
     * Check if element exists
     */
    public function contains($element): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->collection->contains($element));
        });
    }

    /**
     * Check if all elements exist
     */
    public function containsAll(array $elements): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->collection->containsAll($elements));
        });
    }

    /**
     * Check if any of elements exist
     */
    public function containsAny(array $elements): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->collection->containsAny($elements));
        });
    }

    /**
     * Get all elements
     */
    public function readAll(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->collection->readAll());
        });
    }

    /**
     * Clear all elements
     */
    public function clear(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->collection->clear());
        });
    }

    /**
     * Get random element
     */
    public function getRandom(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->collection->getRandom());
        });
    }

    /**
     * Get random elements
     */
    public function getRandomMultiple(int $count): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($count) {
            $resolve($this->collection->getRandomMultiple($count));
        });
    }

    /**
     * Remove random element
     */
    public function removeRandom(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->collection->removeRandom());
        });
    }

    /**
     * Remove random elements
     */
    public function removeRandomMultiple(int $count): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($count) {
            $resolve($this->collection->removeRandomMultiple($count));
        });
    }

    /**
     * Fast element addition
     */
    public function addFast($element): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->collection->addFast($element));
        });
    }

    /**
     * Fast element removal
     */
    public function removeFast($element): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->collection->removeFast($element));
        });
    }

    /**
     * Fast element check
     */
    public function containsFast($element): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->collection->containsFast($element));
        });
    }

    /**
     * Batch operations
     */
    public function batchAdd(array $elements): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($elements) {
            $results = [];
            foreach ($elements as $element) {
                $results[] = $this->collection->add($element);
            }
            $resolve($results);
        });
    }

    public function batchRemove(array $elements): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($elements) {
            $results = [];
            foreach ($elements as $element) {
                $results[] = $this->collection->remove($element);
            }
            $resolve($results);
        });
    }

    public function batchContains(array $elements): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($elements) {
            $results = [];
            foreach ($elements as $element) {
                $results[$element] = $this->collection->contains($element);
            }
            $resolve($results);
        });
    }

    public function batchGet(array $indices): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($indices) {
            $results = [];
            foreach ($indices as $index) {
                $results[$index] = $this->collection->get($index);
            }
            $resolve($results);
        });
    }

    public function batchSet(array $elements): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($elements) {
            $results = [];
            foreach ($elements as $index => $element) {
                if (is_int($index)) {
                    $results[$index] = $this->collection->set($index, $element);
                }
            }
            $resolve($results);
        });
    }

    public function batchRemoveRange(int $start, int $end): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($start, $end) {
            $resolve($this->collection->removeRange($start, $end));
        });
    }

    public function batchGetRange(int $start, int $end): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($start, $end) {
            $resolve($this->collection->range($start, $end));
        });
    }

    public function batchRemoveRandom(int $count): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($count) {
            $resolve($this->collection->removeRandomMultiple($count));
        });
    }

    public function batchGetRandom(int $count): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($count) {
            $resolve($this->collection->getRandomMultiple($count));
        });
    }

    public function batchIfExists(array $elements): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($elements) {
            $results = [];
            foreach ($elements as $element) {
                $results[$element] = $this->collection->contains($element);
            }
            $resolve($results);
        });
    }

    /**
     * Fast batch operations using pipeline if available
     */
    public function fastBatch(callable $operations): RedisPromise
    {
        return new RedisPromise(function ($resolve, $reject) use ($operations) {
            try {
                if (method_exists($this->collection, 'fastBatch')) {
                    // Use fastBatch if available (pipeline-based)
                    $results = $this->collection->fastBatch($operations);
                    $resolve($results);
                } else {
                    // Fallback to regular batch operations
                    $result = $operations($this->collection);
                    $resolve($result);
                }
            } catch (\Exception $e) {
                $reject($e);
            }
        });
    }

    /**
     * Get pipeline stats
     */
    public function getPipelineStats(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            if (method_exists($this->collection, 'getPipelineStats')) {
                $resolve($this->collection->getPipelineStats());
            } else {
                $resolve(['pipeline_supported' => false]);
            }
        });
    }

    /**
     * Get underlying collection
     */
    public function getCollection(): RCollection
    {
        return $this->collection;
    }
}