<?php

namespace Rediphp;

use Redis;

/**
 * Redisson-compatible distributed Stream implementation
 * Uses Redis Stream structure, compatible with Redisson's RStream
 * 
 * Stream is a log-like data structure that allows multiple producers and consumers
 * Supports consumer groups, pending entries, and various read operations
 */
class RStream
{
    private Redis $redis;
    private string $name;

    /**
     * @param Redis $redis Redis extension instance
     * @param string $name Stream name
     */
    public function __construct(Redis $redis, string $name)
    {
        $this->redis = $redis;
        $this->name = $name;
    }

    /**
     * Add an entry to the stream
     *
     * @param array<string, mixed> $fields Field-value pairs to add
     * @param string $id Optional entry ID, use '*' for auto-generation
     * @param array<string, mixed> $options Optional parameters:
     *                       - maxlen: Maximum length of stream
     *                       - approx: Use approximate trimming
     * @return string|false The added entry ID, or false on failure
     */
    public function add(array $fields, string $id = '*', array $options = [])
    {
        // Encode field values
        $encodedFields = [];
        foreach ($fields as $field => $value) {
            $encodedFields[$field] = $this->encodeValue($value);
        }

        // Handle maxlen option - correct order: key, id, fields, maxlen, [approx]
        if (isset($options['maxlen'])) {
            $maxlen = (int)$options['maxlen'];
            $approx = isset($options['approx']) && $options['approx'];
            
            if ($approx) {
                return $this->redis->xAdd($this->name, $id, $encodedFields, $maxlen, true);
            } else {
                return $this->redis->xAdd($this->name, $id, $encodedFields, $maxlen);
            }
        }

        return $this->redis->xAdd($this->name, $id, $encodedFields);
    }

    /**
     * Add multiple entries to the stream
     *
     * @param array<array{0: array<string, mixed>, 1?: string}> $entries Array of entries, each entry is [fields, id]
     * @param array<string, mixed> $options Same options as add method
     * @return array<string> Array of added entry IDs
     */
    public function addAll(array $entries, array $options = []): array
    {
        $addedIds = [];

        foreach ($entries as $entry) {
            $fields = $entry[0];
            $id = $entry[1] ?? '*';
            
            $result = $this->add($fields, $id, $options);
            if ($result !== false) {
                $addedIds[] = $result;
            }
        }

        return $addedIds;
    }

    /**
     * Read entries from a range
     *
     * @param string $start Starting ID, use '-' for beginning
     * @param string $end Ending ID, use '+' for end
     * @param int|null $count Maximum number of entries to return
     * @return array<string, array<string, mixed>> Array of entries with IDs as keys
     */
    public function readRange(string $start, string $end, ?int $count = null): array
    {
        return $this->read($start, $end, $count);
    }

    /**
     * Read entries from the stream
     *
     * @param string $start Starting ID, use '-' for beginning, '0' for first entry
     * @param string $end Ending ID, use '+' for end, '$' for last entry
     * @param int|null $count Maximum number of entries to return
     * @return array<string, array<string, mixed>> Array of stream entries
     */
    public function read(string $start = '-', string $end = '+', ?int $count = null): array
    {
        $args = [$this->name, $start, $end];

        if ($count !== null) {
            $args[] = $count;
        }

        $result = $this->redis->xRange(...$args);
        
        if ($result === false) {
            return [];
        }

        // Decode field values
        $decoded = [];
        foreach ($result as $id => $fields) {
            $decodedFields = [];
            foreach ($fields as $field => $value) {
                $decodedFields[$field] = $this->decodeValue($value);
            }
            $decoded[$id] = $decodedFields;
        }

        return $decoded;
    }

    /**
     * Read entries in reverse order
     *
     * @param string $end Ending ID, use '+' for end
     * @param string $start Starting ID, use '-' for beginning
     * @param int|null $count Maximum number of entries to return
     * @return array<string, array<string, mixed>> Array of entries with IDs as keys
     */
    public function readReverse(string $end = '+', string $start = '-', ?int $count = null): array
    {
        $args = [$this->name, $end, $start];

        if ($count !== null) {
            $args[] = $count;
        }

        $result = $this->redis->xRevRange(...$args);
        
        if ($result === false) {
            return [];
        }

        // Decode field values
        $decoded = [];
        foreach ($result as $id => $fields) {
            $decodedFields = [];
            foreach ($fields as $field => $value) {
                $decodedFields[$field] = $this->decodeValue($value);
            }
            $decoded[$id] = $decodedFields;
        }

        return $decoded;
    }

