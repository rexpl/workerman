<?php

declare(strict_types=1);

namespace Rexpl\Workerman;

use LogicException;
use Rexpl\Workerman\Commands\Command;
use Rexpl\Workerman\Exceptions\WorkermanException;
use Rexpl\Workerman\Tools\Files;
use Rexpl\Workerman\Tools\Helpers;
use Rexpl\Workerman\Tools\Output;
use Rexpl\Workerman\Tools\OutputInterface;
use Rexpl\Workerman\Worker;
use Symfony\Component\Console\Application;
use Workerman\Timer;

class Workerman
{
    /**
     * Workerman version.
     * 
     * @var string
     */
    public const VERSION = '0.1.0';


    /**
     * Exit success.
     * 
     * @var int
     */
    public const EXIT_SUCCESS = 0;


    /**
     * Exit on failure.
     * 
     * @var int
     */
    public const EXIT_FAILURE = 1;


    /**
     * Start command.
     * 
     * @var int
     */
    public const COMMAND_START = 0;


    /**
     * Reload command.
     * 
     * @var int
     */
    public const COMMAND_RELOAD = 1;


    /**
     * Stop command.
     * 
     * @var int
     */
    public const COMMAND_STOP = 2;


    /**
     * Status command.
     * 
     * @var int
     */
    public const COMMAND_STATUS = 3;


    /**
     * Linux like os.
     * 
     * @var int
     */
    public const LINUX = 0;


    /**
     * Windwos os.
     * 
     * @var int
     */
    public const WINDOWS = 1;


    /**
     * Path to the PID file.
     * 
     * @var string
     */
    public const PID_FILE = 'process.pid';


    /**
     * Path to the status info file.
     * 
     * @var string
     */
    public const STATUS_FILE = 'status.workerman';


    /**
     * Path to the restart info file.
     * 
     * @var string
     */
    public const RESTART_FILE = 'restart.workerman';


    /**
     * Path to the shutdown info file.
     * 
     * @var string
     */
    public const SHUTDOWN_FILE = 'shutdown.workerman';


    /**
     * Default backlog. Backlog is the maximum length of the queue of pending connections.
     *
     * @var int
     */
    public const DEFAULT_BACKLOG = 102400;
    

    /**
     * Max udp package size.
     *
     * @var int
     */
    public const MAX_UDP_PACKAGE_SIZE = 65535;


    /**
     * Tcp transport.
     * 
     * @var int
     */
    public const TCP_TRANSPORT = 0;


    /**
     * Udp transport.
     * 
     * @var int
     */
    public const UPD_TRANSPORT = 1;


    /**
     * Ssl transport.
     * 
     * @var int
     */
    public const SSL_TRANSPORT = 2;


    /**
     * Unix socket transport.
     * 
     * @var int
     */
    public const UNIX_TRANSPORT = 3;


    /**
     * Built in transport.
     * 
     * @var array<int,string>
     */
    public const BUILT_IN_TRANSPORT = [
        self::TCP_TRANSPORT => 'tcp',
        self::UPD_TRANSPORT => 'udp',
        self::SSL_TRANSPORT => 'tcp',
        self::UNIX_TRANSPORT => 'unix',
    ];


    /**
     * Frame protocol.
     * 
     * @var int
     */
    public const FRAME_PROTOCOL = 0;


    /**
     * Text protocol.
     * 
     * @var int
     */
    public const TEXT_PROTOCOL = 1;


    /**
     * Http protocol.
     * 
     * @var int
     */
    public const HTTP_PROTOCOL = 2;


    /**
     * Websocket protocol.
     * 
     * @var int
     */
    public const WS_PROTOCOL = 3;


    /**
     * Built in protocols.
     * 
     * @var array<int,string>
     */
    public const BUILT_IN_PROTOCOL = [
        self::FRAME_PROTOCOL => \Workerman\Protocols\Frame::class,
        self::TEXT_PROTOCOL => \Workerman\Protocols\Text::class,
        self::HTTP_PROTOCOL => \Workerman\Protocols\Http::class,
        self::WS_PROTOCOL => \Workerman\Protocols\Websocket::class,
    ];


