<?php

namespace Rediphp;

use Redis;

/**
 * Redisson-compatible distributed Limit Deque implementation
 * Extends basic Deque functionality with size limits
 */
class RLimitDeque extends RedisDataStructure
{
    private int $maxSize;
    
    public function __construct($connection, string $name, int $maxSize)
    {
        parent::__construct($connection, $name);
        $this->maxSize = $maxSize;
    }

    /**
     * Add an element at the head of the deque (checks limit)
     *
     * @param mixed $element
     * @return bool
     */
    public function addFirst($element): bool
    {
        return $this->executeWithPool(function($redis) use ($element) {
            $encoded = $this->encodeValue($element);
            
            // Check if adding would exceed limit
            if ($redis->lLen($this->name) >= $this->maxSize) {
                return false;
            }
            
            return $redis->lPush($this->name, $encoded) !== false;
        });
    }

    /**
     * Add an element at the tail of the deque (checks limit)
     *
     * @param mixed $element
     * @return bool
     */
    public function addLast($element): bool
    {
        return $this->executeWithPool(function($redis) use ($element) {
            $encoded = $this->encodeValue($element);
            
            // Check if adding would exceed limit
            if ($redis->lLen($this->name) >= $this->maxSize) {
                return false;
            }
            
            return $redis->rPush($this->name, $encoded) !== false;
        });
    }

    /**
     * Add multiple elements to left end
     *
     * @param array $elements
     * @return bool
     */
    public function addAllLeft(array $elements): bool
    {
        return $this->executeWithPool(function($redis) use ($elements) {
            $currentSize = $redis->lLen($this->name);
            $availableSpace = $this->maxSize - $currentSize;
            
            if ($availableSpace <= 0) {
                return false;
            }
            
            // Take only the elements that fit
            $elementsToAdd = array_slice($elements, 0, $availableSpace);
            $encoded = array_map([$this, 'encodeValue'], $elementsToAdd);
            
            if (empty($encoded)) {
                return false;
            }
            
            foreach (array_reverse($encoded) as $value) {
                $redis->lPush($this->name, $value);
            }
            
            return true;
        });
    }

    /**
     * Add multiple elements to right end
     *
     * @param array $elements
     * @return bool
     */
    public function addAllRight(array $elements): bool
    {
        return $this->executeWithPool(function($redis) use ($elements) {
            $currentSize = $redis->lLen($this->name);
            $availableSpace = $this->maxSize - $currentSize;
            
            if ($availableSpace <= 0) {
                return false;
            }
            
            // Take only the elements that fit
            $elementsToAdd = array_slice($elements, 0, $availableSpace);
            $encoded = array_map([$this, 'encodeValue'], $elementsToAdd);
            
            if (empty($encoded)) {
                return false;
            }
            
            foreach ($encoded as $value) {
                $redis->rPush($this->name, $value);
            }
            
            return true;
        });
    }

    /**
     * Add element to left if under limit
     *
     * @param mixed $value
     * @return bool
     */
    public function addLeftIfUnderLimit($value): bool
    {
        return $this->executeWithPool(function($redis) use ($value) {
            if ($redis->lLen($this->name) >= $this->maxSize) {
                return false;
            }
            
            $encoded = $this->encodeValue($value);
            return $redis->lPush($this->name, $encoded) !== false;
        });
    }

    /**
     * Add element to right if under limit
     *
     * @param mixed $value
     * @return bool
     */
    public function addRightIfUnderLimit($value): bool
    {
        return $this->executeWithPool(function($redis) use ($value) {
            if ($redis->lLen($this->name) >= $this->maxSize) {
                return false;
            }
            
            $encoded = $this->encodeValue($value);
            return $redis->rPush($this->name, $encoded) !== false;
        });
    }

    /**
     * Add element to left if not exists
     *
     * @param mixed $value
     * @return bool
     */
    public function addLeftIfNotExists($value): bool
    {
        return $this->executeWithPool(function($redis) use ($value) {
            $encoded = $this->encodeValue($value);
            
            // Check if already exists
            $values = $redis->lRange($this->name, 0, -1);
            if (in_array($encoded, $values, true)) {
                return false;
            }
            
            // Check size limit
            if ($redis->lLen($this->name) >= $this->maxSize) {
                return false;
            }
            
            return $redis->lPush($this->name, $encoded) !== false;
        });
    }

    /**
     * Add element to right if not exists
     *
     * @param mixed $value
     * @return bool
     */
    public function addRightIfNotExists($value): bool
    {
        return $this->executeWithPool(function($redis) use ($value) {
            $encoded = $this->encodeValue($value);
            
            // Check if already exists
            $values = $redis->lRange($this->name, 0, -1);
            if (in_array($encoded, $values, true)) {
                return false;
            }
            
            // Check size limit
            if ($redis->lLen($this->name) >= $this->maxSize) {
                return false;
            }
            
            return $redis->rPush($this->name, $encoded) !== false;
        });
    }

