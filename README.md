php-job-queue
-------------

PHP Job Queue is a set of PHP scripts which provides simple manager-worker
job queue functionality. Run under the PHP interpreter, a daemonised manager process
administers a pool of worker children. Each worker implements a configurable set of
PHP classes providing data manipulation procedures.

Managers have the ability to delegate processing of abstract data items to idle
worker processes. The project aims include speed and a focus on data integrity,
as well as flexibility within application configuration.

As-is, this project's scope does not include functionality to retrieve or wait on
any specific queue input, and workers are currently configured to publish processed
data to log files for testing purposes.

Guidelines for configuration are available in CONFIGURATION.md. Details on
implementation, limitations and areas of improvement are outlined in DEVELOPMENT.md

Requirements
------------

PHP Job Queue has been tested on Ubuntu Server 13.04, and should require only
one package, 5.4.9-4ubuntu2.4. This may be installed via the following command:

`sudo apt-get install php5-cli`

To prepare other distributions to run PHP Job Queue, the following requirements
must be met:

- PHP CLI 5.4+
- PHP must have been compiled/enabled with the following non-standard extensions:
	- PCNTL (`--enable-pcntl`)
	- System V Semaphore & Memory (`--enable-sysvsem --enable-sysvshm`)

To execute the included unit tests, PHPUnit must be installed as per
instructions available at http://phpunit.de

On Ubuntu Server 13.04, the following command can be run:

`sudo apt-get install phpunit`

To obtain the project files, Git must be installed:

`sudo apt-get install git-core`

Installation
------------

Currently this project does not utilise a build tool; Source scripts are
executed directly by the PHP-CLI interpreter, with a working directory within ./src
The ideal method by which to launch the application is via the included init.d script.

1. `git clone https://github.com/AndyJS/php-job-queue.git`
 
2. `sudo useradd -r phpjobqueue -s /bin/false`

3. If you wish to install the scripts under a directory in which the above user does not have read access, the following commands should be executed:

	`sudo mkdir -p /opt/phpjobqueue`
	
	`sudo chown -R phpjobqueue:phpjobqueue /opt/phpjobqueue`
	
	`sudo chmod ug+s /opt/phpjobqueue`

4. Copy all files under the src directory to the location from which you wish to run the scripts.

   i.e. `sudo cp -r ./php-job-queue/src/* /opt/phpjobqueue`

5. Edit the file `./php-job-queue/bin/phpjobqueue`

   Replace the value of the DAEMON_PATH property on line 4 to match the location
   to which the PHPJobQueue directory was copied in step 4

6. `sudo cp ./php-job-queue/bin/phpjobqueue /etc/init.d`

   `sudo chmod +x /etc/init.d/phpjobqueue`

7. Set up the default log file.

   It is recommended you retain the default log location, however for a custom location please replace /var/log in the steps below. Please note these steps are required as-is if you are to be running unit tests.
   
   `sudo mkdir /var/log/phpjobqueue`

   `sudo chmod ug+s /var/log/phpjobqueue`

   `sudo touch /var/log/phpjobqueue/phpjobqueue.log`
   
   `sudo chmod 664 /var/log/phpjobqueue/phpjobqueue.log`
   
   `sudo chown -R phpjobqueue:phpjobqueue /var/log/phpjobqueue`

Changing Log Location or User
-----------------------------

Please note, that as it stands if log_path or uname in jobqueue.conf are altered at any time, steps 2, 3, 5 and 7 will need to be re-run with the appropriate path and username.
In addition, the variables LOGFILE and DAEMON_USER within the init.d script phpjobqueue must be changed to reflect the new values.

Execution
---------

Finally, the job queue can be started, restarted, and stopped via the commands:

`sudo service phpjobqueue start`

`sudo service phpjobqueue restart`

`sudo service phpjobqueue stop`

Testing
-------

The Test Suite under tests/PHPJobQueue is defined within phpunit.xml in the
project root.

Please carry out all installation steps prior to running unit tests.

To execute all unit tests, carry out the following commands:

1. `usermod -a -G phpjobqueue yourusername`
	This is to ensure you have correct privileges for log access. Re-log after this has been run.
2. `cd ./php-job-queue` or cd to the root of the cloned git repository
3. `phpunit`

README Files
------------

CONFIGURATION.md

    Defines configurable properties in the job queue configuration file.

DEVELOPMENT.md

    Details current limitations to be aware of, and potential areas for improvement.