    /**
     * On worker start.
     * 
     * @var int
     */
    public const ON_WORKER_START = 0;


    /**
     * On worker stop.
     * 
     * @var int
     */
    public const ON_WORKER_STOP = 1;


    /**
     * On connect.
     * 
     * @var int
     */
    public const ON_CONNECT = 2;


    /**
     * On message.
     * 
     * @var int
     */
    public const ON_MESSAGE = 3;


    /**
     * On connection close.
     * 
     * @var int
     */
    public const ON_CLOSE = 4;


    /**
     * On error.
     * 
     * @var int
     */
    public const ON_ERROR = 5;


    /**
     * On buffer full.
     * 
     * @var int
     */
    public const ON_BUFFER_FULL = 6;


    /**
     * On buffer drain.
     * 
     * @var int
     */
    public const ON_BUFFER_DRAIN = 7;


    /**
     * Path to redirect STDERR.
     * 
     * @var string|null
     */
    protected static ?string $stderr = null;


    /**
     * Name of the master process.
     * 
     * @var string|null
     */
    protected static ?string $name = null;


    /**
     * Operating system.
     * 
     * @var int
     */
    protected int $os;


    /**
     * The command requested.
     * 
     * @var int
     */
    protected int $command;


    /**
     * Should run as a daemon.
     * 
     * @var bool
     */
    protected bool $daemon;


    /**
     * Should stop/reload gracefully.
     * 
     * @var bool
     */
    protected bool $graceful;


    /**
     * Worker ID pointer.
     * 
     * @var int
     */
    protected int $workerID = 0;


    /**
     * All workers.
     * 
     * @var array<int,Worker>
     */
    protected array $workers = [];


    /**
     * @param string $path
     * 
     * @return void
     */
    public function __construct(string $path)
    {
        if (PHP_SAPI !== 'cli') {

            throw new LogicException(
                'Workerman is a command line applicaton. Can only run workerman in cli environment.'
            );
        }

        Files::$rootPath = $path;
        Output::debug(
            [
                sprintf('Workerman version: %s', \Workerman\Worker::VERSION),
                sprintf('Application version: %s', self::VERSION),
                sprintf('Work directory: %s', $path)
            ]
        );

        $this->os = DIRECTORY_SEPARATOR === '\\' ? self::WINDOWS : self::LINUX;
        Output::debug(sprintf(
            'Operating system: %s', $this->os ? 'Windows' : 'Unix'
        ));
    }


    /**
     * Start workerman.
     * 
     * @param bool $daemon
     * 
     * @return int
     */
    public function start(bool $daemon): int
    {
        $this->daemon = $daemon;
        $this->command = self::COMMAND_START;

        return $this->init();
    }


    /**
     * Get the status of workerman.
     * 
     * @return array
     */
    public function status(): array
    {
        $this->command = self::COMMAND_STATUS;

        return $this->init();
    }


    /**
     * Stop workerman.
     * 
     * @param bool $graceful
     * 
     * @return int
     */
    public function stop(bool $graceful): int
    {
        $this->command = self::COMMAND_STOP;
        $this->graceful = $graceful;

        return $this->init();
    }


    /**
     * Restart workerman.
     * 
     * @param bool $graceful
     * 
     * @return int
     */
    public function restart(bool $graceful): int
    {
        $this->command = self::COMMAND_RELOAD;
        $this->graceful = $graceful;

        return $this->init();
    }


    /**
     * Initialize workerman.
     * 
     * @return int|array
     */
    protected function init(): int|array
    {
        Timer::init();

        switch ($this->command) {
            case self::COMMAND_START:
                
                Output::debug('Command: start');
                Output::debug(sprintf('Daemon: %s', $this->daemon ? 'Yes' : 'No'));

                if ($this->os === self::LINUX) return $this->startWorkerman();

                //return $this->forkWorkersForWindows();
            
            case self::COMMAND_RELOAD:

                Output::debug('Command: reload');
                Output::debug(sprintf('Graceful: %s', $this->graceful ? 'Yes' : 'No'));
            
                return $this->restartWorkers();

            case self::COMMAND_STOP:

                Output::debug('Command: stop');
                Output::debug(sprintf('Graceful: %s', $this->graceful ? 'Yes' : 'No'));
            
                return $this->stopWorkers();
            
            case self::COMMAND_STATUS:

                Output::debug('Command: status');
            
                return $this->collectStatus();
        }
    }


