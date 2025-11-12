<?php

namespace Rediphp\Tests;

class RGeoTest extends RedissonTestCase
{
    
    public function testAddAndBasicOperations(): void
    {
        $geo = $this->client->getGeo('test:geo:basic');
        $geo->clear();
        
        // Add some locations (Beijing, Shanghai, Guangzhou)
        $this->assertEquals(1, $geo->add(116.4074, 39.9042, 'beijing'));
        $this->assertEquals(1, $geo->add(121.4737, 31.2304, 'shanghai'));
        $this->assertEquals(1, $geo->add(113.2644, 23.1291, 'guangzhou'));
        
        $this->assertEquals(3, $geo->size());
        $this->assertFalse($geo->isEmpty());
    }
    
    public function testAddAll(): void
    {
        $geo = $this->client->getGeo('test:geo:addall');
        $geo->clear();
        
        $locations = [
            [116.4074, 39.9042, 'beijing'],
            [121.4737, 31.2304, 'shanghai'],
            [113.2644, 23.1291, 'guangzhou'],
            [114.0579, 22.5431, 'shenzhen']
        ];
        
        $added = $geo->addAll($locations);
        $this->assertEquals(4, $added);
        $this->assertEquals(4, $geo->size());
    }
    
    public function testPosition(): void
    {
        $geo = $this->client->getGeo('test:geo:position');
        $geo->clear();
        
        $beijingLon = 116.4074;
        $beijingLat = 39.9042;
        
        $geo->add($beijingLon, $beijingLat, 'beijing');
        
        $position = $geo->position('beijing');
        $this->assertNotNull($position);
        $this->assertEqualsWithDelta($beijingLon, $position[0], 0.0001);
        $this->assertEqualsWithDelta($beijingLat, $position[1], 0.0001);
        
        // Test non-existent member
        $this->assertNull($geo->position('nonexistent'));
    }
    
    public function testDistance(): void
    {
        $geo = $this->client->getGeo('test:geo:distance');
        $geo->clear();
        
        // Beijing and Shanghai coordinates
        $geo->add(116.4074, 39.9042, 'beijing');
        $geo->add(121.4737, 31.2304, 'shanghai');
        
        $distance = $geo->distance('beijing', 'shanghai', 'km');
        $this->assertNotNull($distance);
        $this->assertEqualsWithDelta(1067.611, $distance, 0.1); // 北京到上海的直线距离（km）
        
        // Test different units
        $distanceMeters = $geo->distance('beijing', 'shanghai', 'm');
        $this->assertNotNull($distanceMeters);
        $this->assertEqualsWithDelta($distance * 1000, $distanceMeters, 1.0);
        
        // Test non-existent member
        $this->assertNull($geo->distance('beijing', 'nonexistent'));
    }
    
    public function testHash(): void
    {
        $geo = $this->client->getGeo('test:geo:hash');
        $geo->clear();
        
        $geo->add(116.4074, 39.9042, 'beijing');
        
        $hash = $geo->hash('beijing');
        $this->assertNotNull($hash);
        $this->assertIsString($hash);
        $this->assertGreaterThan(0, strlen($hash));
        
        // Test non-existent member
        $this->assertNull($geo->hash('nonexistent'));
    }
    
    public function testRadius(): void
    {
        $geo = $this->client->getGeo('test:geo:radius');
        $geo->clear();
        
        // Add cities in China
        $geo->add(116.4074, 39.9042, 'beijing');
        $geo->add(121.4737, 31.2304, 'shanghai');
        $geo->add(113.2644, 23.1291, 'guangzhou');
        $geo->add(114.0579, 22.5431, 'shenzhen');
        
        // Search within 200km of Beijing
        $results = $geo->radius(116.4074, 39.9042, 200, 'km');
        $this->assertIsArray($results);
        
        // Search with options
        $resultsWithCoords = $geo->radius(116.4074, 39.9042, 1000, 'km', [
            'withCoord' => true,
            'withDist' => true,
            'sort' => 'ASC'
        ]);
        
        $this->assertIsArray($resultsWithCoords);
        
        // Limit results - use 1200km radius to include Beijing and Shanghai
        $limitedResults = $geo->radius(116.4074, 39.9042, 1200, 'km', [
            'count' => 2
        ]);
        
        $this->assertCount(2, $limitedResults);
    }
    
    public function testRadiusByMember(): void
    {
        $geo = $this->client->getGeo('test:geo:radiusbymember');
        $geo->clear();
        
        $geo->add(116.4074, 39.9042, 'beijing');
        $geo->add(121.4737, 31.2304, 'shanghai');
        $geo->add(113.2644, 23.1291, 'guangzhou');
        
        // Find cities within 1200km of Beijing
        $results = $geo->radiusByMember('beijing', 1200, 'km');
        $this->assertIsArray($results);
        
        // Should include Shanghai (around 1067km from Beijing)
        $this->assertGreaterThan(0, count($results));
    }
    
    public function testRemove(): void
    {
        $geo = $this->client->getGeo('test:geo:remove');
        $geo->clear();
        
        $geo->add(116.4074, 39.9042, 'beijing');
        $geo->add(121.4737, 31.2304, 'shanghai');
        
        $this->assertEquals(2, $geo->size());
        
        $removed = $geo->remove('beijing');
        $this->assertEquals(1, $removed);
        $this->assertEquals(1, $geo->size());
        $this->assertFalse($geo->exists('beijing'));
        $this->assertTrue($geo->exists('shanghai'));
    }
    
    public function testRemoveAll(): void
    {
        $geo = $this->client->getGeo('test:geo:removeall');
        $geo->clear();
        
        $geo->add(116.4074, 39.9042, 'beijing');
        $geo->add(121.4737, 31.2304, 'shanghai');
        $geo->add(113.2644, 23.1291, 'guangzhou');
        
        $removed = $geo->removeAll(['beijing', 'shanghai', 'nonexistent']);
        $this->assertEquals(2, $removed); // Only beijing and shanghai exist
        $this->assertEquals(1, $geo->size()); // Only guangzhou remains
    }
    
    public function testExists(): void
    {
        $geo = $this->client->getGeo('test:geo:exists');
        $geo->clear();
        
        $geo->add(116.4074, 39.9042, 'beijing');
        
        $this->assertTrue($geo->exists('beijing'));
        $this->assertFalse($geo->exists('nonexistent'));
    }
    
    public function testGetMembers(): void
    {
        $geo = $this->client->getGeo('test:geo:members');
        $geo->clear();
        
        $geo->add(116.4074, 39.9042, 'beijing');
        $geo->add(121.4737, 31.2304, 'shanghai');
        $geo->add(113.2644, 23.1291, 'guangzhou');
        
        $members = $geo->getMembers();
        $this->assertCount(3, $members);
        $this->assertContains('beijing', $members);
        $this->assertContains('shanghai', $members);
        $this->assertContains('guangzhou', $members);
        
        // Test with range
        $limitedMembers = $geo->getMembers(0, 1);
        $this->assertCount(2, $limitedMembers);
    }
    
    public function testClear(): void
    {
        $geo = $this->client->getGeo('test:geo:clear');
        $geo->clear();
        
        $geo->add(116.4074, 39.9042, 'beijing');
        $geo->add(121.4737, 31.2304, 'shanghai');
        
        $this->assertEquals(2, $geo->size());
        
        $geo->clear();
        $this->assertEquals(0, $geo->size());
        $this->assertTrue($geo->isEmpty());
    }
}