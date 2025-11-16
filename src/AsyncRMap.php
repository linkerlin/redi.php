<?php

namespace Rediphp;

use Rediphp\RedisPromise;

/**
 * AsyncRMap - Asynchronous wrapper for RMap
 * Provides Promise-based API for all hash operations
 */
class AsyncRMap
{
    private RMap $map;
    
    public function __construct(RMap $map)
    {
        $this->map = $map;
    }

    /**
     * Get a value from the map
     */
    public function get(string $key): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($key) {
            $resolve($this->map->get($key));
        });
    }

    /**
     * Put a value in the map
     */
    public function put(string $key, $value): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($key, $value) {
            $resolve($this->map->put($key, $value));
        });
    }

    /**
     * Put all values in the map
     */
    public function putAll(array $values): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->map->putAll($values));
        });
    }

    /**
     * Remove a key from the map
     */
    public function remove(string $key): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($key) {
            $resolve($this->map->remove($key));
        });
    }

    /**
     * Check if a key exists
     */
    public function containsKey(string $key): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($key) {
            $resolve($this->map->containsKey($key));
        });
    }

    /**
     * Check if a value exists
     */
    public function containsValue($value): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($value) {
            $resolve($this->map->containsValue($value));
        });
    }

    /**
     * Get all keys
     */
    public function keys(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->map->keys());
        });
    }

    /**
     * Get all values
     */
    public function values(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->map->values());
        });
    }

    /**
     * Get all entries
     */
    public function readAllMap(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->map->readAllMap());
        });
    }

    /**
     * Get size of map
     */
    public function size(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->map->size());
        });
    }

    /**
     * Check if map is empty
     */
    public function isEmpty(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->map->isEmpty());
        });
    }

    /**
     * Clear the map
     */
    public function clear(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->map->clear());
        });
    }

    /**
     * Put if absent
     */
    public function putIfAbsent(string $key, $value): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($key, $value) {
            $resolve($this->map->putIfAbsent($key, $value));
        });
    }

    /**
     * Remove if value matches
     */
    public function removeIfPresent(string $key, $value): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($key, $value) {
            $resolve($this->map->removeIfPresent($key, $value));
        });
    }

    /**
     * Replace if key exists and value matches
     */
    public function replace(string $key, $oldValue, $newValue): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($key, $oldValue, $newValue) {
            $resolve($this->map->replace($key, $oldValue, $newValue));
        });
    }

    /**
     * Replace value for key
     */
    public function replaceValue(string $key, $value): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($key, $value) {
            $resolve($this->map->replaceValue($key, $value));
        });
    }

    /**
     * Increment value
     */
    public function addAndGet(string $key, $delta): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($key, $delta) {
            $resolve($this->map->addAndGet($key, $delta));
        });
    }

    /**
     * Get and set
     */
    public function getAndSet(string $key, $value): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($key, $value) {
            $resolve($this->map->getAndSet($key, $value));
        });
    }

    /**
     * Hash operations - add to value
     */
    public function addToValue(string $key, $delta): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($key, $delta) {
            $resolve($this->map->addToValue($key, $delta));
        });
    }

    /**
     * Batch operations
     */
    public function batchGet(array $keys): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($keys) {
            $results = [];
            foreach ($keys as $key) {
                $results[$key] = $this->map->get($key);
            }
            $resolve($results);
        });
    }

    public function batchPut(array $keyValuePairs): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($keyValuePairs) {
            $results = [];
            foreach ($keyValuePairs as $key => $value) {
                $results[$key] = $this->map->put($key, $value);
            }
            $resolve($results);
        });
    }

    public function batchRemove(array $keys): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($keys) {
            $results = [];
            foreach ($keys as $key) {
                $results[$key] = $this->map->remove($key);
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
                if (method_exists($this->map, 'fastBatch')) {
                    // Use fastBatch if available (pipeline-based)
                    $results = $this->map->fastBatch($operations);
                    $resolve($results);
                } else {
                    // Fallback to regular batch operations
                    $result = $operations($this->map);
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
            if (method_exists($this->map, 'getPipelineStats')) {
                $resolve($this->map->getPipelineStats());
            } else {
                $resolve(['pipeline_supported' => false]);
            }
        });
    }

    /**
     * Get underlying map
     */
    public function getMap(): RMap
    {
        return $this->map;
    }
}