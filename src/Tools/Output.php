<?php

declare(strict_types=1);

namespace Rexpl\Workerman\Tools;

use Throwable;

class Output
{
    /**
     * All output handlers.
     * 
     * @var array<OutputInterface>
     */
    protected static array $outputInterfaces = [];


    /**
     * All output handlers for after workerman started.
     * 
     * @var array<OutputInterface>
     */
    protected static array $afterStartHandler = [];


    /**
     * If is daemon.
     * 
     * @var bool
     */
    protected static bool $daemon = false;


    /**
     * Output error.
     * 
     * @param string|array $message
     * 
     * @return void
     */
    public static function error(string|array $message): void
    {
        static::output('error', $message);
    }


    /**
     * Output warning.
     * 
     * @param string|array $message
     * 
     * @return void
     */
    public static function warning(string|array $message): void
    {
        static::output('warning', $message);
    }


    /**
     * Output info.
     * 
     * @param string|array $message
     * 
     * @return void
     */
    public static function info(string|array $message): void
    {
        static::output('info', $message);
    }


    /**
     * Output debug. Debug output is automatically suppressed in daemon mode.
     * 
     * @param string|array $message
     * 
     * @return void
     */
    public static function debug(string|array $message): void
    {
        if (static::$daemon) return;

        static::output('debug', $message);
    }


    /**
     * Success output.
     * 
     * @param string|array $message
     * 
     * @return void
     */
    public static function success(string|array $message): void
    {
        static::output('success', $message);
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
    public static function progressBar(int $moveORamount = 1, bool $start = false): void
    {
        foreach (static::$outputInterfaces as $handler) $handler->progressBar($moveORamount, $start);
    }


    /**
     * Output an exception.
     * 
     * @param Throwable $throwable
     * 
     * @return void
     */
    public static function exception(Throwable $throwable): void
    {
        static::output('exception', $throwable);
    }


    /**
     * Send output to output handlers.
     * 
     * @param string $method
     * @param string|array|Throwable $data
     * 
     * @return void
     */
    protected static function output(string $method, string|array|Throwable $data): void
    {
        foreach (static::$outputInterfaces as $handler) $handler->{$method}($data);
    }


    /**
     * Add output handler. If after start is true, the handler won't be removed after workerman has started.
     * 
     * @param OutputInterface $handler
     * @param bool $afterStart
     * 
     * @return void
     */
    public static function addOutputHandler(OutputInterface $handler, bool $afterStart): void
    {
        static::$outputInterfaces[] = $handler;
        if ($afterStart) static::$afterStartHandler[] = $handler;
    }


    /**
     * Signals that workerman has started in daemon mode.
     * 
     * @return void
     */
    public static function daemonize(): void
    {
        static::$outputInterfaces = static::$afterStartHandler;
        static::$daemon = true;
    }
}