    /**
     * Verify workerman is running.
     * 
     * @return bool
     */
    protected function isWorkermanRunning(): bool
    {
        return Files::fileExists(Workerman::PID_FILE);
    }


    /**
     * Return an array of hashes to identify workers in various tasks.
     *
     * @param string $file The file containing the hashes.
     * 
     * @return array
     */
    protected function getAllHashes(string $file): array
    {
        Output::debug('Collecting hash file');

        while (1) {

            if (Files::fileExists($file)) break;

            if (!isset($ouputAlreadySent)) {

                Output::debug('Waiting for hash file..');
                $ouputAlreadySent = true;
            }

            usleep(200000);
        }

        $result = Files::getFileContent($file);
        Files::deleteFile($file);

        return $result;
    }


    /**
     * --------------------------------------------------------------------------
     * The methods below are for the start process on unix systems.
     * --------------------------------------------------------------------------
     * 
     * Steps:
     *  - We initialize all sockets
     *  - Fork all workers
     *  - Then initialize the master loop for monitoring
     */


    /**
     * Init all sockets.
     * 
     * @return int
     */
    protected function startWorkerman(): int
    {
        if ($this->isWorkermanRunning()) {

            throw new WorkermanException(
                'Cannot start workerman, workerman already running.'
            );
        }

        $this->redirectStdErr();
        $this->setProcessName();
        
        if ($this->daemon) return $this->daemonize();

        return $this->initAllSockets();
    }


    /**
     * Verify redirect STDERR to a file.
     * 
     * @return void
     */
    protected function redirectStdErr(): void
    {
        if (null === static::$stderr) {
            
            Output::warning(
                'No file specified for error ouput (STDERR). ' .
                'Please consider using Workerman::stdErrorPath(/path/to/error/log) to specify a file for the standard error ouput.'
            );
            Helpers::surpressErrorStream();
            return;
        }

        Helpers::moveErrorStream(static::$stderr);
    }


    /**
     * Set process name.
     * 
     * @return void
     */
    protected function setProcessName(): void
    {
        if (null === static::$name) {
            
            Output::warning([
                'No name specified, master process will be named "Workerman master".',
                'To change the process name use Workerman::setName(name)'
            ]);
        }

        Helpers::setProcessTitle((static::$name ?? 'Workerman') . ' master');
    }


    /**
     * Daemonize the process.
     * 
     * @return int
     */
    protected function daemonize(): int
    {
        Output::debug('Detaching process from terminal');

        $pid = pcntl_fork();

        switch ($pid) {
            case 0:
                
                return $this->makeSessionLeader();

            case -1:

                throw new WorkermanException(
                    'Fork failed while trying to detach process from terminal.'
                );
            
            default:
                
                return $this->verifyStartSuccess();
        }
    }


    /**
     * Make the current process master.
     * 
     * @return int
     */
    protected function makeSessionLeader(): int
    {
        if (-1 === posix_setsid()) {

            throw new WorkermanException(
                'Failed ro make the current process a session leader.'
            );
        }

        Output::daemonize();
        Helpers::surpressOuputStream();

        $pid = pcntl_fork();

        switch ($pid) {
            case 0:
                
                return $this->initAllSockets();

            case -1:

                throw new WorkermanException(
                    'Second fork failed while trying to detach process from terminal.'
                );
            
            default:
                
                return self::EXIT_SUCCESS;
        }
    }


    /**
     * Verify that the daemon started successfully.
     * 
     * @return int
     */
    protected function verifyStartSuccess(): int
    {
        Output::debug('Succesfully detached master, verifying workerman is started');

        $i = 0;

        while ($i <= 10) {

            $i++;
            
            usleep(500000);

            if (!$this->isWorkermanRunning()) continue;

            Output::success('Succesfully started workerman in detached mode');

            return self::EXIT_SUCCESS;
        }

        Output::error('Failed to start workerman in detached mode');

        return self::EXIT_FAILURE;
    }


