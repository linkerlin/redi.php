<?php

namespace Rediphp;

use Rediphp\RedisPromise;

/**
 * AsyncRList - Asynchronous wrapper for RList
 * Provides Promise-based API for all list operations
 */
class AsyncRList
{
    private RList $list;
    
    public function __construct(RList $list)
    {
        $this->list = $list;
    }

    /**
     * Add an element to the list
     */
    public function add($value): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($value) {
            $resolve($this->list->add($value));
        });
    }

    /**
     * Add multiple elements
     */
    public function addAll(array $values): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($values) {
            $resolve($this->list->addAll($values));
        });
    }

    /**
     * Add element at specific index
     */
    public function addAt(int $index, $value): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($index, $value) {
            $resolve($this->list->addAt($index, $value));
        });
    }

    /**
     * Add element at the beginning
     */
    public function addFirst($value): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($value) {
            $resolve($this->list->addFirst($value));
        });
    }

    /**
     * Add element at the end
     */
    public function addLast($value): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($value) {
            $resolve($this->list->addLast($value));
        });
    }

    /**
     * Get element at index
     */
    public function get(int $index): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($index) {
            $resolve($this->list->get($index));
        });
    }

    /**
     * Get first element
     */
    public function getFirst(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->list->getFirst());
        });
    }

    /**
     * Get last element
     */
    public function getLast(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->list->getLast());
        });
    }

    /**
     * Set element at index
     */
    public function set(int $index, $value): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($index, $value) {
            $resolve($this->list->set($index, $value));
        });
    }

    /**
     * Remove element at index
     */
    public function remove(int $index): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($index) {
            $resolve($this->list->remove($index));
        });
    }

    /**
     * Remove first occurrence of value
     */
    public function removeValue($value): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($value) {
            $resolve($this->list->removeValue($value));
        });
    }

    /**
     * Remove first element
     */
    public function removeFirst(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->list->removeFirst());
        });
    }

    /**
     * Remove last element
     */
    public function removeLast(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->list->removeLast());
        });
    }

    /**
     * Remove and return first element
     */
    public function takeFirst(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->list->takeFirst());
        });
    }

    /**
     * Remove and return last element
     */
    public function takeLast(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->list->takeLast());
        });
    }

    /**
     * Get range of elements
     */
    public function range(int $start, int $end): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($start, $end) {
            $resolve($this->list->range($start, $end));
        });
    }

    /**
     * Get size of list
     */
    public function size(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->list->size());
        });
    }

    /**
     * Check if list is empty
     */
    public function isEmpty(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->list->isEmpty());
        });
    }

    /**
     * Clear the list
     */
    public function clear(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->list->clear());
        });
    }

    /**
     * Get all elements
     */
    public function readAll(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->list->readAll());
        });
    }

    /**
     * Trim list to specified range
     */
    public function trim(int $start, int $end): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->list->trim($start, $end));
        });
    }

    /**
     * Find index of value
     */
    public function indexOf($value): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->list->indexOf($value));
        });
    }

    /**
     * Find last index of value
     */
    public function lastIndexOf($value): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->list->lastIndexOf($value));
        });
    }

    /**
     * Check if value exists
     */
    public function contains($value): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->list->contains($value));
        });
    }

    /**
     * Fast element addition
     */
    public function addFast($value): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->list->addFast($value));
        });
    }

    /**
     * Element addition in order
     */
    public function addOrdered($value): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->list->addOrdered($value));
        });
    }

    /**
     * Remove range of elements
     */
    public function removeRange(int $start, int $end): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->list->removeRange($start, $end));
        });
    }

    /**
     * Get queue count
     */
    public function getQueueCount(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->list->getQueueCount());
        });
    }

    /**
     * Clear queue
     */
    public function clearQueue(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->list->clearQueue());
        });
    }

    /**
     * Check if queue is empty
     */
    public function isQueueEmpty(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->list->isQueueEmpty());
        });
    }

    /**
     * Batch operations
     */
    public function batchGet(array $indices): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($indices) {
            $results = [];
            foreach ($indices as $index) {
                $results[$index] = $this->list->get($index);
            }
            $resolve($results);
        });
    }

    public function batchAdd(array $values): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($values) {
            $results = [];
            foreach ($values as $value) {
                $results[] = $this->list->add($value);
            }
            $resolve($results);
        });
    }

    public function batchRemove(array $indices): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($indices) {
            $results = [];
            foreach ($indices as $index) {
                $results[$index] = $this->list->remove($index);
            }
            $resolve($results);
        });
    }

    public function batchRange(int $start, int $end): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($start, $end) {
            $resolve($this->list->range($start, $end));
        });
    }

    /**
     * Fast batch operations using pipeline if available
     */
    public function fastBatch(callable $operations): RedisPromise
    {
        return new RedisPromise(function ($resolve, $reject) use ($operations) {
            try {
                if (method_exists($this->list, 'fastBatch')) {
                    // Use fastBatch if available (pipeline-based)
                    $results = $this->list->fastBatch($operations);
                    $resolve($results);
                } else {
                    // Fallback to regular batch operations
                    $result = $operations($this->list);
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
            if (method_exists($this->list, 'getPipelineStats')) {
                $resolve($this->list->getPipelineStats());
            } else {
                $resolve(['pipeline_supported' => false]);
            }
        });
    }

    /**
     * Get underlying list
     */
    public function getList(): RList
    {
        return $this->list;
    }
}