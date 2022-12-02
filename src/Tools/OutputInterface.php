<?php

declare(strict_types=1);

namespace Rexpl\Workerman\Tools;

use Throwable;

interface OutputInterface
{
    /**
     * Output error.
     * 
     * @param string|array $message
     * 
     * @return void
     */
    public function error(string|array $message): void;


    /**
     * Output warning.
     * 
     * @param string|array $message
     * 
     * @return void
     */
    public function warning(string|array $message): void;


    /**
     * Output info.
     * 
     * @param string|array $message
     * 
     * @return void
     */
    public function info(string|array $message): void;


    /**
     * Success output.
     * 
     * @param string|array $message
     * 
     * @return void
     */
    public function success(string|array $message): void;


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
    public function progressBar(int $moveORamount = 1, bool $start = false): void;


    /**
     * Output debug. Debug output is automatically suppressed in daemon mode.
     * 
     * @param string|array $message
     * 
     * @return void
     */
    public function debug(string|array $message): void;


    /**
     * Output an exception.
     * 
     * @param Throwable $throwable
     * 
     * @return void
     */
    public function exception(Throwable $throwable): void;
}