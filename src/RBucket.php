<?php

namespace Rediphp;

use Redis;

/**
 * Redisson-compatible distributed Bucket (object holder) implementation
 * Uses Redis String for distributed object storage, compatible with Redisson's RBucket
 */
class RBucket
{
    private Redis $redis;
    private string $name;

    public function __construct(Redis $redis, string $name)
    {
        $this->redis = $redis;
        $this->name = $name;
    }

    /**
     * Get the value
     *
     * @return mixed
     */
    public function get()
    {
        $value = $this->redis->get($this->name);
        return $value !== false ? $this->decodeValue($value) : null;
    }

    /**
     * Set the value
     *
     * @param mixed $value
     */
    public function set($value): void
    {
        $encoded = $this->encodeValue($value);
        $this->redis->set($this->name, $encoded);
    }

    /**
     * Get and delete the value
     *
     * @return mixed
     */
    public function getAndDelete()
    {
        $value = $this->get();
        $this->redis->del($this->name);
        return $value;
    }

    /**
     * Get and set new value atomically
     *
     * @param mixed $newValue
     * @return mixed Previous value
     */
    public function getAndSet($newValue)
    {
        $old = $this->get();
        $this->set($newValue);
        return $old;
    }

    /**
     * Try to set value if it doesn't exist
     *
     * @param mixed $value
     * @return bool True if value was set
     */
    public function trySet($value): bool
    {
        $encoded = $this->encodeValue($value);
        $result = $this->redis->set($this->name, $encoded, ['NX']);
        return $result !== false;
    }

    /**
     * Set value with time to live
     *
     * @param mixed $value
     * @param int $timeToLive Time to live in milliseconds
     */
    public function setWithTTL($value, int $timeToLive): void
    {
        $encoded = $this->encodeValue($value);
        $ttl = (int)ceil($timeToLive / 1000);
        $this->redis->setex($this->name, $ttl, $encoded);
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
        $encodedExpect = $this->encodeValue($expect);
        $encodedUpdate = $this->encodeValue($update);

        $script = <<<LUA
local value = redis.call('get', KEYS[1])
if value == ARGV[1] then
    redis.call('set', KEYS[1], ARGV[2])
    return 1
else
    return 0
end
LUA;

        $result = $this->redis->eval($script, [$this->name, $encodedExpect, $encodedUpdate], 1);
        return $result === 1;
    }

    /**
     * Check if the bucket exists
     *
     * @return bool
     */
    public function isExists(): bool
    {
        return $this->redis->exists($this->name) > 0;
    }

    /**
     * Delete the bucket
     *
     * @return bool
     */
    public function delete(): bool
    {
        return $this->redis->del($this->name) > 0;
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
