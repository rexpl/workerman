<?php

declare(strict_types=1);

namespace Rexpl\Workerman\Commands;

use Rexpl\Workerman\Exceptions\WorkermanException;
use Rexpl\Workerman\Tools\Output;
use Rexpl\Workerman\Workerman;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

class Stop extends Command
{
    /**
     * Configure the command.
     * 
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('stop')
            ->setDescription('Stop workerman')
            ->setHelp('Stop the workerman application. The graceful option will wait that all connections are closed before stoping.')
            ->addOption('graceful', 'g', InputOption::VALUE_NONE, 'Stop workerman gracefully.');
    }


    /**
     * Execute the command.
     * 
     * @return int
     */
    protected function executeCommand(): int
    {
        try {

            return (new Workerman(static::$path))->stop($this->input->getOption('graceful'));

        } catch (WorkermanException $e) {

            $this->symfonyStyle->error($e->getMessage());

        } catch (Throwable $th) {

            Output::exception($th);
        }
        
        return self::FAILURE;
    }   
}