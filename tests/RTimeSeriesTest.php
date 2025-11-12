<?php

namespace Rediphp\Tests;

class RTimeSeriesTest extends RedissonTestCase
{
    
    public function testAddAndGet(): void
    {
        $ts = $this->client->getTimeSeries('test:timeseries:basic');
        $ts->clear();
        
        $timestamp = time() * 1000; // milliseconds
        $value = 42.5;
        
        // Add single data point
        $result = $ts->add($value, $timestamp);
        $this->assertEquals($timestamp, $result); // add() returns the timestamp
        
        // Get the data point
        $data = $ts->get($timestamp);
        $this->assertEquals($value, $data['value']);
    }
    
    public function testAddAll(): void
    {
        $ts = $this->client->getTimeSeries('test:timeseries:addall');
        $ts->clear();
        
        $baseTime = time() * 1000;
        $dataPoints = [
            [10.0, $baseTime],
            [20.0, $baseTime + 1000],
            [30.0, $baseTime + 2000],
            [40.0, $baseTime + 3000]
        ];
        
        $result = $ts->addAll($dataPoints);
        $this->assertCount(4, $result); // Should add 4 timestamps
        
        // Verify all data points
        foreach ($dataPoints as [$expectedValue, $timestamp]) {
            $actualData = $ts->get($timestamp);
            $this->assertEquals($expectedValue, $actualData['value']);
        }
    }
    
    public function testRange(): void
    {
        $ts = $this->client->getTimeSeries('test:timeseries:range');
        $ts->clear();
        
        $baseTime = time() * 1000;
        $dataPoints = [
            [10.0, $baseTime],
            [20.0, $baseTime + 1000],
            [30.0, $baseTime + 2000],
            [40.0, $baseTime + 3000],
            [50.0, $baseTime + 4000]
        ];
        
        $ts->addAll($dataPoints);
        
        // Get range
        $rangeData = $ts->range($baseTime + 1000, $baseTime + 3000);
        $this->assertCount(3, $rangeData);
        
        // Verify values
        $expectedValues = [20.0, 30.0, 40.0];
        $actualValues = array_column($rangeData, 'value');
        $this->assertEquals($expectedValues, $actualValues);
    }
    
    public function testGetLatest(): void
    {
        $ts = $this->client->getTimeSeries('test:timeseries:latest');
        $ts->clear();
        
        $baseTime = time() * 1000;
        $dataPoints = [
            [10.0, $baseTime],
            [20.0, $baseTime + 1000],
            [30.0, $baseTime + 2000]
        ];
        
        $ts->addAll($dataPoints);
        
        $latest = $ts->getLatest();
        $this->assertNotNull($latest);
        $this->assertEquals($baseTime + 2000, $latest['timestamp']);
        $this->assertEquals(30.0, $latest['value']);
    }
    
    public function testGetEarliest(): void
    {
        $ts = $this->client->getTimeSeries('test:timeseries:earliest');
        $ts->clear();
        
        $baseTime = time() * 1000;
        $dataPoints = [
            [10.0, $baseTime],
            [20.0, $baseTime + 1000],
            [30.0, $baseTime + 2000]
        ];
        
        $ts->addAll($dataPoints);
        
        $earliest = $ts->getEarliest();
        $this->assertNotNull($earliest);
        $this->assertEquals($baseTime, $earliest['timestamp']);
        $this->assertEquals(10.0, $earliest['value']);
    }
    
    public function testGetStats(): void
    {
        $ts = $this->client->getTimeSeries('test:timeseries:stats');
        $ts->clear();
        
        $baseTime = time() * 1000;
        $dataPoints = [
            [10.0, $baseTime],
            [20.0, $baseTime + 1000],
            [30.0, $baseTime + 2000],
            [40.0, $baseTime + 3000]
        ];
        
        $ts->addAll($dataPoints);
        
        $stats = $ts->getStats();
        $this->assertEquals(4, $stats['count']);
        $this->assertEquals(10.0, $stats['min']);
        $this->assertEquals(40.0, $stats['max']);
        $this->assertEquals(25.0, $stats['avg']);
        $this->assertEquals(100.0, $stats['sum']);
    }
    
    public function testDelete(): void
    {
        $ts = $this->client->getTimeSeries('test:timeseries:delete');
        $ts->clear();
        
        $baseTime = time() * 1000;
        $dataPoints = [
            [10.0, $baseTime],
            [20.0, $baseTime + 1000],
            [30.0, $baseTime + 2000]
        ];
        
        $ts->addAll($dataPoints);
        
        // Delete middle point
        $result = $ts->delete($baseTime + 1000);
        $this->assertTrue($result);
        
        // Verify deletion
        $this->assertNull($ts->get($baseTime + 1000));
        
        // Verify other points still exist
        $this->assertEquals(10.0, $ts->get($baseTime)['value']);
        $this->assertEquals(30.0, $ts->get($baseTime + 2000)['value']);
    }
    
