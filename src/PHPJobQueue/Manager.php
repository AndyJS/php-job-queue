<?php

namespace PHPJobQueue;

define('DEBUG', false);

class Manager {

    protected $pid;
    
    protected $numWorkers;
    
    // All timing properties read in from configuration as microseconds to simplify mathematical operations             
    protected $workerShutdownPeriod;                  
    protected $keepaliveKillThreshold;                
    
    protected $workers = array();                     // PIDs of current worker processes
    protected $workersSHMStores = array();            // Processing IPC data stores per worker
    protected $workersSTATStores = array();           // Status IPC data stores per worker
    protected $workersKPAStores = array();            // Keep-alive IPC data stores per worker
    
    protected $dataManager;                           // Stores and manages data pulled from a data source for allocation purposes

    protected $shmDataSize;                           // Bytes to allocate to each worker data chunk
    protected $keyProvider;
    
    const STAT_DATA_SIZE = 1;                         // Allocate 1 byte to store flags for worker status
    const KPA_DATA_SIZE = 8;                          // Allocate 8 bytes to cover double stored for ms accuracy
    const SHM_DATA_SIZE_INCREASE_RATIO = 2;           // When resizing memory allocation for large data, multiply new limit by ratio as buffer memory

    protected $configFile = 'jobqueue.conf';
    protected $logPath = "/var/log/";
    protected $logName = "phpjobqueue.log";
    protected $logFile = "/var/log/phpjobqueue.log";  // Default log prior to configuration parsing
    protected $pidFile = "/var/run/phpjobqueue.pid";
    
    protected $logger;
    protected $shutdown = false;
    protected $restart = false;
    
    function __construct($configFile) {
        $this->logger = new Logger();
        $this->logger->setLogFile($this->logFile);
        ini_set("error_log" , $this->logFile);
        
        $this->setConfiguration($configFile);
        $this->keyProvider = new KeyProvider();
        $this->daemonise();
        if (!$this->createWorkers($this->numWorkers)) {
            $this->logger->log("Error: Initialisation was not successful, daemon is now exiting...");
            $this->shutdown();
        }
        $this->dataManager = new DataManager();
        
        // Perform maintenance and monitor processes
        $this->monitor();
    }
    
    protected function setConfiguration($configFile) {
        $this->logger->log("Manager reading configuration file " . $configFile);
        $configParser = new ConfigParser($configFile);
        $this->configFile = $configFile;
        
        if (!$configParser->parseConfiguration()) {
            $this->logger->log("Parsing errors found. Manager is terminating...");
            exit(6);
        }
        
        $this->logFile = $configParser->getConfigProperty("log_path", "log path", false, $this->logPath, true) . $this->logName;
        $this->logger->setLogFile($this->logFile);
        $this->numWorkers = $configParser->getConfigProperty("manager_num_workers", "number of workers", true, 10, true);
        $this->shmDataSize = $configParser->getConfigProperty("worker_data_chunk_maxsize", "worker data size", true, 2048, true);
        $this->workerShutdownPeriod = $configParser->getConfigProperty("manager_worker_shutdown_period", "worker shutdown period", true, 3000, true);
        $this->keepaliveKillThreshold = $configParser->getConfigProperty("manager_keepalive_threshold", "keep-alive threshold", true, 2000, true);
    }
    
    protected function daemonise() {
        if (DEBUG) { 
            $this->pid = getmypid();
            $this->keyProvider->updateBaseIDFromPID();
            return;
        }
        
        if ($pid = pcntl_fork()) { exit(0); }
        if (posix_setsid() < 0) { exit(0); }
        $this->registerSignalHandler();
        //if ($pid = pcntl_fork()) { exit(0); }

        $this->pid = getmypid();
        $this->keyProvider->updateBaseIDFromPID();
        
        // Update daemon PID
        $handle = @fopen($this->pidFile, 'w');
        if ($handle) {
            fwrite($handle, $this->pid);
            fclose($handle);
        } else {
            trigger_error(__CLASS__ . "Error: Could not update " . $this->pidFile . " with new PID " . $this->pid, E_USER_ERROR);
        }
        
        $this->logger->log("Manager daemon running under PID: " . $this->pid);
    }
    
    protected function registerSignalHandler() {
        $signals = array (SIGTERM, SIGHUP, SIGINT, SIGCHLD);
        foreach(array_unique($signals) as $signal) {
            pcntl_signal($signal, array($this, 'processSignal'), false);
        }
    }

