<?php

namespace PHPJobQueue;

class ConfigParser {

    protected $inputFile;
    protected $configFile;
    protected $logger;
    protected $queueConfiguration;
    protected $logFile = "phpjobqueue.log";
    
    function __construct($configFile) {
        $this->inputFile = $configFile;
        $this->logger = new Logger();
    }
    
    public function parseConfiguration($log = true) {
        if ($this->inputFile[0] == "/") {
            $this->configFile = $this->inputFile;
        } else {
            $this->configFile = realpath(__DIR__ . "/" . $this->inputFile);
        }
        if ($log) { $this->logger->log("Reading configuration file " . $this->inputFile . " resolved to " . $this->configFile); }
        $this->queueConfiguration = parse_ini_file($this->configFile);
        
         if (! $this->queueConfiguration) {
            $this->logger->log("Error: " . $this->configFile . " not found. Parsing failed.");
            return false;
        } else {
            $this->logger->setLogFile($this->getConfigProperty("log_path", "log path", false, "/var/log/phpjobqueue/" . $this->logFile, false) . $this->logFile);
            return true;
        }
    }
    
    public function getConfigProperty($name, $desc, $numeric, $defaultValue, $log) {
         if (array_key_exists($name, $this->queueConfiguration)) {
                $property = $this->queueConfiguration[$name];
                if ($numeric && !is_numeric($property)) {
                    if ($log) { $this->logger->log("Warning: invalid value for " . $name . " in " . $this->configFile . ". Defaulting to " . $defaultValue); }
                    return $defaultValue;
                } else {
                    $logEntry = "Setting " . $desc . " to ";
                    if (is_array($property)) {
                        foreach($property as $item) {
                            $logEntry .= $item . " ";
                        }
                    } else {
                        $logEntry .= $property;
                    }
                    if ($log) { $this->logger->log($logEntry); }
                    return $property;
                }
            } else {
                if ($log) { $this->logger->log("Warning: " . $name . " not defined in " . $this->configFile . ". Defaulting to " . $defaultValue); }
                return $defaultValue;
            }
    }
}