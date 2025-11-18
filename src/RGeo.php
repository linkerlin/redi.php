<?php

namespace Rediphp;

use Redis;

/**
 * Redisson-compatible distributed Geo implementation
 * Uses Redis Geo structure, compatible with Redisson's RGeo
 * 
 * Geo is a data structure for storing and querying geographic coordinates
 * Supports distance calculations, radius searches, and geohash operations
 */
class RGeo
{
    private Redis $redis;
    private string $name;

    public function __construct(Redis $redis, string $name)
    {
        $this->redis = $redis;
        $this->name = $name;
    }

    /**
     * Add a geographic member with coordinates
     *
     * @param float $longitude Longitude (-180 to 180)
     * @param float $latitude Latitude (-85.05112878 to 85.05112878)
     * @param string $member Member name
     * @return int Number of elements added (0 or 1)
     * @throws \InvalidArgumentException If coordinates are invalid
     */
    public function add(float $longitude, float $latitude, string $member): int
    {
        // 验证经度范围
        if ($longitude < -180 || $longitude > 180) {
            throw new \InvalidArgumentException('Invalid longitude: must be between -180 and 180');
        }
        
        // 验证纬度范围
        if ($latitude < -85.05112878 || $latitude > 85.05112878) {
            throw new \InvalidArgumentException('Invalid latitude: must be between -85.05112878 and 85.05112878');
        }
        
        // 验证成员名
        if (empty($member)) {
            throw new \InvalidArgumentException('Member name cannot be empty');
        }
        
        return $this->redis->geoAdd($this->name, $longitude, $latitude, $member);
    }

    /**
     * Add multiple geographic members
     *
     * @param array $locations Array of [longitude, latitude, member] tuples
     * @return int Number of elements added
     */
    public function addAll(array $locations): int
    {
        if (empty($locations)) {
            return 0;
        }

        $args = [];
        foreach ($locations as $location) {
            if (count($location) === 3) {
                $args[] = $location[0]; // longitude
                $args[] = $location[1]; // latitude
                $args[] = $location[2]; // member
            }
        }

        if (empty($args)) {
            return 0;
        }

        return $this->redis->geoAdd($this->name, ...$args);
    }

    /**
     * Get the distance between two members
     *
     * @param string $member1 First member
     * @param string $member2 Second member
     * @param string $unit Unit of distance (m, km, mi, ft)
     * @return float|null Distance in specified unit, or null if member not found
     */
    public function distance(string $member1, string $member2, string $unit = 'km'): ?float
    {
        $result = $this->redis->geoDist($this->name, $member1, $member2, $unit);
        return $result !== false ? (float)$result : null;
    }

    /**
     * Get the geohash of a member
     *
     * @param string $member Member name
     * @return string|null Geohash string, or null if member not found
     */
    public function hash(string $member): ?string
    {
        $result = $this->redis->geoHash($this->name, $member);
        if ($result !== false && isset($result[0]) && !empty($result[0])) {
            return $result[0];
        }
        return null;
    }

    /**
     * Get the coordinates of a member
     *
     * @param string $member Member name
     * @return array|null Array with [longitude, latitude], or null if member not found
     */
    public function position(string $member): ?array
    {
        $result = $this->redis->geoPos($this->name, $member);
        if ($result !== false && !empty($result) && isset($result[0]) && is_array($result[0]) && count($result[0]) >= 2) {
            return [
                (float)$result[0][0], // longitude
                (float)$result[0][1]  // latitude
            ];
        }
        return null;
    }

