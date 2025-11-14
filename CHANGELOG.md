# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed
- RSortedSet::valueRange() method now correctly distinguishes between score ranges and rank ranges
  - Score ranges (e.g., 10.0, 20.0) now properly call rangeByScore() instead of range()
  - Rank ranges (e.g., 0, -1) continue to work as expected
  - Fixed syntax error in conditional logic

## [0.9.1] - 2024-12-13

### Added
- **Connection Pool Support**: Comprehensive connection pooling implementation for improved performance and resource management
  - New `RedisPool` class with advanced connection pool management
  - Support for minimum and maximum connection pool sizing
  - Connection lifecycle management with idle timeout and connection validation
  - Thread-safe connection acquisition and release mechanisms
  
- **Performance Monitoring & Statistics**: Enhanced observability for connection pool operations
  - Real-time connection pool statistics (idle/active connections, utilization rates)
  - Performance metrics tracking (acquisition time, request counts, success rates)
  - Detailed connection pool health monitoring with configurable thresholds
  - New `getConnectionPoolStats()` method in `RedissonClient` for runtime monitoring

- **Connection Pool Configuration**: Flexible configuration options
  - Configurable minimum and maximum pool sizes
  - Connection timeout and maximum wait time settings
  - Pool warm-up functionality for optimal startup performance
  - Graceful degradation and connection recycling mechanisms

### Enhanced
- **All Data Structures**: Complete connection pool support across all Redis data structures
  - `RBucket`: Object holder with connection pooling
  - `RSet`: Distributed set operations with pool optimization
  - `RSortedSet`: Sorted set operations with connection reuse
  - `RList`: List operations with efficient connection management
  - `RQueue`: Queue operations with pool-backed connections
  - `RDeque`: Double-ended queue with connection pooling
  - `RMap`: Hash map operations with pool support

- **RedissonClient**: Enhanced client with connection pool integration
  - New `isUsingPool()` method to check pool usage status
  - Automatic connection pool initialization and management
  - Backward compatibility with direct connection mode
  - Seamless pool mode switching based on configuration

### Technical Improvements
- **Memory Management**: Optimized connection lifecycle with proper cleanup
- **Error Handling**: Enhanced error handling for pool exhaustion scenarios
- **Type Safety**: Improved type declarations and parameter validation
- **Code Quality**: Enhanced code organization and documentation

### Testing
- **Comprehensive Test Suite**: Added connection pool integration tests
- **Performance Validation**: Verified pool performance under various load conditions
- **Compatibility Testing**: Ensured backward compatibility with existing implementations
- **Memory Leak Detection**: Validated proper resource cleanup and memory management

### Configuration Example
```php
$client = new RedissonClient([
    'use_pool' => true,
    'pool_config' => [
        'min_size' => 2,
        'max_size' => 10,
        'max_wait_time' => 3000
    ]
]);

// Monitor connection pool statistics
$stats = $client->getConnectionPoolStats();
echo "Pool Utilization: {$stats['pool_utilization']}\n";
echo "Average Acquisition Time: {$stats['avg_acquire_time_ms']}ms\n";
```

## [1.0.0] - 2025-11-11

### Added
- Initial release of redi.php
- RedissonClient main entry point
- Distributed data structures:
  - RMap - Distributed Map using Redis Hash
  - RList - Distributed List using Redis List
  - RSet - Distributed Set using Redis Set
  - RSortedSet - Distributed Sorted Set using Redis Sorted Set
  - RQueue - Distributed Queue using Redis List
  - RDeque - Distributed Double-ended Queue using Redis List
- Distributed synchronization primitives:
  - RLock - Distributed Lock with automatic expiration
  - RReadWriteLock - Distributed Read-Write Lock
  - RSemaphore - Distributed Semaphore
  - RCountDownLatch - Distributed CountDown Latch
- Atomic operations:
  - RAtomicLong - Distributed Atomic Long with CAS support
  - RAtomicDouble - Distributed Atomic Double with CAS support
- Advanced data structures:
  - RBucket - Distributed Object Holder
  - RBitSet - Distributed BitSet using Redis Bitmap
  - RBloomFilter - Distributed Bloom Filter with configurable false positive rate
- Pub/Sub support:
  - RTopic - Distributed Topic for publish/subscribe
  - RPatternTopic - Pattern-based Topic for publish/subscribe
- Comprehensive documentation:
  - Chinese README (README.md)
  - English README (README_EN.md)
  - Compatibility guide (COMPATIBILITY.md)
  - Contributing guide (CONTRIBUTING.md)
- Usage examples:
  - Basic usage examples (examples/basic_usage.php)
  - Locks and atomics examples (examples/locks_and_atomics.php)
  - Advanced features examples (examples/advanced_features.php)
- PHPUnit test suite:
  - RMap tests
  - RList tests
  - RLock tests
  - RAtomicLong tests
- CI/CD workflow with GitHub Actions
- 100% Redisson compatibility with JSON encoding
- PSR-4 autoloading
- Composer package configuration

### Technical Details
- PHP >= 7.4 support
- Uses PHP Redis extension
- JSON encoding for Redisson compatibility
- Lua scripts for atomic operations
- Compatible with Redis 6.2+ and 7.0+

[1.0.0]: https://github.com/linkerlin/redi.php/releases/tag/v1.0.0
