<?php

namespace PHPJobQueue;

class DataHandler extends MemoryHandler {

    public function readData($format) {
        $this->lockData();
        $data = shmop_read($this->dataID, 0, $this->dataSize);
        $this->unlockData();
        $bytesRead = unpack($format, $data);

        return $bytesRead[1];
    }
    
    public function writeData($dataToWrite, $format) {
        $binary = pack($format == "a" ? $format . $this->dataSize : $format, $dataToWrite);
        if ($this->dataSize < strlen($binary)) {
            $this->logger->log("Error: Attempted to write data of size " . strlen($binary) . "b to shared memory size " . $this->dataSize . "b");
            return false;
        }
        $this->lockData();
        $bytesWritten = shmop_write($this->dataID, $binary, 0);
        $this->unlockData();

        return $bytesWritten;
    }
}