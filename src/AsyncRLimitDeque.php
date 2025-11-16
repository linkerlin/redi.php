<?php

namespace Rediphp;

use Rediphp\RedisPromise;

/**
 * AsyncRLimitDeque - Asynchronous wrapper for RLimitDeque
 * Provides Promise-based API for all deque operations with limits
 */
class AsyncRLimitDeque
{
    private RLimitDeque $deque;
    
    public function __construct(RLimitDeque $deque)
    {
        $this->deque = $deque;
    }

    /**
     * Add to left end
     */
    public function addLeft($value): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->deque->addLeft($value));
        });
    }

    /**
     * Add to right end
     */
    public function addRight($value): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->deque->addRight($value));
        });
    }

    /**
     * Add multiple elements to left
     */
    public function addAllLeft(array $values): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->deque->addAllLeft($values));
        });
    }

    /**
     * Add multiple elements to right
     */
    public function addAllRight(array $values): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->deque->addAllRight($values));
        });
    }

    /**
     * Add element to left if below limit
     */
    public function addLeftIfUnderLimit($value): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->deque->addLeftIfUnderLimit($value));
        });
    }

    /**
     * Add element to right if below limit
     */
    public function addRightIfUnderLimit($value): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->deque->addRightIfUnderLimit($value));
        });
    }

    /**
     * Add element to left if not exists
     */
    public function addLeftIfNotExists($value): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->deque->addLeftIfNotExists($value));
        });
    }

    /**
     * Add element to right if not exists
     */
    public function addRightIfNotExists($value): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->deque->addRightIfNotExists($value));
        });
    }

    /**
     * Add multiple if not exists
     */
    public function addAllIfNotExists(array $values): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->deque->addAllIfNotExists($values));
        });
    }

    /**
     * Get from left end
     */
    public function getLeft(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->deque->getLeft());
        });
    }

    /**
     * Get from right end
     */
    public function getRight(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->deque->getRight());
        });
    }

    /**
     * Get from left end without removing
     */
    public function peekLeft(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->deque->peekLeft());
        });
    }

    /**
     * Get from right end without removing
     */
    public function peekRight(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->deque->peekRight());
        });
    }

    /**
     * Remove from left end
     */
    public function takeLeft(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->deque->takeLeft());
        });
    }

    /**
     * Remove from right end
     */
    public function takeRight(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->deque->takeRight());
        });
    }

    /**
     * Remove from left end if value matches
     */
    public function takeLeftIf($value): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->deque->takeLeftIf($value));
        });
    }

    /**
     * Remove from right end if value matches
     */
    public function takeRightIf($value): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->deque->takeRightIf($value));
        });
    }

    /**
     * Remove from ends based on value matching
     */
    public function removeFromEndsIf($value): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->deque->removeFromEndsIf($value));
        });
    }

    /**
     * Get all elements
     */
    public function readAll(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->deque->readAll());
        });
    }

    /**
     * Get size
     */
    public function size(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->deque->size());
        });
    }

    /**
     * Check if empty
     */
    public function isEmpty(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->deque->isEmpty());
        });
    }

    /**
     * Check if full
     */
    public function isFull(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->deque->isFull());
        });
    }

    /**
     * Clear all
     */
    public function clear(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->deque->clear());
        });
    }

    /**
     * Get remaining capacity
     */
    public function remainingCapacity(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->deque->remainingCapacity());
        });
    }

    /**
     * Get max limit
     */
    public function getMax(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->deque->getMax());
        });
    }

    /**
     * Remove all elements
     */
    public function removeAll(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->deque->removeAll());
        });
    }

    /**
     * Remove multiple elements
     */
    public function removeRange(int $start, int $end): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->deque->removeRange($start, $end));
        });
    }

    /**
     * Trim to limit
     */
    public function trim(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->deque->trim());
        });
    }

    /**
     * Check if element exists
     */
    public function contains($value): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->deque->contains($value));
        });
    }

    /**
     * Find index of element
     */
    public function indexOf($value): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->deque->indexOf($value));
        });
    }

    /**
     * Find last index of element
     */
    public function lastIndexOf($value): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->deque->lastIndexOf($value));
        });
    }

    /**
     * Get elements in range
     */
    public function range(int $start, int $end): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->deque->range($start, $end));
        });
    }

    /**
     * Add multiple with filtering
     */
    public function addAll(array $values, bool $skipDuplicates = true): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($values, $skipDuplicates) {
            $resolve($this->deque->addAll($values, $skipDuplicates));
        });
    }

    /**
     * Fast batch operations
     */
    public function batchAddLeft(array $values): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($values) {
            $results = [];
            foreach ($values as $value) {
                $results[] = $this->deque->addLeft($value);
            }
            $resolve($results);
        });
    }

    public function batchAddRight(array $values): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($values) {
            $results = [];
            foreach ($values as $value) {
                $results[] = $this->deque->addRight($value);
            }
            $resolve($results);
        });
    }

    public function batchTakeLeft(int $count): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($count) {
            $results = [];
            for ($i = 0; $i < $count && !$this->deque->isEmpty(); $i++) {
                $results[] = $this->deque->takeLeft();
            }
            $resolve($results);
        });
    }

    public function batchTakeRight(int $count): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($count) {
            $results = [];
            for ($i = 0; $i < $count && !$this->deque->isEmpty(); $i++) {
                $results[] = $this->deque->takeRight();
            }
            $resolve($results);
        });
    }

    public function batchRemove(array $values): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($values) {
            $results = [];
            foreach ($values as $value) {
                // Remove from both ends and return first successful removal
                $results[$value] = $this->deque->removeFromEndsIf($value);
            }
            $resolve($results);
        });
    }

    /**
     * Get elements in order
     */
    public function getOrderedElements(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->deque->readAll());
        });
    }

    /**
     * Remove by order
     */
    public function removeByOrder(int $start, int $end): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->deque->removeRange($start, $end));
        });
    }

    /**
     * Fast batch using pipeline if available
     */
    public function fastBatch(callable $operations): RedisPromise
    {
        return new RedisPromise(function ($resolve, $reject) use ($operations) {
            try {
                if (method_exists($this->deque, 'fastBatch')) {
                    // Use fastBatch if available (pipeline-based)
                    $results = $this->deque->fastBatch($operations);
                    $resolve($results);
                } else {
                    // Fallback to regular batch operations
                    $result = $operations($this->deque);
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
            if (method_exists($this->deque, 'getPipelineStats')) {
                $resolve($this->deque->getPipelineStats());
            } else {
                $resolve(['pipeline_supported' => false]);
            }
        });
    }

    /**
     * Get underlying deque
     */
    public function getDeque(): RLimitDeque
    {
        return $this->deque;
    }
}