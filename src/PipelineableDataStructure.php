<?php

namespace Rediphp;

use Redis;

/**
 * Pipelineable Data Structure Base Class
 * Extends RedisDataStructure with pipeline support for batch operations
 */
abstract class PipelineableDataStructure extends RedisDataStructure
{
    private ?PipelineFactory $pipelineFactory = null;

    /**
     * Get or create pipeline factory
     *
     * @return PipelineFactory
     */
    protected function getPipelineFactory(): PipelineFactory
    {
        if ($this->pipelineFactory === null) {
            $this->pipelineFactory = new PipelineFactory();
        }

        return $this->pipelineFactory;
    }

    /**
     * Execute operations in a pipeline for improved performance
     *
     * @param callable $operations Callback that receives a RedisPipeline
     * @param string $pipelineName Optional pipeline name
     * @return array Results from pipeline execution
     */
    public function pipeline(callable $operations, ?string $pipelineName = null): array
    {
        $pipelineName = $pipelineName ?? (get_class($this) . '_' . $this->name . '_' . uniqid());
        
        return $this->executeWithPool(function(Redis $redis) use ($operations) {
            return $this->getPipelineFactory()->batch($redis, function($pipeline) use ($operations) {
                $operations(new PipelineableRedis($pipeline, $this));
            });
        });
    }

    /**
     * Execute operations in a fast batch using direct Redis pipeline
     *
     * @param callable $operations
     * @param string $pipelineName
     * @return array
     */
    public function fastPipeline(callable $operations, ?string $pipelineName = null): array
    {
        return $this->executeWithPool(function(Redis $redis) use ($operations) {
            return $this->getPipelineFactory()->fastBatch($redis, $operations);
        });
    }

    /**
     * Execute operations in a transaction-safe pipeline
     *
     * @param callable $operations
     * @param callable|null $onCommit
     * @param callable|null $onRollback
     * @param string $pipelineName
     * @return array
     */
    public function transaction(callable $operations, ?callable $onCommit = null, ?callable $onRollback = null, ?string $pipelineName = null): array
    {
        return $this->executeWithPool(function(Redis $redis) use ($operations, $onCommit, $onRollback) {
            return $this->getPipelineFactory()->transaction($redis, $operations, $onCommit, $onRollback);
        });
    }

    /**
     * Create a batch operation for multiple reads
     *
     * @param callable $batchReader Callback that receives a BatchReader
     * @return array Results from batch operations
     */
    public function batchRead(callable $batchReader): array
    {
        return $this->pipeline(function($pipeline) use ($batchReader) {
            $batchReader(new BatchReader($pipeline, $this));
        });
    }

    /**
     * Create a batch operation for multiple writes
     *
     * @param callable $batchWriter Callback that receives a BatchWriter
     * @return array Results from batch operations
     */
    public function batchWrite(callable $batchWriter): array
    {
        return $this->pipeline(function($pipeline) use ($batchWriter) {
            $batchWriter(new BatchWriter($pipeline, $this));
        });
    }

    /**
     * Get pipeline statistics
     *
     * @return array
     */
    public function getPipelineStats(): array
    {
        return $this->getPipelineFactory()->getStatistics();
    }
}