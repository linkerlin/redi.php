<?php

namespace Rediphp;

use Exception;

/**
 * Promise implementation for Redis asynchronous operations
 * Provides a thenable interface compatible with Redisson's async patterns
 */
class RedisPromise implements PromiseInterface
{
    private const STATE_PENDING = 'pending';
    private const STATE_FULFILLED = 'fulfilled';
    private const STATE_REJECTED = 'rejected';

    private string $state = self::STATE_PENDING;
    private $value;
    private $reason;
    private array $handlers = [];
    private ?\Closure $waitFunction = null;

    /**
     * Create a new promise
     *
     * @param callable|null $executor Function called with ($resolve, $reject)
     */
    public function __construct(?callable $executor = null)
    {
        if ($executor !== null) {
            try {
                $executor(
                    [$this, 'doResolve'],
                    [$this, 'doReject']
                );
            } catch (Exception $e) {
                $this->doReject($e);
            }
        }
    }

    /**
     * Create a resolved promise
     *
     * @param mixed $value
     * @return PromiseInterface
     */
    public static function resolve($value = null): PromiseInterface
    {
        $promise = new self();
        $promise->doResolve($value);
        return $promise;
    }

    /**
     * Create a rejected promise
     *
     * @param mixed $reason
     * @return PromiseInterface
     */
    public static function reject($reason = null): PromiseInterface
    {
        $promise = new self();
        $promise->doReject($reason);
        return $promise;
    }

    /**
     * Create a promise that resolves when all promises resolve
     *
     * @param array $promises
     * @return PromiseInterface
     */
    public static function all(array $promises): PromiseInterface
    {
        return new self(function ($resolve, $reject) use ($promises) {
            if (empty($promises)) {
                $resolve([]);
                return;
            }

            $results = [];
            $remaining = count($promises);
            $alreadyRejected = false;

            foreach ($promises as $index => $promise) {
                if (!$promise instanceof PromiseInterface) {
                    $results[$index] = $promise;
                    $remaining--;
                    if ($remaining === 0) {
                        $resolve($results);
                    }
                    continue;
                }

                $promise->then(
                    function ($value) use ($index, &$results, &$remaining, $resolve) {
                        $results[$index] = $value;
                        $remaining--;
                        if ($remaining === 0) {
                            ksort($results);
                            $resolve($results);
                        }
                    },
                    function ($reason) use ($reject, &$alreadyRejected) {
                        if (!$alreadyRejected) {
                            $alreadyRejected = true;
                            $reject($reason);
                        }
                    }
                );
            }
        });
    }

    /**
     * @inheritDoc
     */
    public function then(callable $onFulfilled, ?callable $onRejected = null): PromiseInterface
    {
        return new self(function ($resolve, $reject) use ($onFulfilled, $onRejected) {
            $this->handlers[] = [
                'fulfilled' => $onFulfilled,
                'rejected' => $onRejected,
                'resolve' => $resolve,
                'reject' => $reject
            ];

            $this->processHandlers();
        });
    }

    /**
     * @inheritDoc
     */
    public function catch(callable $onRejected): PromiseInterface
    {
        return $this->then(null, $onRejected);
    }

    /**
     * @inheritDoc
     */
    public function finally(callable $onFinally): PromiseInterface
    {
        return $this->then(
            function ($value) use ($onFinally) {
                $onFinally();
                return $value;
            },
            function ($reason) use ($onFinally) {
                $onFinally();
                throw $reason;
            }
        );
    }

    /**
     * @inheritDoc
     */
    public function wait(?float $timeout = null)
    {
        $startTime = microtime(true);

        while ($this->state === self::STATE_PENDING) {
            if ($timeout !== null && microtime(true) - $startTime > $timeout) {
                throw new Exception("Promise wait timeout after {$timeout} seconds");
            }

            if ($this->waitFunction !== null) {
                call_user_func($this->waitFunction);
            } else {
                usleep(1000); // Sleep for 1ms to avoid busy waiting
            }
        }

        if ($this->state === self::STATE_FULFILLED) {
            return $this->value;
        }

        if ($this->reason instanceof Exception) {
            throw $this->reason;
        }

        throw new Exception($this->reason ?? 'Promise rejected with unknown reason');
    }

    /**
     * @inheritDoc
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * @inheritDoc
     */
    public function isPending(): bool
    {
        return $this->state === self::STATE_PENDING;
    }

    /**
     * @inheritDoc
     */
    public function isFulfilled(): bool
    {
        return $this->state === self::STATE_FULFILLED;
    }

    /**
     * @inheritDoc
     */
    public function isRejected(): bool
    {
        return $this->state === self::STATE_REJECTED;
    }


    /**
     * Internal resolve implementation
     *
     * @param mixed $value
     */
    private function doResolve($value): void
    {
        if ($this->state !== self::STATE_PENDING) {
            return;
        }

        // If value is a promise, adopt its state
        if ($value instanceof PromiseInterface) {
            $value->then(
                [$this, 'doResolve'],
                [$this, 'doReject']
            );
            return;
        }

        $this->state = self::STATE_FULFILLED;
        $this->value = $value;
        $this->processHandlers();
    }

    /**
     * Internal reject implementation
     *
     * @param mixed $reason
     */
    private function doReject($reason): void
    {
        if ($this->state !== self::STATE_PENDING) {
            return;
        }

        $this->state = self::STATE_REJECTED;
        $this->reason = $reason;
        $this->processHandlers();
    }

    /**
     * Set a function to be called while waiting
     *
     * @param callable $waitFunction
     */
    public function setWaitFunction(callable $waitFunction): void
    {
        $this->waitFunction = $waitFunction;
    }

    /**
     * Process all pending handlers
     */
    private function processHandlers(): void
    {
        if ($this->state === self::STATE_PENDING) {
            return;
        }

        foreach ($this->handlers as $handler) {
            $this->processHandler($handler);
        }

        $this->handlers = [];
    }

    /**
     * Process a single handler
     *
     * @param array $handler
     */
    private function processHandler(array $handler): void
    {
        $callback = $this->state === self::STATE_FULFILLED
            ? ($handler['fulfilled'] ?? null)
            : ($handler['rejected'] ?? null);

        if ($callback === null) {
            if ($this->state === self::STATE_FULFILLED) {
                $handler['resolve']($this->value);
            } else {
                $handler['reject']($this->reason);
            }
            return;
        }

        try {
            $result = call_user_func($callback, $this->state === self::STATE_FULFILLED ? $this->value : $this->reason);
            $handler['resolve']($result);
        } catch (Exception $e) {
            $handler['reject']($e);
        }
    }
}