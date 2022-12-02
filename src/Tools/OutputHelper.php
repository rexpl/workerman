<?php

declare(strict_types=1);

namespace Rexpl\Workerman\Tools;

trait OutputHelper
{
    /**
     * Prefix for all ouput.
     * 
     * @var string
     */
    protected string $prefix = '';


    /**
     * Output error.
     * 
     * @param string $message
     * @param array $arguments
     * 
     * @return void
     */
    public function error(string $message, array $arguments = []): void
    {
        Output::error(sprintf(
            $this->prefix . $message, ...$arguments
        ));
    }


    /**
     * Output debug. Debug output is automatically suppressed in daemon mode.
     * 
     * @param string $message
     * @param array $arguments
     * 
     * @return void
     */
    public function debug(string $message, array $arguments = []): void
    {
        if ($this->daemon) return;

        Output::debug(sprintf(
            $this->prefix . $message, ...$arguments
        ));
    }
}