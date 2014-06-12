<?php

namespace PHPJobQueue;

class ZMQTask implements Task {

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
        // HTTP/1.1 streaming input
        $context = new \ZMQContext();

        $receiver = new \ZMQSocket($context, \ZMQ::SOCKET_PULL);
        $receiver->connect($this->inputStream);

        try {
            $data = $receiver->recv(\ZMQ::MODE_NOBLOCK);
        } catch (\ZMQSocketException $e) {
            if ($e->getCode() === \ZMQ::ERR_EAGAIN) {
                $this->logger->log("ZMQTask: No data on socket; not blocked.");
            }
        }

        $this->dataItem = new DataItem($data);
        return true;
    }

    public function publish() {
        // ZMQ push socket publishing
        $context = new \ZMQContext();

        $publisher = new \ZMQSocket($context, \ZMQ::SOCKET_PUSH);
        $publisher->connect($this->endpoint);

        try {
            $publisher->send(serialize($this->dataItem));
        } catch (\ZMQSocketException $e) {

        }

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

        $this->logPath = $configParser->getConfigProperty("task_zmq_inputstream", "input stream", false, $this->inputStream, true);
        $this->logPath = $configParser->getConfigProperty("task_zmq_endpoint", "endpoint", false, $this->endpoint, true);
    }
}