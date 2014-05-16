<?php

namespace PHPJobQueue;

interface Task {
    
    public function process($data);
    
}