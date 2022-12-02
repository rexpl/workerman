<?php

declare(strict_types=1);

namespace Rexpl\Workerman\Commands;

use Rexpl\Workerman\Exceptions\WorkermanException;
use Rexpl\Workerman\Tools\Output;
use Rexpl\Workerman\Workerman;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

class Status extends Command
{
    /**
     * Configure the command.
     * 
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('status')
            ->setDescription('Workerman status')
            ->setHelp('Display the status of the workerman application.')
            ->addOption('info', 'i', InputOption::VALUE_NONE, 'Display information about each status field.');
    }


    /**
     * Execute the command.
     * 
     * @return int
     */
    protected function executeCommand(): int
    {
        if ($this->input->getOption('info')) return $this->showInfo();

        return $this->showStatus();
    }


    /**
     * Show info.
     * 
     * @return int
     */
    protected function showInfo(): int
    {
        $this->symfonyStyle->createTable()
            ->setStyle('box')
            ->setHorizontal(true)
            ->setHeaders(
                ['ID', 'Listening', 'Name', 'Memory', 'Peak memory', 'Running', 'Connections', 'Timers']
            )
            ->addRows([[
                'Worker unique identifier, M stands for master.',
                'Local address on wich the worker is listening.',
                'Name of the worker.',
                'Memory used by the process.',
                'Peak memory used by the process.',
                'How many times the process unexpectedly exited, and since how long the process is running.',
                'Active connections/Total connections.',
                'How many timers are running in the worker.'
            ]])
            ->render();

        return self::SUCCESS;
    }


    /**
     * Shos status.
     * 
     * @return int
     */
    protected function showStatus(): int
    {
        try {
            
            $status = (new Workerman(static::$path))->status();

            $this->symfonyStyle->createTable()
                ->setStyle('box')
                ->setHeaders(
                    ['ID', 'Listening', 'Name', 'Memory', 'Peak memory', 'Running', 'Connections', 'Timers']
                )
                ->addRows($status)
                ->render();
            

            return self::SUCCESS;

        } catch (WorkermanException $e) {

            $this->symfonyStyle->error($e->getMessage());

        } catch (Throwable $th) {

            Output::exception($th);
        }
        
        return self::FAILURE;
    }
}