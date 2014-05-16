<?php

namespace PHPJobQueue;

class ExampleTaskTwo implements Task {
    
    public function process($data) {
        return $data . "\n[This data parsed by ExampleTaskTwo]";
    }
    
}