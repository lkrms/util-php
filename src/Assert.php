<?php

declare(strict_types=1);

namespace Lkrms;

use RuntimeException;
use UnexpectedValueException;

/**
 * Throw an exception if a condition isn't met
 *
 * @package Lkrms
 */
class Assert
{
    private static function throwUnexpectedValue(string $message, ?string $name): void
    {
        $message = str_replace("{}", is_null($name) ? "value" : "'$name'", $message);
        throw new UnexpectedValueException($message);
    }

    /**
     *
     * @param mixed $value
     * @param string|null $name
     * @return void
     * @throws UnexpectedValueException
     */
    public static function notNull($value, string $name = null): void
    {
        if (is_null($value))
        {
            self::throwUnexpectedValue("{} cannot be null", $name);
        }
    }

    /**
     *
     * @param mixed $value
     * @param string|null $name
     * @return void
     * @throws UnexpectedValueException
     */
    public static function notEmpty($value, string $name = null): void
    {
        if (empty($value))
        {
            self::throwUnexpectedValue("{} cannot be empty", $name);
        }
    }

    /**
     *
     * @param null|string $value
     * @param string $pattern
     * @param string|null $name
     * @return void
     * @throws UnexpectedValueException
     */
    public static function pregMatch(?string $value, string $pattern, string $name = null): void
    {
        if (is_null($value) || !preg_match($pattern, $value))
        {
            self::throwUnexpectedValue("{} must match pattern '$pattern'", $name);
        }
    }

    /**
     *
     * @param mixed $value
     * @param int $length
     * @param string|null $name
     * @return void
     * @throws UnexpectedValueException
     */
    public static function exactStringLength($value, int $length, string $name = null): void
    {
        if (!is_string($value) || strlen($value) != $length)
        {
            self::throwUnexpectedValue("{} must be a string with length $length", $name);
        }
    }

    /**
     *
     * @param mixed $value
     * @param int $minLength
     * @param string|null $name
     * @return void
     * @throws UnexpectedValueException
     */
    public static function minimumStringLength($value, int $minLength, string $name = null): void
    {
        if (!is_string($value) || strlen($value) < $minLength)
        {
            self::throwUnexpectedValue("{} must be a string with length at least $minLength", $name);
        }
    }

    /**
     *
     * @param mixed $value
     * @param string|null $name
     * @return void
     * @throws UnexpectedValueException
     */
    public static function isArray($value, string $name = null): void
    {
        if (!is_array($value))
        {
            self::throwUnexpectedValue("{} must be an array", $name);
        }
    }

    /**
     *
     * @param mixed $value
     * @param string|null $name
     * @return void
     * @throws UnexpectedValueException
     */
    public static function isIntArray($value, string $name = null): void
    {
        if (!Test::isIndexedArray($value) ||
            count(array_filter($value,
                function ($v)
                {
                    return !is_int($v);
                })))
        {
            self::throwUnexpectedValue("{} must be an integer array", $name);
        }
    }

    /**
     *
     * @return void
     * @throws RuntimeException
     */
    public static function sapiIsCli(): void
    {
        if (PHP_SAPI != "cli")
        {
            throw new RuntimeException("CLI required");
        }
    }

    /**
     *
     * @return void
     * @throws RuntimeException
     */
    public static function localeIsUtf8(): void
    {
        if (($locale = setlocale(LC_CTYPE, "")) === false)
        {
            throw new RuntimeException("Invalid locale (check LANG and LC_*)");
        }

        if (!preg_match('/\.UTF-?8$/i', $locale))
        {
            throw new RuntimeException("Locale '$locale' does not support UTF-8");
        }
    }
}

