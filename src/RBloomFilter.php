<?php

namespace Rediphp;

use Redis;
use Rediphp\Services\SerializationService;

/**
 * Redisson-compatible distributed BloomFilter implementation
 * Uses Redis Bitmap for distributed bloom filter, compatible with Redisson's RBloomFilter
 */
class RBloomFilter
{
    private Redis $redis;
    private string $name;
    private int $expectedInsertions = 55000000;
    private float $falseProbability = 0.03;
    private int $size = 0;
    private int $hashIterations = 0;

    public function __construct(Redis $redis, string $name)
    {
        $this->redis = $redis;
        $this->name = $name;
    }

    /**
     * Try to initialize the bloom filter
     *
     * @param int $expectedInsertions Expected number of insertions
     * @param float $falseProbability False positive probability
     * @return bool
     */
    public function tryInit(int $expectedInsertions, float $falseProbability): bool
    {
        $this->expectedInsertions = $expectedInsertions;
        $this->falseProbability = $falseProbability;

        // Calculate optimal size and hash iterations
        $this->size = $this->optimalNumOfBits($expectedInsertions, $falseProbability);
        $this->hashIterations = $this->optimalNumOfHashFunctions($expectedInsertions, $this->size);

        // Store config
        $configKey = $this->name . ':config';
        $config = SerializationService::getInstance()->encode([
            'size' => $this->size,
            'hashIterations' => $this->hashIterations,
            'expectedInsertions' => $expectedInsertions,
            'falseProbability' => $falseProbability,
        ]);

        $result = $this->redis->set($configKey, $config, ['NX']);
        return $result !== false;
    }

    /**
     * Load existing bloom filter configuration
     */
    private function loadConfig(): void
    {
        $configKey = $this->name . ':config';
        $config = $this->redis->get($configKey);
        
        if ($config !== false) {
            $data = SerializationService::getInstance()->decode($config, true);
            $this->size = $data['size'];
            $this->hashIterations = $data['hashIterations'];
            $this->expectedInsertions = $data['expectedInsertions'];
            $this->falseProbability = $data['falseProbability'];
        }
    }

    /**
     * Add an element to the bloom filter
     *
     * @param mixed $element
     * @return bool
     * @throws \InvalidArgumentException If the element is empty
     */
    public function add($element): bool
    {
        if (empty($element) && $element !== '0' && $element !== 0) {
            throw new \InvalidArgumentException('Element cannot be empty');
        }
        
        $this->loadConfig();
        
        // 如果没有配置，使用默认配置初始化
        if ($this->size === 0 || $this->hashIterations === 0) {
            $this->tryInit($this->expectedInsertions, $this->falseProbability);
        }
        
        $hash = $this->hash($element);
        
        for ($i = 0; $i < $this->hashIterations; $i++) {
            $bitIndex = $this->getBitIndex($hash, $i);
            $this->redis->setBit($this->name, $bitIndex, 1);
        }
        
        return true;
    }

    /**
     * Check if an element might exist in the bloom filter
     *
     * @param mixed $element
     * @return bool
     */
    public function contains($element): bool
    {
        $this->loadConfig();
        
        // 如果没有配置，使用默认配置初始化
        if ($this->size === 0 || $this->hashIterations === 0) {
            $this->tryInit($this->expectedInsertions, $this->falseProbability);
        }
        
        $hash = $this->hash($element);
        
        for ($i = 0; $i < $this->hashIterations; $i++) {
            $bitIndex = $this->getBitIndex($hash, $i);
            if ($this->redis->getBit($this->name, $bitIndex) === 0) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get approximate count of elements
     *
     * @return int
     */
    public function count(): int
    {
        $this->loadConfig();
        
        // 如果没有配置，使用默认配置初始化
        if ($this->size === 0 || $this->hashIterations === 0) {
            $this->tryInit($this->expectedInsertions, $this->falseProbability);
        }
        
        $bitCount = $this->redis->bitCount($this->name);
        
        if ($bitCount === 0) {
            return 0;
        }
        
        // Estimate count using formula: count ≈ -size * ln(1 - bitCount/size) / hashIterations
        $fractionOfBitsSet = $bitCount / $this->size;
        return (int)(-$this->size * log(1 - $fractionOfBitsSet) / $this->hashIterations);
    }

    /**
     * Delete the bloom filter
     *
     * @return bool
     */
    public function delete(): bool
    {
        $this->redis->del($this->name . ':config');
        return $this->redis->del($this->name) > 0;
    }

    /**
     * Clear the bloom filter
     *
     * @return void
     */
    public function clear(): void
    {
        $this->redis->del($this->name);
        $this->redis->del($this->name . ':config');
        $this->size = 0;
        $this->hashIterations = 0;
    }

    /**
     * Hash an element
     *
     * @param mixed $element
     * @return string
     */
    private function hash($element): string
    {
        return hash('sha256', SerializationService::getInstance()->encode($element), true);
    }

    /**
     * Get bit index for hash iteration
     *
     * @param string $hash
     * @param int $iteration
     * @return int
     */
    private function getBitIndex(string $hash, int $iteration): int
    {
        $bytes = unpack('V*', substr($hash, $iteration * 4, 4));
        $value = $bytes[1] ?? 0;
        return abs($value) % $this->size;
    }

    /**
     * Calculate optimal number of bits
     *
     * @param int $n Expected insertions
     * @param float $p False probability
     * @return int
     */
    private function optimalNumOfBits(int $n, float $p): int
    {
        return (int)ceil(-($n * log($p)) / (log(2) ** 2));
    }

    /**
     * Calculate optimal number of hash functions
     *
     * @param int $n Expected insertions
     * @param int $m Size in bits
     * @return int
     */
    private function optimalNumOfHashFunctions(int $n, int $m): int
    {
        return max(1, (int)round(($m / $n) * log(2)));
    }
}
