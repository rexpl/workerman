<?php

declare(strict_types=1);

namespace Rexpl\Workerman;

use Rexpl\Workerman\Exceptions\WorkermanException;
use Rexpl\Workerman\Tools\Files;
use Rexpl\Workerman\Tools\Helpers;
use Rexpl\Workerman\Tools\Output;
use Rexpl\Workerman\Tools\OutputHelper;

class Master
{
    use OutputHelper;

    /**
     * Hash of the master instance.
     * 
     * @var string
     */
    protected readonly string $hash;


    /**
     * Master start time.
     * 
     * @var int
     */
    protected int $startTime;


    /**
     * Should continue running.
     * 
     * @var bool
     */
    protected bool $run = true;

    
    /**
     * Shutdown disabled.
     * 
     * @var bool
     */
    protected bool $disabledShutdown = false;


    /**
     * Should a worker be revived.
     * 
     * @var bool
     */
    protected bool $expectDeadWorker = false;


    /**
     * Callable to execute when a dead worker is expected.
     * 
     * @var string
     */
    protected string $expectedDeadWorkerMethod;


    /**
     * Is a daemon process.
     * 
     * @var bool
     */
    protected bool $daemon;


    /**
     * All workers.
     * 
     * @var array<int,Worker>
     */
    protected array $workers;


    /**
     * All workers wich haven't stopped yet.
     * 
     * This var is a copy of $this->workers taken when initializing a graceful restart
     * action, every worker left in this var is not yet shutdown.
     * 
     * @var array<int,Worker>
     */
    protected array $workersStopAction;


    /**
     * @param array $workers
     * 
     * @return void
     */
    public function __construct(array $workers)
    {
        $this->workers = $workers;
        $this->prefix = 'Master: ';
        $this->hash = spl_object_hash($this);
    }


    /**
     * @param bool $daemon
     * 
     * @return int
     */
    public function start(bool $daemon): int
    {
        $this->daemon = $daemon;
        $this->startTime = time();

        register_shutdown_function([$this, 'shutdown']);
        
        Files::setFileContent(Workerman::PID_FILE, posix_getpid());
        
        Helpers::installSignalHandler($this, 'signal');

        $this->monitor();

        return $this->exit();
    }


    /**
     * Monitor all workers.
     * 
     * Endless loop wich detect signals and monitor all child processes.
     * 
     * @return void
     */
    protected function monitor(): void
    {
        while ($this->run) {

            pcntl_signal_dispatch();

            $status = 0;
            $pid = pcntl_wait($status, WUNTRACED);

            pcntl_signal_dispatch();

            if ($pid > 0) $this->reportDeadWorker($pid, $status);
        }
    }


    /**
     * Report a dead worker.
     * 
     * Decides what to do with the dead worker.
     * 
     * @param int $pid Process ID of the dead worker.
     * @param int $status Exit status of the worker.
     * 
     * @return void
     */
    protected function reportDeadWorker(int $pid, int $status): void
    {
        // We did not expect this = not shuting down or restarting.
        if (false === $this->expectDeadWorker) {

            $this->handleUnexpectedDeadWorker($pid, $status, true);
            return;
        }

        // The worker exited like planned.
        $this->{$this->expectedDeadWorkerMethod}($pid, $status);
    }


    /**
     * Handle an unexpected dead worker.
     * 
     * This method report the error to the ouput handlers and optionnaly revives the worker.
     * 
     * @param int $pid Process ID of the dead worker.
     * @param int $status Exit status of the worker.
     * @param bool $revive True if the worker should be revived, False otherwise.
     * 
     * @return void
     */
    protected function handleUnexpectedDeadWorker(int $pid, int $status, bool $revive): void
    {
        $worker = $this->workers[$pid];
        unset($this->workers[$pid]);

        $worker->unexpectedCrash();

        $this->error('Worker %d unexpectedly exited with status %d', [$worker->id, $status]);

        if ($revive) $this->reviveWorker($worker);
    }


    /**
     * Reviving a worker.
     * 
     * This can happen on unexpected exit and on reload.
     * 
     * @param Worker $worker The worker wich needs to be forked again.
     * 
     * @return void
     */
    protected function reviveWorker(Worker $worker): void
    {
        $this->debug('Reviving worker %d', [$worker->id]);

        $pid = pcntl_fork();

        switch ($pid) {
            case 0:
                
                $this->removeAllMasterActions();
                $worker->start($this->daemon);
                break;

            case -1:

                throw new WorkermanException(
                    'Fork worker failed.'
                );
            
            default:
                
                $this->workers[$pid] = $worker;
                break;
        }
    }


    /**
     * Remove all set actions for master.
     * 
     * Removes the shutdown handler, the signal handler and terminates the master loop.
     * This is necessary otherwise the newly forked worker will inherit all those things.
     * 
     * @return void
     */
    protected function removeAllMasterActions(): void
    {
        $this->run = false;
        $this->disabledShutdown = true;

        Helpers::removeSignalHandler();
    }


    /**
     * Handles incommming signals.
     *
     * @param int $signal Incomming signal.
     * 
     * @return void
     */
    public function signal(int $signal): void
    {
        $this->debug('Received signal %d', [$signal]);

        switch ($signal) {
            case SIGINT:
            case SIGTERM:
            case SIGHUP:
            case SIGTSTP:
                
                $this->stop(false);
                break;

            case SIGQUIT:
            
                $this->stop(true);
                break;
            
            case SIGUSR1:
        
                $this->restart(false);
                break;
            
            case SIGUSR2:
        
                $this->restart(true);
                break;

            case SIGIOT:
                
                $this->status();
                break;
        }
    }


