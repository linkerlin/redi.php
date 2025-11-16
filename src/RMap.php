<?php

namespace Rediphp;

use Redis;

/**
 * Redisson-compatible distributed Map implementation
 * Uses Redis Hash structure, compatible with Redisson's RMap
 * Enhanced with Pipeline support for batch operations
 */
class RMap extends PipelineableDataStructure
{
    public function __construct($connection, string $name)
    {
        parent::__construct($connection, $name);
    }

    /**
     * Put a key-value pair into the map
     *
     * @param mixed $key
     * @param mixed $value
     * @return mixed Previous value or null
     */
    public function put($key, $value)
    {
        return $this->executeWithPool(function(Redis $redis) use ($key, $value) {
            $encodedKey = $this->encodeKey($key);
            $encodedValue = $this->encodeValue($value);
            
            $prev = $redis->hGet($this->name, $encodedKey);
            $redis->hSet($this->name, $encodedKey, $encodedValue);
            
            return $prev !== false ? $this->decodeValue($prev) : null;
        });
    }

    /**
     * Get a value by key
     *
     * @param mixed $key
     * @return mixed
     */
    public function get($key)
    {
        return $this->executeWithPool(function(Redis $redis) use ($key) {
            $encodedKey = $this->encodeKey($key);
            $value = $redis->hGet($this->name, $encodedKey);
            
            return $value !== false ? $this->decodeValue($value) : null;
        });
    }

    /**
     * Remove a key from the map
     *
     * @param mixed $key
     * @return mixed Previous value or null
     */
    public function remove($key)
    {
        return $this->executeWithPool(function(Redis $redis) use ($key) {
            $encodedKey = $this->encodeKey($key);
            $prev = $redis->hGet($this->name, $encodedKey);
            $redis->hDel($this->name, $encodedKey);
            
            return $prev !== false ? $this->decodeValue($prev) : null;
        });
    }

    /**
     * Check if the map contains a key
     *
     * @param mixed $key
     * @return bool
     */
    public function containsKey($key): bool
    {
        return $this->executeWithPool(function(Redis $redis) use ($key) {
            $encodedKey = $this->encodeKey($key);
            return $redis->hExists($this->name, $encodedKey);
        });
    }

    /**
     * Get the size of the map
     *
     * @return int
     */
    public function size(): int
    {
        return $this->executeWithPool(function(Redis $redis) {
            return $redis->hLen($this->name);
        });
    }

    /**
     * Check if the map is empty
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->size() === 0;
    }

    /**
     * Clear all entries from the map
     */
    public function clear(): void
    {
        $this->executeWithPool(function(Redis $redis) {
            $redis->del($this->name);
        });
    }

    /**
     * Get all keys in the map
     *
     * @return array
     */
    public function keySet(): array
    {
        return $this->executeWithPool(function(Redis $redis) {
            $keys = $redis->hKeys($this->name);
            return array_map(fn($k) => $this->decodeKey($k), $keys);
        });
    }

    /**
     * Get all values in the map
     *
     * @return array
     */
    public function values(): array
    {
        return $this->executeWithPool(function(Redis $redis) {
            $values = $redis->hVals($this->name);
            return array_map(fn($v) => $this->decodeValue($v), $values);
        });
    }

    /**
     * Get all entries in the map
     *
     * @return array
     */
    public function entrySet(): array
    {
        return $this->executeWithPool(function(Redis $redis) {
            $entries = $redis->hGetAll($this->name);
            $result = [];
            foreach ($entries as $key => $value) {
                $result[$this->decodeKey($key)] = $this->decodeValue($value);
            }
            return $result;
        });
    }

    /**
     * Put all entries from another array into this map
     *
     * @param array $map
     */
    public function putAll(array $map): void
    {
        $this->executeWithPool(function(Redis $redis) use ($map) {
            $encoded = [];
            foreach ($map as $key => $value) {
                $encoded[$this->encodeKey($key)] = $this->encodeValue($value);
            }
            if (!empty($encoded)) {
                $redis->hMSet($this->name, $encoded);
            }
        });
    }

    /**
     * Put if absent
     *
     * @param mixed $key
     * @param mixed $value
     * @return mixed Previous value or null if absent
     */
    public function putIfAbsent($key, $value)
    {
        return $this->executeWithPool(function(Redis $redis) use ($key, $value) {
            $encodedKey = $this->encodeKey($key);
            $encodedValue = $this->encodeValue($value);
            
            $result = $redis->hSetNx($this->name, $encodedKey, $encodedValue);
            
            if ($result === 0) {
                $existingValue = $redis->hGet($this->name, $encodedKey);
                return $existingValue !== false ? $this->decodeValue($existingValue) : null;
            }
            
            return null;
        });
    }

