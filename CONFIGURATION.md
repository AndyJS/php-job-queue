php-job-queue Configuration
---------------------------

All configuration properties are currently specified within the plain text file jobqueue.conf. By default this is located within the src/PHPJobQueue directory.

The location of the configuration file may be changed by altering the path passed by parameter -c to the JobQueue.php bootstrap script. For normal execution this parameter is defined in the ./bin/phpjobqueue init.d shell script. This parameter may be relative or absolute. If a relative path is used, it must currently be in relation to the "src/PHPJobQueue" directory.

Properties
----------

The following properties are available for assignment:

  - `uname`
    
    The system user under which all queue processes will be run. During initialisation, the Manager script sets the UID of all subsequent processes.
    This user must have read and write access to the path specified as log_path. Please refer to README Installation section for commands to run once altered.
    If modifying this value, the DAEMON_USER variable in the init.d script must be changed to match, to allow correct setup of the PID file.

  - `log_path`
    
    The absolute path pointing to the directory under which all php-job-queue log files will be created. Currently must include a trailing forward slash.
    The user specified by uname must have read and write access to this directory. Please refer to README Installation section for commands to run once altered.
    IF modifying this value, the LOGFILE variable in the init.d script must be changed to reflect the new location.

  - `manager_num_workers`

    Numeric value specifying the amount of worker processes to sustain under the Manager process. There is currently no limit on this value, however due to limitations touched on in DEVELOPMENT.md bugs may be encountered if this value is high enough.

  - `manager_worker_shutdown_period`

    Period in milliseconds to allow after sending worker processes the termination signal. Once this threshold is reached, workers are forcefully killed.

  - `manager_keepalive_threshold`

    Period in milliseconds defining the threshold at which a worker's last keep-alive update is considered invalid. If a manager finds a worker has exceeded this threshold, it is terminated and re-created.

  - `worker_keepalive_period`

    Period in milliseconds defining the threshold in which a worker should attempt to update it's keep-alive signal. This should be some fraction of the manager's keep-alive threshold to avoid premature terminations, although the worker currently employs a safety margin in addition to this difference.

  - `worker_data_chunk_maxsize`

    The initial maximum size in bytes allocated to each worker for the purpose of transferring data to be processed. If data exceeds this amount, the manager will automatically re-allocate to twice the original maximum size. This should be set with regards to expected source data sizes, to optimise the memory usage of manager and workers.

  - `worker_modules[]`

    Specified as per PHP INI file array guidlines, and the included Task examples. This array provides a list of class names, implementing the Task interface, which will be included in any worker's data processing pipeline.
    The class names listed here must be valid PHP class files, defined within the PHPJobQueue namespace.
