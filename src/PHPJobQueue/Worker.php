<?php

namespace PHPJobQueue;

class Worker {

    // Worker-specific shared memory handlers
    protected $shmStore;
    protected $statStore;
    protected $kpaStore;

    protected $kpaPeriod;
    protected $kpaPeriodSendThreshold;        // Stores the saftey threshold for updating the worker's keep-alive signal

    protected $shmDataSize;

    const KPA_SEND_THRESHOLD_RATIO = 0.75;    // Defines the fraction of the update period to assign to saftey threshold
    
    protected $shutdown = false;
    protected $workAvailable = false;
    protected $kpaLastUpdate;
    
    protected $data;
    protected $pipelineModules;               // Stores array of classes implementing the worker's current Tasks
    protected $dataPipeline;                  // Stores array of objects implementing the classes defined in pipelineModules
    
    protected $logger;
    protected $logPath = "/var/log/phpjobqueue/";
    protected $logName = "phpjobqueue.log";
    protected $configFile = 'jobqueue.conf';
    
    function __construct($shmSem, $shmKey, $statSem, $statKey, $kpaSem, $kpaKey, $configFile) {
        $this->logger = new Logger();
        $this->logger->setLogFile($this->logPath . $this->logName);
        ini_set("error_log" , $this->logPath . $this->logName);

        $this->setConfiguration($configFile);
        $this->setupDataStores($shmSem, $shmKey, $statSem, $statKey, $kpaSem, $kpaKey);

        $this->buildPipeline();
        
        $this->registerSignalHandler();
        if (!$this->statStore->setIdle()) {
            $this->logger->log("Error initialising idle status for worker. Terminating...");
            exit(1);
        } else {
            $this->logger->log("Worker is ready for work.");
        }
        
        $this->waitForWork();
    }

    protected function setupDataStores($shmSem, $shmKey, $statSem, $statKey, $kpaSem, $kpaKey) {
        // Setup worker data stores to access existing Manager-created memory
        $this->shmStore = new DataHandler($shmSem, $shmKey, null);
        $this->statStore = new StatusHandler($statSem, $statKey, null);
        $this->kpaStore = new DataHandler($kpaSem, $kpaKey, null);

        if (!($this->shmStore && $this->statStore && $this->kpaStore)) {
            $this->logger->log("Warning: Could not open for access one or more shared memory spaces for this worker. Terminating...");
            exit(9);
        }
    }
    
    protected function setConfiguration($configFile) {
        $this->logger->log("Worker reading configuration file " . $configFile);
        $configParser = new ConfigParser($configFile);
        $this->configFile = $configFile;
        
        if (!$configParser->parseConfiguration()) {
            $this->logger->log("Parsing errors found. Worker is terminating...");
            exit(1);
        }
        
        $this->logPath = $configParser->getConfigProperty("log_path", "log path", false, $this->logPath, true);
        $this->logger->setLogFile($this->logPath . $this->logName);

        $this->kpaPeriod = $configParser->getConfigProperty("worker_keepalive_period", "worker keepalive period", true, 2000, true);
        $this->kpaPeriodSendThreshold = $this->kpaPeriod * static::KPA_SEND_THRESHOLD_RATIO;
        $this->kpaLastUpdate = 0;

        $this->shmDataSize = $configParser->getConfigProperty("worker_data_chunk_maxsize", "worker data size", true, 2048, true);
        
        $this->pipelineModules = $configParser->getConfigProperty("worker_modules", "worker pipeline tasks", false, array(), true);
        if (count($this->pipelineModules) == 0) {
            $this->logger->log("Worker has no tasks to load. Set worker_modules[] in configuration. Terminating...");
            exit(10);
        }
    }
    
    protected function buildPipeline() {
        foreach($this->pipelineModules as $moduleName) {
            $module = null;
            if (class_exists('PHPJobQueue\\' . $moduleName, true)) {
                $moduleToLoad = 'PHPJobQueue\\' . $moduleName;
                $module = new $moduleToLoad($this->configFile);
            }
            if (empty($module)) {
                $this->logger->log("Worker cannot load module " . $moduleName);
            } else {
                $this->dataPipeline[] = $module;
            }
        }
        if (count($this->dataPipeline) === 0) {
            $this->logger->log("No modules were loaded successfully for worker. Terminating...");
            exit(1);
        }
    }
    