    /**
     * Dispatch a signal.
     * 
     * @param int $signal The signal to dispatch to all workers.
     * 
     * @return void
     */
    protected function dispatch(int $signal): void
    {
        $this->debug('Dispatch signal %d', [$signal]);

        foreach ($this->workers as $pid => $worker) {
            
            posix_kill($pid, $signal);
        }
    }


    /**
     * Stop all workers.
     * 
     * @param bool $graceful
     * @param bool $restart
     * 
     * @return void
     */
    protected function stop(bool $graceful, $restart = false): void
    {
        $this->expectDeadWorker = true;

        if (!$restart) $this->expectedDeadWorkerMethod = 'workerSuccesfullyStoped';

        if (!$graceful) {

            $this->dispatch(SIGINT);
            return;
        }

        $this->makeHashFile(Workerman::SHUTDOWN_FILE);

        /**
         * We wait half a second to give time for the calling process to
         * create files do be deleted by the workers.
         */
        usleep(500000);
        $this->dispatch(SIGQUIT);

        $this->workersStopAction = $this->workers;
    }


    /**
     * Register a dead worker on stop.
     * 
     * If this is the last worker the master process will exit.
     * 
     * @param int $pid The process ID of the dead worker.
     * @param int $status Worker exit status.
     * 
     * @return void
     */
    protected function workerSuccesfullyStoped(int $pid, int $status): void
    {
        $this->verifyWorkerShutdown($pid, $status);

        if ($this->workers !== []) return;

        $this->run = false;
        $this->disabledShutdown = true;
    }


    /**
     * verify the worker shutdown correctly.
     * 
     * @param int $pid The process ID of the dead worker.
     * @param int $status Worker exit status.
     * 
     * @return void
     */
    protected function verifyWorkerShutdown(int $pid, int $status): void
    {
        $id = $this->workers[$pid]->id;

        // It is expected for workers to exit but not with this status.
        if ($status !== 0) {
            
            $this->handleUnexpectedDeadWorker($pid, $status, false);
        }
        else {
            
            $this->debug('Succesfully stoped worker %d', [$id]);
            unset($this->workers[$pid]);
        }
    }


    /**
     * Reaload all workers.
     * 
     * @param bool $graceful
     * 
     * @return void
     */
    protected function restart(bool $graceful): void
    {
        $this->workersStopAction = $this->workers;

        $this->expectedDeadWorkerMethod = 'workerReadyForRestart';
        $this->stop($graceful, true);
    }


    /**
     * Register a dead worker ready for restart.
     * 
     * @param int $pid The process ID of the dead worker.
     * @param int $status Worker exit status.
     * 
     * @return void
     */
    protected function workerReadyForRestart(int $pid, int $status): void
    {
        $this->verifyWorkerShutdown($pid, $status);

        $worker = $this->workersStopAction[$pid];
        unset($this->workersStopAction[$pid]);

        $this->reviveWorker($worker);

        if ($this->workersStopAction !== []) return;

        Files::setFileContent(Workerman::RESTART_FILE, time());
        Output::info('Succesfully restarted workerman');
    }


    /**
     * Dispatch status signal.
     * 
     * @return void
     */
    protected function status(): void
    {
        $this->writeMasterStatus();
        $this->makeHashFile(Workerman::STATUS_FILE, $this->hash);
        $this->dispatch(SIGIOT);
    }


    /**
     * Write master status to status file.
     * 
     * @return void
     */
    protected function writeMasterStatus(): void
    {
        Files::setFileContent($this->hash, [
            'id' => 'M',
            'listen' => 'N/A',
            'name' => 'N/A',
            'memory' => round(memory_get_usage() / (1024 * 1024), 2) . "M",
            'peak_memory' => round(memory_get_peak_usage() / (1024 * 1024), 2) . "M",
            'start_time' =>  '(0) ' . Helpers::uptime($this->startTime),
            'connections' => 'N/A',
            'timers' => 'N/A',
        ]);
    }


    /**
     * Make hash file.
     * 
     * @param string $file
     * @param string|null $masterHash
     * 
     * @return void
     */
    protected function makeHashFile(string $file, ?string $masterHash = null): void
    {
        $hashCollection = null === $masterHash ? [] : [$masterHash];

        foreach ($this->workers as $worker) {
            
            $hashCollection[] = $worker->hash;
        }

        Files::setFileContent($file, $hashCollection);
    }


    /**
     * Shutdown handler.
     * 
     * @return void
     */
    protected function shutdown(): void
    {
        if ($this->disabledShutdown) return;

        $this->error('Unexpected shutdown, killing all worker processes');

        $this->dispatch(SIGKILL);
        $this->cleanOnExit();
    }


    /**
     * Remove all files possibly created by workerman.
     *
     * @return void
     */
    protected function cleanOnExit(): void
    {
        Files::deleteFile(Workerman::PID_FILE);
        Files::deleteFile(Workerman::STATUS_FILE);
        Files::deleteFile(Workerman::SHUTDOWN_FILE);
        Files::deleteFile(Workerman::RESTART_FILE);

        Files::deleteFile($this->hash);
    }


    /**
     * Exit the process.
     * 
     * @return int
     */
    protected function exit(): int
    {
        $this->disabledShutdown = true;
        $this->cleanOnExit();

        Output::success('Succesfully stopped workerman');
        return Workerman::EXIT_SUCCESS;
    }
}