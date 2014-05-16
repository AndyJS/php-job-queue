<?php

namespace PHPJobQueue;

class DataManager {
    protected $dataMappings = array();
    protected $dataPool = array();

    public function checkPendingData() {
        if (count($this->dataPool) > 0) {
            return array_keys($this->dataPool)[0];
        } else {
            return -1;
        }
    }

    public function getDataItem($index) {
        if (array_key_exists($index, $this->dataPool)) {
            return $this->dataPool[$index];
        } else {
            return false;
        }
    }

    public function getAllocatedDataItem($pid) {
        if (array_key_exists($pid, $this->dataMappings)) {
            return $this->dataMappings[$pid];
        } else {
            return false;
        }
    }

    public function allocatePIDToDataItem($pid, $index) {
        if (array_key_exists($index, $this->dataPool)) {
            $dataItem = $this->dataPool[$index];
            $this->dataMappings[$pid] = $dataItem;
            unset($this->dataPool[$index]);
            return true;
        } else {
            return false;
        }
    }

    public function freeAllocatedDataItem($pid) {
        if (array_key_exists($pid, $this->dataMappings)) {
            $dataItem = $this->dataMappings[$pid];
            $this->dataPool[] = $dataItem;
            unset($this->dataMappings[$pid]);
            return true;
        } else {
            return false;
        }
    }

    public function hasAllocation($pid) {
        if (array_key_exists($pid, $this->dataMappings)) {
            return true;
        } else {
            return false;
        }
    }

    public function clearCompletedDataItem($pid) {
        if (array_key_exists($pid, $this->dataMappings)) {
            unset($this->dataMappings[$pid]);
            return true;
        } else {
            return false;
        }
    }

    public function addDataToPool($dataItem) {
        $this->dataPool[] = $dataItem;
        end($this->dataPool);
        return key($this->dataPool);
    }

    public function logAllData($baseLogPath) {
        // Allocated data
        if (count($this->dataMappings) > 0) {
            $tmpLogger = new Logger();
            $tmpLogBase = $baseLogPath . "phpjobqueue_unprocessed_allocated_m" . getmypid() . "_" . time();
            foreach($this->dataMappings as $pid => $data) {
                $tmpLogger->setLogFile($tmpLogBase . "_pid" . $pid . ".log");
                $tmpLogger->logUnformatted($data->getData());
            }
        }

        // Unallocated pool data
        if (count($this->dataPool) > 0) {
            $tmpLogger = new Logger();
            $tmpLogBase = $baseLogPath . "phpjobqueue_unprocessed_unallocated_m" . getmypid() . "_" . time();
            foreach($this->dataPool as $pid => $data) {
                $tmpLogger->setLogFile($tmpLogBase . "_pid" . $pid . ".log");
                $tmpLogger->logUnformatted($data->getData());
            }
        }
    }
}