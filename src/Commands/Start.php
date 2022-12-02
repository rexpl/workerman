<?php

declare(strict_types=1);

namespace Rexpl\Workerman\Commands;

use Rexpl\Workerman\Exceptions\WorkermanException;
use Rexpl\Workerman\Tools\Output;
use Rexpl\Workerman\Workerman;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

class Start extends Command
{
    /**
     * Configure the command.
     * 
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('start')
            ->setDescription('Start workerman')
            ->setHelp('Start the workerman application.')
            ->addOption('daemon', 'd', InputOption::VALUE_NONE, 'Start workerman in daemon mode (unix systems only).');
    }


    /**
     * Execute the command.
     * 
     * @return int
     */
    protected function executeCommand(): int
    {
        try {

            return (new Workerman(static::$path))->start($this->input->getOption('daemon'));

        } catch (WorkermanException $e) {

            $this->symfonyStyle->error($e->getMessage());

        } catch (Throwable $th) {

            Output::exception($th);
        }
        
        return self::FAILURE;
    }   
}