    /**
     * Init all sockets.
     * 
     * @return int
     */
    protected function initAllSockets(): int
    {
        foreach (Socket::allSockets() as $socket) $this->initSocket($socket);

        return $this->forkAllSocketWorkers();
    }


    /**
     * Init socket.
     * 
     * @param Socket $socket
     * 
     * @return void
     */
    protected function initSocket(Socket $socket): void
    {
        if ($socket->reusePort()) return;

        Output::debug(sprintf(
            '(%s) Attempting to listen on: %s', $socket->getName(), $socket->getAddress()
        ));
        
        $socket->startSocket();
    }


    /**
     * Fork all socket workers.
     * 
     * @return int
     */
    protected function forkAllSocketWorkers(): int
    {
        foreach (Socket::allSockets() as $socket) $this->forkWorkers($socket);

        return $this->monitorWorkers();
    }


    /**
     * Fork workers for socket.
     * 
     * @param Socket $socket
     * 
     * @return void
     */
    protected function forkWorkers(Socket $socket): void
    {
        Output::debug(sprintf('(%s) Forking workers', $socket->getName()));

        $this->workerID++;

        foreach (range($this->workerID, $this->workerID + $socket->getWorkerCount()) as $id) {
            
            $worker = new Worker($socket, $id);

            $pid = pcntl_fork();

            switch ($pid) {
                case 0:
                    
                    $worker->start($this->daemon);
                    break;

                case -1:

                    throw new WorkermanException(
                        'Fork worker failed.'
                    );
                    // no break
                
                default:
                    
                    $this->registerWorker($worker, $pid);
                    break;
            }
        }

        $this->workerID += $socket->getWorkerCount();
    }


    /**
     * Register a new worker.
     * 
     * @param Worker $worker
     * @param int $pid
     * 
     * @return void
     */
    protected function registerWorker(Worker $worker, int $pid): void
    {
        $this->workers[$pid] = $worker;
    }


    /**
     * Monitor Workers.
     * 
     * @return int
     */
    protected function monitorWorkers(): int
    {
        Output::success('Succesfully started workerman');

        return (new Master($this->workers))->start($this->daemon);
    }


    /**
     * --------------------------------------------------------------------------
     * The methods below are for stopping/restarting workerman on unix systems.
     * --------------------------------------------------------------------------
     * 
     * Steps:
     *  - Send stop/reload signal to master process.
     *  - If graceful = true, create a file for each worker sich the worker will delete on shutdown
     *    this allows us to display progress bar
     *  - Verfy the process.pid file isn't there anymore
     */


    /**
     * Send stop signal to master process.
     *
     * @return int
     */
    protected function stopWorkers(): int
    {
        if (!$this->isWorkermanRunning()) {

            throw new WorkermanException(
                'Cannot stop workerman, workerman is not running.'
            );
        }

        $pid = Files::getFileContent(Workerman::PID_FILE);

        Output::debug(sprintf(
            'Sending stop signal (%s) to master process', $this->graceful ? 'SIGQUIT' : 'SIGINT'
        ));

        if (false === posix_kill($pid, $this->graceful ? SIGQUIT : SIGINT)) {

            throw new WorkermanException(
                'Failed to send status signal to master process.'
            );
        }

        if ($this->graceful) $this->verifyAllWorkersAreShutdown();

        return $this->verifyMasterIsShutdown();
    }


    /**
     * Send reload signal to master process.
     * 
     * @return int
     */
    protected function restartWorkers(): int
    {
        if (!$this->isWorkermanRunning()) {

            throw new WorkermanException(
                'Cannot restart workerman, workerman is not running.'
            );
        }

        $start = time();
        $pid = Files::getFileContent(Workerman::PID_FILE);

        Output::debug(sprintf(
            'Sending restart signal (%s) to master process', $this->graceful ? 'SIGUSR2' : 'SIGUSR1'
        ));

        if (false === posix_kill($pid, $this->graceful ? SIGUSR2 : SIGUSR1)) {

            throw new WorkermanException(
                'Failed to send status signal to master process.'
            );
        }

        if ($this->graceful) $this->verifyAllWorkersAreShutdown();

        return $this->verifyMasterConfirmReload($start);
    }


