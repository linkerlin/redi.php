<?php

namespace Rediphp\Async;

require_once __DIR__ . '/src/PromiseInterface.php';
require_once __DIR__ . '/src/Services/SerializationService.php';
require_once __DIR__ . '/src/RedisDataStructure.php';
require_once __DIR__ . '/src/PipelineableDataStructure.php';
require_once __DIR__ . '/src/PipelineableRedis.php';
require_once __DIR__ . '/src/RedisPool.php';
require_once __DIR__ . '/src/PooledRedis.php';
require_once __DIR__ . '/src/RMap.php';
require_once __DIR__ . '/src/RList.php';
require_once __DIR__ . '/src/RSet.php';
require_once __DIR__ . '/src/RDeque.php';
require_once __DIR__ . '/src/RLimitDeque.php';
require_once __DIR__ . '/src/RBucket.php';
require_once __DIR__ . '/src/RedissonClient.php';
require_once __DIR__ . '/src/RedisPromise.php';
require_once __DIR__ . '/src/AsyncRedissonClient.php';
require_once __DIR__ . '/src/AsyncRMap.php';
// require_once __DIR__ . '/src/AsyncRSet.php'; // 暂未实现
require_once __DIR__ . '/src/AsyncRList.php';
require_once __DIR__ . '/src/AsyncRLimitDeque.php';
require_once __DIR__ . '/src/AsyncRString.php';
require_once __DIR__ . '/src/AsyncRCollection.php';

echo "=== AsyncRClient Test ===\n";

try {
    // Create async client
    $asyncClient = new \Rediphp\AsyncRedissonClient([
        'host' => '127.0.0.1',
        'port' => 6379,
        'timeout' => 1.0
    ]);

    echo "✓ Async client created\n";

    // Test AsyncRMap
    $asyncMap = $asyncClient->getMap("test_map");
    echo "✓ AsyncRMap created\n";

    // Test async put
    $putPromise = $asyncMap->put("key1", "value1");
    $putPromise->then(function($result) {
        echo "✓ Async put result: " . ($result ? "success" : "failed") . "\n";
    });

    // Test async get
    $getPromise = $asyncMap->get("key1");
    $getPromise->then(function($value) {
        echo "✓ Async get value: " . ($value ?? "null") . "\n";
    });

    // Test batch operations
    $batchData = [
        "key2" => "value2",
        "key3" => "value3",
        "key4" => "value4"
    ];
    
    $batchPromise = $asyncMap->batchPut($batchData);
    $batchPromise->then(function($result) {
        echo "✓ Batch put completed\n";
    });

    // Test batch get
    $batchGetPromise = $asyncMap->batchGet(["key1", "key2", "key3"]);
    $batchGetPromise->then(function($results) {
        echo "✓ Batch get results: " . json_encode($results) . "\n";
    });

    // Test AsyncRList
    $asyncList = $asyncClient->getList("test_list");
    echo "✓ AsyncRList created\n";

    // Test async add
    $addPromise = $asyncList->add("item1");
    $addPromise->then(function($result) {
        echo "✓ Async list add result: " . ($result ? "success" : "failed") . "\n";
    });

    // Test batch add
    $batchAddPromise = $asyncList->batchAdd(["item2", "item3", "item4"]);
    $batchAddPromise->then(function($result) {
        echo "✓ Batch list add completed\n";
    });

    // Test AsyncRLimitDeque
    $asyncDeque = $asyncClient->getLimitDeque("test_deque", 5);
    echo "✓ AsyncRLimitDeque created\n";

    // Test async add left
    $addLeftPromise = $asyncDeque->addLeft("deque_item1");
    $addLeftPromise->then(function($result) {
        echo "✓ Async deque add left result: " . ($result ? "success" : "failed") . "\n";
    });

    // Test batch add left
    $batchAddLeftPromise = $asyncDeque->batchAddLeft(["deque_item2", "deque_item3"]);
    $batchAddLeftPromise->then(function($result) {
        echo "✓ Batch deque add left completed\n";
    });

    // Test AsyncRString
    $asyncString = $asyncClient->getString("test_string");
    echo "✓ AsyncRString created\n";

    // Test async set
    $setPromise = $asyncString->set("hello world");
    $setPromise->then(function($result) {
        echo "✓ Async string set result: " . ($result ? "success" : "failed") . "\n";
    });

    // Test async get
    $getStringPromise = $asyncString->get();
    $getStringPromise->then(function($value) {
        echo "✓ Async string get value: " . ($value ?? "null") . "\n";
    });

    // Test async increase
    $increasePromise = $asyncString->increase();
    $increasePromise->then(function($result) {
        echo "✓ Async string increase result: " . $result . "\n";
    });

    // Test AsyncRCollection
    $asyncCollection = $asyncClient->getCollection("test_collection");
    echo "✓ AsyncRCollection created\n";

    // Test async add
    $addCollPromise = $asyncCollection->add("collection_item1");
    $addCollPromise->then(function($result) {
        echo "✓ Async collection add result: " . ($result ? "success" : "failed") . "\n";
    });

    // Test batch add
    $batchAddCollPromise = $asyncCollection->batchAdd(["coll_item2", "coll_item3", "coll_item4"]);
    $batchAddCollPromise->then(function($result) {
        echo "✓ Batch collection add completed\n";
    });

    // Test batch get
    $batchGetCollPromise = $asyncCollection->batchContains(["collection_item1", "coll_item2"]);
    $batchGetCollPromise->then(function($results) {
        echo "✓ Batch collection contains results: " . json_encode($results) . "\n";
    });

    echo "\n=== All async operations started ===\n";
    echo "Note: Operations are executed asynchronously\n";
    echo "In a real async environment, these would execute in parallel\n";

    // Simulate some delay to show the operations would complete
    sleep(1);

    echo "\n=== Async API Test Completed ===\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>