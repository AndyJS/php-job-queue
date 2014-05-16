<?php

namespace PHPJobQueue;

abstract class MemoryHandler {
       
    protected $semaphore;
    protected $semaphoreKey;
    protected $dataID;
    protected $dataKey;
    protected $dataSize;
    protected $logger;
        
    function __construct($semaphoreKey, $dataKey, $dataSize) {
        $this->semaphore = sem_get($semaphoreKey);

        if (!empty($dataSize) && $dataSize > 0) {
            $this->dataID = shmop_open($dataKey, "n", 0666, $dataSize);
            $this->dataSize = $dataSize;
        } else {
            $this->dataID = shmop_open($dataKey, "w", 0, 0);
            $this->dataSize = shmop_size($this->dataID);
        }
        shmop_close($this->dataID);
        if (!$this->semaphore || !$this->dataID) { return false; }

        $this->logger = new Logger();
        $this->semaphoreKey = $semaphoreKey;
        $this->dataKey = $dataKey;
    }

    public function cleanUpMemory() {
        $this->unlockData();
        @sem_remove($this->semaphore);
        $this->dataID = @shmop_open($this->dataKey, "w", 0, 0);
        @shmop_delete($this->dataID);
        @shmop_close($this->dataID);
    }
    
    protected function lockData() {
        if (!@sem_acquire($this->semaphore)) {
            $this->semaphore = sem_get($this->semaphoreKey);
            sem_acquire($this->semaphore);
        }
    }
    
    protected function unlockData() {
        @sem_release($this->semaphore);
    }
    
    public function getDataID() { return $this->dataID; }
    public function getDataSize() { return $this->dataSize; }
    
}