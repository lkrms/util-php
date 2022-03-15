<?php

declare(strict_types=1);

namespace Lkrms;

use ReflectionClass;
use UnexpectedValueException;

/**
 * Sometimes The Reflector Is Not Enough
 *
 * @package Lkrms
 */
class Reflect
{
    /**
     * Return Reflector->name for the given Reflector or list thereof
     *
     * @param Reflector|Reflector[] $reflection
     * @return string|string[]
     */
    public static function getName($reflection)
    {
        if (is_array($reflection))
        {
            return array_map(function ($r) { return $r->name; }, $reflection);
        }

        return $reflection->name;
    }

    /**
     * Return an array of traits used by this class and its parent classes
     *
     * In other words, merge arrays returned by `ReflectionClass::getTraits()`
     * for `$class`, `$class->getParentClass()`, etc.
     *
     * @param ReflectionClass $class
     * @return array<string,ReflectionClass> An array that maps trait names to
     * `ReflectionClass` instances.
     */
    public static function getAllTraits(ReflectionClass $class): array
    {
        $allTraits = [];

        while ($class && !is_null($traits = $class->getTraits()))
        {
            $allTraits = array_merge($allTraits, $traits);
            $class     = $class->getParentClass();
        }

        if (is_null($traits))
        {
            throw new UnexpectedValueException("Error retrieving traits for class {$class->name}");
        }

        return $traits;
    }
}

