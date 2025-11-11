<?php

namespace Rediphp;

use Redis;

/**
 * Redisson-compatible distributed AtomicDouble implementation
 * Uses Redis for distributed atomic double, compatible with Redisson's RAtomicDouble
 */
class RAtomicDouble
{
    private Redis $redis;
    private string $name;

    public function __construct(Redis $redis, string $name)
    {
        $this->redis = $redis;
        $this->name = $name;
    }

    /**
     * Get the current value
     *
     * @return float
     */
    public function get(): float
    {
        $value = $this->redis->get($this->name);
        return $value !== false ? (float)$value : 0.0;
    }

    /**
     * Set the value
     *
     * @param float $newValue
     */
    public function set(float $newValue): void
    {
        $this->redis->set($this->name, $newValue);
    }

    /**
     * Get and set new value atomically
     *
     * @param float $newValue
     * @return float Previous value
     */
    public function getAndSet(float $newValue): float
    {
        $old = $this->get();
        $this->set($newValue);
        return $old;
    }

    /**
     * Compare and set
     *
     * @param float $expect
     * @param float $update
     * @return bool True if successful
     */
    public function compareAndSet(float $expect, float $update): bool
    {
        $script = <<<LUA
local value = redis.call('get', KEYS[1])
local current = value == false and 0 or tonumber(value)
if current == tonumber(ARGV[1]) then
    redis.call('set', KEYS[1], ARGV[2])
    return 1
else
    return 0
end
LUA;

        $result = $this->redis->eval($script, [$this->name, $expect, $update], 1);
        return $result === 1;
    }

    /**
     * Add and get the new value
     *
     * @param float $delta
     * @return float New value
     */
    public function addAndGet(float $delta): float
    {
        $script = <<<LUA
local value = redis.call('get', KEYS[1])
local current = value == false and 0 or tonumber(value)
local newValue = current + tonumber(ARGV[1])
redis.call('set', KEYS[1], newValue)
return newValue
LUA;

        $result = $this->redis->eval($script, [$this->name, $delta], 1);
        return (float)$result;
    }

    /**
     * Get and add
     *
     * @param float $delta
     * @return float Previous value
     */
    public function getAndAdd(float $delta): float
    {
        $old = $this->get();
        $this->addAndGet($delta);
        return $old;
    }

    /**
     * Delete the atomic double
     *
     * @return bool
     */
    public function delete(): bool
    {
        return $this->redis->del($this->name) > 0;
    }
}