    protected function waitForWork() {
        while(true) {
            // Utilise best practice for picking up posix signals. Potential performance impact if signals not picked up
            // during usleep, as worker needs to wait for dispatch to process any data passed. Analysis needed.
            pcntl_signal_dispatch();

            // Shutdown immediately if request has been received
            if ($this->shutdown) {
                $this->logger->log("Worker is shutting down as per instruction whilst idle...");
                exit(0);
            }
            // Generate keep-alive if required
            $this->sendKeepAlive();
            // Sleep to minimise busy wait impact
            usleep($this->kpaPeriodSendThreshold);

            // Execute tasks
            $this->work();
        }
    }
    
    protected function sendKeepAlive() {
        $currentStamp = round(microtime(true) * 1000);
        if (($currentStamp - $this->kpaLastUpdate) > ($this->kpaPeriodSendThreshold)) {
            if (!$this->kpaStore->writeDouble($currentStamp)) {
                $this->logger->log("Worker could not write it's keep-alive time: " . $currentStamp . "!");
            }
            $this->kpaLastUpdate = $currentStamp;
        }
    }
    
    protected function prepareWork() {
        // Copy job data passed by manager
        $this->data = $this->shmStore->readString();
        if (!$this->data) {
            $this->logger->log("Worker could not successfully copy in data.");
            // As work preparation is kicked off by SIGUSR1, wokrer exits signal handler processing to return to main
            // loop to await manager re-allocation
            return;
        } else {
            $this->work();
        }
    }
    
    protected function work() {
        $this->statStore->setActive();
        // No longer read in data as Tasks stream data from external sources, not the Manager/Worker
        //$dataToProcess = $this->data;
        
        foreach($this->dataPipeline as $task) {
            pcntl_signal_dispatch();

            // Continue to update keep-alive whilst processing each task
            $this->sendKeepAlive();
            
            $processResult = $task->process();
            if (!$processResult) {
                $this->logger->log("Worker task " . get_class($task) . " failed to process data");
            }
            $publishResult = $task->publish();
            if (!$publishResult) {
                $this->logger->log("Worker task " . get_class($task) . " failed to publish data");
            }
        }
    }
    
    protected function publish() {
        $published = false;
        
        // Publish data to endpoint until successful
        // Worker does not want to accept any further data until this has been confirmed
        while (!$published) {
            pcntl_signal_dispatch();

            // Continue to send keep-alive whilst publishing data
            $this->sendKeepAlive();
                        
            $published = $this->sendData();
            
            // Check if worker has received a request to shutdown whilst processing
            if ($this->shutdown) {
                if ($published) {
                    // Data was published successfully, signal to manager and exit
                    $this->statStore->setIdle();
                    $this->logger->log("Worker has published data successfully and will now shutdown");
                    exit(0);
                }
                // Manager has requested shutdown and worker has not published yet.
                // Worker will reattempt to publish data prior to manager forcing termination within it's threshold
            }
        }
        // Signal to manager that data has been published and return to main wait loop
        $this->workAvailable = false;
        unset($this->data);
        $this->statStore->setIdle();
    }
    
    protected function sendData() {
        // Current publishing process out of scope. If successful function returns true.
        // Currently we prototype publishing by dumping output data to flatfile
        
        $tmpLogger = new Logger();
        $tmpWorkLog = $this->logPath . "phpjobqueue_worker_" . $this->pid . "_output_" . time() . ".log";
        $tmpLogger->setLogFile($tmpWorkLog);
        $tmpLogger->logUnformatted($this->data);
        
        return true;
    }
    
    protected function registerSignalHandler() {
        $signals = array (SIGTERM, SIGINT, SIGUSR1);
        foreach(array_unique($signals) as $signal) {
            pcntl_signal($signal, array($this, 'processSignal'), false);
        }
    }
    
    protected function processSignal($signal) {
        switch ($signal)
        {
            case SIGINT:
            case SIGTERM:
                // Shutdown signal
                $this->logger->log("Worker has received instruction to shutdown");
                $this->shutdown = true;
                break;
            case SIGUSR1:
                // Worker has received a job
                if (!$this->workAvailable) {
                    $this->workAvailable = true;
                    $this->prepareWork();
                }
                break;
        }
    }
}