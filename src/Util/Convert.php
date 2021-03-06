<?php

declare(strict_types=1);

namespace Lkrms\Util;

use Closure;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Lkrms\Concept\Utility;
use Lkrms\Support\DateFormatter;
use UnexpectedValueException;

/**
 * Convert data from one type/format/structure to another
 *
 * Examples:
 * - normalise alphanumeric text
 * - convert a list array to a map array
 * - pluralise a singular noun
 * - extract a class name from a FQCN
 */
final class Convert extends Utility
{
    /**
     * "snake_case"
     */
    public const IDENTIFIER_CASE_SNAKE = 0;

    /**
     * "kebab-case"
     */
    public const IDENTIFIER_CASE_KEBAB = 1;

    /**
     * "PascalCase"
     */
    public const IDENTIFIER_CASE_PASCAL = 2;

    /**
     * "camelCase"
     */
    public const IDENTIFIER_CASE_CAMEL = 3;

    /**
     * If a value isn't an array, make it the first element of one
     *
     * @param mixed $value
     * @param bool $emptyIfNull
     * @return array Either `$value`, `[$value]`, or `[]` (only if
     * `$emptyIfNull` is set and `$value` is `null`).
     */
    public static function toArray($value, bool $emptyIfNull = false): array
    {
        return is_array($value)
            ? $value
            : ($emptyIfNull && is_null($value) ? [] : [$value]);
    }

    /**
     * If a value isn't a list, make it the first element of one
     *
     * @param mixed $value
     * @param bool $emptyIfNull
     * @return array Either `$value`, `[$value]`, or `[]` (only if
     * `$emptyIfNull` is set and `$value` is `null`).
     */
    public static function toList($value, bool $emptyIfNull = false): array
    {
        return Test::isListArray($value, true)
            ? $value
            : ($emptyIfNull && is_null($value) ? [] : [$value]);
            ;
    }

    /**
     * Convert an interval to the equivalent number of seconds
     *
     * Works with ISO 8601 durations like `PT48M`.
     *
     * @param DateInterval|string $value
     * @return int
     */
    public static function intervalToSeconds($value): int
    {
        if (!($value instanceof DateInterval))
        {
            $value = new DateInterval($value);
        }
        $then = new DateTimeImmutable();
        $now  = $then->add($value);
        return $now->getTimestamp() - $then->getTimestamp();
    }

    /**
     * A shim for DateTimeImmutable::createFromInterface() (PHP 8+)
     *
     * @param DateTimeInterface $date
     * @return DateTimeImmutable
     */
    public static function toDateTimeImmutable(DateTimeInterface $date): DateTimeImmutable
    {
        if ($date instanceof DateTimeImmutable)
        {
            return $date;
        }
        return DateTimeImmutable::createFromMutable($date);
    }

    /**
     * Convert a value to a DateTimeZone instance
     *
     * @param DateTimeZone|string $value
     * @return DateTimeZone
     */
    public static function toTimezone($value): DateTimeZone
    {
        if ($value instanceof DateTimeZone)
        {
            return $value;
        }
        elseif (is_string($value))
        {
            return new DateTimeZone($value);
        }
        throw new UnexpectedValueException("Invalid timezone");
    }

    /**
     * If a value is 'falsey', make it null
     *
     * @param mixed $value
     * @return mixed Either `$value` or `null`.
     */
    public static function emptyToNull($value)
    {
        return !$value ? null : $value;
    }

    /**
     * Remove the namespace and an optional suffix from a class name
     *
     * @param string $class
     * @param string|null $suffix Removed from the end of `$class` if set.
     * @return string
     */
    public static function classToBasename(string $class, string $suffix = null): string
    {
        $class = substr(strrchr("\\" . $class, "\\"), 1);
        if ($suffix && ($pos = strrpos($class, $suffix)) > 0)
        {
            return substr($class, 0, $pos);
        }
        return $class;
    }

    /**
     * Return the namespace of a class
     *
     * Returns an empty string if `$class` is not namespaced, otherwise returns
     * the namespace followed by a namespace separator.
     *
     * @param string $class
     * @return string
     */
    public static function classToNamespace(string $class): string
    {
        return substr($class, 0, strrpos("\\" . $class, "\\"));
    }

    /**
     * Remove the class from a method name
     *
     * @param string $method
     * @return string
     */
    public static function methodToFunction(string $method): string
    {
        return preg_replace('/^.*?([a-z0-9_]*)$/i', '$1', $method);
    }

