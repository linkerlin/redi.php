<?php

namespace Rediphp;

use Redis;
use Rediphp\Services\SerializationService;

/**
 * Redisson-compatible distributed TimeSeries implementation
 * Uses Redis Sorted Set and Hash structures, compatible with Redisson's RTimeSeries
 * 
 * TimeSeries is optimized for storing and querying time-series data
 * with automatic timestamp handling, aggregation, and retention policies
 */
class RTimeSeries
{
    private Redis $redis;
    private string $name;
    private string $dataKey;
    private string $indexKey;

    public function __construct(Redis $redis, string $name)
    {
        $this->redis = $redis;
        $this->name = $name;
        $this->dataKey = "{$name}:data";
        $this->indexKey = "{$name}:index";
    }

    /**
     * Add a data point to the time series
     *
     * @param float $value The value to store
     * @param int|null $timestamp Optional timestamp (milliseconds), null for current time
     * @param array $tags Optional tags as key-value pairs
     * @param array $options Optional parameters:
     *                       - retention: Retention time in milliseconds
     *                       - uncompressed: Store without compression
     * @return int|false The timestamp of the added data point, or false on failure
     */
    public function add(float $value, ?int $timestamp = null, array $tags = [], array $options = [])
    {
        // Set default timestamp if not provided
        if ($timestamp === null) {
            $timestamp = (int)(microtime(true) * 1000);
        } else {
            // Ensure timestamp is an integer
            $timestamp = (int)$timestamp;
        }
        
        // Store the data point in a hash
        $data = [
            'value' => $value,
            'timestamp' => $timestamp
        ];

        // Add tags if provided
        if (!empty($tags)) {
            $data['tags'] = SerializationService::getInstance()->encode($tags);
        }

        // Create a unique key for this data point
        $dataPointKey = "{$this->dataKey}:{$timestamp}";
        
        // Store data in hash
        $success = $this->redis->hMSet($dataPointKey, $data);
        if (!$success) {
            return false;
        }

        // Add to sorted set for time-based indexing
        $this->redis->zAdd($this->indexKey, $timestamp, $timestamp);

        // Handle retention policy
        if (isset($options['retention'])) {
            $this->applyRetention($options['retention']);
        }

        return $timestamp;
    }

    /**
     * Add multiple data points to the time series
     *
     * @param array $dataPoints Array of [value, timestamp, tags] tuples
     * @param array $options Same options as add method
     * @return array Array of successfully added timestamps
     */
    public function addAll(array $dataPoints, array $options = []): array
    {
        $addedTimestamps = [];
        
        foreach ($dataPoints as $dataPoint) {
            $value = $dataPoint[0] ?? 0;
            $timestamp = isset($dataPoint[1]) ? (int)$dataPoint[1] : null;
            $tags = $dataPoint[2] ?? [];
            
            $result = $this->add($value, $timestamp, $tags, $options);
            if ($result !== false) {
                $addedTimestamps[] = $result;
            }
        }

        return $addedTimestamps;
    }

    /**
     * Get a data point by timestamp
     *
     * @param int $timestamp The timestamp to retrieve
     * @return array|null The data point with value, timestamp, and tags, or null if not found
     */
    public function get(int $timestamp): ?array
    {
        $dataPointKey = "{$this->dataKey}:{$timestamp}";
        $data = $this->redis->hGetAll($dataPointKey);
        
        if (empty($data)) {
            return null;
        }

        $result = [
            'value' => (float)$data['value'],
            'timestamp' => (int)$data['timestamp']
        ];

        if (isset($data['tags'])) {
            $result['tags'] = SerializationService::getInstance()->decode($data['tags'], true);
        }

        return $result;
    }

    /**
     * Get data points within a time range
     *
     * @param int|null $start Start timestamp (null for beginning)
     * @param int|null $end End timestamp (null for end)
     * @param int|null $limit Maximum number of data points
     * @param bool $reverse Return in reverse chronological order
     * @return array Array of data points
     */
    public function range(?int $start = null, ?int $end = null, ?int $limit = null, bool $reverse = false): array
    {
        $start = $start ?? '-inf';
        $end = $end ?? '+inf';
        
        // Get timestamps from sorted set
        $timestamps = $this->redis->zRangeByScore($this->indexKey, $start, $end, ['limit' => [0, $limit ?? -1]]);
        
        if ($reverse) {
            $timestamps = array_reverse($timestamps);
        }

        // Get data points for each timestamp
        $dataPoints = [];
        foreach ($timestamps as $timestamp) {
            $dataPoint = $this->get((int)$timestamp);
            if ($dataPoint !== null) {
                $dataPoints[] = $dataPoint;
            }
        }

        return $dataPoints;
    }

