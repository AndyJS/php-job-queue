<?php

namespace PHPJobQueue;

class StatusHandler extends MemoryHandler {
    
    const STATUS_IDLE = 0x80;
    const STATUS_PROCESSING = 0x40;
        
    public function setReady() {
        $this->lockData();
        $bytesWritten = shmop_write($this->dataID, pack("C", 0), 0);
        $this->unlockData();
        
        return $bytesWritten;
    }
    
    public function setActive() {
        $this->lockData();
        $status = shmop_read($this->dataID, 0, $this->dataSize);
        $statusByte = unpack("C", $status);

        $statusToWrite = pack("C", $statusByte[1] | static::STATUS_PROCESSING);

        $bytesWritten = shmop_write($this->dataID, $statusToWrite, 0);
        $this->unlockData();
        
        return $bytesWritten;
    }
    
    public function setIdle() {
        $this->lockData();
        $status = shmop_read($this->dataID, 0, $this->dataSize);
        $statusByte = unpack("C", $status);

        $statusToWrite = pack("C", $statusByte[1] | static::STATUS_IDLE);

        $bytesWritten = shmop_write($this->dataID, $statusToWrite, 0);
        $this->unlockData();

        return $bytesWritten;
    }
    
    public function isActive() {
        $this->lockData();
        $status = shmop_read($this->dataID, 0, $this->dataSize);
        $this->unlockData();
        $statusByte = unpack("C", $status);

        if (empty($statusByte)) { return false; }
        $result = $statusByte[1] & static::STATUS_PROCESSING;
        if ($result > 0) { return true; }
        else { return false; }
    }
    
    public function isIdle() {
        $this->lockData();
        $status = shmop_read($this->dataID, 0, $this->dataSize);
        $this->unlockData();
        $statusByte = unpack("C", $status);

        if (empty($statusByte)) { return false; }
        $result = $statusByte[1] & static::STATUS_IDLE;
        if ($result > 0) { return true; }
        else { return false; }
    }
}