<?php

declare(strict_types=1);

namespace Lkrms;

/**
 * Functions for type wrangling
 *
 * @package Lkrms
 */
class Convert
{
    /**
     * If a variable isn't an array, make it the first element of one
     *
     * @param mixed $value The variable being checked.
     * @return array Either `$value` or `[$value]`.
     */
    public static function AnyToArray($value): array
    {
        return is_array($value) ? $value : [
            $value
        ];
    }

    /**
     * If a variable is 'falsey', make it null
     *
     * @param mixed $value The variable being checked.
     * @return mixed Either `$value` or `null`.
     */
    public static function EmptyToNull($value)
    {
        return ! $value ? null : $value;
    }

    /**
     * Return `'true'` if a boolean is true, `'false'` if it's not
     *
     * @param bool $value The variable being checked.
     * @return string Either `'true'` or `'false'`.
     */
    public static function BoolToString(bool $value)
    {
        return $value ? "true" : "false";
    }
}

