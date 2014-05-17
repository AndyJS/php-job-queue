<?php
namespace PHPJobQueue;

class Logger {
    
    protected $logFile;
    
    function __construct() {
        $this->logFile = "/var/log/phpjobqueue/phpjobqueue.log";
    }
    
    public function setLogFile($logFile) {
        $this->logFile = $logFile;
    }
    
    public function log($message) {
        static $handle = false;
        $date = date("Y-m-d H:i:s");
        if (!$handle) {
            $handle = @fopen($this->logFile, 'a');
        }
        if ($handle) {
            fwrite($handle, $date . " [PID:" . getmypid() . "]\t" . $message . "\n");
        } else {
            trigger_error(__CLASS__ . "Error: Could not open log file " . $this->logFile, E_USER_ERROR);
        }
    }
    
    public function logUnformatted($message) {
        static $handle = false;
        if (!$handle) {
            $handle = @fopen($this->logFile, 'a+');
        }
        if ($handle) {
            fwrite($handle, $message . "\n");
        } else {
            trigger_error(__CLASS__ . "Error: Could not open log file " . $this->logFile, E_USER_ERROR);
        }
    }
}