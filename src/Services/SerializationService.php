<?php

namespace Rediphp\Services;

/**
 * Redis 序列化服务
 * 支持多种序列化方式（JSON、igbinary、msgpack），自动选择最快的可用方式
 * 保持向后兼容，可以读取旧格式的数据
 */
class SerializationService
{
    private static $instance = null;
    private $serializer = null;
    private $serializerName = 'json';
    private $formatPrefix = ''; // 序列化格式前缀标识

    // 支持的序列化方式（按优先级排序）
    private const SERIALIZERS = [
        'igbinary' => [
            'encode' => 'igbinary_serialize',
            'decode' => 'igbinary_unserialize',
            'check' => 'extension_loaded',
            'extension' => 'igbinary',
            'prefix' => "\x00\x00\x00\x02" // igbinary 格式标识
        ],
        'msgpack' => [
            'encode' => 'msgpack_pack',
            'decode' => 'msgpack_unpack',
            'check' => 'function_exists',
            'function' => 'msgpack_pack',
            'prefix' => "\x82" // msgpack map 标识
        ],
        'json' => [
            'encode' => 'json_encode',
            'decode' => 'json_decode',
            'check' => 'always_available',
            'prefix' => '' // JSON 不需要特殊前缀
        ]
    ];

    private function __construct()
    {
        $this->detectBestSerializer();
    }

    /**
     * 获取单例实例
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 检测并选择最佳的序列化方式
     */
    private function detectBestSerializer(): void
    {
        // 按优先级自动选择最快的可用序列化方式
        foreach (self::SERIALIZERS as $name => $config) {
            if ($this->isSerializerAvailable($name)) {
                $this->setSerializer($name);
                error_log("[SerializationService] 自动选择序列化方式: {$name}");
                return;
            }
        }

        // 如果都不可用，使用 JSON（应该总是可用的）
        $this->setSerializer('json');
        error_log("[SerializationService] 使用默认序列化方式: json");
    }

    /**
     * 检查序列化方式是否可用
     */
    private function isSerializerAvailable(string $name): bool
    {
        $config = self::SERIALIZERS[$name];
        
        switch ($config['check']) {
            case 'extension_loaded':
                return extension_loaded($config['extension']);
            
            case 'function_exists':
                return function_exists($config['function']);
            
            case 'always_available':
                return true;
            
            default:
                return false;
        }
    }

    /**
     * 设置序列化器
     */
    private function setSerializer(string $name): void
    {
        $this->serializerName = $name;
        $this->formatPrefix = self::SERIALIZERS[$name]['prefix'];
    }