    public function testDeleteRange(): void
    {
        $ts = $this->client->getTimeSeries('test:timeseries:deleterange');
        $ts->clear();
        
        $baseTime = time() * 1000;
        $dataPoints = [
            [10.0, $baseTime],
            [20.0, $baseTime + 1000],
            [30.0, $baseTime + 2000],
            [40.0, $baseTime + 3000],
            [50.0, $baseTime + 4000]
        ];
        
        $ts->addAll($dataPoints);
        
        // Delete range
        $deletedCount = $ts->deleteRange($baseTime + 1000, $baseTime + 3000);
        $this->assertEquals(3, $deletedCount);
        
        // Verify deletion
        $this->assertNull($ts->get($baseTime + 1000));
        $this->assertNull($ts->get($baseTime + 2000));
        $this->assertNull($ts->get($baseTime + 3000));
        
        // Verify other points still exist
        $this->assertEquals(10.0, $ts->get($baseTime)['value']);
        $this->assertEquals(50.0, $ts->get($baseTime + 4000)['value']);
    }
    
    public function testSize(): void
    {
        $ts = $this->client->getTimeSeries('test:timeseries:size');
        $ts->clear();
        
        $this->assertEquals(0, $ts->size());
        
        $baseTime = time() * 1000;
        $ts->add($baseTime, 10.0);
        $this->assertEquals(1, $ts->size());
        
        $ts->add($baseTime + 1000, 20.0);
        $this->assertEquals(2, $ts->size());
    }
    
    public function testIsEmpty(): void
    {
        $ts = $this->client->getTimeSeries('test:timeseries:empty');
        $ts->clear();
        
        $this->assertTrue($ts->isEmpty());
        
        $ts->add(time() * 1000, 10.0);
        $this->assertFalse($ts->isEmpty());
        
        $ts->clear();
        $this->assertTrue($ts->isEmpty());
    }
    
    public function testClear(): void
    {
        $ts = $this->client->getTimeSeries('test:timeseries:clear');
        $ts->clear();
        
        $baseTime = time() * 1000;
        $ts->add($baseTime, 10.0);
        $ts->add($baseTime + 1000, 20.0);
        
        $this->assertEquals(2, $ts->size());
        
        $ts->clear();
        $this->assertEquals(0, $ts->size());
        $this->assertTrue($ts->isEmpty());
    }
    
    public function testExists(): void
    {
        $ts = $this->client->getTimeSeries('test:timeseries:exists');
        $ts->clear();
        
        $this->assertFalse($ts->exists());
        
        $ts->add(time() * 1000, 10.0);
        $this->assertTrue($ts->exists());
        
        $ts->clear();
        $this->assertFalse($ts->exists());
    }
    
    public function testLargeDataset(): void
    {
        $ts = $this->client->getTimeSeries('test:timeseries:large');
        $ts->clear();
        
        $baseTime = time() * 1000;
        $dataPoints = [];
        
        // Create 1000 data points
        for ($i = 0; $i < 1000; $i++) {
            $dataPoints[] = [$i * 0.1, $baseTime + ($i * 100)];
        }
        
        $result = $ts->addAll($dataPoints);
        $this->assertCount(1000, $result); // Should add 1000 timestamps
        
        $this->assertEquals(1000, $ts->size());
        
        // Test range query on large dataset
        $rangeData = $ts->range($baseTime, $baseTime + 9900);
        $this->assertCount(100, $rangeData); // 10 seconds at 100ms intervals = 100 points (0-9900ms)
    }
    
    public function testComplexDataTypes(): void
    {
        $ts = $this->client->getTimeSeries('test:timeseries:complex');
        $ts->clear();
        
        $baseTime = time() * 1000;
        $complexData = [
            [3.14159, $baseTime],
            [2.71828, $baseTime + 1000],
            [1.41421, $baseTime + 2000]
        ];
        
        $ts->addAll($complexData);
        
        foreach ($complexData as [$expectedValue, $timestamp]) {
            $actualData = $ts->get($timestamp);
            $this->assertNotNull($actualData, "Data point not found for timestamp $timestamp");
            $this->assertEqualsWithDelta($expectedValue, $actualData['value'], 0.00001);
        }
    }
}