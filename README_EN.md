# redi.php - English Documentation

A pure PHP distributed data structures library, equivalent to Redisson implementation for PHP.

## Introduction

redi.php is a PHP library that provides 100% compatibility with Java's Redisson. It offers the same distributed data structures and synchronization primitives, allowing seamless collaboration between PHP and Java applications in distributed environments.

## Features

- ✅ **100% Redisson Compatible** - Data structures and encoding format fully compatible with Redisson
- ✅ **Rich Data Structures** - Support for Map, List, Set, Queue, Lock, and more distributed data structures
- ✅ **Distributed Locks** - Support for distributed locks, read-write locks, semaphores, and other synchronization mechanisms
- ✅ **Atomic Operations** - Support for atomic long, atomic double, and other atomic operations
- ✅ **Pub/Sub** - Support for Topic and Pattern Topic
- ✅ **Advanced Data Structures** - Support for BitSet, BloomFilter, and other advanced data structures

## Installation

```bash
composer require linkerlin/redi.php
```

## Requirements

- PHP >= 7.4
- Redis extension
- Redis server

## Quick Start

### Basic Usage

```php
<?php

require 'vendor/autoload.php';

use Rediphp\RedissonClient;

// Create client
$client = new RedissonClient([
    'host' => '127.0.0.1',
    'port' => 6379,
]);

// Connect to Redis
$client->connect();

// Use distributed Map
$map = $client->getMap('myMap');
$map->put('key1', 'value1');
$map->put('key2', ['nested' => 'value']);
echo $map->get('key1'); // Output: value1

// Use distributed List
$list = $client->getList('myList');
$list->add('item1');
$list->add('item2');
print_r($list->toArray()); // Output: ['item1', 'item2']

// Use distributed Lock
$lock = $client->getLock('myLock');
if ($lock->tryLock()) {
    try {
        // Execute synchronized code
        echo "Lock acquired\n";
    } finally {
        $lock->unlock();
    }
}

// Close connection
$client->shutdown();
```

## Supported Data Structures

### Basic Data Structures

- **RMap** - Distributed Map (Hash)
- **RList** - Distributed List
- **RSet** - Distributed Set
- **RSortedSet** - Distributed Sorted Set
- **RQueue** - Distributed Queue
- **RDeque** - Distributed Double-ended Queue

### Synchronization

- **RLock** - Distributed Lock
- **RReadWriteLock** - Distributed Read-Write Lock
- **RSemaphore** - Distributed Semaphore
- **RCountDownLatch** - Distributed CountDown Latch

### Atomic Operations

- **RAtomicLong** - Distributed Atomic Long
- **RAtomicDouble** - Distributed Atomic Double

### Advanced

- **RBucket** - Distributed Object Holder
- **RBitSet** - Distributed BitSet
- **RBloomFilter** - Distributed Bloom Filter
- **RTopic** - Distributed Topic (Pub/Sub)
- **RPatternTopic** - Pattern-based Topic

## Compatibility with Redisson

redi.php uses the same data encoding format as Redisson, ensuring complete interoperability:

- **Data Format**: Uses JSON encoding, compatible with Redisson's default encoder
- **Key Naming**: Uses the same key naming conventions
- **Distributed Algorithms**: Implements the same distributed lock and synchronization algorithms
- **Lua Scripts**: For atomic operations, uses the same Lua script logic

This means:
- PHP applications can read and modify data created by Java Redisson applications
- Java Redisson applications can read and modify data created by PHP redi.php applications
- Distributed locks work correctly across PHP and Java applications

See [COMPATIBILITY.md](COMPATIBILITY.md) for detailed compatibility information (in Chinese).

## Configuration Options

```php
$client = new RedissonClient([
    'host' => '127.0.0.1',      // Redis host
    'port' => 6379,             // Redis port
    'password' => null,         // Redis password (optional)
    'database' => 0,            // Redis database number
    'timeout' => 0.0,           // Connection timeout (seconds)
]);
```

## Testing

```bash
# Install dependencies
composer install

# Run tests (requires Redis server)
vendor/bin/phpunit
```

## Examples

See the `examples/` directory for comprehensive usage examples:

- `basic_usage.php` - Basic data structure operations
- `locks_and_atomics.php` - Locks and atomic operations
- `advanced_features.php` - Advanced features and interoperability

## License

Apache License 2.0

## Contributing

Contributions are welcome! Please submit Issues and Pull Requests.

## Support

For compatibility issues, please provide:
1. redi.php version
2. Redisson version (if applicable)
3. Redis version
4. Steps to reproduce
5. Expected vs actual behavior
