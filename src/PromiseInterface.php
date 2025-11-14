<?php

namespace Rediphp;

/**
 * Promise interface for asynchronous operations
 * Compatible with Redisson's async API patterns
 */
interface PromiseInterface
{
    /**
     * Register a callback for when the promise is fulfilled
     *
     * @param callable $onFulfilled Called with the fulfillment value
     * @param callable|null $onRejected Called with the rejection reason
     * @return PromiseInterface A new promise for chaining
     */
    public function then(callable $onFulfilled, ?callable $onRejected = null): PromiseInterface;

    /**
     * Register a callback for when the promise is rejected
     *
     * @param callable $onRejected Called with the rejection reason
     * @return PromiseInterface A new promise for chaining
     */
    public function catch(callable $onRejected): PromiseInterface;

    /**
     * Register a callback for when the promise is settled (fulfilled or rejected)
     *
     * @param callable $onFinally Called with no arguments
     * @return PromiseInterface A new promise for chaining
     */
    public function finally(callable $onFinally): PromiseInterface;

    /**
     * Wait for the promise to settle and return its value
     *
     * @param float|null $timeout Maximum time to wait in seconds
     * @return mixed The fulfillment value
     * @throws \Exception The rejection reason if the promise was rejected
     */
    public function wait(?float $timeout = null);

    /**
     * Get the current state of the promise
     *
     * @return string 'pending', 'fulfilled', or 'rejected'
     */
    public function getState(): string;

    /**
     * Check if the promise is pending
     *
     * @return bool
     */
    public function isPending(): bool;

    /**
     * Check if the promise is fulfilled
     *
     * @return bool
     */
    public function isFulfilled(): bool;

    /**
     * Check if the promise is rejected
     *
     * @return bool
     */
    public function isRejected(): bool;
}