<?php

namespace PHPJobQueue;


class StatusHandlerTest extends \PHPUnit_Framework_TestCase {

    public function testCreateStatusHandler() {
        $handler = new StatusHandler(1901, 1900, 1);
        $this->assertInstanceOf('\PHPJobQueue\StatusHandler', $handler);

        $shmID = shmop_open(1900, "w", 0, 0);
        $this->assertEquals(1, $handler->getDataSize());

        shmop_close($shmID);
        $handler->cleanUpMemory();
    }

    public function testCreateStatusHandlerForAccess() {
        $shmID = shmop_open(1910, "n", 0666, 2048);

        $handler = new StatusHandler(1911, 1910, 0);
        $this->assertInstanceOf('\PHPJobQueue\StatusHandler', $handler);
        $this->assertEquals(2048, $handler->getDataSize());

        shmop_close($shmID);
        $handler->cleanUpMemory();
    }

    public function testSetReady() {
        $handler = new StatusHandler(1921, 1920, 1);
        $shmID = shmop_open(1920, "w", 0, 0);

        $handler->setReady();

        $status = shmop_read($shmID, 0, 1);
        $statusByte = unpack("C", $status);
        $this->assertEquals(0, $statusByte[1]);

        shmop_close($shmID);
        $handler->cleanUpMemory();
    }

    public function testSetActive() {
        $handler = new StatusHandler(1931, 1930, 1);
        $shmID = shmop_open(1930, "w", 0, 0);

        $handler->setActive();

        $status = shmop_read($shmID, 0, 1);
        $statusByte = unpack("C", $status);
        $this->assertEquals($handler::STATUS_PROCESSING, $statusByte[1]);

        shmop_close($shmID);
        $handler->cleanUpMemory();
    }

    public function testSetIdle() {
        $handler = new StatusHandler(1941, 1940, 1);
        $shmID = shmop_open(1940, "w", 0, 0);

        $handler->setIdle();

        $status = shmop_read($shmID, 0, 1);
        $statusByte = unpack("C", $status);
        $this->assertEquals($handler::STATUS_IDLE, $statusByte[1]);

        shmop_close($shmID);
        $handler->cleanUpMemory();
    }

    public function testIsActive() {
        $shmID = shmop_open(1950, "n", 0666, 1);
        $handler = new StatusHandler(1951, 1950, 0);

        $statusToWrite = pack("C", $handler::STATUS_PROCESSING);
        shmop_write($shmID, $statusToWrite, 0);

        $this->assertTrue($handler->isActive());

        shmop_close($shmID);
        $handler->cleanUpMemory();
    }

    public function testIsNotActive() {
        $shmID = shmop_open(1960, "n", 0666, 1);
        $handler = new StatusHandler(1961, 1960, 0);

        $statusToWrite = pack("C", ~$handler::STATUS_PROCESSING);
        shmop_write($shmID, $statusToWrite, 0);

        $this->assertFalse($handler->isActive());

        shmop_close($shmID);
        $handler->cleanUpMemory();
    }

    public function testIsIdle() {
        $shmID = shmop_open(1970, "n", 0666, 1);
        $handler = new StatusHandler(1971, 1970, 0);

        $statusToWrite = pack("C", $handler::STATUS_IDLE);
        shmop_write($shmID, $statusToWrite, 0);

        $this->assertTrue($handler->isIdle());

        shmop_close($shmID);
        $handler->cleanUpMemory();
    }

    public function testIsNotIdle() {
        $shmID = shmop_open(1980, "n", 0666, 1);
        $handler = new StatusHandler(1981, 1980, 0);

        $statusToWrite = pack("C", ~$handler::STATUS_IDLE);
        shmop_write($shmID, $statusToWrite, 0);

        $this->assertFalse($handler->isIdle());

        shmop_close($shmID);
        $handler->cleanUpMemory();
    }

}
 