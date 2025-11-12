<?php

namespace Rediphp\Tests;

class RStreamTest extends RedissonTestCase
{
    
    public function testAddAndRead(): void
    {
        $stream = $this->client->getStream('test:stream:basic');
        $stream->clear();
        
        // Add single entry
        $id1 = $stream->add(['field1' => 'value1', 'field2' => 'value2']);
        $this->assertNotFalse($id1);
        $this->assertIsString($id1);
        
        // Add another entry with specific ID (use a future timestamp to ensure it's greater than auto-generated ID)
        $id2 = $stream->add(['field3' => 'value3'], '9999999999999-0');
        $this->assertEquals('9999999999999-0', $id2);
        
        // Read all entries
        $entries = $stream->read();
        $this->assertCount(2, $entries);
        $this->assertArrayHasKey($id1, $entries);
        $this->assertArrayHasKey($id2, $entries);
        
        // Verify entry content
        $this->assertEquals('value1', $entries[$id1]['field1']);
        $this->assertEquals('value2', $entries[$id1]['field2']);
        $this->assertEquals('value3', $entries[$id2]['field3']);
    }
    
    public function testAddWithMaxlen(): void
    {
        $stream = $this->client->getStream('test:stream:maxlen');
        $stream->clear();
        
        // Add entries with maxlen
        for ($i = 0; $i < 10; $i++) {
            $stream->add(['index' => $i], '*', ['maxlen' => 5]);
        }
        
        // Should only have 5 entries due to maxlen
        $this->assertEquals(5, $stream->length());
        
        $entries = $stream->read();
        $this->assertCount(5, $entries);
        
        // Should contain the last 5 entries (indices 5-9)
        $indices = array_column($entries, 'index');
        sort($indices);
        $this->assertEquals([5, 6, 7, 8, 9], $indices);
    }
    
    public function testAddAll(): void
    {
        $stream = $this->client->getStream('test:stream:addall');
        $stream->clear();
        
        $entries = [
            [['field1' => 'value1'], '*'],
            [['field2' => 'value2'], '*'],
            [['field3' => 'value3'], '9999999999999-1']
        ];
        
        $addedIds = $stream->addAll($entries);
        $this->assertCount(3, $addedIds);
        
        $allEntries = $stream->read();
        $this->assertCount(3, $allEntries);
    }
    
    public function testReadRange(): void
    {
        $stream = $this->client->getStream('test:stream:range');
        $stream->clear();
        
        // Add multiple entries
        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $ids[] = $stream->add(['index' => $i]);
        }
        
        // Read specific range
        $rangeEntries = $stream->read($ids[1], $ids[3]);
        $this->assertCount(3, $rangeEntries); // Should include indices 1, 2, 3
        
        // Read with count limit
        $limitedEntries = $stream->read('-', '+', 2);
        $this->assertCount(2, $limitedEntries);
    }
    
    public function testReadReverse(): void
    {
        $stream = $this->client->getStream('test:stream:reverse');
        $stream->clear();
        
        // Add entries
        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $ids[] = $stream->add(['index' => $i]);
        }
        
        // Read in reverse order
        $reverseEntries = $stream->readReverse();
        $this->assertCount(5, $reverseEntries);
        
        // Should be in reverse order
        $indices = array_column($reverseEntries, 'index');
        $this->assertEquals([4, 3, 2, 1, 0], $indices);
    }
    
    public function testLength(): void
    {
        $stream = $this->client->getStream('test:stream:length');
        $stream->clear();
        
        $this->assertEquals(0, $stream->length());
        
        $stream->add(['field' => 'value1']);
        $this->assertEquals(1, $stream->length());
        
        $stream->add(['field' => 'value2']);
        $this->assertEquals(2, $stream->length());
    }
    
    public function testTrim(): void
    {
        $stream = $this->client->getStream('test:stream:trim');
        $stream->clear();
        
        // Add 10 entries
        for ($i = 0; $i < 10; $i++) {
            $stream->add(['index' => $i]);
        }
        
        $this->assertEquals(10, $stream->length());
        
        // Trim to 5 entries
        $removed = $stream->trim(5);
        $this->assertEquals(5, $removed);
        $this->assertEquals(5, $stream->length());
    }
    
    public function testDelete(): void
    {
        $stream = $this->client->getStream('test:stream:delete');
        $stream->clear();
        
        $id1 = $stream->add(['field1' => 'value1']);
        $id2 = $stream->add(['field2' => 'value2']);
        $id3 = $stream->add(['field3' => 'value3']);
        
        $this->assertEquals(3, $stream->length());
        
        // Delete middle entry
        $deleted = $stream->delete([$id2]);
        $this->assertEquals(1, $deleted);
        $this->assertEquals(2, $stream->length());
        
        // Verify remaining entries
        $entries = $stream->read();
        $this->assertArrayHasKey($id1, $entries);
        $this->assertArrayNotHasKey($id2, $entries);
        $this->assertArrayHasKey($id3, $entries);
    }
    
    public function testConsumerGroup(): void
    {
        $stream = $this->client->getStream('test:stream:group');
        $stream->clear();
        
        // Create consumer group
        $result = $stream->createGroup('test-group', '0');
        $this->assertTrue($result);
        
        // Add some entries
        $id1 = $stream->add(['field1' => 'value1']);
        $id2 = $stream->add(['field2' => 'value2']);
        
        // Read from group (use '>' to read new entries, specify count to get all entries)
        $entries = $stream->readGroup('test-group', 'consumer1', '>', 10);
        $this->assertCount(2, $entries);
        
        // Acknowledge entries
        $acked = $stream->ack('test-group', [$id1, $id2]);
        $this->assertEquals(2, $acked);
        
        // Delete group
        $result = $stream->deleteGroup('test-group');
        $this->assertTrue($result);
    }
    
    public function testPending(): void
    {
        $stream = $this->client->getStream('test:stream:pending');
        $stream->clear();
        
        // Create group and add entries
        $stream->createGroup('test-group', '0');
        $id1 = $stream->add(['field1' => 'value1']);
        $id2 = $stream->add(['field2' => 'value2']);
        
        // Read without acknowledging
        $stream->readGroup('test-group', 'consumer1', '0');
        
        // Check pending entries
        $pending = $stream->pending('test-group');
        $this->assertIsArray($pending);
        
        // Clean up
        $stream->deleteGroup('test-group');
    }
    
    public function testClear(): void
    {
        $stream = $this->client->getStream('test:stream:clear');
        $stream->clear();
        
        $stream->add(['field1' => 'value1']);
        $stream->add(['field2' => 'value2']);
        
        $this->assertEquals(2, $stream->length());
        
        $stream->clear();
        $this->assertEquals(0, $stream->length());
        $this->assertFalse($stream->exists());
    }
    
    public function testExists(): void
    {
        $stream = $this->client->getStream('test:stream:exists');
        $stream->clear();
        
        $this->assertFalse($stream->exists());
        
        $stream->add(['field' => 'value']);
        $this->assertTrue($stream->exists());
        
        $stream->clear();
        $this->assertFalse($stream->exists());
    }
}