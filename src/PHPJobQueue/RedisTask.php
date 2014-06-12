<?php

namespace PHPJobQueue;

class RedisTask implements Task {

    private $dataItem = null;
    private $inputStream = null;
    private $endpoint = null;

    protected $logger;
    protected $logPath = "/var/log/phpjobqueue/";
    protected $logName = "phpjobqueue.log";
    protected $configFile = 'jobqueue.conf';

    function __construct($configFile) {
        $this->logger = new Logger();
        $this->logger->setLogFile($this->logPath . $this->logName);
        ini_set("error_log" , $this->logPath . $this->logName);

        $this->setConfiguration($configFile);
    }

    public function process() {
        // Redis FIFO streaming input
        $data = "";

        $this->dataItem = new DataItem($data);
        return true;
    }

    public function publish() {
        // Redis FIFO publishing


        return true;
    }

    protected function setConfiguration($configFile) {
        $this->logger->log("Task reading configuration file " . $configFile);
        $configParser = new ConfigParser($configFile);
        $this->configFile = $configFile;

        if (!$configParser->parseConfiguration()) {
            $this->logger->log("Parsing errors found. Task is terminating...");
            return false;
        }

        $this->logPath = $configParser->getConfigProperty("log_path", "log path", false, $this->logPath, true);
        $this->logger->setLogFile($this->logPath . $this->logName);

        $this->logPath = $configParser->getConfigProperty("task_redis_inputstream", "input stream", false, $this->inputStream, true);
        $this->logPath = $configParser->getConfigProperty("task_redis_endpoint", "endpoint", false, $this->endpoint, true);
    }

}