    /**
     * Move array values to a nested array
     *
     * @param array $array The array to transform.
     * @param string $key The key in `$array` where the child array should be
     * created.
     * @param array $map An array that maps child array keys to `$array` keys.
     * For example, to move `$data['userId']` to `$data['user']['id']`:
     * ```php
     * $data = Convert::arrayEntriesToChildArray($data, 'user', ['id' => 'userId']);
     * ```
     * @param bool $merge If `true` (the default), merge values from `$array`
     * with an existing array at `$array[$key]`. If `false`, throw an exception
     * if `$array[$key]` is already set.
     * @return array
     * @throws UnexpectedValueException if `$key` already exists in `$array` and
     * merging is not possible
     */
    public static function arrayValuesToChildArray(
        array $array,
        string $key,
        array $map,
        bool $merge = true
    ): array
    {
        if (array_key_exists($key, $array) &&
            (!$merge || !is_array($array[$key])))
        {
            throw new UnexpectedValueException("'$key' is already set");
        }
        $child = $array[$key] ?? [];
        foreach ($map as $to => $from)
        {
            if (array_key_exists($from, $array))
            {
                $child[$to] = $array[$from];
                unset($array[$from]);
            }
        }
        if (empty(array_filter($child, fn($value) => !is_null($value))))
        {
            $child = null;
        }
        $array[$key] = $child;
        return $array;
    }

    /**
     * Apply multiple arrayValuesToChildArray transformations to an array
     *
     * @param array $array The array to transform.
     * @param array $maps An array that maps `$key` to `$map`, where `$key` and
     * `$map` are passed to {@see Convert::arrayValuesToChildArray()}.
     * @param bool $merge Passed to {@see Convert::arrayValuesToChildArray()}.
     * @return array
     */
    public static function toNestedArrays(
        array $array,
        array $maps,
        bool $merge = true
    ): array
    {
        foreach ($maps as $key => $map)
        {
            $array = self::arrayValuesToChildArray($array, $key, $map, $merge);
        }
        return $array;
    }

    /**
     * Create a map from a list
     *
     * For example, to map from each array's `id` to the array itself:
     *
     * ```php
     * $list = [
     *     ['id' => 32, 'name' => 'Greta'],
     *     ['id' => 71, 'name' => 'Terry'],
     * ];
     *
     * $map = Convert::listToMap($list, 'id');
     *
     * print_r($map);
     * ```
     *
     * ```
     * Array
     * (
     *     [32] => Array
     *         (
     *             [id] => 32
     *             [name] => Greta
     *         )
     *
     *     [71] => Array
     *         (
     *             [id] => 71
     *             [name] => Terry
     *         )
     *
     * )
     * ```
     *
     * @param array $list
     * @param string|Closure $key Either the index or property name to use when
     * retrieving keys from arrays or objects in `$list`, or a closure that
     * returns a key for each item in `$list`.
     * @return array
     */
    public static function listToMap(array $list, $key): array
    {
        if ($key instanceof Closure)
        {
            $callback = $key;
        }
        else
        {
            $callback = function ($item) use ($key)
            {
                if (is_array($item))
                {
                    return $item[$key];
                }
                elseif (is_object($item))
                {
                    return $item->$key;
                }
                else
                {
                    throw new UnexpectedValueException("Item is not an array or object");
                }
            };
        }

        return array_combine(
            array_map($callback, $list),
            $list
        );
    }

    /**
     * Remove zero-width values from an array before imploding it
     *
     * @param string $separator
     * @param array $array
     * @return string
     */
    public static function sparseToString(string $separator, array $array): string
    {
        return implode($separator, array_filter(
            $array,
            function ($value) { return strlen((string)$value) > 0; }
        ));
    }

    /**
     * Convert a scalar to a string
     *
     * @param mixed $value
     * @return string|false Returns `false` if `$value` is not a scalar
     */
    public static function scalarToString($value)
    {
        if (is_scalar($value))
        {
            return (string)$value;
        }
        else
        {
            return false;
        }
    }

    /**
     * If a number is 1, return $singular, otherwise return $plural
     *
     * @param int $number
     * @param string $singular
     * @param string|null $plural If `null`, `{$singular}s` will be used instead
     * @param bool $includeNumber Return `$number $noun` instead of `$noun`
     * @return string
     */
    public static function numberToNoun(int $number, string $singular, string $plural = null, bool $includeNumber = false): string
    {
        if ($number == 1)
        {
            $noun = $singular;
        }
        else
        {
            $noun = is_null($plural) ? $singular . "s" : $plural;
        }

        if ($includeNumber)
        {
            return "$number $noun";
        }

        return $noun;
    }

