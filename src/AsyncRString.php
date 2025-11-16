<?php

namespace Rediphp;

use Rediphp\RedisPromise;

/**
 * AsyncRString - Asynchronous wrapper for RString
 * Provides Promise-based API for all string operations
 */
class AsyncRString
{
    private RString $string;
    
    public function __construct(RString $string)
    {
        $this->string = $string;
    }

    /**
     * Get the string value
     */
    public function get(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->string->get());
        });
    }

    /**
     * Set the string value
     */
    public function set($value): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->string->set($value));
        });
    }

    /**
     * Set if not exists
     */
    public function setIfAbsent($value): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->string->setIfAbsent($value));
        });
    }

    /**
     * Set if exists
     */
    public function setIfExists($value): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->string->setIfExists($value));
        });
    }

    /**
     * Append value
     */
    public function append($value): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->string->append($value));
        });
    }

    /**
     * Prepend value
     */
    public function prepend($value): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->string->prepend($value));
        });
    }

    /**
     * Set with expiration (seconds)
     */
    public function setWithExpireSeconds($value, int $seconds): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->string->setWithExpireSeconds($value, $seconds));
        });
    }

    /**
     * Set with expiration (timestamp)
     */
    public function setWithExpireTimestamp($value, int $timestamp): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->string->setWithExpireTimestamp($value, $timestamp));
        });
    }

    /**
     * Get and set (atomically)
     */
    public function getSet($value): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->string->getSet($value));
        });
    }

    /**
     * Compare and set
     */
    public function compareAndSet($oldValue, $newValue): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->string->compareAndSet($oldValue, $newValue));
        });
    }

    /**
     * Get with expiration info
     */
    public function getWithExpireInfo(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->string->getWithExpireInfo());
        });
    }

    /**
     * Get length
     */
    public function length(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->string->length());
        });
    }

    /**
     * Get substring
     */
    public function substring(int $offset, int $length = null): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($offset, $length) {
            $resolve($this->string->substring($offset, $length));
        });
    }

    /**
     * Increase by one
     */
    public function increase(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->string->increase());
        });
    }

    /**
     * Decrease by one
     */
    public function decrease(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->string->decrease());
        });
    }

    /**
     * Increase by amount
     */
    public function increaseBy(float $amount): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($amount) {
            $resolve($this->string->increaseBy($amount));
        });
    }

    /**
     * Decrease by amount
     */
    public function decreaseBy(float $amount): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($amount) {
            $resolve($this->string->decreaseBy($amount));
        });
    }

    /**
     * Set if greater than
     */
    public function setIfGreater($value): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->string->setIfGreater($value));
        });
    }

    /**
     * Set if less than
     */
    public function setIfLess($value): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->string->setIfLess($value));
        });
    }

    /**
     * Fast operations
     */
    public function fastIncrease(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->string->fastIncrease());
        });
    }

    public function fastDecrease(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->string->fastDecrease());
        });
    }

    public function fastSet($value): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($value) {
            $resolve($this->string->fastSet($value));
        });
    }

    public function fastGet(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->string->fastGet());
        });
    }

    /**
     * Conditional fast set
     */
    public function fastSetIfAbsent($value): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($value) {
            $resolve($this->string->fastSetIfAbsent($value));
        });
    }

    /**
     * Batch operations
     */
    public function batchGet(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->string->get());
        });
    }

    public function batchSet($value): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($value) {
            $resolve($this->string->set($value));
        });
    }

    public function batchSetIfAbsent($value): RedisPromise
    {
        return new RedisPromise(function ($resolve) use ($value) {
            $resolve($this->string->setIfAbsent($value));
        });
    }

    public function batchIncrease(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->string->increase());
        });
    }

    public function batchDecrease(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->string->decrease());
        });
    }

    public function batchLength(): RedisPromise
    {
        return new RedisPromise(function ($resolve) {
            $resolve($this->string->length());
        });
    }

    /**
     * Fast batch operations using pipeline if available
     */
    public function fastBatch(callable $operations): RedisPromise
    {
        return new RedisPromise(function ($resolve, $reject) use ($operations) {
            try {
                if (method_exists($this->string, 'fastBatch')) {
                    // Use fastBatch if available (pipeline-based)
                    $results = $this->string->fastBatch($operations);
                    $resolve($results);
                } else {
                    // Fallback to regular batch operations
                    $result = $operations($this->string);
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
            if (method_exists($this->string, 'getPipelineStats')) {
                $resolve($this->string->getPipelineStats());
            } else {
                $resolve(['pipeline_supported' => false]);
            }
        });
    }

    /**
     * Get underlying string
     */
    public function getString(): RString
    {
        return $this->string;
    }
}