<?php

namespace Rediphp;

use Redis;

/**
 * Redisson-compatible distributed Map implementation
 * Uses Redis Hash structure, compatible with Redisson's RMap
 */
class RMap
{
    private Redis $redis;
    private string $name;

    public function __construct(Redis $redis, string $name)
    {
        $this->redis = $redis;
        $this->name = $name;
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
        $encodedKey = $this->encodeKey($key);
        $encodedValue = $this->encodeValue($value);
        
        $prev = $this->redis->hGet($this->name, $encodedKey);
        $this->redis->hSet($this->name, $encodedKey, $encodedValue);
        
        return $prev !== false ? $this->decodeValue($prev) : null;
    }

    /**
     * Get a value by key
     *
     * @param mixed $key
     * @return mixed
     */
    public function get($key)
    {
        $encodedKey = $this->encodeKey($key);
        $value = $this->redis->hGet($this->name, $encodedKey);
        
        return $value !== false ? $this->decodeValue($value) : null;
    }

    /**
     * Remove a key from the map
     *
     * @param mixed $key
     * @return mixed Previous value or null
     */
    public function remove($key)
    {
        $encodedKey = $this->encodeKey($key);
        $prev = $this->redis->hGet($this->name, $encodedKey);
        $this->redis->hDel($this->name, $encodedKey);
        
        return $prev !== false ? $this->decodeValue($prev) : null;
    }

    /**
     * Check if the map contains a key
     *
     * @param mixed $key
     * @return bool
     */
    public function containsKey($key): bool
    {
        $encodedKey = $this->encodeKey($key);
        return $this->redis->hExists($this->name, $encodedKey);
    }

    /**
     * Get the size of the map
     *
     * @return int
     */
    public function size(): int
    {
        return $this->redis->hLen($this->name);
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
        $this->redis->del($this->name);
    }

    /**
     * Get all keys in the map
     *
     * @return array
     */
    public function keySet(): array
    {
        $keys = $this->redis->hKeys($this->name);
        return array_map(fn($k) => $this->decodeKey($k), $keys);
    }

    /**
     * Get all values in the map
     *
     * @return array
     */
    public function values(): array
    {
        $values = $this->redis->hVals($this->name);
        return array_map(fn($v) => $this->decodeValue($v), $values);
    }

    /**
     * Get all entries in the map
     *
     * @return array
     */
    public function entrySet(): array
    {
        $entries = $this->redis->hGetAll($this->name);
        $result = [];
        foreach ($entries as $key => $value) {
            $result[$this->decodeKey($key)] = $this->decodeValue($value);
        }
        return $result;
    }

    /**
     * Put all entries from another array into this map
     *
     * @param array $map
     */
    public function putAll(array $map): void
    {
        $encoded = [];
        foreach ($map as $key => $value) {
            $encoded[$this->encodeKey($key)] = $this->encodeValue($value);
        }
        if (!empty($encoded)) {
            $this->redis->hMSet($this->name, $encoded);
        }
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
        $encodedKey = $this->encodeKey($key);
        $encodedValue = $this->encodeValue($value);
        
        $result = $this->redis->hSetNx($this->name, $encodedKey, $encodedValue);
        
        if ($result === 0) {
            return $this->get($key);
        }
        
        return null;
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
     * Encode key for storage (Redisson compatibility)
     *
     * @param mixed $key
     * @return string
     */
    private function encodeKey($key): string
    {
        if (is_string($key)) {
            return $key;
        }
        return json_encode($key);
    }

    /**
     * Decode key from storage
     *
     * @param string $key
     * @return mixed
     */
    private function decodeKey(string $key)
    {
        $decoded = json_decode($key, true);
        return $decoded !== null ? $decoded : $key;
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