    /**
     * Return the plural of a singular noun
     *
     * @param string $noun
     * @return string
     */
    public static function nounToPlural(string $noun): string
    {
        if (preg_match('/(?:(sh?|ch|x|z|(?<!^phot)(?<!^pian)(?<!^hal)o)|([^aeiou]y)|(is)|(on))$/i', $noun, $matches))
        {
            if ($matches[1])
            {
                return $noun . "es";
            }
            elseif ($matches[2])
            {
                return substr_replace($noun, "ies", -1);
            }
            elseif ($matches[3])
            {
                return substr_replace($noun, "es", -2);
            }
            elseif ($matches[4])
            {
                return substr_replace($noun, "a", -2);
            }
        }

        return $noun . "s";
    }

    /**
     * Remove duplicates in a string where 'top-level' lines ("section names")
     * are grouped with any subsequent 'child' lines ("list items")
     *
     * Lines that match `$regex` are regarded as list items. Other lines are
     * used as the section name for subsequent list items. Blank lines clear the
     * current section name and are not included in the return value.
     *
     * @param string $text
     * @param string $separator Used between top-level lines and sections.
     * @param null|string $marker Added before each section name. The equivalent
     * number of spaces are added before each list item. To add a leading `"- "`
     * to top-level lines and indent others with two spaces, set `$marker` to
     * `"-"`.
     * @param string $regex
     * @return string
     */
    public static function linesToLists(
        string $text,
        string $separator = "\n",
        ?string $marker   = null,
        string $regex     = '/^\h*[-*] /'
    ): string
    {
        $marker       = $marker ? $marker . " " : null;
        $indent       = $marker ? str_repeat(" ", mb_strlen($marker)) : "";
        $markerIsItem = $marker && preg_match($regex, $marker);

        $sections = [];
        foreach (preg_split('/\r\n|\n/', $text) as $line)
        {
            // Remove pre-existing markers early to ensure sections with the
            // same name are combined
            if ($marker && !$markerIsItem && strpos($line, $marker) === 0)
            {
                $line = substr($line, strlen($marker));
            }
            if (!trim($line))
            {
                unset($section);
                continue;
            }
            if (!preg_match($regex, $line))
            {
                $section = $line;
            }
            $key = $section ?? $line;
            if (!array_key_exists($key, $sections))
            {
                $sections[$key] = [];
            }
            if ($key != $line && !in_array($line, $sections[$key]))
            {
                $sections[$key][] = $line;
            }
        }
        // Move lines with no associated list to the top
        $sections = array_merge(
            array_filter($sections, fn($lines) => !count($lines)),
            array_filter($sections, fn($lines) => count($lines))
        );
        $groups = [];
        foreach ($sections as $section => $sectionLines)
        {
            if ($marker &&
                !($markerIsItem && strpos($section, $marker) === 0) &&
                !preg_match($regex, $section))
            {
                $section = $marker . $section;
            }
            $groups[] = $section;
            if ($sectionLines)
            {
                $groups[] = $indent . implode("\n" . $indent, $sectionLines);
            }
        }
        return implode($separator, $groups);
    }

    /**
     * Convert php.ini values like "128M" to bytes
     *
     * @param string $size From the PHP FAQ: "The available options are K (for
     * Kilobytes), M (for Megabytes) and G (for Gigabytes), and are all
     * case-insensitive."
     * @return int
     */
    public static function sizeToBytes(string $size): int
    {
        if (!preg_match('/^(.+?)([KMG]?)$/', strtoupper($size), $match) || !is_numeric($match[1]))
        {
            throw new UnexpectedValueException("Invalid shorthand: '$size'");
        }

        $power = ['' => 0, 'K' => 1, 'M' => 2, 'G' => 3];

        return (int)($match[1] * (1024 ** $power[$match[2]]));
    }

    /**
     * Convert the given strings and Stringables to an array of strings
     *
     * @param string|\Stringable ...$value
     * @return string[]
     */
    public static function toStrings(...$value): array
    {
        return array_map(function ($string) { return (string)$string; }, $value);
    }

    /**
     * Perform the given case conversion
     *
     * @param string $text
     * @param int $case
     * @return string
     */
    public static function toCase(string $text, int $case = self::IDENTIFIER_CASE_SNAKE): string
    {
        switch ($case)
        {
            case self::IDENTIFIER_CASE_SNAKE:

                return self::toSnakeCase($text);

            case self::IDENTIFIER_CASE_KEBAB:

                return self::toKebabCase($text);

            case self::IDENTIFIER_CASE_PASCAL:

                return self::toPascalCase($text);

            case self::IDENTIFIER_CASE_CAMEL:

                return self::toCamelCase($text);
        }

        throw new UnexpectedValueException("Invalid case: $case");
    }

