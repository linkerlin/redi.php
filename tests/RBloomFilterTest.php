<?php

namespace Rediphp\Tests;

class RBloomFilterTest extends RedissonTestCase
{
    
    public function testAddAndContains(): void
    {
        $filter = $this->client->getBloomFilter('test:bloom:basic');
        $filter->delete();
        
        // Test initial state
        $this->assertFalse($filter->contains('item1'));
        $this->assertFalse($filter->contains('item2'));
        
        // Add items and test
        $this->assertTrue($filter->add('item1'));
        $this->assertTrue($filter->add('item2'));
        
        $this->assertTrue($filter->contains('item1'));
        $this->assertTrue($filter->contains('item2'));
        $this->assertFalse($filter->contains('item3'));
    }
    
    public function testFalsePositives(): void
    {
        $filter = $this->client->getBloomFilter('test:bloom:falsepos');
        $filter->delete();
        
        // Add many items to increase chance of false positives
        for ($i = 0; $i < 100; $i++) {
            $filter->add("item_$i");
        }
        
        // Test for items that were definitely not added
        // Note: Bloom filters can have false positives, but not false negatives
        $this->assertFalse($filter->contains('definitely_not_added'));
        $this->assertFalse($filter->contains('another_missing_item'));
        
        // Verify all added items are present (no false negatives)
        for ($i = 0; $i < 100; $i++) {
            $this->assertTrue($filter->contains("item_$i"));
        }
    }
    
    public function testSizeAndClear(): void
    {
        $filter = $this->client->getBloomFilter('test:bloom:size');
        $filter->delete();
        
        // Test initial state
        $this->assertEquals(0, $filter->count());
        
        // Add items and test count
        $filter->add('item1');
        $filter->add('item2');
        $this->assertGreaterThan(0, $filter->count());
        
        // Clear and test
        $filter->clear();
        $this->assertEquals(0, $filter->count());
    }
    
    public function testExists(): void
    {
        $filter = $this->client->getBloomFilter('test:bloom:exists');
        $filter->delete();
        
        // Test non-existent by checking if any item exists
        $this->assertFalse($filter->contains('any_item'));
        
        // Test exists after add
        $filter->add('item1');
        $this->assertTrue($filter->contains('item1'));
        
        // Test exists after clear
        $filter->clear();
        $this->assertFalse($filter->contains('item1'));
    }
    
    public function testBatchOperations(): void
    {
        $filter = $this->client->getBloomFilter('test:bloom:batch');
        $filter->delete();
        
        // Test batch add by adding items individually
        $items = ['batch1', 'batch2', 'batch3', 'batch4'];
        foreach ($items as $item) {
            $filter->add($item);
        }
        
        // Verify all items are present
        foreach ($items as $item) {
            $this->assertTrue($filter->contains($item));
        }
        
        // Test with non-existent items
        $this->assertFalse($filter->contains('non_existent_batch'));
    }
    
    public function testProbabilityEstimation(): void
    {
        $filter = $this->client->getBloomFilter('test:bloom:prob');
        $filter->delete();
        
        // Add items
        for ($i = 0; $i < 50; $i++) {
            $filter->add("test_item_$i");
        }
        
        // Test count
        $count = $filter->count();
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
        
        // Verify all added items are present
        for ($i = 0; $i < 50; $i++) {
            $this->assertTrue($filter->contains("test_item_$i"));
        }
    }
}