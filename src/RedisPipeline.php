<?php

namespace Rediphp;

use Redis;

/**
 * Redis Pipeline Implementation
 * Provides batch execution of Redis commands for improved performance
 */
class RedisPipeline implements RPipeline
{
    private Redis $redis;
    private array $commands = [];
    private string $name;
    private ?\Closure $encoder = null;

    public function __construct(Redis $redis, string $name = 'default')
    {
        $this->redis = $redis;
        $this->name = $name;
    }

    /**
     * Set custom encoder function
     *
     * @param callable $encoder
     * @return self
     */
    public function setEncoder(callable $encoder): self
    {
        $this->encoder = $encoder;
        return $this;
    }

    /**
     * Queue a Redis command for batch execution
     *
     * @param string $method Method name
     * @param array $args Arguments
     * @return self
     */
    public function queueCommand(string $method, array $args): self
    {
        $this->commands[] = [
            'method' => $method,
            'args' => $args,
            'encoded_args' => $this->encodeArgs($args)
        ];

        return $this;
    }

    /**
     * Encode arguments using custom encoder or default
     *
     * @param array $args
     * @return array
     */
    private function encodeArgs(array $args): array
    {
        if ($this->encoder !== null) {
            return array_map($this->encoder, $args);
        }

        return $args;
    }

    /**
     * Execute all queued commands and return results
     *
     * @return array Results from all queued commands
     */
    public function execute(): array
    {
        if (empty($this->commands)) {
            return [];
        }

        $startTime = microtime(true);
        $results = [];

        try {
            // Start pipeline
            $this->redis->multi();

            foreach ($this->commands as $command) {
                $method = $command['method'];
                $args = $command['encoded_args'];

                // Execute the command in pipeline
                call_user_func_array([$this->redis, $method], $args);
            }

            // Execute all queued commands
            $rawResults = $this->redis->exec();

            // Process results
            foreach ($rawResults as $index => $result) {
                $command = $this->commands[$index];
                $results[$index] = [
                    'success' => true,
                    'data' => $result,
                    'method' => $command['method'],
                    'args' => $command['args']
                ];
            }

            $executionTime = microtime(true) - $startTime;
            error_log("Pipeline '{$this->name}' executed {$this->getQueuedCount()} commands in {$executionTime}ms");

            return $results;

        } catch (\Exception $e) {
            $executionTime = microtime(true) - $startTime;
            error_log("Pipeline '{$this->name}' execution failed after {$executionTime}ms: " . $e->getMessage());
            
            // Clear pipeline on error
            $this->redis->discard();
            
            throw new \RuntimeException("Pipeline execution failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Execute commands asynchronously using promises
     *
     * @return PromiseInterface
     */
    public function executeAsync(): PromiseInterface
    {
        return new \Rediphp\RedisPromise(function ($resolve, $reject) {
            try {
                $results = $this->execute();
                $resolve($results);
            } catch (\Exception $e) {
                $reject($e);
            }
        });
    }

    /**
     * Get the number of queued commands
     *
     * @return int
     */
    public function getQueuedCount(): int
    {
        return count($this->commands);
    }

    /**
     * Clear all queued commands
     *
     * @return self
     */
    public function clear(): self
    {
        $this->commands = [];
        return $this;
    }

    /**
     * Check if pipeline is empty
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->commands);
    }

    /**
     * Get pipeline statistics
     *
     * @return array
     */
    public function getStats(): array
    {
        return [
            'name' => $this->name,
            'queued_commands' => $this->getQueuedCount(),
            'is_empty' => $this->isEmpty(),
            'has_encoder' => $this->encoder !== null
        ];
    }

    /**
     * Convenient method to queue multiple commands at once
     *
     * @param array $commands Array of ['method' => string, 'args' => array]
     * @return self
     */
    public function queueMultiple(array $commands): self
    {
        foreach ($commands as $command) {
            if (isset($command['method']) && isset($command['args'])) {
                $this->queueCommand($command['method'], $command['args']);
            }
        }

        return $this;
    }

    /**
     * Execute pipeline and return only successful results
     *
     * @return array
     */
    public function executeSuccessful(): array
    {
        $results = $this->execute();
        return array_filter($results, function ($result) {
            return $result['success'] === true;
        });
    }

    /**
     * Execute pipeline and return only failed results
     *
     * @return array
     */
    public function executeFailed(): array
    {
        $results = $this->execute();
        return array_filter($results, function ($result) {
            return $result['success'] === false;
        });
    }
}