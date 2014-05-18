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

Installation
------------

Currently this project does not utilise a build tool; Source scripts are
executed directly by the PHP-CLI interpreter, with a working directory within ./src
The ideal method by which to launch the application is via the included init.d script.

1. `git clone https://github.com/AndyJS/php-job-queue.git`

2. `cp -r ./php-job-queue/src/PHPJobQueue /opt`

   Replace /opt with directory of choice if required

3. Edit the file `./php-job-queue/bin/phpjobqueue`

   Replace the value of the DAEMON_PATH property on line 4 to match the location
   to which the PHPJobQueue directory was copied in step 2

4. `cp ./php-job-queue/bin/phpjobqueue /etc/init.d`

5. `sudo chmod +x /etc/init.d/phpjobqueue`

6. `sudo useradd -r phpjobqueue -s /bin/false`

7. `sudo mkdir /var/log/phpjobqueue`

8. `sudo chown phpjobqueue:phpjobqueue /var/log/phpjobqueue`

Finally, the job queue can be started, restarted, and stopped via the commands:

`service phpjobqueue start`

`service phpjobqueue restart`

`service phpjobqueue stop`

Testing
-------

The Test Suite under tests/PHPJobQueue is defined within phpunit.xml in the
project root. To execute all unit tests, carry out the following commands:

1. `git clone https://github.com/AndyJS/php-job-queue.git`
2. `cd php-job-queue`
3. `phpunit`

README Files
------------

CONFIGURATION.md

    Defines configurable properties in the job queue configuration file.

DEVELOPMENT.md

    Details current limitations to be aware of, and potential areas for improvement.
