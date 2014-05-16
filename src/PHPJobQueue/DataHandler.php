<?php

namespace PHPJobQueue;

class DataHandler extends MemoryHandler {

    public function readDouble() {
        $this->refreshDataReference();
        return $this->readData("d");
    }

    public function readString() {
        $this->refreshDataReference();
        return $this->readData("a" . $this->dataSize);
    }

    public function readData($format) {
        $this->dataID = shmop_open($this->dataKey, "w", 0, 0);
        $this->dataSize = shmop_size($this->dataID);

        $this->lockData();
        $data = shmop_read($this->dataID, 0, $this->dataSize);
        $this->unlockData();
        $bytesRead = unpack($format, $data);

        shmop_close($this->dataID);

        return $bytesRead[1];
    }

    public function refreshDataReference() {
        $this->dataID = shmop_open($this->dataKey, "w", 0, 0);
        $this->dataSize = shmop_size($this->dataID);
        shmop_close($this->dataID);
    }

    public function writeDouble($dataToWrite) {
        $this->refreshDataReference();
        return $this->writeData($dataToWrite, "d");
    }

    public function writeString($dataToWrite) {
        $this->refreshDataReference();

        if (strlen($dataToWrite) > $this->dataSize) {
            $this->logger->log("Warning: It appears DataHandler is writing string data of length " . strlen($dataToWrite) .
                "b to shared memory of current size " . $this->dataSize . "b. Data will be truncated!");
        }

        return $this->writeData($dataToWrite, "a" . $this->dataSize);
    }
    
    public function writeData($dataToWrite, $format) {
        $binaryString = pack($format , $dataToWrite);

        if ($this->dataSize < strlen($binaryString)) {
            $this->logger->log("Error: Attempted to write data of size " . strlen($binaryString) . "b to shared memory size " . $this->dataSize . "b");
            return false;
        }

        $this->dataID = shmop_open($this->dataKey, "w", 0, 0);

        $this->lockData();
        $bytesWritten = shmop_write($this->dataID, $binaryString, 0);
        $this->unlockData();

        shmop_close($this->dataID);

        return $bytesWritten;
    }

    public function resizeMemory($newSize) {
        shmop_close($this->dataID);
        shmop_delete($this->dataID);
        $this->dataID = shmop_open($this->dataKey, "n", 0666, $newSize);
        $this->dataSize = $newSize;
        shmop_close($this->dataID);
    }
}