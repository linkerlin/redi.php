<?php

namespace Rediphp;

use Redis;

/**
 * Redisson-compatible distributed Bucket (object holder) implementation
 * Uses Redis String for distributed object storage, compatible with Redisson's RBucket
 */
class RBucket extends RedisDataStructure
{
    public function __construct($connection, string $name)
    {
        parent::__construct($connection, $name);
    }

    /**
     * Get the value
     *
     * @return mixed
     */
    public function get()
    {
        return $this->executeWithPool(function ($redis) {
            $value = $redis->get($this->name);
            return $value !== false ? $this->decodeValue($value) : null;
        });
    }

    /**
     * Set the value
     *
     * @param mixed $value
     */
    public function set($value): void
    {
        $this->executeWithPool(function ($redis) use ($value) {
            $encoded = $this->encodeValue($value);
            $redis->set($this->name, $encoded);
        });
    }

    /**
     * Get and delete the value
     *
     * @return mixed
     */
    public function getAndDelete()
    {
        return $this->executeWithPool(function ($redis) {
            $value = $redis->get($this->name);
            $redis->del($this->name);
            return $value !== false ? $this->decodeValue($value) : null;
        });
    }

    /**
     * Get and set new value atomically
     *
     * @param mixed $newValue
     * @return mixed Previous value
     */
    public function getAndSet($newValue)
    {
        return $this->executeWithPool(function ($redis) use ($newValue) {
            $oldValue = $redis->get($this->name);
            $encoded = $this->encodeValue($newValue);
            $redis->set($this->name, $encoded);
            return $oldValue !== false ? $this->decodeValue($oldValue) : null;
        });
    }

    /**
     * Try to set value if it doesn't exist
     *
     * @param mixed $value
     * @return bool True if value was set
     */
    public function trySet($value): bool
    {
        return $this->executeWithPool(function ($redis) use ($value) {
            $encoded = $this->encodeValue($value);
            $result = $redis->set($this->name, $encoded, ['NX']);
            return $result !== false;
        });
    }

    /**
     * Set value with time to live
     *
     * @param mixed $value
     * @param int $timeToLive Time to live in milliseconds
     */
    public function setWithTTL($value, int $timeToLive): void
    {
        $this->executeWithPool(function ($redis) use ($value, $timeToLive) {
            $encoded = $this->encodeValue($value);
            $ttl = (int)ceil($timeToLive / 1000);
            $redis->setex($this->name, $ttl, $encoded);
        });
    }

    /**
     * Compare and set
     *
     * @param mixed $expect
     * @param mixed $update
     * @return bool True if successful
     */
    public function compareAndSet($expect, $update): bool
    {
        return $this->executeWithPool(function ($redis) use ($expect, $update) {
            $encodedExpect = $this->encodeValue($expect);
            $encodedUpdate = $this->encodeValue($update);

            $script = <<<LUA
local value = redis.call('get', KEYS[1])
if value == false and ARGV[1] == "null" then
    redis.call('set', KEYS[1], ARGV[2])
    return 1
elseif value == ARGV[1] then
    redis.call('set', KEYS[1], ARGV[2])
    return 1
else
    return 0
end
LUA;

            $result = $redis->eval($script, [$this->name, $encodedExpect, $encodedUpdate], 1);
            return $result === 1;
        });
    }

    /**
     * Check if the bucket exists
     *
     * @return bool
     */
    public function isExists(): bool
    {
        return $this->executeWithPool(function ($redis) {
            return $redis->exists($this->name) > 0;
        });
    }

    /**
     * Delete the bucket
     *
     * @return bool
     */
    public function delete(): bool
    {
        return $this->executeWithPool(function ($redis) {
            return $redis->del($this->name) > 0;
        });
    }

    /**
     * Encode value for storage (Redisson compatibility)
     *
     * @param mixed $value
     * @return string
     */
    protected function encodeValue($value): string
    {
        return $this->serializationService->encode($value);
    }

    /**
     * Decode value from storage
     *
     * @param string $value
     * @return mixed
     */
    protected function decodeValue(string $value)
    {
        return $this->serializationService->decode($value);
    }
}
