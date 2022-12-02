<?php

declare(strict_types=1);

namespace Rexpl\Workerman\Commands;

use Rexpl\Workerman\Exceptions\WorkermanException;
use Rexpl\Workerman\Tools\Output;
use Rexpl\Workerman\Workerman;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

class Reload extends Command
{
    /**
     * Configure the command.
     * 
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('restart')
            ->setDescription('Restart workerman')
            ->setHelp('Restart the workerman application. The graceful option will wait that all connections are closed before restarting.')
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

            return (new Workerman(static::$path))->restart($this->input->getOption('graceful'));

        } catch (WorkermanException $e) {

            $this->symfonyStyle->error($e->getMessage());

        } catch (Throwable $th) {

            Output::exception($th);
        }
        
        return self::FAILURE;
    }   
}