<?php

namespace Rediphp;

/**
 * Batch Writer for Data Structure Operations
 * Provides efficient batch write operations
 */
class BatchWriter
{
    private \Rediphp\RedisPipeline $pipeline;
    private PipelineableDataStructure $dataStructure;
    private array $results = [];

    public function __construct(RedisPipeline $pipeline, PipelineableDataStructure $dataStructure)
    {
        $this->pipeline = $pipeline;
        $this->dataStructure = $dataStructure;
    }

    /**
     * Set multiple key-value pairs
     *
     * @param array $data Associative array of key-value pairs
     * @param callable $callback Callback to receive results
     * @return self
     */
    public function setMultiple(array $data, callable $callback = null): self
    {
        return $this->setMultipleWithPrefix($data, $this->dataStructure->name, $callback);
    }

    /**
     * Set multiple key-value pairs with a common prefix
     *
     * @param array $data
     * @param string $prefix
     * @param callable $callback
     * @return self
     */
    public function setMultipleWithPrefix(array $data, string $prefix, callable $callback = null): self
    {
        foreach ($data as $key => $value) {
            $fullKey = $prefix . ':' . $key;
            $encodedValue = $this->dataStructure->encodeValue($value);
            $this->pipeline->queueCommand('set', [$fullKey, $encodedValue]);
        }

        if ($callback) {
            $results = $this->pipeline->execute();
            $callback($results);
        }

        return $this;
    }

    /**
     * Delete multiple keys
     *
     * @param array $keys
     * @param callable $callback Callback to receive results
     * @return self
     */
    public function deleteMultiple(array $keys, callable $callback = null): self
    {
        return $this->deleteMultipleWithPrefix($keys, $this->dataStructure->name, $callback);
    }

    /**
     * Delete multiple keys with a common prefix
     *
     * @param array $keys
     * @param string $prefix
     * @param callable $callback
     * @return self
     */
    public function deleteMultipleWithPrefix(array $keys, string $prefix, callable $callback = null): self
    {
        $fullKeys = array_map(function($key) use ($prefix) {
            return $prefix . ':' . $key;
        }, $keys);

        $this->pipeline->queueCommand('del', $fullKeys);

        if ($callback) {
            $results = $this->pipeline->execute();
            $callback($results);
        }

        return $this;
    }

    /**
     * Set multiple hash fields
     *
     * @param string $hashKey
     * @param array $data Associative array of field-value pairs
     * @param callable $callback Callback to receive results
     * @return self
     */
    public function hashFields(string $hashKey, array $data, callable $callback = null): self
    {
        $encodedData = [];
        foreach ($data as $field => $value) {
            $encodedData[$field] = $this->dataStructure->encodeValue($value);
        }

        $this->pipeline->queueCommand('hMSet', [$hashKey, $encodedData]);

        if ($callback) {
            $results = $this->pipeline->execute();
            $callback($results);
        }

        return $this;
    }

    /**
     * Set individual hash fields
     *
     * @param string $hashKey
     * @param array $fields Array of ['field' => 'value'] pairs
     * @param callable $callback
     * @return self
     */
    public function hashSetFields(string $hashKey, array $fields, callable $callback = null): self
    {
        foreach ($fields as $field => $value) {
            $encodedValue = $this->dataStructure->encodeValue($value);
            $this->pipeline->queueCommand('hSet', [$hashKey, $field, $encodedValue]);
        }

        if ($callback) {
            $results = $this->pipeline->execute();
            $callback($results);
        }

        return $this;
    }

    /**
     * Delete multiple hash fields
     *
     * @param string $hashKey
     * @param array $fields
     * @param callable $callback
     * @return self
     */
    public function hashDeleteFields(string $hashKey, array $fields, callable $callback = null): self
    {
        $this->pipeline->queueCommand('hDel', array_merge([$hashKey], $fields));

        if ($callback) {
            $results = $this->pipeline->execute();
            $callback($results);
        }

        return $this;
    }

    /**
     * Add multiple values to a list
     *
     * @param string $listKey
     * @param array $values
     * @param callable $callback
     * @return self
     */
    public function listAdd(string $listKey, array $values, callable $callback = null): self
    {
        foreach ($values as $value) {
            $encodedValue = $this->dataStructure->encodeValue($value);
            $this->pipeline->queueCommand('rPush', [$listKey, $encodedValue]);
        }

        if ($callback) {
            $results = $this->pipeline->execute();
            $callback($results);
        }

        return $this;
    }

    /**
     * Add multiple values to the beginning of a list
     *
     * @param string $listKey
     * @param array $values
     * @param callable $callback
     * @return self
     */
    public function listAddFront(string $listKey, array $values, callable $callback = null): self
    {
        foreach (array_reverse($values) as $value) {
            $encodedValue = $this->dataStructure->encodeValue($value);
            $this->pipeline->queueCommand('lPush', [$listKey, $encodedValue]);
        }

        if ($callback) {
            $results = $this->pipeline->execute();
            $callback($results);
        }

        return $this;
    }