    /**
     * Get the length of the stream
     *
     * @return int Number of entries in the stream
     */
    public function length(): int
    {
        return (int) $this->redis->xLen($this->name);
    }

    /**
     * Trim the stream to a maximum length
     *
     * @param int $maxlen Maximum length
     * @param bool $approx Use approximate trimming
     * @return int Number of entries removed
     */
    public function trim(int $maxlen, bool $approx = false): int
    {
        // xTrim signature: xTrim(key, threshold, approx, minid, limit)
        // We don't need to specify limit when using maxlen trimming
        return (int) $this->redis->xTrim($this->name, (string) $maxlen, $approx, false);
    }

    /**
     * Delete entries from the stream
     *
     * @param array<string> $ids Array of entry IDs to delete
     * @return int Number of entries deleted
     */
    public function delete(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $args = array_merge([$this->name], $ids);
        return $this->redis->xDel($this->name, $ids);
    }

    /**
     * Create a consumer group
     *
     * @param string $groupName Name of the consumer group
     * @param string $startId Starting ID, use '0' for beginning or '$' for new entries only
     * @param bool $createStream Create stream if it doesn't exist
     * @return bool True on success, false on failure
     */
    public function createGroup(string $groupName, string $startId = '$', bool $createStream = true): bool
    {
        try {
            $args = ['CREATE', $this->name, $groupName, $startId];
            
            if ($createStream && !$this->exists()) {
                // MKSTREAM option to create stream if it doesn't exist
                $args[] = 'MKSTREAM';
            }
            
            return $this->redis->xGroup(...$args);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Delete a consumer group
     *
     * @param string $groupName Name of the consumer group
     * @return bool True on success, false on failure
     */
    public function deleteGroup(string $groupName): bool
    {
        try {
            return $this->redis->xGroup('DESTROY', $this->name, $groupName);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Read entries from a consumer group
     *
     * @param string $groupName Consumer group name
     * @param string $consumerName Consumer name
     * @param string $startId Starting ID, use '>' for new entries
     * @param int|null $count Maximum number of entries
     * @param int|null $block Block for up to N milliseconds
     * @return array<string, array<string, mixed>> Array of entries with IDs as keys
     */
    public function readGroup(string $groupName, string $consumerName, string $startId = '>', 
                              ?int $count = null, ?int $block = null): array
    {
        $streams = [$this->name => $startId];
        
        $result = $this->redis->xReadGroup($groupName, $consumerName, $streams, $count ?? 1, $block ?? 1);
        
        if ($result === false || !isset($result[$this->name])) {
            return [];
        }

        // Decode field values
        $decoded = [];
        foreach ($result[$this->name] as $id => $fields) {
            $decodedFields = [];
            foreach ($fields as $field => $value) {
                $decodedFields[$field] = $this->decodeValue($value);
            }
            $decoded[$id] = $decodedFields;
        }

        return $decoded;
    }

    /**
     * Get pending messages for a consumer group
     *
     * @param string $groupName Consumer group name
     * @param string $consumerName Optional consumer name to filter
     * @param int $count Optional maximum number of messages to return
     * @return array<int, array{name: string, consumer: string, idle: int, deliveries: int}>
     */
    public function pending(string $groupName, string $consumerName = '', int $count = 100): array
    {
        $result = $this->redis->xPending($this->name, $groupName, '-', '+', $count, $consumerName ?: null);
        
        if ($result === false) {
            return [];
        }

        return $result;
    }

    /**
     * Acknowledge processing of entries
     *
     * @param string $groupName Consumer group name
     * @param array<string> $ids Array of entry IDs to acknowledge
     * @return int Number of entries acknowledged
     */
    public function ack(string $groupName, array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $result = $this->redis->xAck($this->name, $groupName, $ids);
        return $result === false ? 0 : $result;
    }

    /**
     * Check if the stream exists
     *
     * @return bool True if stream exists, false otherwise
     */
    public function exists(): bool
    {
        return $this->redis->exists($this->name) > 0;
    }

    /**
     * Clear the stream (delete all entries)
     */
    public function clear(): void
    {
        $this->redis->del($this->name);
    }

    /**
     * Encode value for storage (Redisson compatibility)
     *
     * @param mixed $value
     * @return string
     * @throws \RuntimeException If encoding fails
     */
    private function encodeValue($value): string
    {
        $result = json_encode($value);
        if ($result === false) {
            throw new \RuntimeException('Failed to encode value: ' . json_last_error_msg());
        }
        return $result;
    }

    /**
     * Decode value from storage
     *
     * @param string $value
     * @return mixed
     */
    private function decodeValue(string $value)
    {
        return json_decode($value, true);
    }
}