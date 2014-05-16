<?php

namespace PHPJobQueue;

class DataItem {
    protected $data;

    function __construct($dataToStore) {
        $this->data = $dataToStore;
    }

    public function getData() {
        return $this->data;
    }
}