    /**
     * Get the latest data point
     *
     * @return array|null The latest data point, or null if empty
     */
    public function getLatest(): ?array
    {
        $timestamps = $this->redis->zRevRange($this->indexKey, 0, 0);
        
        if (empty($timestamps)) {
            return null;
        }

        return $this->get((int)$timestamps[0]);
    }

    /**
     * Get the earliest data point
     *
     * @return array|null The earliest data point, or null if empty
     */
    public function getEarliest(): ?array
    {
        $timestamps = $this->redis->zRange($this->indexKey, 0, 0);
        
        if (empty($timestamps)) {
            return null;
        }

        return $this->get((int)$timestamps[0]);
    }

    /**
     * Get aggregated statistics for a time range
     *
     * @param int|null $start Start timestamp
     * @param int|null $end End timestamp
     * @param string $aggregation Aggregation type: avg, sum, min, max, count
     * @return array Statistics including count, min, max, avg, sum
     */
    public function getStats(?int $start = null, ?int $end = null, string $aggregation = 'avg'): array
    {
        $dataPoints = $this->range($start, $end);
        
        if (empty($dataPoints)) {
            return [
                'count' => 0,
                'min' => null,
                'max' => null,
                'avg' => null,
                'sum' => null
            ];
        }

        $values = array_column($dataPoints, 'value');
        $count = count($values);
        $sum = array_sum($values);
        
        return [
            'count' => $count,
            'min' => min($values),
            'max' => max($values),
            'avg' => $sum / $count,
            'sum' => $sum
        ];
    }

    /**
     * Delete a data point by timestamp
     *
     * @param int $timestamp The timestamp to delete
     * @return bool True if deleted, false if not found
     */
    public function delete(int $timestamp): bool
    {
        $dataPointKey = "{$this->dataKey}:{$timestamp}";
        
        // Remove from index
        $this->redis->zRem($this->indexKey, $timestamp);
        
        // Remove data
        return $this->redis->del($dataPointKey) > 0;
    }

    /**
     * Delete data points within a time range
     *
     * @param int|null $start Start timestamp
     * @param int|null $end End timestamp
     * @return int Number of data points deleted
     */
    public function deleteRange(?int $start = null, ?int $end = null): int
    {
        $start = $start ?? '-inf';
        $end = $end ?? '+inf';
        
        // Get timestamps in range
        $timestamps = $this->redis->zRangeByScore($this->indexKey, $start, $end);
        
        if (empty($timestamps)) {
            return 0;
        }

        $deletedCount = 0;
        foreach ($timestamps as $timestamp) {
            if ($this->delete((int)$timestamp)) {
                $deletedCount++;
            }
        }

        return $deletedCount;
    }

    /**
     * Apply retention policy to remove old data
     *
     * @param int $retention Retention time in milliseconds
     */
    private function applyRetention(int $retention): void
    {
        $cutoffTime = (int)(microtime(true) * 1000) - $retention;
        $this->deleteRange(null, $cutoffTime);
    }

    /**
     * Get the size of the time series (number of data points)
     *
     * @return int Number of data points
     */
    public function size(): int
    {
        return $this->redis->zCard($this->indexKey);
    }

    /**
     * Check if the time series is empty
     *
     * @return bool True if empty, false otherwise
     */
    public function isEmpty(): bool
    {
        return $this->size() === 0;
    }

    /**
     * Clear all data points from the time series
     */
    public function clear(): void
    {
        // Get all timestamps
        $timestamps = $this->redis->zRange($this->indexKey, 0, -1);
        
        // Delete each data point
        foreach ($timestamps as $timestamp) {
            $dataPointKey = "{$this->dataKey}:{$timestamp}";
            $this->redis->del($dataPointKey);
        }

        // Clear index
        $this->redis->del($this->indexKey);
    }

    /**
     * Check if the time series exists
     *
     * @return bool True if exists, false otherwise
     */
    public function exists(): bool
    {
        return $this->redis->exists($this->indexKey) > 0;
    }
}