    /**
     * Convert an identifier to snake_case
     *
     * @param string $text The identifier to convert.
     * @return string
     */
    public static function toSnakeCase(string $text): string
    {
        $text = preg_replace("/[^[:alnum:]]+/", "_", $text);
        $text = preg_replace("/([[:lower:]])([[:upper:]])/", '$1_$2', $text);

        return strtolower(trim($text, "_"));
    }

    /**
     * Convert an identifier to kebab-case
     *
     * @param string $text
     * @return string
     */
    public static function toKebabCase(string $text): string
    {
        $text = preg_replace("/[^[:alnum:]]+/", "-", $text);
        $text = preg_replace("/([[:lower:]])([[:upper:]])/", '$1-$2', $text);

        return strtolower(trim($text, "-"));
    }

    /**
     * Convert an identifier to PascalCase
     *
     * @param string $text
     * @return string
     */
    public static function toPascalCase(string $text): string
    {
        $text = preg_replace_callback(
            '/([[:upper:]]?[[:lower:][:digit:]]+|([[:upper:]](?![[:lower:]]))+)/',
            function (array $matches) { return ucfirst(strtolower($matches[0])); },
            $text
        );

        return preg_replace("/[^[:alnum:]]+/", "", $text);
    }

    /**
     * Convert an identifier to camelCase
     *
     * @param string $text
     * @return string
     */
    public static function toCamelCase(string $text): string
    {
        return lcfirst(self::toPascalCase($text));
    }

    /**
     * Clean up a string for comparison with other strings
     *
     * This method is not guaranteed to be idempotent between releases.
     *
     * Here's what it currently does:
     * 1. Replaces ampersands (`&`) with ` and `
     * 2. Removes full stops (`.`)
     * 3. Replaces non-alphanumeric sequences with a space (` `)
     * 4. Trims leading and trailing spaces
     * 5. Makes letters uppercase
     *
     * @param string $text
     * @return string
     */
    public static function toNormal(string $text)
    {
        $replace = [
            "/(?<=[^&])&(?=[^&])/u" => " and ",
            "/\.+/u"           => "",
            "/[^[:alnum:]]+/u" => " ",
        ];

        return strtoupper(trim(preg_replace(
            array_keys($replace),
            array_values($replace),
            $text
        )));
    }

    /**
     * A wrapper for get_object_vars
     *
     * Because you can't exclude `private` and `protected` properties from
     * inside the class. (Not easily, anyway.)
     *
     * @param object $object
     * @return array
     */
    public static function objectToArray(object $object)
    {
        return get_object_vars($object);
    }

    private static function _dataToQuery(
        array $data,
        bool $forceNumericKeys,
        DateFormatter $dateFormatter,
        string & $query = null,
        string $name    = "",
        string $format  = "%s"
    ): string
    {
        if (is_null($query))
        {
            $query = "";
        }

        foreach ($data as $param => $value)
        {
            $_name = sprintf($format, $param);

            if (!is_array($value))
            {
                if (is_bool($value))
                {
                    $value = (int)$value;
                }
                elseif ($value instanceof DateTimeInterface)
                {
                    $value = $dateFormatter->format($value);
                }

                $query .= ($query ? "&" : "") . rawurlencode($name . $_name) . "=" . rawurlencode((string)$value);

                continue;
            }
            elseif (!$forceNumericKeys && Test::isListArray($value, true))
            {
                $_format = "[]";
            }
            else
            {
                $_format = "[%s]";
            }

            self::_dataToQuery($value, $forceNumericKeys, $dateFormatter, $query, $name . $_name, $_format);
        }

        return $query;
    }

    /**
     * A more API-friendly http_build_query
     *
     * Booleans are cast to integers (`0` or `1`), {@see \DateTime}s are
     * formatted by `$dateFormatter`, and other values are cast to string.
     *
     * Arrays with consecutive integer keys numbered from 0 are considered to be
     * lists. By default, keys are not included when adding lists to query
     * strings. Set `$forceNumericKeys` to override this behaviour.
     *
     * @param array $data
     * @param bool $forceNumericKeys
     * @param DateFormatter|null $dateFormatter
     * @return string
     */
    public static function dataToQuery(
        array $data,
        bool $forceNumericKeys       = false,
        DateFormatter $dateFormatter = null
    ): string
    {
        return self::_dataToQuery(
            $data,
            $forceNumericKeys,
            $dateFormatter ?: new DateFormatter()
        );
    }

    /**
     * @deprecated Use {@see Convert::linesToLists()} instead
     */
    public static function mergeLists(
        string $text,
        string $regex = '/^\h*[-*] /'
    ): string
    {
        return self::linesToLists($text, "\n", $regex);
    }
}
