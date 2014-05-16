<?php

namespace PHPJobQueue;

class ExampleTask implements Task {
    
    public function process($data) {
        return str_rot13($data);
    }
    
}