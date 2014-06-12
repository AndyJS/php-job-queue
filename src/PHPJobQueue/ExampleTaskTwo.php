<?php

namespace PHPJobQueue;

class ExampleTaskTwo implements Task {

    private $data = null;

    public function process() {
        $this->data = "Test \n[This data parsed by ExampleTaskTwo]";
        return true;
    }

    public function publish() {
        return true;
    }
}