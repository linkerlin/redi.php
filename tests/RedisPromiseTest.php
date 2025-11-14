<?php

namespace Rediphp\Tests;

use PHPUnit\Framework\TestCase;
use Rediphp\RedisPromise;
use Rediphp\PromiseInterface;
use Exception;

/**
 * Unit tests for RedisPromise
 */
class RedisPromiseTest extends TestCase
{
    /**
     * Test basic promise resolution
     */
    public function testPromiseResolution(): void
    {
        $promise = new RedisPromise(function ($resolve) {
            $resolve('success');
        });

        $result = $promise->wait();
        $this->assertEquals('success', $result);
        $this->assertTrue($promise->isFulfilled());
        $this->assertFalse($promise->isPending());
        $this->assertFalse($promise->isRejected());
    }

    /**
     * Test basic promise rejection
     */
    public function testPromiseRejection(): void
    {
        $promise = new RedisPromise(function ($resolve, $reject) {
            $reject(new Exception('failure'));
        });

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('failure');
        $promise->wait();
    }

    /**
     * Test promise then chaining
     */
    public function testPromiseThenChaining(): void
    {
        $promise = new RedisPromise(function ($resolve) {
            $resolve(5);
        });

        $result = $promise
            ->then(function ($value) {
                return $value * 2;
            })
            ->then(function ($value) {
                return $value + 3;
            })
            ->wait();

        $this->assertEquals(13, $result); // (5 * 2) + 3
    }

    /**
     * Test promise catch handling
     */
    public function testPromiseCatchHandling(): void
    {
        $promise = new RedisPromise(function ($resolve, $reject) {
            $reject(new Exception('initial error'));
        });

        $result = $promise
            ->catch(function ($error) {
                return 'recovered: ' . $error->getMessage();
            })
            ->wait();

        $this->assertEquals('recovered: initial error', $result);
    }

    /**
     * Test promise finally
     */
    public function testPromiseFinally(): void
    {
        $finallyCalled = false;
        $promise = new RedisPromise(function ($resolve) {
            $resolve('success');
        });

        $result = $promise
            ->finally(function () use (&$finallyCalled) {
                $finallyCalled = true;
            })
            ->wait();

        $this->assertEquals('success', $result);
        $this->assertTrue($finallyCalled);
    }

    /**
     * Test promise finally with rejection
     */
    public function testPromiseFinallyWithRejection(): void
    {
        $finallyCalled = false;
        $promise = new RedisPromise(function ($resolve, $reject) {
            $reject(new Exception('error'));
        });

        try {
            $promise
                ->finally(function () use (&$finallyCalled) {
                    $finallyCalled = true;
                })
                ->wait();
            $this->fail('Expected exception was not thrown');
        } catch (Exception $e) {
            $this->assertEquals('error', $e->getMessage());
            $this->assertTrue($finallyCalled);
        }
    }

    /**
     * Test static resolve method
     */
    public function testStaticResolve(): void
    {
        $promise = RedisPromise::resolve('static value');
        $result = $promise->wait();
        $this->assertEquals('static value', $result);
        $this->assertTrue($promise->isFulfilled());
    }

    /**
     * Test static reject method
     */
    public function testStaticReject(): void
    {
        $promise = RedisPromise::reject(new Exception('static error'));
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('static error');
        $promise->wait();
    }

    /**
     * Test promise all with all resolved
     */
    public function testPromiseAllAllResolved(): void
    {
        $promise1 = RedisPromise::resolve(1);
        $promise2 = RedisPromise::resolve(2);
        $promise3 = RedisPromise::resolve(3);

        $result = RedisPromise::all([$promise1, $promise2, $promise3])->wait();
        $this->assertEquals([1, 2, 3], $result);
    }

    /**
     * Test promise all with mixed values
     */
    public function testPromiseAllMixedValues(): void
    {
        $promise1 = RedisPromise::resolve(1);
        $promise2 = 2; // Non-promise value
        $promise3 = RedisPromise::resolve(3);

        $result = RedisPromise::all([$promise1, $promise2, $promise3])->wait();
        $this->assertEquals([1, 2, 3], $result);
    }

    /**
     * Test promise all with rejection
     */
    public function testPromiseAllWithRejection(): void
    {
        $promise1 = RedisPromise::resolve(1);
        $promise2 = RedisPromise::reject(new Exception('error in promise 2'));
        $promise3 = RedisPromise::resolve(3);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('error in promise 2');
        RedisPromise::all([$promise1, $promise2, $promise3])->wait();
    }

    /**
     * Test promise all with empty array
     */
    public function testPromiseAllEmptyArray(): void
    {
        $result = RedisPromise::all([])->wait();
        $this->assertEquals([], $result);
    }

    /**
     * Test promise timeout
     */
    public function testPromiseTimeout(): void
    {
        $promise = new RedisPromise(function ($resolve) {
            // Never resolve
        });

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Promise wait timeout');
        $promise->wait(0.1); // 100ms timeout
    }

    /**
     * Test promise with wait function
     */
    public function testPromiseWithWaitFunction(): void
    {
        $waitCalled = false;
        $promise = new RedisPromise(function ($resolve) {
            // Simulate async operation that will be triggered by wait function
        });

        $promise->setWaitFunction(function () use (&$waitCalled, $promise) {
            $waitCalled = true;
            $promise->resolve('async result');
        });

        $result = $promise->wait();
        $this->assertTrue($waitCalled);
        $this->assertEquals('async result', $result);
    }

    /**
     * Test promise resolution with another promise
     */
    public function testPromiseResolutionWithPromise(): void
    {
        $innerPromise = RedisPromise::resolve('inner value');
        $outerPromise = new RedisPromise(function ($resolve) use ($innerPromise) {
            $resolve($innerPromise);
        });

        $result = $outerPromise->wait();
        $this->assertEquals('inner value', $result);
    }

    /**
     * Test multiple then handlers
     */
    public function testMultipleThenHandlers(): void
    {
        $promise = new RedisPromise(function ($resolve) {
            $resolve('value');
        });

        $result1 = null;
        $result2 = null;

        $promise->then(function ($v) use (&$result1) {
            $result1 = $v . '_1';
        });

        $promise->then(function ($v) use (&$result2) {
            $result2 = $v . '_2';
        });

        $promise->wait();

        $this->assertEquals('value_1', $result1);
        $this->assertEquals('value_2', $result2);
    }

    /**
     * Test error in then callback
     */
    public function testErrorInThenCallback(): void
    {
        $promise = new RedisPromise(function ($resolve) {
            $resolve('value');
        });

        $chainedPromise = $promise->then(function ($v) {
            throw new Exception('error in then');
        });

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('error in then');
        $chainedPromise->wait();
    }

    /**
     * Test promise state transitions
     */
    public function testPromiseStateTransitions(): void
    {
        $promise = new RedisPromise(function ($resolve) {
            $this->assertTrue($promise->isPending());
            $resolve('value');
        });

        $this->assertTrue($promise->isPending());
        $promise->wait();
        $this->assertTrue($promise->isFulfilled());
        $this->assertFalse($promise->isPending());
        $this->assertFalse($promise->isRejected());
    }
}