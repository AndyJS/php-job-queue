#!/usr/bin/php
<?php

namespace PHPJobQueue;

// Close off standard input buffer to isolate processes, and route any standard output/error to default logs
// Note we open handles immediately as first three handles are automatically assigned within PHP to these buffers
fclose(STDIN);
fclose(STDOUT);
fclose(STDERR);
$STDIN = fopen('/dev/null', 'r');
$STDOUT = fopen('/var/log/phpjobqueue/phpjobqueue_std.log', 'a');
$STDERR = fopen('/var/log/phpjobqueue/phpjobqueue_err.log', 'a');

require_once('autoloader.php');

// Avoid PHP script timeout for manager and worker instances
ini_set("max_execution_time", "0");
ini_set("max_input_time", "0");
set_time_limit(0);

// Route any PHP errors to default log file
ini_set("log_errors" , "1");
ini_set("error_log" , "/var/log/phpjobqueue/phpjobqueue.log");
ini_set("display_errors" , "1");
ini_set("error_reporting", E_ALL);

// Basic parsing of JobQueue flags
$manager = false;
$worker = false;
$logger = new Logger();

if ($argc == 0) {
    $logger->log("Insufficient flags provided. Terminating...");
    exit(1);
} else {
    $options = getopt("mwd:s:k:D:S:K:c:");
    $manager = isset($options["m"]) ? true : false;
    $worker = isset($options["w"]) ? true : false;
    $workerShmID = isset($options["d"]) ? $options["d"] : false;
    $workerStatID = isset($options["s"]) ? $options["s"] : false;
    $workerKpaID = isset($options["k"]) ? $options["k"] : false;
    $workerShmSemID = isset($options["D"]) ? $options["D"] : false;
    $workerStatSemID = isset($options["S"]) ? $options["S"] : false;
    $workerKpaSemID = isset($options["K"]) ? $options["K"] : false;
    $configFile = isset($options["c"]) ? $options["c"] : false;
}

if (($manager && $worker) || (!($manager || $worker))) {
    // Only one mode allowed
    $logger->log("Invalid manager/worker flags provided. Terminating...");
    exit(2);
}
if (!$workerShmID || !$workerStatID || !$workerKpaID
        || !$workerShmSemID || !$workerStatSemID || !$workerKpaSemID) {
    if ($worker) {
        // Not all keys provided for shared memory functionality
        $logger->log("Not all Shared Memory keys and semaphores have been provided. Terminating worker...");
        exit(3);
    }
} else if ($manager) {
    $logger->log("Worker keys and semaphores provided will be discarded as we're running in manager mode");
}

if (!$configFile) {
    $logger->log("Configuration file not provided. Terminating...");
    exit(4);
} else {
    $configFile = trim($configFile);
}

if ($manager) {
    $manager = new Manager($configFile);
} else if ($worker) {
    // Note validation of keys and semaphores delegated to worker
    $logger->log("New worker executing under PID: " . getmypid() . " with config file " . $configFile);
    $worker = new Worker($workerShmSemID, $workerShmID,
                            $workerStatSemID, $workerStatID,
                            $workerKpaSemID, $workerKpaID, $configFile);
}

// This process terminated cleanly by Manager's daemonisation or by Worker's EOL
// destruction process. If script reaches this point we have rogue execution.
$logger->log("Error: Process with PID " . getmypid() . " has escaped Manager/Worker execution");
exit(5);