    /**
     * Find members within a radius of a coordinate point
     *
     * @param float $longitude Center longitude
     * @param float $latitude Center latitude
     * @param float $radius Radius distance
     * @param string $unit Unit of distance (m, km, mi, ft)
     * @param array $options Optional parameters:
     *                       - withCoord: Include coordinates in results
     *                       - withDist: Include distance in results
     *                       - withHash: Include geohash in results
     *                       - count: Limit number of results
     *                       - sort: Sort order (ASC or DESC)
     * @return array Array of results
     */
    public function radius(float $longitude, float $latitude, float $radius, string $unit = 'km', array $options = []): array
    {
        // 新版本的Redis扩展使用数组作为第6个参数
        $redisOptions = [];
        
        if (isset($options['withCoord']) && $options['withCoord']) {
            $redisOptions[] = 'WITHCOORD';
        }
        if (isset($options['withDist']) && $options['withDist']) {
            $redisOptions[] = 'WITHDIST';
        }
        if (isset($options['withHash']) && $options['withHash']) {
            $redisOptions[] = 'WITHHASH';
        }
        if (isset($options['count'])) {
            $redisOptions[] = 'COUNT';
            $redisOptions[] = $options['count'];
        }
        if (isset($options['sort'])) {
            $redisOptions[] = strtoupper($options['sort']);
        }
        
        return $this->redis->geoRadius($this->name, $longitude, $latitude, $radius, $unit, $redisOptions);
    }

    /**
     * Find members within a radius of a member
     *
     * @param string $member Center member
     * @param float $radius Radius distance
     * @param string $unit Unit of distance (m, km, mi, ft)
     * @param array $options Same options as radius method
     * @return array Array of results
     */
    public function radiusByMember(string $member, float $radius, string $unit = 'km', array $options = []): array
    {
        // 新版本的Redis扩展使用数组作为第6个参数
        $redisOptions = [];
        
        if (isset($options['withCoord']) && $options['withCoord']) {
            $redisOptions[] = 'WITHCOORD';
        }
        if (isset($options['withDist']) && $options['withDist']) {
            $redisOptions[] = 'WITHDIST';
        }
        if (isset($options['withHash']) && $options['withHash']) {
            $redisOptions[] = 'WITHHASH';
        }
        if (isset($options['count'])) {
            $redisOptions[] = 'COUNT';
            $redisOptions[] = $options['count'];
        }
        if (isset($options['sort'])) {
            $redisOptions[] = strtoupper($options['sort']);
        }
        
        return $this->redis->geoRadiusByMember($this->name, $member, $radius, $unit, $redisOptions);
    }

    /**
     * Remove a member from the geo set
     *
     * @param string $member Member to remove
     * @return int Number of members removed (0 or 1)
     */
    public function remove(string $member): int
    {
        return $this->redis->zRem($this->name, $member);
    }

    /**
     * Remove multiple members from the geo set
     *
     * @param array $members Array of members to remove
     * @return int Number of members removed
     */
    public function removeAll(array $members): int
    {
        if (empty($members)) {
            return 0;
        }

        return $this->redis->zRem($this->name, ...$members);
    }

    /**
     * Check if a member exists in the geo set
     *
     * @param string $member Member name
     * @return bool True if member exists, false otherwise
     */
    public function exists(string $member): bool
    {
        return $this->redis->zScore($this->name, $member) !== false;
    }

    /**
     * Get the number of members in the geo set
     *
     * @return int Number of members
     */
    public function size(): int
    {
        return $this->redis->zCard($this->name);
    }

    /**
     * Check if the geo set is empty
     *
     * @return bool True if empty, false otherwise
     */
    public function isEmpty(): bool
    {
        return $this->size() === 0;
    }

    /**
     * Clear all members from the geo set
     */
    public function clear(): void
    {
        $this->redis->del($this->name);
    }

    /**
     * Get all members in the geo set
     *
     * @param int $start Start index (0-based)
     * @param int $stop Stop index (-1 for all)
     * @param bool $withScores Include scores (coordinates encoded)
     * @return array Array of members
     */
    public function getMembers(int $start = 0, int $stop = -1, bool $withScores = false): array
    {
        return $this->redis->zRange($this->name, $start, $stop, $withScores);
    }
}