    protected function createWorkers($numWorkers) {
        // Spin up worker processes
        for ($i=1; $i <= $numWorkers; $i++) {
            $pid = $this->forkWorker();
            if (!$pid) {
                $this->logger->log("Error: Could not fork worker number " . $i . " in worker creation round");
                return false;
            } else {
                $this->logger->log("Manager successfully forked worker child with PID: " . $pid);
            }
        }
        // All workers created successfully
        return true;
    }
    
    protected function forkWorker() {
        // Prepare shared memory for worker IPC
        // Pre-generate unique keys for performance - earlier return if collisions halt assignment
        $shmKeys = $this->keyProvider->getMultipleUniqueKeys(6);
        if (!$shmKeys) {
            $this->logger->log("Warning: Forking of new worker aborted due to key generation failures");
            return false;
        }

        $shmStore = new DataHandler($shmKeys[3], $shmKeys[0], $this->shmDataSize);
        $statStore = new StatusHandler($shmKeys[4], $shmKeys[1], static::STAT_DATA_SIZE);
        $kpaStore = new DataHandler($shmKeys[5], $shmKeys[2], static::KPA_DATA_SIZE);

        if (!($shmStore && $statStore && $kpaStore)) {
            $this->logger->log("Warning: Could not allocate one or more shared memory spaces for current new worker");
            return false;
        }
        
        // Initialise keep-alive timestamp to ensure Manager does not prematurely kill worker
        $currentStamp = round(microtime(true) * 1000);
        if (!$kpaStore->writeDouble($currentStamp)) {
            $this->logger->log("Warning: Manager could not initialise worker timestamp!");
        } else {
            $this->logger->log("Manager initialised worker timestamp at: " . $currentStamp);
        }

        // Initialise status to allow worker to setup prior to data assignment
        if (!$statStore->setReady()) {
            $this->logger->log("Warning: Manager could not initialise worker ready status!");
        }
        
        if (DEBUG) {
            $pid = 0;   // Breakpoint for simulation of worker PID
            $this->workers[] = $pid;
            $this->workersSHMStores[$pid] = $shmStore;
            $this->workersSTATStores[$pid] = $statStore;
            $this->workersKPAStores[$pid] = $kpaStore;

            return $pid;
        }
        
        $pid = pcntl_fork();
        if ($pid == -1) {
            // Error forking from daemon parent, trigger error
            return 0;
        }
        else if ($pid) {
            // We are the daemon parent, store created process ID and memory stores
            $this->workers[] = $pid;
            $this->workersSHMStores[$pid] = $shmStore;
            $this->workersSTATStores[$pid] = $statStore;
            $this->workersKPAStores[$pid] = $kpaStore;

            return $pid;
        }
        else {
            $path = __DIR__ . "/JobQueue.php";
            $arguments = array($path, "-w", "-d " . $shmKeys[0], "-D " . $shmKeys[3], "-s " . $shmKeys[1], "-S " . $shmKeys[4], "-k " . $shmKeys[2], "-K " . $shmKeys[5], "-c " . $this->configFile);
            $argumentText = '';
            foreach ($arguments as $key => $arg) {
                $argumentText .= $arg . ' ';
            }

            // We are the worker child, execute queue launcher in worker mode, replacing this process
            $this->logger->log("Forked worker launching using script: " . $path . " with arguments: " . $argumentText);
            $result = pcntl_exec("/usr/bin/php", $arguments);

            // If the child process reaches this point, execution of worker script failed.
            // Signal manager via SIGCHLD
            $this->logger->log("Execution of new worker failed. Zombie child terminating...");
            exit(7);
        }
    }
    
    protected function monitor() {
        while(true) {
            usleep(100);

            // Check with the PHP interpreter for pending signals
            pcntl_signal_dispatch();

            if ($this->shutdown) { $this->shutdown(); }
            if ($this->restart) { $this->shutdown(true); }

            // Pull data into pool from designated source, if required
            $this->populateDataPool();
            // Perform health check on all workers
            $this->checkWorkerCount();
            $this->checkWorkerHealth();
            // Update delegated data chunks for workers
            $this->updateDataAllocation();
        }
    }
    
    protected function checkWorkerCount() {
        $numWorkersToFork = 0;
        while(count($this->workers) < $this->numWorkers) {
            $this->logger->log("Manager forking additional worker to meet capacity...");
            $numWorkersToFork++;
        }
        if ($numWorkersToFork > 0) { $this->createWorkers($numWorkersToFork); }
    }
    
