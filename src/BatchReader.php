<?php

namespace Rediphp;

/**
 * Batch Reader for Data Structure Operations
 * Provides efficient batch read operations
 */
class BatchReader
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
     * Get multiple items by keys
     *
     * @param array $keys
     * @param callable $callback Callback to receive results
     * @return self
     */
    public function getMultiple(array $keys, callable $callback): self
    {
        return $this->getMultipleWithPrefix($keys, $this->dataStructure->name, $callback);
    }

    /**
     * Get multiple items by keys with a common prefix
     *
     * @param array $keys
     * @param string $prefix
     * @param callable $callback
     * @return self
     */
    public function getMultipleWithPrefix(array $keys, string $prefix, callable $callback): self
    {
        foreach ($keys as $key) {
            $fullKey = $prefix . ':' . $key;
            $this->pipeline->queueCommand('get', [$fullKey]);
        }

        $this->pipeline->execute(); // Execute pipeline
        $results = $this->pipeline->execute();

        // Process results
        $processedResults = [];
        foreach ($results as $index => $result) {
            if ($result['success'] && $result['data'] !== false) {
                $processedResults[$keys[$index]] = $this->dataStructure->decodeValue($result['data']);
            } else {
                $processedResults[$keys[$index]] = null;
            }
        }

        $callback($processedResults);
        return $this;
    }

    /**
     * Check multiple keys exist
     *
     * @param array $keys
     * @param callable $callback
     * @return self
     */
    public function existsMultiple(array $keys, callable $callback): self
    {
        return $this->existsMultipleWithPrefix($keys, $this->dataStructure->name, $callback);
    }

    /**
     * Check multiple keys exist with prefix
     *
     * @param array $keys
     * @param string $prefix
     * @param callable $callback
     * @return self
     */
    public function existsMultipleWithPrefix(array $keys, string $prefix, callable $callback): self
    {
        $fullKeys = array_map(function($key) use ($prefix) {
            return $prefix . ':' . $key;
        }, $keys);

        $this->pipeline->queueCommand('exists', $fullKeys);
        $this->pipeline->execute();

        $results = $this->pipeline->execute();
        $existsResults = [];

        foreach ($results as $index => $result) {
            if ($result['success']) {
                $existsResults[$keys[$index]] = $result['data'] > 0;
            } else {
                $existsResults[$keys[$index]] = false;
            }
        }

        $callback($existsResults);
        return $this;
    }

    /**
     * Get TTL for multiple keys
     *
     * @param array $keys
     * @param callable $callback
     * @return self
     */
    public function ttlMultiple(array $keys, callable $callback): self
    {
        return $this->ttlMultipleWithPrefix($keys, $this->dataStructure->name, $callback);
    }

    /**
     * Get TTL for multiple keys with prefix
     *
     * @param array $keys
     * @param string $prefix
     * @param callable $callback
     * @return self
     */
    public function ttlMultipleWithPrefix(array $keys, string $prefix, callable $callback): self
    {
        foreach ($keys as $key) {
            $fullKey = $prefix . ':' . $key;
            $this->pipeline->queueCommand('ttl', [$fullKey]);
        }

        $results = $this->pipeline->execute();
        $ttlResults = [];

        foreach ($results as $index => $result) {
            if ($result['success']) {
                $ttlResults[$keys[$index]] = $result['data'];
            } else {
                $ttlResults[$keys[$index]] = -1;
            }
        }

        $callback($ttlResults);
        return $this;
    }

    /**
     * Batch read hash fields
     *
     * @param string $hashKey
     * @param array $fields
     * @param callable $callback
     * @return self
     */
    public function hashFields(string $hashKey, array $fields, callable $callback): self
    {
        $this->pipeline->queueCommand('hMGet', [$hashKey, $fields]);
        
        $results = $this->pipeline->execute();
        $hashData = [];

        if (!empty($results) && $results[0]['success']) {
            $fieldValues = $results[0]['data'];
            foreach ($fields as $index => $field) {
                $value = $fieldValues[$index] ?? false;
                $hashData[$field] = $value !== false ? $this->dataStructure->decodeValue($value) : null;
            }
        }

        $callback($hashData);
        return $this;
    }

    /**
     * Get all hash fields
     *
     * @param string $hashKey
     * @param callable $callback
     * @return self
     */
    public function hashAll(string $hashKey, callable $callback): self
    {
        $this->pipeline->queueCommand('hGetAll', [$hashKey]);
        
        $results = $this->pipeline->execute();
        $hashData = [];

        if (!empty($results) && $results[0]['success']) {
            $rawData = $results[0]['data'];
            foreach ($rawData as $field => $value) {
                $hashData[$field] = $this->dataStructure->decodeValue($value);
            }
        }

        $callback($hashData);
        return $this;
    }

    /**
     * Get list elements in range
     *
     * @param string $listKey
     * @param int $start
     * @param int $end
     * @param callable $callback
     * @return self
     */
    public function listRange(string $listKey, int $start, int $end, callable $callback): self
    {
        $this->pipeline->queueCommand('lRange', [$listKey, $start, $end]);
        
        $results = $this->pipeline->execute();
        $listData = [];

        if (!empty($results) && $results[0]['success']) {
            foreach ($results[0]['data'] as $value) {
                $listData[] = $this->dataStructure->decodeValue($value);
            }
        }

        $callback($listData);
        return $this;
    }

    /**
     * Get set members
     *
     * @param string $setKey
     * @param callable $callback
     * @return self
     */
    public function setMembers(string $setKey, callable $callback): self
    {
        $this->pipeline->queueCommand('sMembers', [$setKey]);
        
        $results = $this->pipeline->execute();
        $members = [];

        if (!empty($results) && $results[0]['success']) {
            foreach ($results[0]['data'] as $member) {
                $members[] = $this->dataStructure->decodeValue($member);
            }
        }

        $callback($members);
        return $this;
    }

    /**
     * Check multiple set members
     *
     * @param string $setKey
     * @param array $members
     * @param callable $callback
     * @return self
     */
    public function setContains(string $setKey, array $members, callable $callback): self
    {
        foreach ($members as $member) {
            $encodedMember = $this->dataStructure->encodeValue($member);
            $this->pipeline->queueCommand('sIsMember', [$setKey, $encodedMember]);
        }

        $results = $this->pipeline->execute();
        $membershipResults = [];

        foreach ($results as $index => $result) {
            if ($result['success']) {
                $membershipResults[$members[$index]] = $result['data'] > 0;
            } else {
                $membershipResults[$members[$index]] = false;
            }
        }

        $callback($membershipResults);
        return $this;
    }

    /**
     * Get sorted set range
     *
     * @param string $zsetKey
     * @param int $start
     * @param int $end
     * @param bool $withScores
     * @param callable $callback
     * @return self
     */
    public function zsetRange(string $zsetKey, int $start, int $end, bool $withScores = false, callable $callback): self
    {
        $this->pipeline->queueCommand('zRange', [$zsetKey, $start, $end, $withScores]);
        
        $results = $this->pipeline->execute();
        $zsetData = [];

        if (!empty($results) && $results[0]['success']) {
            if ($withScores) {
                $data = $results[0]['data'];
                for ($i = 0; $i < count($data); $i += 2) {
                    $member = $this->dataStructure->decodeValue($data[$i]);
                    $score = (float)$data[$i + 1];
                    $zsetData[$member] = $score;
                }
            } else {
                foreach ($results[0]['data'] as $member) {
                    $zsetData[] = $this->dataStructure->decodeValue($member);
                }
            }
        }

        $callback($zsetData);
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
     * Clear the batch reader
     *
     * @return self
     */
    public function clear(): self
    {
        $this->results = [];
        $this->pipeline->clear();
        return $this;
    }
}