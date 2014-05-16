<?php

namespace PHPJobQueue;

class DataHandlerTest extends \PHPUnit_Framework_TestCase {

    public function testCreateDataHandler() {
        $handler = new DataHandler(1201, 1200, 1);
        $this->assertInstanceOf('\PHPJobQueue\DataHandler', $handler);

        $shmID = shmop_open(1200, "w", 0, 0);
        $this->assertEquals(1, $handler->getDataSize());

        shmop_close($shmID);
        $handler->cleanUpMemory();
    }

    public function testCreateDataHandlerForAccess() {
        $shmID = shmop_open(1210, "n", 0666, 1);

        $handler = new DataHandler(1211, 1210, 0);
        $this->assertInstanceOf('\PHPJobQueue\DataHandler', $handler);
        $this->assertEquals(1, $handler->getDataSize());

        shmop_close($shmID);
        $handler->cleanUpMemory();
    }

    public function testReadTimeDouble() {
        $shmID = shmop_open(1220, "n", 0666, 8);
        $semID = sem_get(1221);

        $timeToCheck = round(microtime(true));
        $binary = pack("d", $timeToCheck);
        shmop_write($shmID, $binary, 0);

        $handler = new DataHandler(1221, 1220, 0);
        $timeReturned = $handler->readData("d");

        $this->assertEquals($timeToCheck, $timeReturned);

        $handler->cleanUpMemory();
    }

    public function testReadDataString() {
        $shmID = shmop_open(1230, "n", 0666, 2048);
        $semID = sem_get(1231);

        $dataToCheck = "G M Police @gmpolice Join us tomorrow between 12pm and 2pm for a twitterchat on dangerous dogs. Post your questions using the hashtag #AskGMP";
        $binary = pack("a2048", $dataToCheck);
        shmop_write($shmID, $binary, 0);

        $handler = new DataHandler(1231, 1230, 0);
        $dataReturned = $handler->readData("a2048");

        $this->assertEquals($dataToCheck, $dataReturned);

        shmop_close($shmID);
        $handler->cleanUpMemory();
    }

    public function testWriteTimeDouble() {
        $timeToWrite = round(microtime(true));

        $handler = new DataHandler(1241, 1240, 8);
        $bytesWritten = $handler->writeData($timeToWrite, "d");
        $this->assertGreaterThan(0, $bytesWritten);

        $shmID = shmop_open(1240, "w", 0, 0);
        $timeResult = unpack("d", shmop_read($shmID, 0, 8));

        $this->assertEquals($timeToWrite, $timeResult[1]);

        shmop_close($shmID);
        $handler->cleanUpMemory();
    }

    public function testWriteDataString() {
        $dataToWrite = "G M Police @gmpolice Join us tomorrow between 12pm and 2pm for a twitterchat on dangerous dogs. Post your questions using the hashtag #AskGMP";

        $handler = new DataHandler(1251, 1250, 2048);
        $bytesWritten = $handler->writeData($dataToWrite, "a2048");
        $this->assertGreaterThan(0, $bytesWritten);

        $shmID = shmop_open(1250, "w", 0, 0);
        $dataResult = unpack("a2048", shmop_read($shmID, 0, 2048));

        $this->assertEquals($dataToWrite, $dataResult[1]);

        shmop_close($shmID);
        $handler->cleanUpMemory();
    }
}
 