    protected function checkWorkerHealth() {
        $pidsToClean = array();
        $numNewWorkers = 0;
        foreach ($this->workers as $pid) {
            $currentTime = round(microtime(true) * 1000);
            $lastWorkerUpdate = $this->workersKPAStores[$pid]->readDouble();

            if ($lastWorkerUpdate) {
                if (($currentTime - ($lastWorkerUpdate)) > $this->keepaliveKillThreshold) {
                    $this->logger->log("Manager has found unresponsive worker with PID " . $pid);
                    $this->logger->log("Difference between current stamp and last report is: " . ($currentTime - $lastWorkerUpdate) . ". Restarting worker...");
                    posix_kill($pid, SIGKILL);
                    $pidsToClean[] = $pid;
                    $numNewWorkers++;
                }
            } else {
                $this->logger->log("Manager could not read keep-alive time for worker " . $pid . "!");
            }
        }
        foreach ($pidsToClean as $pid) {
            $this->cleanUpWorkerMemory($pid);
        }
        if ($numNewWorkers > 0) { $this->createWorkers($numNewWorkers); }
    }
    
    protected function updateDataAllocation() {
        foreach($this->workers as $pid) {
            if ($this->dataManager->hasAllocation($pid)) {
                if ($this->workersSTATStores[$pid]->isIdle()) {
                    // Worker has finished publishing it's assigned data
                    $this->dataManager->clearCompletedDataItem($pid);
                    // If data is available, assign more
                    $this->allocateWork($pid);
                } else {
                    if (!$this->workersSTATStores[$pid]->isActive()) {
                        // Worker has yet to pick up work and set processing flag. Re-copy memory for integrity
                        $dataToProcess = $this->dataManager->getAllocatedDataItem($pid)->getData();

                        if (!$this->workersSHMStores[$pid]->writeString($dataToProcess)) {
                            $this->logger->log("Reallocating data for worker PID:" . $pid . " but error encountered writing shared memory");
                        } else {
                            posix_kill($pid, SIGUSR1);
                            /* Potential improvement: Add counter and restart worker if data is not picked up
                             * after a number of signals */
                        }
                    }
                }
            }

        }
    }
    
    protected function allocateWork($pid) {
        // If data is available, delegate to worker
        $dataAvailableIndex = $this->dataManager->checkPendingData();
        if ($dataAvailableIndex > -1) {
            // Write data into shared memory

            $dataToProcess = $this->dataManager->getDataItem($dataAvailableIndex);
            $currentWorkerSHMSize = $this->workersSHMStores[$pid]->getDataSize();
            $newDataSize = $dataToProcess->getSize();

            if ($currentWorkerSHMSize < $newDataSize) {
                // Resize shared memory for this worker as default is too low
                $this->workersSHMStores[$pid]->resizeMemory($newDataSize * static::SHM_DATA_SIZE_INCREASE_RATIO);
            }

            if (!$this->workersSHMStores[$pid]->writeString($dataToProcess->getData())) {
                $this->logger->log("Data found to allocate to worker PID:" . $pid . " but error encountered writing shared memory using data manager index " . $dataAvailableIndex);
                return false;
            }
            // Set worker status
            if (!$this->workersSTATStores[$pid]->setReady()) {
                $this->logger->log("Data manager index " . $dataAvailableIndex . " found to allocate to worker PID:" . $pid . " but error encountered setting status to ready");
                return false;
            }
            $this->dataManager->allocatePIDToDataItem($pid, $dataAvailableIndex);

            // Signal worker to begin work
            posix_kill($pid, SIGUSR1);
            return true;
        }
        return false;
    }
    