    /**
     * Replace value for key only if it exists
     *
     * @param mixed $key
     * @param mixed $value
     * @return mixed Previous value or null if key doesn't exist
     */
    public function replace($key, $value)
    {
        if (!$this->containsKey($key)) {
            return null;
        }
        return $this->put($key, $value);
    }

    /**
     * Pipeline-supported batch put operations
     *
     * @param array $map Key-value pairs to put
     * @return array Results from batch operation
     */
    public function batchPut(array $map): array
    {
        return $this->batchWrite(function($batch) use ($map) {
            $encoded = [];
            foreach ($map as $key => $value) {
                $encoded[$this->encodeKey($key)] = $this->encodeValue($value);
            }
            $batch->hashFields($this->name, $encoded);
        });
    }

    /**
     * Pipeline-supported batch get operations
     *
     * @param array $keys Keys to get
     * @return array Results indexed by key
     */
    public function batchGet(array $keys): array
    {
        $results = [];

        return $this->batchRead(function($batch) use ($keys, &$results) {
            $encodedKeys = array_map([$this, 'encodeKey'], $keys);
            $batch->hashFields($this->name, $encodedKeys, function($data) use (&$results, $keys) {
                foreach ($keys as $index => $key) {
                    $encodedKey = $this->encodeKey($key);
                    $value = $data[$encodedKey] ?? null;
                    $results[$key] = $value !== null ? $this->decodeValue($value) : null;
                }
            });
        });
    }

    /**
     * Pipeline-supported batch remove operations
     *
     * @param array $keys Keys to remove
     * @return array Results from batch operation
     */
    public function batchRemove(array $keys): array
    {
        return $this->batchWrite(function($batch) use ($keys) {
            $encodedKeys = array_map([$this, 'encodeKey'], $keys);
            $batch->hashDeleteFields($this->name, $encodedKeys);
        });
    }

    /**
     * Pipeline-supported batch contains check
     *
     * @param array $keys Keys to check
     * @return array Results indexed by key
     */
    public function batchContainsKey(array $keys): array
    {
        $results = [];

        return $this->batchRead(function($batch) use ($keys, &$results) {
            foreach ($keys as $key) {
                $encodedKey = $this->encodeKey($key);
                $batch->getPipeline()->queueCommand('hExists', [$this->name, $encodedKey]);
            }

            $pipelineResults = $batch->getPipeline()->execute();
            foreach ($keys as $index => $key) {
                $result = $pipelineResults[$index] ?? ['success' => false, 'data' => false];
                $results[$key] = $result['success'] && $result['data'] > 0;
            }
        });
    }

    /**
     * Pipeline-supported batch get all entries
     *
     * @param array $keys Keys to get entries for
     * @return array Results indexed by key
     */
    public function batchGetAll(array $keys): array
    {
        $results = [];

        return $this->batchRead(function($batch) use ($keys, &$results) {
            $encodedKeys = array_map([$this, 'encodeKey'], $keys);
            $batch->hashFields($this->name, $encodedKeys, function($data) use (&$results, $keys) {
                foreach ($keys as $key) {
                    $encodedKey = $this->encodeKey($key);
                    $value = $data[$encodedKey] ?? null;
                    $results[$key] = $value !== null ? $this->decodeValue($value) : null;
                }
            });
        });
    }

    /**
     * Get pipeline statistics for this Map
     *
     * @return array
     */
    public function getPipelineStats(): array
    {
        $baseStats = parent::getPipelineStats();
        $baseStats['data_structure'] = 'RMap';
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
     * Pipeline-supported conditional put operations
     *
     * @param array $conditions Array of ['key' => key, 'value' => value, 'condition' => callable]
     * @return array Results from batch operation
     */
    public function batchPutIf(array $conditions): array
    {
        return $this->batchWrite(function($batch) use ($conditions) {
            foreach ($conditions as $condition) {
                $key = $condition['key'] ?? null;
                $value = $condition['value'] ?? null;
                $testFunc = $condition['condition'] ?? null;

                if ($key !== null && $value !== null && $testFunc !== null) {
                    // First check current value
                    $currentValue = $this->get($key);
                    if ($testFunc($currentValue)) {
                        $encodedKey = $this->encodeKey($key);
                        $encodedValue = $this->encodeValue($value);
                        $batch->getPipeline()->queueCommand('hSet', [$this->name, $encodedKey, $encodedValue]);
                    }
                }
            }
        });
    }
}