    /**
     * Verify that all the workers are shutdown.
     * 
     * @return void
     */
    protected function verifyAllWorkersAreShutdown(): void
    {
        $result = 0;
        $hashes = $this->getAllHashes(Workerman::SHUTDOWN_FILE);
        $workerCount = count($hashes);

        Output::debug('Waiting for all workers to shutdown..');

        /**
         * We create empty shutdown actions for workers to delete.
         */
        foreach ($hashes as $hash) Files::setFileContent($hash, 'shutdown_action');

        Output::progressBar($workerCount, true);

        while (1) {

            $new = false;

            /**
             * Workers are required to delete any hash before shutting down
             * The file doesn't exist = the worker is shutdown
             * This allows us to display a user friendly progress bar to keep the user updated
             */
            foreach ($hashes as $key => $hash) {
                
                if (Files::fileExists($hash)) continue;

                $new = $key;
                break;
            }

            // if all files still exist no workers have shutdown
            if (false === $new) {

                usleep(500000);
                continue;
            }

            Output::progressBar();
            unset($hashes[$new]);

            if (++$result === $workerCount) break;
        }
    }


    /**
     * Verify the master is shutdown.
     * 
     * @return int
     */
    protected function verifyMasterIsShutdown(): int
    {
        Output::debug('Waiting for master process to confirm the shutdown..');

        while (Files::fileExists(Workerman::PID_FILE)) {
            
            usleep(500000);
        }

        Output::success('Succesfully stopped workerman');

        return self::EXIT_SUCCESS;
    }


    /**
     * Verify that the master process confirmed the reload.
     * 
     * @param int $start
     * 
     * @return int
     */
    protected function verifyMasterConfirmReload(int $start): int
    {
        Output::debug('Waiting for master process to confirm the reload..');

        while (!Files::fileExists(Workerman::RESTART_FILE)) {
            
            usleep(500000);
        }

        $lastReloadTime = Files::getFileContent(Workerman::RESTART_FILE);
        Files::deleteFile(Workerman::RESTART_FILE);

        if ($start < $lastReloadTime) {

            Output::success('Succesfully restarted workerman');
            return self::EXIT_SUCCESS;
        }

        Output::error([
            'Unexpected error encountered while restarting workerman.',
            'The reload signal was sent after the master confirmed the reload.'
        ]);
        return self::EXIT_FAILURE;
    }


    /**
     * --------------------------------------------------------------------------
     * The methods below are for collecting the status on unix systems.
     * --------------------------------------------------------------------------
     * 
     * Steps:
     *  - Send status signal to master process
     *  - Read status file to get all worker hashes (i.e the path wo wich each process will write it's status data)
     *  - Return an array with all status data
     */

    
    /**
     * Collect the status of each worker.
     * 
     * @return array
     */
    protected function collectStatus(): array
    {
        if (!$this->isWorkermanRunning()) {

            throw new WorkermanException(
                'Cannot collect worker status, workerman is not running.'
            );
        }

        $pid = Files::getFileContent(Workerman::PID_FILE);

        if (false === posix_kill($pid, SIGIOT)) {

            throw new WorkermanException(
                'Failed to send status signal to master process.'
            );
        }

        return $this->returnStatusResults();
    }


    protected function returnStatusResults(): array
    {
        $result = [];
        $workersHash = $this->getAllHashes(Workerman::STATUS_FILE);
        $workerCount = count($workersHash);

        Output::progressBar($workerCount, true);

        while (1) {

            $new = false;

            // See if a new status file is available
            foreach ($workersHash as $hash) {
                
                if (!Files::fileExists($hash)) continue;

                $new = $hash;
                break;
            }

            // if none available we wait a little bit before retrying
            if (false === $new) {

                usleep(500000);
                continue;
            }

            Output::progressBar();

            $result[] = Files::getFileContent($new);
            Files::deleteFile($new);

            if (count($result) === $workerCount) break;
        }

        return $result;
    }


    /**
     * --------------------------------------------------------------------------
     * The methods below are made to start & configure the workerman environment.
     * --------------------------------------------------------------------------
     */


    /**
     * Set the master process name.
     * 
     * @param string $name
     * 
     * @return void
     */
    public static function setName(string $name): void
    {
        static::$name = $name;
    }


