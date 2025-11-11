<?php

namespace Rediphp;

use Redis;

/**
 * Redisson-compatible distributed AtomicLong implementation
 * Uses Redis for distributed atomic long, compatible with Redisson's RAtomicLong
 */
class RAtomicLong
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
     * @return int
     */
    public function get(): int
    {
        $value = $this->redis->get($this->name);
        return $value !== false ? (int)$value : 0;
    }

    /**
     * Set the value
     *
     * @param int $newValue
     */
    public function set(int $newValue): void
    {
        $this->redis->set($this->name, $newValue);
    }

    /**
     * Get and set new value atomically
     *
     * @param int $newValue
     * @return int Previous value
     */
    public function getAndSet(int $newValue): int
    {
        $old = $this->get();
        $this->set($newValue);
        return $old;
    }

    /**
     * Compare and set
     *
     * @param int $expect
     * @param int $update
     * @return bool True if successful
     */
    public function compareAndSet(int $expect, int $update): bool
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
     * Increment and get the new value
     *
     * @param int $delta
     * @return int New value
     */
    public function addAndGet(int $delta = 1): int
    {
        return $this->redis->incrBy($this->name, $delta);
    }

    /**
     * Get and increment
     *
     * @param int $delta
     * @return int Previous value
     */
    public function getAndAdd(int $delta = 1): int
    {
        $old = $this->get();
        $this->redis->incrBy($this->name, $delta);
        return $old;
    }

    /**
     * Increment and get
     *
     * @return int New value
     */
    public function incrementAndGet(): int
    {
        return $this->addAndGet(1);
    }

    /**
     * Get and increment
     *
     * @return int Previous value
     */
    public function getAndIncrement(): int
    {
        return $this->getAndAdd(1);
    }

    /**
     * Decrement and get
     *
     * @return int New value
     */
    public function decrementAndGet(): int
    {
        return $this->addAndGet(-1);
    }

    /**
     * Get and decrement
     *
     * @return int Previous value
     */
    public function getAndDecrement(): int
    {
        return $this->getAndAdd(-1);
    }

    /**
     * Delete the atomic long
     *
     * @return bool
     */
    public function delete(): bool
    {
        return $this->redis->del($this->name) > 0;
    }
}
