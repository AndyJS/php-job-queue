<?php

namespace PHPJobQueue;

class StatusHandler extends MemoryHandler {
    
    const STATUS_IDLE = 0x80;
    const STATUS_PROCESSING = 0x40;
        
    public function setReady() {
        $this->dataID = shmop_open($this->dataKey, "w", 0, 0);
        $this->lockData();
        $bytesWritten = shmop_write($this->dataID, pack("C", 0), 0);
        $this->unlockData();
        shmop_close($this->dataID);
        
        return $bytesWritten;
    }
    
    public function setActive() {
        $this->dataID = shmop_open($this->dataKey, "w", 0, 0);
        $this->lockData();
        $status = shmop_read($this->dataID, 0, $this->dataSize);
        $statusByte = unpack("C", $status);

        $statusToWrite = pack("C", $statusByte[1] | static::STATUS_PROCESSING);

        $bytesWritten = shmop_write($this->dataID, $statusToWrite, 0);
        $this->unlockData();
        shmop_close($this->dataID);
        
        return $bytesWritten;
    }
    
    public function setIdle() {
        $this->dataID = shmop_open($this->dataKey, "w", 0, 0);
        $this->lockData();
        $status = shmop_read($this->dataID, 0, $this->dataSize);
        $statusByte = unpack("C", $status);

        $statusToWrite = pack("C", $statusByte[1] | static::STATUS_IDLE);

        $bytesWritten = shmop_write($this->dataID, $statusToWrite, 0);
        $this->unlockData();
        shmop_close($this->dataID);

        return $bytesWritten;
    }
    
    public function isActive() {
        $this->dataID = shmop_open($this->dataKey, "w", 0, 0);
        $this->lockData();
        $status = shmop_read($this->dataID, 0, $this->dataSize);
        $this->unlockData();
        shmop_close($this->dataID);

        $statusByte = unpack("C", $status);

        if (empty($statusByte)) { return false; }
        return (($statusByte[1] & static::STATUS_PROCESSING) > 0 ? true : false);
    }
    
    public function isIdle() {
        $this->dataID = shmop_open($this->dataKey, "w", 0, 0);
        $this->lockData();
        $status = shmop_read($this->dataID, 0, $this->dataSize);
        $this->unlockData();
        shmop_close($this->dataID);

        $statusByte = unpack("C", $status);

        if (empty($statusByte)) { return false; }
        return (($statusByte[1] & static::STATUS_IDLE) > 0 ? true : false);
    }
}