    /**
     * Trim a list to specified range
     *
     * @param string $listKey
     * @param int $start
     * @param int $end
     * @param callable $callback
     * @return self
     */
    public function listTrim(string $listKey, int $start, int $end, callable $callback = null): self
    {
        $this->pipeline->queueCommand('lTrim', [$listKey, $start, $end]);

        if ($callback) {
            $results = $this->pipeline->execute();
            $callback($results);
        }

        return $this;
    }

    /**
     * Add multiple members to a set
     *
     * @param string $setKey
     * @param array $members
     * @param callable $callback
     * @return self
     */
    public function setAdd(string $setKey, array $members, callable $callback = null): self
    {
        foreach ($members as $member) {
            $encodedMember = $this->dataStructure->encodeValue($member);
            $this->pipeline->queueCommand('sAdd', [$setKey, $encodedMember]);
        }

        if ($callback) {
            $results = $this->pipeline->execute();
            $callback($results);
        }

        return $this;
    }

    /**
     * Remove multiple members from a set
     *
     * @param string $setKey
     * @param array $members
     * @param callable $callback
     * @return self
     */
    public function setRemove(string $setKey, array $members, callable $callback = null): self
    {
        foreach ($members as $member) {
            $encodedMember = $this->dataStructure->encodeValue($member);
            $this->pipeline->queueCommand('sRem', [$setKey, $encodedMember]);
        }

        if ($callback) {
            $results = $this->pipeline->execute();
            $callback($results);
        }

        return $this;
    }

    /**
     * Add multiple members to a sorted set
     *
     * @param string $zsetKey
     * @param array $members Associative array of ['member' => score]
     * @param callable $callback
     * @return self
     */
    public function zsetAdd(string $zsetKey, array $members, callable $callback = null): self
    {
        foreach ($members as $member => $score) {
            $encodedMember = $this->dataStructure->encodeValue($member);
            $this->pipeline->queueCommand('zAdd', [$zsetKey, $score, $encodedMember]);
        }

        if ($callback) {
            $results = $this->pipeline->execute();
            $callback($results);
        }

        return $this;
    }

    /**
     * Remove multiple members from a sorted set
     *
     * @param string $zsetKey
     * @param array $members
     * @param callable $callback
     * @return self
     */
    public function zsetRemove(string $zsetKey, array $members, callable $callback = null): self
    {
        foreach ($members as $member) {
            $encodedMember = $this->dataStructure->encodeValue($member);
            $this->pipeline->queueCommand('zRem', [$zsetKey, $encodedMember]);
        }

        if ($callback) {
            $results = $this->pipeline->execute();
            $callback($results);
        }

        return $this;
    }

    /**
     * Set expiration for multiple keys
     *
     * @param array $keys
     * @param int $seconds
     * @param callable $callback
     * @return self
     */
    public function expireMultiple(array $keys, int $seconds, callable $callback = null): self
    {
        return $this->expireMultipleWithPrefix($keys, $seconds, $this->dataStructure->name, $callback);
    }

    /**
     * Set expiration for multiple keys with prefix
     *
     * @param array $keys
     * @param int $seconds
     * @param string $prefix
     * @param callable $callback
     * @return self
     */
    public function expireMultipleWithPrefix(array $keys, int $seconds, string $prefix, callable $callback = null): self
    {
        foreach ($keys as $key) {
            $fullKey = $prefix . ':' . $key;
            $this->pipeline->queueCommand('expire', [$fullKey, $seconds]);
        }

        if ($callback) {
            $results = $this->pipeline->execute();
            $callback($results);
        }

        return $this;
    }

    /**
     * Perform a complex batch write operation
     *
     * @param callable $operation Custom operation callback
     * @return self
     */
    public function custom(callable $operation): self
    {
        $operation($this->pipeline);
        return $this;
    }

    /**
     * Execute the batch and return results
     *
     * @return array
     */
    public function execute(): array
    {
        $this->results = $this->pipeline->execute();
        return $this->results;
    }

    /**
     * Execute asynchronously and return promise
     *
     * @return PromiseInterface
     */
    public function executeAsync(): PromiseInterface
    {
        return $this->pipeline->executeAsync();
    }

    /**
     * Get the number of queued operations
     *
     * @return int
     */
    public function getQueueCount(): int
    {
        return $this->pipeline->getQueuedCount();
    }

    /**
     * Clear the batch writer
     *
     * @return self
     */
    public function clear(): self
    {
        $this->results = [];
        $this->pipeline->clear();
        return $this;
    }

    /**
     * Get results from the last batch operation
     *
     * @return array
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Check if the batch is empty
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->pipeline->isEmpty();
    }
}