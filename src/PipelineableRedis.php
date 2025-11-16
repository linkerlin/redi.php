<?php

namespace Rediphp;

/**
 * Pipelineable Redis Wrapper
 * Provides a Redis-like interface for pipeline operations
 */
class PipelineableRedis
{
    private \Rediphp\RedisPipeline $pipeline;
    private PipelineableDataStructure $dataStructure;

    public function __construct(RedisPipeline $pipeline, PipelineableDataStructure $dataStructure)
    {
        $this->pipeline = $pipeline;
        $this->dataStructure = $dataStructure;
    }

    /**
     * Queue a Redis command in the pipeline
     *
     * @param string $method
     * @param array $args
     * @return self
     */
    public function queue(string $method, array $args = []): self
    {
        $this->pipeline->queueCommand($method, $args);
        return $this;
    }

    // Redis List operations
    public function rPush(string $key, ...$values): self
    {
        $args = array_merge([$key], $values);
        return $this->queue('rPush', $args);
    }

    public function lPush(string $key, ...$values): self
    {
        $args = array_merge([$key], $values);
        return $this->queue('lPush', $args);
    }

    public function lIndex(string $key, int $index): self
    {
        return $this->queue('lIndex', [$key, $index]);
    }

    public function lSet(string $key, int $index, string $value): self
    {
        return $this->queue('lSet', [$key, $index, $value]);
    }

    public function lRem(string $key, string $value, int $count = 0): self
    {
        return $this->queue('lRem', [$key, $value, $count]);
    }

    public function lLen(string $key): self
    {
        return $this->queue('lLen', [$key]);
    }

    public function lRange(string $key, int $start, int $end): self
    {
        return $this->queue('lRange', [$key, $start, $end]);
    }

    public function lTrim(string $key, int $start, int $end): self
    {
        return $this->queue('lTrim', [$key, $start, $end]);
    }

    // Redis Hash operations
    public function hSet(string $key, string $field, string $value): self
    {
        return $this->queue('hSet', [$key, $field, $value]);
    }

    public function hGet(string $key, string $field): self
    {
        return $this->queue('hGet', [$key, $field]);
    }

    public function hDel(string $key, string ...$fields): self
    {
        $args = array_merge([$key], $fields);
        return $this->queue('hDel', $args);
    }

    public function hExists(string $key, string $field): self
    {
        return $this->queue('hExists', [$key, $field]);
    }

    public function hLen(string $key): self
    {
        return $this->queue('hLen', [$key]);
    }

    public function hGetAll(string $key): self
    {
        return $this->queue('hGetAll', [$key]);
    }

    public function hKeys(string $key): self
    {
        return $this->queue('hKeys', [$key]);
    }

    public function hVals(string $key): self
    {
        return $this->queue('hVals', [$key]);
    }

    public function hMSet(string $key, array $data): self
    {
        return $this->queue('hMSet', [$key, $data]);
    }

    public function hMGet(string $key, array $fields): self
    {
        return $this->queue('hMGet', [$key, $fields]);
    }

    // Redis String operations
    public function set(string $key, string $value): self
    {
        return $this->queue('set', [$key, $value]);
    }

    public function get(string $key): self
    {
        return $this->queue('get', [$key]);
    }

    public function del(string ...$keys): self
    {
        $args = array_values($keys);
        return $this->queue('del', $args);
    }

    public function exists(string $key): self
    {
        return $this->queue('exists', [$key]);
    }

    public function expire(string $key, int $seconds): self
    {
        return $this->queue('expire', [$key, $seconds]);
    }

    public function ttl(string $key): self
    {
        return $this->queue('ttl', [$key]);
    }

    // Redis Set operations
    public function sAdd(string $key, ...$members): self
    {
        $args = array_merge([$key], $members);
        return $this->queue('sAdd', $args);
    }

    public function sRem(string $key, ...$members): self
    {
        $args = array_merge([$key], $members);
        return $this->queue('sRem', $args);
    }

    public function sMembers(string $key): self
    {
        return $this->queue('sMembers', [$key]);
    }

    public function sIsMember(string $key, string $member): self
    {
        return $this->queue('sIsMember', [$key, $member]);
    }

    public function sCard(string $key): self
    {
        return $this->queue('sCard', [$key]);
    }

    // Redis Sorted Set operations
    public function zAdd(string $key, float $score, string $member): self
    {
        return $this->queue('zAdd', [$key, $score, $member]);
    }

    public function zRem(string $key, string ...$members): self
    {
        $args = array_merge([$key], $members);
        return $this->queue('zRem', $args);
    }

    public function zScore(string $key, string $member): self
    {
        return $this->queue('zScore', [$key, $member]);
    }

    public function zRange(string $key, int $start, int $end, bool $withScores = false): self
    {
        return $this->queue('zRange', [$key, $start, $end, $withScores]);
    }

    public function zRevRange(string $key, int $start, int $end, bool $withScores = false): self
    {
        return $this->queue('zRevRange', [$key, $start, $end, $withScores]);
    }

    public function zCard(string $key): self
    {
        return $this->queue('zCard', [$key]);
    }

    // Get the underlying pipeline
    public function getPipeline(): \Rediphp\RedisPipeline
    {
        return $this->pipeline;
    }

    // Execute pipeline and return results
    public function execute(): array
    {
        return $this->pipeline->execute();
    }

    // Async execution
    public function executeAsync(): PromiseInterface
    {
        return $this->pipeline->executeAsync();
    }

    // Get queue count
    public function getQueueCount(): int
    {
        return $this->pipeline->getQueuedCount();
    }

    // Clear queue
    public function clear(): self
    {
        $this->pipeline->clear();
        return $this;
    }

    // Check if empty
    public function isEmpty(): bool
    {
        return $this->pipeline->isEmpty();
    }
}