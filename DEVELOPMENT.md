Limitations and Issues
----------------------

Currently there are a number of limitations and known issues present.

Data Sources
------------

The application implements shared memory funcitonality to provide speedy data IPC between Manager and Worker. Currently, the code governing the reading and writing of data to memory assumes one byte per character encoding, whilst transforming between PHP object memory and binary strings. This imposes a hard-coded restriction on more elaborate encodings such as Unicode, which require 2 bytes per character.

Known Issues
------------

Manager and workers all log by default to a single log file, phpjobqueue.log. When a high amount of logging needs to take place within a short period, from multiple processes, this can cause bottleneck in execution as different handles are managed on the file. When the worker number is increased significantly within the config file, and the service started, this delay can cause workers to fail to send keep-alives, resulting in unnecessary re-forks.

Increasing the keep-alive period or reducing the number of workers is a temporary solution. Logs would ideally be split into multiple files, and/or some form of queue would be implemented. A singleton Logging pattern may aid in this.

User Experience
---------------

An automated installation script, or use of a deployment tool would cut the need for multiple installation steps, reducing risk and increasing stability.

Shared Memory Support
---------------------

By default, Ubuntu Server 13.04 supports the following limits on System V Shared Memory usage:

Max total shared memory:        8388MB

Max shared memory segment size: 33554KB

Depending on the data sizes and number of workers being maintained after deployment, these values may have to be tweaked via manipulation of the values in the files under `/proc/sys/kernel`. See http://tech.vys.in/2007/08/ipc-limits-in-linux.html
