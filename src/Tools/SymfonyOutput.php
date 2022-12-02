<?php

declare(strict_types=1);

namespace Rexpl\Workerman\Tools;

use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Helper\ProgressBar;
use Throwable;
use Symfony\Component\Console\Style\SymfonyStyle;

class SymfonyOutput implements OutputInterface
{
    /**
     * Progress bar.
     * 
     * @var ProgressBar
     */
    protected ProgressBar $progressBar;


    /**
     * @param SymfonyStyle $output
     * @param Cursor $cursor
     * 
     * @return void
     */
    public function __construct(
        protected SymfonyStyle $output,
        protected Cursor $cursor
    ) {}


    /**
     * Output error.
     * 
     * @param string|array $message
     * 
     * @return void
     */
    public function error(string|array $message): void
    {
        $this->cursor->moveToColumn(0);
        $this->output->error($message);
    }


    /**
     * Output warning.
     * 
     * @param string|array $message
     * 
     * @return void
     */
    public function warning(string|array $message): void
    {
        $this->cursor->moveToColumn(0);
        $this->output->warning($message);
    }


    /**
     * Output info.
     * 
     * @param string|array $message
     * 
     * @return void
     */
    public function info(string|array $message): void
    {
        $this->cursor->moveToColumn(0);
        $this->output->info($message);
    }


    /**
     * Output debug. Debug output is automatically suppressed in daemon mode.
     * 
     * @param string|array $message
     * 
     * @return void
     */
    public function debug(string|array $message): void
    {
        if (!$this->output->isVerbose()) return;
        
        $this->cursor->moveToColumn(0);
        $this->output->writeln($message);
    }


    /**
     * Success output.
     * 
     * @param string|array $message
     * 
     * @return void
     */
    public function success(string|array $message): void
    {
        $this->cursor->moveToColumn(0);
        $this->output->success($message);
    }


    /**
     * Load bar.
     * 
     * If $start is false move the amount specified, if $start is true create new for this amount.
     * 
     * @param int $moveORamount 
     * @param bool $start
     * 
     * @return void
     */
    public function progressBar(int $moveORamount = 1, bool $start = false): void
    {
        if (!$start) {
           
            $this->progressBar->advance($moveORamount);

            if (
                $this->progressBar->getMaxSteps() === $this->progressBar->getProgress()
            ) $this->output->newLine(2);

            return;
        }

        $this->output->newLine(1);
        $this->progressBar = $this->output->createProgressBar($moveORamount);
        $this->progressBar->display();
    }


    /**
     * Output an exception.
     * 
     * @param Throwable $th
     * 
     * @return void
     */
    public function exception(Throwable $th): void
    {
        $this->cursor->moveToColumn(0);
        $this->output->block([
            sprintf('%s: %s', get_class($th), $th->getMessage()),
            sprintf('Thrown in %s:%s', $th->getFile(), $th->getLine()),
            $th->getTraceAsString()
        ]);
    }
}