    /**
     * Add multiple elements if not exists
     *
     * @param array $values
     * @return bool
     */
    public function addAllIfNotExists(array $values): bool
    {
        return $this->executeWithPool(function($redis) use ($values) {
            $currentSize = $redis->lLen($this->name);
            $availableSpace = $this->maxSize - $currentSize;
            
            if ($availableSpace <= 0) {
                return false;
            }
            
            $existing = $redis->lRange($this->name, 0, -1);
            $encodedValues = array_map([$this, 'encodeValue'], $values);
            
            // Filter out existing values
            $newValues = array_filter($encodedValues, function($encoded) use ($existing) {
                return !in_array($encoded, $existing, true);
            });
            
            // Take only the new values that fit
            $valuesToAdd = array_slice($newValues, 0, $availableSpace);
            
            if (empty($valuesToAdd)) {
                return false;
            }
            
            foreach (array_reverse($valuesToAdd) as $value) {
                $redis->lPush($this->name, $value);
            }
            
            return true;
        });
    }

    /**
     * Get from left end
     *
     * @return mixed
     */
    public function getLeft()
    {
        return $this->executeWithPool(function($redis) {
            $value = $redis->lPop($this->name);
            return $value !== false ? $this->decodeValue($value) : null;
        });
    }

    /**
     * Get from right end
     *
     * @return mixed
     */
    public function getRight()
    {
        return $this->executeWithPool(function($redis) {
            $value = $redis->rPop($this->name);
            return $value !== false ? $this->decodeValue($value) : null;
        });
    }

    /**
     * Get from left end without removing
     *
     * @return mixed
     */
    public function peekLeft()
    {
        return parent::peekFirst();
    }

    /**
     * Get from right end without removing
     *
     * @return mixed
     */
    public function peekRight()
    {
        return parent::peekLast();
    }

    /**
     * Remove from left end
     *
     * @return mixed
     */
    public function takeLeft()
    {
        return $this->getLeft();
    }

    /**
     * Remove from right end
     *
     * @return mixed
     */
    public function takeRight()
    {
        return $this->getRight();
    }

    /**
     * Remove from left end if value matches
     *
     * @param mixed $value
     * @return bool
     */
    public function takeLeftIf($value): bool
    {
        return $this->executeWithPool(function($redis) use ($value) {
            $encoded = $this->encodeValue($value);
            $firstValue = $redis->lIndex($this->name, 0);
            
            if ($firstValue === $encoded) {
                $redis->lPop($this->name);
                return true;
            }
            
            return false;
        });
    }

    /**
     * Remove from right end if value matches
     *
     * @param mixed $value
     * @return bool
     */
    public function takeRightIf($value): bool
    {
        return $this->executeWithPool(function($redis) use ($value) {
            $encoded = $this->encodeValue($value);
            $lastValue = $redis->lIndex($this->name, -1);
            
            if ($lastValue === $encoded) {
                $redis->rPop($this->name);
                return true;
            }
            
            return false;
        });
    }

    /**
     * Remove from ends based on value matching
     *
     * @param mixed $value
     * @return int Number of elements removed
     */
    public function removeFromEndsIf($value): int
    {
        return $this->executeWithPool(function($redis) use ($value) {
            $encoded = $this->encodeValue($value);
            $removed = 0;
            
            // Remove from left
            while (true) {
                $leftValue = $redis->lIndex($this->name, 0);
                if ($leftValue === $encoded) {
                    $redis->lPop($this->name);
                    $removed++;
                } else {
                    break;
                }
            }
            
            // Remove from right
            while (true) {
                $rightValue = $redis->lIndex($this->name, -1);
                if ($rightValue === $encoded) {
                    $redis->rPop($this->name);
                    $removed++;
                } else {
                    break;
                }
            }
            
            return $removed;
        });
    }

    /**
     * Get maximum size limit
     *
     * @return int
     */
    public function getMaxSize(): int
    {
        return $this->maxSize;
    }

    /**
     * Check if deque is at capacity
     *
     * @return bool
     */
    public function isAtCapacity(): bool
    {
        return $this->executeWithPool(function($redis) {
            return $redis->lLen($this->name) >= $this->maxSize;
        });
    }

    /**
     * Get remaining capacity
     *
     * @return int
     */
    public function getRemainingCapacity(): int
    {
        return $this->executeWithPool(function($redis) {
            $current = $redis->lLen($this->name);
            return max(0, $this->maxSize - $current);
        });
    }
}