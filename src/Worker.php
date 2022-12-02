<?php

declare(strict_types=1);

namespace Rexpl\Workerman;

use Rexpl\Workerman\Tools\Files;
use Rexpl\Workerman\Tools\Helpers;
use Rexpl\Workerman\Tools\OutputHelper;
use Workerman\Worker as WorkermanWorker;
use Workerman\Connection\TcpConnection;
use Workerman\Events\EventInterface;
use Workerman\Timer;

class Worker
{
    use OutputHelper;


    /**
     * Worker start time.
     * 
     * @var int
     */
    protected int $startTime;


    /**
     * Start count.
     * 
     * @var int
     */
    protected int $restartCount = 0;


    /**
     * Socket.
     * 
     * @var Socket
     */
    protected Socket $socket;


    /**
     * Worker ID.
     * 
     * @var int
     */
    public readonly int $id;


    /**
     * Worker unique hash.
     * 
     * @var string
     */
    public readonly string $hash;


    /**
     * Event loop.
     * 
     * @var EventInterface
     */
    protected EventInterface $eventLoop;


    /**
     * All connections.
     * 
     * @var array<int,TcpConnection>
     */
    public array $connections = [];


    /**
     * Total connections.
     * 
     * @var int
     */
    protected int $connectionCount = 0;


    /**
     * Is a daemon process.
     * 
     * @var bool
     */
    protected bool $daemon;


    /**
     * @param Socket $socket
     * @param int $id
     * 
     * @return void
     */
    public function __construct(Socket $socket, int $id)
    {
        $this->socket = $socket;
        $this->id = $id;
        $this->hash = spl_object_hash($this);
        $this->eventLoop = $socket->getEventLoop();

        $this->prefix = sprintf('Worker (%d): ', $id);
    }


    /**
     * Notify the worker that it crashed unexpectedly. This is for stats.
     * 
     * @return void
     */
    public function unexpectedCrash(): void
    {
        $this->restartCount++;
    }


    /**
     * Start the worker.
     * 
     * @param bool $daemon
     * 
     * @return void
     */
    public function start(bool $daemon): void
    {
        $this->startTime = time();
        $this->daemon = $daemon;

        $this->debug('listen: %s name: %s', [$this->socket->getAddress(), $this->socket->getName()]);

        register_shutdown_function([$this, 'shutdown']);

        Timer::delAll();
        
        if ($this->socket->reusePort()) $this->socket->startSocket();

        $this->socket->destroyCompetition();

        Helpers::setProcessTitle(sprintf(
            '%s worker (%d)', $this->socket->getName(), $this->id
        ));
        Helpers::eventSignalHandler($this->eventLoop, $this, 'signalHandler');

        /**
         * /!\ Temporary solution /!\
         * 
         * This is currently needed as the official workerman library 
         * expect to comunicate with the worker in this way.
         * 
         * Potential solutions:
         *  - Rewrite connection and event loops
         *  - Extend classes wich need it
         */
        WorkermanWorker::$globalEvent = $this->eventLoop;

        Timer::init($this->eventLoop);

        $this->socket->resumeAccept($this);
        $this->eventLoop->loop();
    }


    /**
     * Accept a connection.
     * 
     * @param resource $socket
     * 
     * @return void
     */
    public function acceptConnection($socket)
    {
        $socket = @stream_socket_accept($socket, 0, $remoteAddress);

        // Thundering herd.
        if (false === $socket) return;

        $this->connectionCount++;
        
        $connection = new TcpConnection($socket, $remoteAddress);

        $this->connections[$connection->id] = $connection;

        $connection->worker = $this;
        $connection->protocol = $this->socket->getProtocol();
        $connection->transport = Workerman::BUILT_IN_TRANSPORT[$this->socket->getTransport()];
        
        $this->socket->feedConnection($connection);
        
        call_user_func($connection->onConnect, $connection);
    }


    /**
     * Singal handler.
     * 
     * @param int $signal
     * 
     * @return void
     */
    public function signalHandler(int $signal): void
    {
        $this->debug('Received signal %d', [$signal]);
        
        switch ($signal) {
            case SIGINT:
            case SIGTERM:
            case SIGHUP:
            case SIGTSTP:
            case SIGUSR1:
                
                $this->hardStop();
                break;

            case SIGQUIT:
            case SIGUSR2:
                
                $this->gracefullStop();
                break;

            case SIGIOT:

                $this->writeStatus();
                break;
        }
    }


    /**
     * Stop the worker.
     * 
     * @return void
     */
    protected function stopSocket(): void
    {
        if ($this->socket->isSocketStarted()) $this->socket->destroySocket();
        
        $this->debug('Socket destroyed succesfully');
    }


    /**
     * Gracefull stop.
     * 
     * @return void
     */
    protected function gracefullStop(): void
    {
        if (
            $this->socket->isSocketStarted()
            && $this->socket->isAccepting()
        ) $this->socket->pauseAccept();

        if ($this->connections === []) {

            $this->stopSocket();
            $this->debug('Worker exit succesfully');
            exit(Workerman::EXIT_SUCCESS);
        }

        $this->debug('Not all connections have been closed retrying in 1 sec');

        Timer::add(1, [$this, 'gracefullStop'], [], false);
    }


    /**
     * Hard stop.
     * 
     * @return void
     */
    protected function hardStop(): void
    {
        $this->stopSocket();

        foreach ($this->connections as $co) $co->close();

        $this->debug('Worker exit succesfully');

        exit(Workerman::EXIT_SUCCESS);
    }


    /**
     * Write status to status file.
     * 
     * @return void
     */
    protected function writeStatus(): void
    {
        Files::setFileContent($this->hash, [
            'id' => $this->id,
            'listen' => $this->socket->getAddress(),
            'name' => $this->socket->getName(),
            'memory' => round(memory_get_usage() / (1024 * 1024), 2) . "M",
            'peak_memory' => round(memory_get_peak_usage() / (1024 * 1024), 2) . "M",
            'start_time' =>  '(' . $this->restartCount . ') ' . Helpers::uptime($this->startTime),
            'connections' => count($this->connections) . '/' . $this->connectionCount,
            'timers' => $this->eventLoop->getTimerCount(),
        ]);
    }


    /**
     * Shutdown handler.
     * 
     * @return void
     */
    public function shutdown(): void
    {
        Files::deleteFile($this->hash);
    }
}