    protected function shutdown($restart = false) {
        foreach ($this->workers as $pid) {
            if (!DEBUG) { posix_kill($pid, SIGTERM); }
        }
        
        $startStamp = round(microtime(true) * 1000);
        $workersToCheck = array();
        $killSent = false;
        while (count($this->workers) > 0) {
            // If wait threshold for worker shutdown reached, force kill all workers
            if (!$killSent && (((round(microtime(true) * 1000)) - $startStamp) > $this->workerShutdownPeriod)) {
                $this->logger->log("Worker shutdown period has been reached. Manager is forcing termination...");
                foreach ($this->workers as $pid) {
                    $this->logger->log("Killing PID: " . $pid);
                    if (!DEBUG) { posix_kill($pid, SIGKILL); }
                    $killSent = true;
                }
            }

            // Reap any children that have terminated
            $this->reapWorkers();

            // Check workers for termination
            $workersToPurge = array();
            foreach ($this->workers as $pid) {
                if(!file_exists('/proc/' . $pid)) {
                    $this->logger->log("Manager has confirmed shutdown of worker " . $pid);
                    $workersToCheck[] = $pid;
                    $workersToPurge[] = $pid;
                }
            }
            // Purge any terminated workers from local pool
            foreach($workersToPurge as $pidToPurge) {
                foreach($this->workers as $index => $workerPid) {
                    if ($pidToPurge == $workerPid) {
                        $this->logger->log("Removing worker " . $workerPid . " from active pool");
                        unset($this->workers[$index]);
                    }
                }
            }
        }
        
        // Check status of all killed workers to determine if data was processed
        foreach ($workersToCheck as $pid) {
            if (!$this->workersSTATStores[$pid]->isIdle()) {
                // Worker was still executing, or encountered issues
                $this->logger->log("Shutdown warning: Worker PID " . $pid . "may not have finished and published last data chunk with SHMID " . $this->workersSHMStores->getDataID());
            }
        }
        
        /* Dump all unprocessed data to log for integrity. This method creates a
         * file per data item dumped. For scale, some serialisation and file
         * encoding may be required to minimise the number of files created.
         * Alternatively, this data could be published elsewhere for reprocessing.
         */
        $this->dataManager->logAllData($this->logPath);

        // Clean up all shared memory in use
        $this->cleanUpSharedMemory();
        
        if ($restart) {
            $this->logger->log("Manager restarting...");
            // Fully replace current process with new instance of Manager
            pcntl_exec("/usr/bin/php", array(__DIR__ . "/JobQueue.php", "-m", "-c " . $this->configFile));
            // If execution continues, process replacement has failed, exit abnormally
            $this->logger->log("Manager has not been able to restart and maintain stability. Terminating...");
            exit(8);
        } else {
            $this->logger->log("Manager shutting down");
            exit(0);
        }
    }
    
    protected function cleanUpSharedMemory() {
        $memoryHandlers = array_merge($this->workersSHMStores,$this->workersSTATStores,$this->workersKPAStores);
        foreach($memoryHandlers as $memoryHandler) {
            unset($memoryHandler);
        }
    }
    
    protected function cleanUpWorkerMemory($pid) {
        $this->logger->log("Clearing up worker memory for PID: " . $pid);
        foreach($this->workers as $index => $workerPid) {
            if ($pid == $workerPid) {
                unset($this->workers[$index]);
            }
        }
        $this->workersKPAStores[$pid]->cleanUpMemory();
        $this->workersSHMStores[$pid]->cleanUpMemory();
        $this->workersSTATStores[$pid]->cleanUpMemory();
        unset($this->workersKPAStores[$pid]);
        unset($this->workersSHMStores[$pid]);
        unset($this->workersSTATStores[$pid]);
        
        // Any data assigned to PID killed we free up for other workers
        $this->dataManager->freeAllocatedDataItem($pid);
    }
    
    protected function populateDataPool() {
        /* Functionality currently out of scope.
         * Function populates Data Manager object with any available data which
         * requires processing, from the designated data source.
         */
    }

    protected function reapWorkers() {
        $childStatus = null;
        $child = pcntl_waitpid(-1, $childStatus, WNOHANG);
        if ($child > 0) {
            if (pcntl_wifexited($childStatus)) {
                $this->logger->log("Manager detected termination of worker process PID: " . $child . " with return code: " . pcntl_wexitstatus($childStatus));
                $pcntlAdditionalInfo = pcntl_strerror(pcntl_wexitstatus($childStatus));
                if ($pcntlAdditionalInfo && strlen($pcntlAdditionalInfo) > 0 && $pcntlAdditionalInfo !== "Success") {
                    $this->logger->log("PCNTL status reported: " . $pcntlAdditionalInfo);
                }
            } else {
                $this->logger->log("Manager detected abnormal termination of worker process PID: " . $child);
            }
        }
    }
    
    protected function processSignal($signal) {
        switch ($signal)
        {
            case SIGHUP:
                // Restart signal
                $this->restart = true;
                $this->logger->log("Manager received restart signal");
                break;
            case SIGINT:
            case SIGTERM:
                // Shutdown signal
                $this->shutdown = true;
                $this->logger->log("Manager received shutdown signal");
                break;
            case SIGCHLD:
                // Deal with zombie child
                $this->reapWorkers();
                break;
        }
    }
}