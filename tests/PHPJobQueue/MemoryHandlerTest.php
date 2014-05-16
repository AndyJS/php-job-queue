<?php

namespace PHPJobQueue;

class MockMemoryHandler extends MemoryHandler {}

class MemoryHandlerTest extends \PHPUnit_Framework_TestCase {

    public function testCreateMemoryHandler() {
        $handler = new MockMemoryHandler(1801, 1800, 1);
        $this->assertInstanceOf('\PHPJobQueue\MockMemoryHandler', $handler);

        $shmID = shmop_open(1800, "w", 0, 0);
        $this->assertEquals(1, $handler->getDataSize());

        shmop_close($shmID);
        $handler->cleanUpMemory();
    }

    public function testCreateStatusHandlerForAccess() {
        $shmID = shmop_open(1810, "n", 0666, 2048);

        $handler = new MockMemoryHandler(1811, 1810, 0);
        $this->assertInstanceOf('\PHPJobQueue\MockMemoryHandler', $handler);
        $this->assertEquals(2048, $handler->getDataSize());

        shmop_close($shmID);
        $handler->cleanUpMemory();
    }

}
 