<?php

namespace Rediphp\Tests;

class RHyperLogLogTest extends RedissonTestCase
{
    
    public function testAddAndCount(): void
    {
        $hll = $this->client->getHyperLogLog('test:hll:basic');
        $hll->clear();
        
        $this->assertEquals(0, $hll->count());
        $this->assertTrue($hll->isEmpty());
        
        // Add single element
        $this->assertTrue($hll->add('element1'));
        $this->assertEquals(1, $hll->count());
        $this->assertFalse($hll->isEmpty());
        
        // Add duplicate element (should not increase count)
        $this->assertFalse($hll->add('element1'));
        $this->assertEquals(1, $hll->count());
        
        // Add different element
        $this->assertTrue($hll->add('element2'));
        $this->assertEquals(2, $hll->count());
    }
    
    public function testAddAll(): void
    {
        $hll = $this->client->getHyperLogLog('test:hll:addall');;
        $hll->clear();
        
        $elements = ['elem1', 'elem2', 'elem3', 'elem2']; // elem2 is duplicate
        $result = $hll->addAll($elements);
        
        $this->assertTrue($result); // At least one element was added
        $this->assertEquals(3, $hll->count()); // Only unique elements counted
    }
    
    public function testAddAllEmptyArray(): void
    {
        $hll = $this->client->getHyperLogLog('test:hll:empty');
        $hll->clear();
        
        $this->assertFalse($hll->addAll([]));
        $this->assertEquals(0, $hll->count());
    }
    
    public function testMerge(): void
    {
        $hll1 = $this->client->getHyperLogLog('test:hll:merge1');
        $hll2 = $this->client->getHyperLogLog('test:hll:merge2');
        $hll3 = $this->client->getHyperLogLog('test:hll:merge3');
        
        $hll1->clear();
        $hll2->clear();
        $hll3->clear();
        
        // Add elements to first HLL
        $hll1->add('a');
        $hll1->add('b');
        $hll1->add('c');
        
        // Add elements to second HLL
        $hll2->add('c');
        $hll2->add('d');
        $hll2->add('e');
        
        // Merge hll1 and hll2 into hll3
        $result = $hll3->merge(['test:hll:merge1', 'test:hll:merge2'], 'test:hll:merge3');
        $this->assertTrue($result);
        
        // Should have 5 unique elements (a, b, c, d, e)
        $this->assertEquals(5, $hll3->count());
    }
    
    public function testMergeIntoSelf(): void
    {
        $hll1 = $this->client->getHyperLogLog('test:hll:merge:self1');
        $hll2 = $this->client->getHyperLogLog('test:hll:merge:self2');
        
        $hll1->clear();
        $hll2->clear();
        
        $hll1->add('x');
        $hll1->add('y');
        $hll2->add('y');
        $hll2->add('z');
        
        // Merge hll2 into hll1 (default behavior when destination is null)
        $result = $hll1->merge(['test:hll:merge:self2']);
        $this->assertTrue($result);
        
        // Should have 3 unique elements (x, y, z)
        $this->assertEquals(3, $hll1->count());
    }
    
    public function testClear(): void
    {
        $hll = $this->client->getHyperLogLog('test:hll:clear');
        $hll->clear();
        
        $hll->add('element1');
        $hll->add('element2');
        $this->assertEquals(2, $hll->count());
        
        $hll->clear();
        $this->assertEquals(0, $hll->count());
        $this->assertTrue($hll->isEmpty());
    }
    
    public function testExists(): void
    {
        $hll = $this->client->getHyperLogLog('test:hll:exists');
        $hll->clear();
        
        $this->assertFalse($hll->exists());
        
        $hll->add('element');
        $this->assertTrue($hll->exists());
        
        $hll->clear();
        $this->assertFalse($hll->exists());
    }
    
    public function testLargeCardinality(): void
    {
        $hll = $this->client->getHyperLogLog('test:hll:large');
        $hll->clear();
        
        // Add 1000 unique elements
        for ($i = 0; $i < 1000; $i++) {
            $hll->add("element_{$i}");
        }
        
        $count = $hll->count();
        
        // HyperLogLog should be reasonably accurate for large cardinalities
        // Allow for up to 5% error (HyperLogLog has ~0.81% standard error)
        $this->assertGreaterThan(950, $count);
        $this->assertLessThanOrEqual(1000, $count);
    }
    
    public function testComplexDataTypes(): void
    {
        $hll = $this->client->getHyperLogLog('test:hll:complex');
        $hll->clear();
        
        // Test with complex data types
        $complexData = [
            ['array' => 'data'],
            ['nested' => ['array' => 'value']],
            ['number' => 42],
            ['string' => 'test'],
            ['boolean' => true],
            ['null' => null]
        ];
        
        foreach ($complexData as $data) {
            $hll->add($data);
        }
        
        $this->assertEquals(6, $hll->count());
    }
}