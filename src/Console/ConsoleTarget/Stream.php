<?php

declare(strict_types=1);

namespace Lkrms\Console\ConsoleTarget;

use DateTime;
use DateTimeZone;
use Lkrms\Console\ConsoleLevel;
use RuntimeException;

/**
 * Sends `Console` output to a stream (e.g. a file or TTY)
 *
 * @package Lkrms
 */
class Stream extends \Lkrms\Console\ConsoleTarget
{
    private $Stream;

    /**
     * @var array
     */
    private $Levels;

    /**
     * @var bool
     */
    private $AddTimestamp;

    /**
     * @var string
     */
    private $Timestamp = "[d M H:i:s.uO] ";

    /**
     * @var DateTimeZone
     */
    private $Timezone;

    /**
     * @var bool
     */
    private $AddColour;

    /**
     * @var string
     */
    private $Path;

    /**
     * Use an open stream as a console output target
     *
     * @param resource      $stream
     * @param array         $levels
     * @param bool|null     $addColour      If `null`, colour will not be added unless `$stream` is a TTY
     * @param bool|null     $addTimestamp   If `null`, timestamps will be added unless `$stream` is a TTY
     * @param string|null   $timestamp      Default: `[d M H:i:s.uO] `
     * @param string|null   $timezone       Default: as per `date_default_timezone_set` or INI setting `date.timezone`
     */
    public function __construct($stream, array $levels = [
        ConsoleLevel::EMERGENCY,
        ConsoleLevel::ALERT,
        ConsoleLevel::CRITICAL,
        ConsoleLevel::ERROR,
        ConsoleLevel::WARNING,
        ConsoleLevel::NOTICE,
        ConsoleLevel::INFO,
        ConsoleLevel::DEBUG
    ], bool $addColour = null, bool $addTimestamp = null, string $timestamp = null, string $timezone = null)
    {
        $isTty = stream_isatty($stream);
        stream_set_write_buffer($stream, 0);

        //
        $this->Stream       = $stream;
        $this->Levels       = $levels;
        $this->AddColour    = ! is_null($addColour) ? $addColour : $isTty;
        $this->AddTimestamp = ! is_null($addTimestamp) ? $addTimestamp : ! $isTty;

        if ( ! is_null($timestamp))
        {
            $this->Timestamp = $timestamp;
        }

        if ( ! is_null($timezone))
        {
            $this->Timezone = new DateTimeZone($timezone);
        }
    }

    public static function FromPath($path, array $levels = [
        ConsoleLevel::EMERGENCY,
        ConsoleLevel::ALERT,
        ConsoleLevel::CRITICAL,
        ConsoleLevel::ERROR,
        ConsoleLevel::WARNING,
        ConsoleLevel::NOTICE,
        ConsoleLevel::INFO,
        ConsoleLevel::DEBUG
    ], bool $addColour = null, bool $addTimestamp = null, string $timestamp = null, string $timezone = null): Stream
    {
        $stream = fopen($path, "a");

        if ($stream === false)
        {
            throw new RuntimeException("Could not open $path");
        }

        $_this       = new Stream($stream, $levels, $addColour, $addTimestamp, $timestamp, $timezone);
        $_this->Path = $path;

        return $_this;
    }

    protected function WriteToTarget(int $level, string $message, array $context)
    {
        if (in_array($level, $this->Levels))
        {
            if ($this->AddTimestamp)
            {
                $now     = (new DateTime("now", $this->Timezone))->format($this->Timestamp);
                $message = $now . str_replace("\n", "\n" . str_repeat(" ", strlen($now)), $message);
            }

            fwrite($this->Stream, $message . "\n");
        }
    }

    public function AddColour(): bool
    {
        return $this->AddColour;
    }

    public function Path(): string
    {
        return $this->Path;
    }
}