    /**
     * Set the path to STDERR.
     * 
     * @param string $path
     * 
     * @return void
     */
    public static function stdErrorPath(string $path): void
    {
        static::$stderr = $path;
    }


    /**
     * Add an ouput handler to workerman. The earlier the ouput handler is set the more info you get from the verbose mode (-v).
     * 
     * @param OutputInterface $handler
     * @param bool $afterStart If set to true the ouput interface will still be called after the start of the workerman daemon. Can become handy for a logger for exemple.
     * 
     * @return void
     */
    public static function addOutput(OutputInterface $handler, bool $afterStart = false): void
    {
        Output::addOutputHandler($handler, $afterStart);
    }


    /**
     * Create a new symfony console application.
     * 
     * The symfony console application will add an ouput handler to workerman and will
     * instantiate workerman and call the right method depending on the command.
     * 
     * @param string $path Application root path.
     * @param Application|null $app To only add the workerman commands to an exisitng symfony console application.
     * 
     * @return Application
     */
    public static function symfonyConsole(string $path, ?Application $app = null): Application
    {
        if (!$app) $app = new Application('Workerman revised (rexpl/workerman)', self::VERSION);

        Command::$path = $path;

        $app->add(new \Rexpl\Workerman\Commands\Start);
        $app->add(new \Rexpl\Workerman\Commands\Stop);
        $app->add(new \Rexpl\Workerman\Commands\Status);
        $app->add(new \Rexpl\Workerman\Commands\Reload);

        return $app;
    }


    /**
     * --------------------------------------------------------------------------
     * The methods below are shortcuts to create sockets.
     * --------------------------------------------------------------------------
     */


    /**
     * New worker.
     * 
     * @param int $transport
     * @param string $address
     * @param array $context
     * 
     * @return Socket
     */
    public static function newSocket(int $transport, string $address, array $context): Socket
    {
        return new Socket($transport, $address, $context);
    }


    /**
     * New unix socket.
     * 
     * @param string $address
     * @param array $context
     * 
     * @return Socket
     */
    public static function newUnixSocket(string $address, array $context = []): Socket
    {
        return self::newSocket(self::UNIX_TRANSPORT, $address, $context);
    }


    /**
     * New tcp server.
     * 
     * @param string $address
     * @param array $context
     * 
     * @return Socket
     */
    public static function newTcpServer(string $address, array $context = []): Socket
    {
        return self::newSocket(self::TCP_TRANSPORT, $address, $context);
    }


    /**
     * New udp server.
     * 
     * @param string $address
     * @param array $context
     * 
     * @return Socket
     */
    public static function newUdpServer(string $address, array $context = []): Socket
    {
        return self::newSocket(self::UPD_TRANSPORT, $address, $context);
    }


    /**
     * New ssl/tcp server.
     * 
     * @param string $address
     * @param array $context
     * 
     * @return Socket
     */
    public static function newSslServer(string $address, array $context = []): Socket
    {
        return self::newSocket(self::SSL_TRANSPORT, $address, $context);
    }


    /**
     * New tcp server with optional ssl.
     * 
     * @param string $address
     * @param array $context
     * @param bool $ssl
     * 
     * @return Socket
     */
    protected static function newPotentialSslServer(string $address, array $context, bool $ssl): Socket
    {
        return $ssl
            ? self::newSslServer($address, $context)
            : self::newTcpServer($address, $context);
    }


    /**
     * New http server. (over tcp)
     * 
     * @param string $address
     * @param array $context
     * @param bool $ssl
     * 
     * @return Socket
     */
    public static function newHttpServer(string $address, array $context = [], bool $ssl = false): Socket
    {
        return self::newPotentialSslServer($address, $context, $ssl)->setProtocol(self::HTTP_PROTOCOL);
    }


    /**
     * New websocket server. (over tcp)
     * 
     * @param string $address
     * @param array $context
     * @param bool $ssl
     * 
     * @return Socket
     */
    public static function newWebsocketServer(string $address, array $context = [], bool $ssl = false): Socket
    {
        return self::newPotentialSslServer($address, $context, $ssl)->setProtocol(self::WS_PROTOCOL);
    }
}