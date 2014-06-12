<?php

namespace PHPJobQueue;

class ExampleTask implements Task {

    private $data = null;

    public function process() {
        $this->data = str_rot13("test");
        return true;
    }

    public function publish() {
        return true;
    }
    
}