# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