    /**
     * 编码数据（序列化）
     * 
     * @param mixed $data 要编码的数据
     * @return string 编码后的字符串
     * @throws \RuntimeException 如果编码失败
     */
    public function encode($data): string
    {
        $config = self::SERIALIZERS[$this->serializerName];
        $encodeFunc = $config['encode'];

        try {
            switch ($this->serializerName) {
                case 'igbinary':
                    $encoded = igbinary_serialize($data);
                    if ($encoded === false) {
                        throw new \RuntimeException('igbinary_serialize failed');
                    }
                    return $encoded;

                case 'msgpack':
                    $encoded = msgpack_pack($data);
                    if ($encoded === false) {
                        throw new \RuntimeException('msgpack_pack failed');
                    }
                    return $encoded;

                case 'json':
                default:
                    $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if ($encoded === false) {
                        throw new \RuntimeException('json_encode failed: ' . json_last_error_msg());
                    }
                    return $encoded;
            }
        } catch (\Throwable $e) {
            error_log("[SerializationService] encode 失败 ({$this->serializerName}): " . $e->getMessage());
            throw new \RuntimeException("序列化失败: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 解码数据（反序列化）
     * 自动检测数据格式并选择对应的解码方式
     * 
     * @param string $data 编码后的字符串
     * @param bool $assoc 是否返回关联数组（仅对 JSON 有效）
     * @return mixed 解码后的数据
     * @throws \RuntimeException 如果解码失败
     */
    public function decode(string $data, bool $assoc = true)
    {
        // 自动检测数据格式
        $detectedFormat = $this->detectFormat($data);
        
        try {
            switch ($detectedFormat) {
                case 'igbinary':
                    $decoded = igbinary_unserialize($data);
                    if ($decoded === false) {
                        throw new \RuntimeException('igbinary_unserialize failed');
                    }
                    return $decoded;

                case 'msgpack':
                    $decoded = msgpack_unpack($data);
                    if ($decoded === false) {
                        throw new \RuntimeException('msgpack_unpack failed');
                    }
                    return $decoded;

                case 'json':
                default:
                    $decoded = json_decode($data, $assoc);
                    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                        throw new \RuntimeException('json_decode failed: ' . json_last_error_msg());
                    }
                    return $decoded;
            }
        } catch (\Throwable $e) {
            error_log("[SerializationService] decode 失败 ({$detectedFormat}): " . $e->getMessage());
            throw new \RuntimeException("反序列化失败: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 检测数据格式
     * 通过检查数据的前缀/特征来判断序列化方式
     * 
     * @param string $data 编码后的数据
     * @return string 格式名称
     */
    private function detectFormat(string $data): string
    {
        if (empty($data)) {
            return 'json'; // 空数据默认按 JSON 处理
        }

        // 检查 igbinary 格式（前4字节是格式标识）
        if (strlen($data) >= 4 && substr($data, 0, 4) === self::SERIALIZERS['igbinary']['prefix']) {
            if (extension_loaded('igbinary')) {
                return 'igbinary';
            }
        }

        // 检查 msgpack 格式（第一个字节通常是 0x82 表示 map）
        if (strlen($data) >= 1 && ord($data[0]) === 0x82) {
            if (function_exists('msgpack_pack')) {
                return 'msgpack';
            }
        }

        // 检查是否是 JSON（以 { 或 [ 开头，或以 " 开头）
        $firstChar = $data[0];
        if ($firstChar === '{' || $firstChar === '[' || $firstChar === '"') {
            return 'json';
        }

        // 默认尝试当前配置的序列化方式
        return $this->serializerName;
    }

    /**
     * 获取当前使用的序列化方式名称
     */
    public function getSerializerName(): string
    {
        return $this->serializerName;
    }

    /**
     * 获取所有可用的序列化方式
     */
    public function getAvailableSerializers(): array
    {
        $available = [];
        foreach (self::SERIALIZERS as $name => $config) {
            if ($this->isSerializerAvailable($name)) {
                $available[] = $name;
            }
        }
        return $available;
    }

    /**
     * 性能基准测试
     * 测试不同序列化方式的编码/解码速度
     */
    public function benchmark(array $testData, int $iterations = 1000): array
    {
        $results = [];
        
        foreach (self::SERIALIZERS as $name => $config) {
            if (!$this->isSerializerAvailable($name)) {
                continue;
            }

            $encodeFunc = $config['encode'];
            $decodeFunc = $config['decode'];

            // 编码测试
            $encodeStart = microtime(true);
            $encoded = null;
            for ($i = 0; $i < $iterations; $i++) {
                switch ($name) {
                    case 'igbinary':
                        $encoded = igbinary_serialize($testData);
                        break;
                    case 'msgpack':
                        $encoded = msgpack_pack($testData);
                        break;
                    case 'json':
                    default:
                        $encoded = json_encode($testData, JSON_UNESCAPED_UNICODE);
                        break;
                }
            }
            $encodeTime = (microtime(true) - $encodeStart) * 1000; // 转换为毫秒

            // 解码测试
            $decodeStart = microtime(true);
            for ($i = 0; $i < $iterations; $i++) {
                switch ($name) {
                    case 'igbinary':
                        igbinary_unserialize($encoded);
                        break;
                    case 'msgpack':
                        msgpack_unpack($encoded);
                        break;
                    case 'json':
                    default:
                        json_decode($encoded, true);
                        break;
                }
            }
            $decodeTime = (microtime(true) - $decodeStart) * 1000; // 转换为毫秒

            $results[$name] = [
                'encode_time_ms' => $encodeTime,
                'decode_time_ms' => $decodeTime,
                'total_time_ms' => $encodeTime + $decodeTime,
                'size_bytes' => strlen($encoded),
                'encode_avg_ms' => $encodeTime / $iterations,
                'decode_avg_ms' => $decodeTime / $iterations,
            ];
        }

        return $results;
    }
}

