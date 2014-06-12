<?php

namespace PHPJobQueue;

interface Task {

    function __construct($configFile);

    public function process();
    public function publish();
    
}