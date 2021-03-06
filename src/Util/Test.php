<?php

declare(strict_types=1);

namespace Lkrms\Util;

use Lkrms\Concept\Utility;

/**
 * Perform a true/false test on a value
 *
 */
final class Test extends Utility
{
    /**
     * Return true for integers and integer strings
     *
     * @param mixed $value
     * @return bool
     */
    public static function isIntValue($value): bool
    {
        return (is_int($value) ||
            (is_string($value) && preg_match('/^[0-9]+$/', $value)));
    }

    /**
     * Check if a flag is set in a bitmask
     *
     * If `$mask` is not set, returns `true` if bits set in `$flag` are also set
     * in `$value`.
     *
     * If `$mask` is set, returns `true` if masked bits in `$flag` and `$value`
     * have the same state.
     *
     * @param int $value The bitmask being checked.
     * @param int $flag The value of the flag.
     * @param null|int $mask The mask being applied to `$value` and `$flag`.
     * @return bool
     */
    public static function isFlagSet(int $value, int $flag, ?int $mask = null): bool
    {
        return ($value & ($mask ?? $flag)) === $flag;
    }

    /**
     * Check if only one flag is set in a bitmask
     *
     * Returns `true` if exactly one of the masked bits in `$value` is set.
     *
     * @param int $value The bitmask being checked.
     * @param int $mask The mask being applied to `$value`.
     * @return bool
     */
    public static function isOneFlagSet(int $value, int $mask): bool
    {
        return substr_count(decbin($value & $mask), "1") === 1;
    }

    /**
     * Return true for arrays with consecutive integer keys numbered from 0
     *
     * @param mixed $value
     * @param bool $allowEmpty
     * @return bool
     */
    public static function isListArray($value, bool $allowEmpty = false): bool
    {
        return is_array($value) &&
            (empty($value) ? $allowEmpty : array_keys($value) === range(0, count($value) - 1));
    }

    /**
     * Return true for arrays with one or more string keys
     *
     * @param mixed $value
     * @param bool $allowEmpty
     * @return bool
     */
    public static function isAssociativeArray($value, bool $allowEmpty = false): bool
    {
        if (is_array($value))
        {
            if (empty($value))
            {
                return $allowEmpty;
            }

            foreach (array_keys($value) as $key)
            {
                if (is_string($key))
                {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Return true for arrays with no string keys
     *
     * @param mixed $value
     * @param bool $allowEmpty
     * @return bool
     */
    public static function isIndexedArray($value, bool $allowEmpty = false): bool
    {
        return is_array($value) &&
            (empty($value) ? $allowEmpty : !self::isAssociativeArray($value));
    }

    /**
     * Return true for absolute paths
     *
     * @param string $path
     * @return bool
     */
    public static function isAbsolutePath(string $path): bool
    {
        return (bool)preg_match('/^(\\/|\\\\|[a-z]:\\\\)/i', $path);
    }

    /**
     * Return true if an object or class implements the given interface
     *
     * @param object|string $class
     * @param string $interface
     * @return bool
     */
    public static function classImplements($class, string $interface): bool
    {
        return in_array($interface, class_implements($class) ?: []);
    }

    /**
     * Return true if two paths exist and refer to the same file
     *
     * @param string $path1
     * @param string $path2
     * @return bool
     */
    public static function areSameFile(string $path1, string $path2): bool
    {
        return file_exists($path1) && file_exists($path2) &&
            is_int($inode = fileinode($path1)) &&
            fileinode($path2) === $inode;
    }
}
