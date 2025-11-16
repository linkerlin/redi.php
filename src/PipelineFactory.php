<?php

namespace Rediphp;

use Redis;

/**
 * Pipeline Factory
 * Creates and manages RedisPipeline instances
 */
class PipelineFactory
{
    private array $pipelines = [];

    /**
     * Create a new pipeline
     *
     * @param Redis $redis Redis connection
     * @param string $name Pipeline name
     * @return RedisPipeline
     */
    public function create(Redis $redis, string $name = 'default'): RedisPipeline
    {
        $pipeline = new RedisPipeline($redis, $name);
        $this->pipelines[$name] = $pipeline;

        return $pipeline;
    }

    /**
     * Get or create a pipeline by name
     *
     * @param Redis $redis Redis connection
     * @param string $name Pipeline name
     * @return RedisPipeline
     */
    public function getOrCreate(Redis $redis, string $name = 'default'): RedisPipeline
    {
        if (!isset($this->pipelines[$name])) {
            return $this->create($redis, $name);
        }

        return $this->pipelines[$name];
    }

    /**
     * Get pipeline by name
     *
     * @param string $name
     * @return RedisPipeline|null
     */
    public function get(string $name): ?RedisPipeline
    {
        return $this->pipelines[$name] ?? null;
    }

    /**
     * Remove pipeline by name
     *
     * @param string $name
     * @return bool
     */
    public function remove(string $name): bool
    {
        if (isset($this->pipelines[$name])) {
            unset($this->pipelines[$name]);
            return true;
        }

        return false;
    }

    /**
     * Get all pipeline names
     *
     * @return array
     */
    public function getPipelineNames(): array
    {
        return array_keys($this->pipelines);
    }

    /**
     * Clear all pipelines
     *
     * @return self
     */
    public function clear(): self
    {
        $this->pipelines = [];
        return $this;
    }

    /**
     * Get pipeline statistics
     *
     * @return array
     */
    public function getStatistics(): array
    {
        $stats = [];
        foreach ($this->pipelines as $name => $pipeline) {
            $stats[$name] = $pipeline->getStats();
        }

        return [
            'total_pipelines' => count($this->pipelines),
            'pipelines' => $stats
        ];
    }

    /**
     * Create a batch processor for multiple Redis operations
     *
     * @param Redis $redis
     * @param callable $operations Callback that receives a RedisPipeline
     * @return array
     */
    public function batch(Redis $redis, callable $operations): array
    {
        $pipeline = $this->create($redis, 'batch_' . uniqid());
        
        try {
            $operations($pipeline);
            return $pipeline->execute();
        } finally {
            $this->remove($pipeline->getStats()['name']);
        }
    }

    /**
     * Create a fast batch using direct Redis pipeline commands
     *
     * @param Redis $redis
     * @param callable $operations
     * @return array
     */
    public function fastBatch(Redis $redis, callable $operations): array
    {
        $startTime = microtime(true);
        
        $redis->multi();
        
        try {
            $operations($redis);
            $results = $redis->exec();
            
            $executionTime = (microtime(true) - $startTime) * 1000;
            error_log("Fast batch executed in {$executionTime}ms");
            
            return $results ?? [];
        } catch (\Exception $e) {
            $redis->discard();
            throw $e;
        }
    }

    /**
     * Create a transaction-safe pipeline
     *
     * @param Redis $redis
     * @param callable $operations
     * @param callable $onCommit Callback executed on successful commit
     * @param callable $onRollback Callback executed on rollback
     * @return array
     */
    public function transaction(Redis $redis, callable $operations, ?callable $onCommit = null, ?callable $onRollback = null): array
    {
        $pipeline = $this->create($redis, 'transaction_' . uniqid());
        
        try {
            $operations($pipeline);
            $results = $pipeline->execute();
            
            if ($onCommit) {
                $onCommit($results);
            }
            
            return $results;
        } catch (\Exception $e) {
            if ($onRollback) {
                $onRollback($e);
            }
            throw $e;
        } finally {
            $this->remove($pipeline->getStats()['name']);
        }
    }
}